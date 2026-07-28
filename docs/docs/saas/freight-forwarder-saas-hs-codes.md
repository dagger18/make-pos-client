# Freight Forwarder SaaS — HS Code and Tariff Classification

## 1. What HS Codes Are

The Harmonised System (HS) is an internationally standardised nomenclature for classifying traded products, maintained by the World Customs Organisation (WCO). Every physical good that crosses an international border must be assigned an HS code. The code determines:

- The applicable import duty rate in the destination country
- Whether the goods require an import licence or are restricted
- Which free trade agreement (FTA) preferential rates apply
- Whether a certificate of origin is required and what type
- Whether the goods are subject to anti-dumping or countervailing duties

---

## 2. HS Code Structure

```
HS code:  8471.30.90

84        Chapter     — Automatic data processing machines and units
8471      Heading     — Computers and similar machines
8471.30   Subheading  — Portable machines (≤ 10kg)
8471.30.90 National   — Country-specific subdivision (Vietnam example)
```

| Level | Digits | Governed by | Universal? |
|---|---|---|---|
| Chapter | 2 | WCO | Yes — all countries |
| Heading | 4 | WCO | Yes — all countries |
| Subheading | 6 | WCO | Yes — all countries |
| National tariff line | 8–12 | National customs authority | No — varies by country |

The first 6 digits are identical worldwide. Digits 7 onwards are country-specific.

---

## 3. HS Code Reference Table

```sql
CREATE TABLE hs_code (
  code              VARCHAR(12)   PRIMARY KEY,    -- full code with dots stripped: 847130900000
  code_display      VARCHAR(16)   NOT NULL,        -- formatted for display: 8471.30.90.00.00
  level             VARCHAR(16)   NOT NULL,        -- CHAPTER / HEADING / SUBHEADING / NATIONAL
  digits            SMALLINT      NOT NULL,        -- 2 / 4 / 6 / 8 / 10 / 12
  parent_code       VARCHAR(12)   REFERENCES hs_code(code),
  description       TEXT          NOT NULL,
  description_local TEXT,                          -- local language description
  country_code      CHAR(2),                       -- NULL = universal (WCO level); set for national lines
  hs_version        VARCHAR(8)    NOT NULL,         -- HS2017 / HS2022 — WCO updates every 5 years
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  effective_from    DATE          NOT NULL,
  effective_to      DATE,
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE INDEX idx_hs_parent  ON hs_code (parent_code);
CREATE INDEX idx_hs_country ON hs_code (country_code);
CREATE INDEX idx_hs_desc    ON hs_code USING gin(to_tsvector('english', description));
CREATE INDEX idx_hs_trgm    ON hs_code USING gin(description gin_trgm_ops);
```

---

## 4. Duty Rate Table

Duty rates are country-specific and change frequently. They must be stored separately from the HS code itself.

```sql
CREATE TABLE duty_rate (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  hs_code           VARCHAR(12)   NOT NULL REFERENCES hs_code(code),
  import_country    CHAR(2)       NOT NULL REFERENCES country(code),
  export_country    CHAR(2),                       -- NULL = applies to all origins; set for FTA/preferential
  rate_type         VARCHAR(32)   NOT NULL,         -- MFN / FTA / PREFERENTIAL / ANTI_DUMPING / SAFEGUARD
  fta_name          VARCHAR(64),                   -- CPTPP / RCEP / EVFTA / ASEAN / AHK ...
  duty_rate         NUMERIC(8,4),                  -- ad valorem rate (% of CIF value): 0.1200 = 12%
  specific_duty     NUMERIC(20,6),                 -- specific duty amount per unit (some goods)
  specific_uom      VARCHAR(16),                   -- KG / UNIT / LITRE — for specific duties
  combined_formula  TEXT,                          -- for compound duties: "12% + 5 USD/kg"
  vat_rate          NUMERIC(6,4)  NOT NULL DEFAULT 0,  -- import VAT rate
  excise_rate       NUMERIC(6,4)  NOT NULL DEFAULT 0,
  effective_from    DATE          NOT NULL,
  effective_to      DATE,
  source            VARCHAR(32)   NOT NULL DEFAULT 'MANUAL',  -- MANUAL / CUSTOMS_API / TARIFF_FEED
  last_verified_at  DATE,

  UNIQUE (hs_code, import_country, export_country, rate_type, effective_from)
);

CREATE INDEX idx_duty_hs      ON duty_rate (hs_code);
CREATE INDEX idx_duty_import  ON duty_rate (import_country);
CREATE INDEX idx_duty_fta     ON duty_rate (fta_name) WHERE fta_name IS NOT NULL;
```

### Rate type priority

When multiple duty rates exist for the same HS code and import country, apply in this priority:

```
1. ANTI_DUMPING     — additional duty on top of normal rate
2. SAFEGUARD        — temporary protection measure
3. FTA              — preferential rate (requires certificate of origin)
4. PREFERENTIAL     — GSP and similar unilateral preferences
5. MFN              — Most Favoured Nation — the standard WTO rate
```

---

## 5. Duty Calculation

Import duty is typically calculated on the **CIF value** (Cost + Insurance + Freight). The system calculates duty at customs entry time.

```python
def calculate_duty(
    hs_code: str,
    import_country: str,
    export_country: str,
    cif_value: float,
    cif_currency: str,
    quantity: float,
    uom: str,
    fta_eligible: bool = False,
    date: date = None
) -> dict:
    """
    Returns estimated duty, VAT, excise, and total tax for a customs entry line.
    """
    date = date or today()

    # Find applicable duty rate — prefer FTA if eligible and origin qualifies
    rate = db.fetch_one("""
        SELECT * FROM duty_rate
        WHERE hs_code       = :hs_code
          AND import_country = :import_country
          AND (export_country = :export_country OR export_country IS NULL)
          AND rate_type = CASE WHEN :fta_eligible THEN 'FTA' ELSE 'MFN' END
          AND effective_from <= :date
          AND (effective_to IS NULL OR effective_to >= :date)
        ORDER BY
          CASE WHEN export_country IS NOT NULL THEN 0 ELSE 1 END
        LIMIT 1
    """, hs_code=hs_code, import_country=import_country,
         export_country=export_country, fta_eligible=fta_eligible, date=date)

    # Convert CIF value to import country currency if needed
    cif_local = convert_currency(cif_value, cif_currency, get_local_currency(import_country))

    # Calculate ad valorem duty
    ad_valorem_duty = cif_local * (rate.duty_rate or 0)

    # Calculate specific duty if applicable
    specific_duty = 0
    if rate.specific_duty and uom == rate.specific_uom:
        specific_duty = quantity * rate.specific_duty

    total_duty = ad_valorem_duty + specific_duty

    # VAT is calculated on CIF + duty
    vat_base  = cif_local + total_duty
    vat       = vat_base * (rate.vat_rate or 0)
    excise    = cif_local * (rate.excise_rate or 0)

    return {
        "hs_code":         hs_code,
        "cif_value":       cif_local,
        "duty_rate":       rate.duty_rate,
        "duty_amount":     total_duty,
        "vat_rate":        rate.vat_rate,
        "vat_amount":      vat,
        "excise_rate":     rate.excise_rate,
        "excise_amount":   excise,
        "total_tax":       total_duty + vat + excise,
        "rate_type_used":  rate.rate_type,
        "fta_name":        rate.fta_name,
        "is_estimate":     True   -- always an estimate until customs officially assesses
    }
```

---

## 6. Restricted and Controlled Goods

Some HS codes require additional checks before a shipment can proceed.

```sql
CREATE TABLE hs_restriction (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  hs_code           VARCHAR(12)   NOT NULL REFERENCES hs_code(code),
  country_code      CHAR(2)       REFERENCES country(code),  -- NULL = worldwide
  restriction_type  VARCHAR(32)   NOT NULL,   -- PROHIBITED / LICENCE_REQUIRED / INSPECTION / CITES / DUAL_USE / DANGEROUS
  description       TEXT          NOT NULL,
  authority         VARCHAR(128),              -- issuing authority name
  licence_type      VARCHAR(64),               -- type of licence/permit required
  effective_from    DATE          NOT NULL,
  effective_to      DATE
);
```

| restriction_type | Meaning |
|---|---|
| `PROHIBITED` | Import or export completely banned |
| `LICENCE_REQUIRED` | Import/export licence must be obtained before shipment |
| `INSPECTION` | Physical inspection required before clearance |
| `CITES` | Convention on International Trade in Endangered Species — wildlife permit required |
| `DUAL_USE` | Goods with civilian and military applications — export control |
| `DANGEROUS` | Additional handling, documentation, and carrier acceptance required |

---

## 7. HS Code Search

Operators need to find HS codes quickly by description. Two search modes:

```sql
-- Full-text search by description
SELECT code_display, description, level
FROM hs_code
WHERE to_tsvector('english', description) @@ plainto_tsquery('english', :query)
  AND country_code IS NULL    -- WCO universal codes only at first
  AND level = 'SUBHEADING'    -- 6-digit codes — most commonly used
  AND is_active = true
ORDER BY ts_rank(to_tsvector('english', description), plainto_tsquery('english', :query)) DESC
LIMIT 20;

-- Hierarchical browse — children of a given code
SELECT code_display, description, level
FROM hs_code
WHERE parent_code = :parent_code
  AND is_active = true
ORDER BY code_display;
```

---

## 8. HS Version Management

The WCO releases a new HS version every 5 years (HS2017, HS2022, HS2027). When a new version is released, some codes are restructured, split, or merged. The system must handle both old and new codes on historical jobs.

```sql
CREATE TABLE hs_version_mapping (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  old_code          VARCHAR(12)   NOT NULL REFERENCES hs_code(code),
  new_code          VARCHAR(12)   NOT NULL REFERENCES hs_code(code),
  old_version       VARCHAR(8)    NOT NULL,   -- HS2017
  new_version       VARCHAR(8)    NOT NULL,   -- HS2022
  change_type       VARCHAR(16)   NOT NULL,   -- RENAMED / SPLIT / MERGED / MOVED
  notes             TEXT
);
```

Historical customs entries keep their original HS code. The version mapping is used only for reporting and classification assistance.

---

## 9. Integration with Customs Filing

When an import customs entry is created, the HS code fields populate from the cargo detail and drive:

- Duty rate lookup (from `duty_rate` table)
- Restriction check (from `hs_restriction` table)
- Required documents check (certificate of origin, phytosanitary, etc.)
- Dangerous goods flag (cross-reference with the DG sub-object)
- FTA eligibility check (does the origin country qualify for preferential treatment?)

---

## 10. Golden Rules

1. **Store the 6-digit subheading as the minimum.** National extensions (8–12 digits) are country-specific and change frequently. The 6-digit WCO subheading is universally valid.
2. **Duty rates are estimates until customs officially assesses.** Always set `is_estimate = true` on duty charge lines. The actual assessment may differ.
3. **FTA rates require proof of origin.** A preferential duty rate only applies if a valid certificate of origin is presented. The system should flag FTA rate usage and require a CO document.
4. **HS version must be recorded on every customs entry.** A 2022 entry and a 2017 entry for the same goods may use different codes. Always store which HS version was used.
5. **Restricted goods must be checked before job creation.** A job with a restricted HS code should trigger a warning or block, not fail silently at customs filing time.
