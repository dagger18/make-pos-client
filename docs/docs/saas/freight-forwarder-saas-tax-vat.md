# Freight Forwarder SaaS — Tax and VAT Handling

## 1. Why Tax Handling Is Non-Trivial

A freight forwarder operating across multiple countries faces a complex tax landscape:
- VAT rates vary by country and by charge type
- Some services are zero-rated (international freight) while others are standard-rated (domestic trucking)
- Tax-exempt customers (e.g. diplomatic entities, exporters claiming VAT refunds) require special handling
- Each country has specific invoice format requirements for tax compliance
- Tax filing reports must be generated per jurisdiction

Without a proper tax model, invoices are non-compliant and tax audits become painful.

---

## 2. Tax Configuration Table

Tax rules are configurable per country, per charge type, and per customer category.

```sql
CREATE TABLE tax_rule (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  country_code      CHAR(2)       NOT NULL REFERENCES country(code),
  charge_category   VARCHAR(16),              -- FREIGHT / LOCAL / CUSTOMS / SERVICE — NULL = all
  service_type      VARCHAR(16),              -- FCL / LCL / AIR / RD — NULL = all
  customer_type     VARCHAR(32),              -- DOMESTIC / EXPORTER / DIPLOMAT / NULL = standard
  tax_type          VARCHAR(16)   NOT NULL,   -- VAT / GST / SALES_TAX / EXCISE / WITHHOLDING
  tax_code          VARCHAR(16)   NOT NULL,   -- country-specific tax code: VN-VAT-10 / SG-GST-9
  tax_rate          NUMERIC(6,4)  NOT NULL,   -- 0.10 = 10%
  is_reverse_charge BOOLEAN       NOT NULL DEFAULT false,  -- EU reverse charge mechanism
  is_zero_rated     BOOLEAN       NOT NULL DEFAULT false,  -- 0% but still reportable
  is_exempt         BOOLEAN       NOT NULL DEFAULT false,  -- truly exempt — not on tax return
  description       VARCHAR(128),
  effective_from    DATE          NOT NULL,
  effective_to      DATE,

  UNIQUE (country_code, charge_category, service_type, customer_type, tax_type, effective_from)
);
```

### Vietnam VAT rules (example seed data)

| charge_category | service_type | customer_type | tax_rate | notes |
|---|---|---|---|---|
| FREIGHT | AIR / OCN | NULL | 0% | International freight — zero-rated |
| FREIGHT | RD | NULL | 10% | Domestic trucking — standard rate |
| LOCAL | NULL | NULL | 10% | Port handling charges — standard |
| CUSTOMS | NULL | NULL | 10% | Customs brokerage fee — standard |
| SERVICE | NULL | EXPORTER | 0% | Services to exporters — zero-rated |
| ANY | NULL | DIPLOMAT | 0% | Diplomatic exemption |

---

## 3. Tax Lookup Function

When an invoice line is created, the system looks up the applicable tax rule.

```python
def lookup_tax_rule(
    country_code: str,
    charge_category: str,
    service_type: str,
    customer_type: str,
    date: date = None
) -> TaxRule:
    """
    Find the most specific applicable tax rule.
    Priority: most specific (all fields match) → least specific (only country matches).
    """
    date = date or today()

    rule = db.fetch_one("""
        SELECT * FROM tax_rule
        WHERE country_code = :country
          AND effective_from <= :date
          AND (effective_to IS NULL OR effective_to >= :date)
          AND (charge_category = :category OR charge_category IS NULL)
          AND (service_type    = :service  OR service_type    IS NULL)
          AND (customer_type   = :cust_type OR customer_type  IS NULL)
        ORDER BY
          (charge_category IS NOT NULL)::int DESC,
          (service_type    IS NOT NULL)::int DESC,
          (customer_type   IS NOT NULL)::int DESC,
          effective_from DESC
        LIMIT 1
    """, country=country_code, date=date, category=charge_category,
         service=service_type, cust_type=customer_type)

    return rule
```

---

## 4. Tax on Invoice Lines

Tax is calculated per invoice line and stored explicitly — never just as a total.

```sql
-- Additional fields on invoice_line
ALTER TABLE invoice_line ADD COLUMN tax_code    VARCHAR(16);
ALTER TABLE invoice_line ADD COLUMN tax_rate    NUMERIC(6,4)  NOT NULL DEFAULT 0;
ALTER TABLE invoice_line ADD COLUMN tax_amount  NUMERIC(20,6) NOT NULL DEFAULT 0;
ALTER TABLE invoice_line ADD COLUMN is_zero_rated   BOOLEAN   NOT NULL DEFAULT false;
ALTER TABLE invoice_line ADD COLUMN is_exempt        BOOLEAN   NOT NULL DEFAULT false;
ALTER TABLE invoice_line ADD COLUMN is_reverse_charge BOOLEAN  NOT NULL DEFAULT false;
```

### Invoice line tax calculation

```python
def calculate_invoice_line_tax(
    charge_code: str,
    amount: float,
    job: Job,
    customer_org: Organisation,
    invoice_country: str
) -> dict:
    charge = fetch_charge_master(charge_code)
    customer_type = determine_customer_type(customer_org)

    rule = lookup_tax_rule(
        country_code     = invoice_country,
        charge_category  = charge.category,
        service_type     = job.service_type,
        customer_type    = customer_type
    )

    if not rule or rule.is_exempt:
        return {"tax_rate": 0, "tax_amount": 0, "is_exempt": True, "tax_code": None}

    if rule.is_zero_rated:
        return {"tax_rate": 0, "tax_amount": 0, "is_zero_rated": True, "tax_code": rule.tax_code}

    tax_amount = amount * rule.tax_rate

    return {
        "tax_code":         rule.tax_code,
        "tax_rate":         rule.tax_rate,
        "tax_amount":       round(tax_amount, 2),
        "is_zero_rated":    False,
        "is_exempt":        False,
        "is_reverse_charge": rule.is_reverse_charge
    }
```

---

## 5. Tax-Exempt Customers

Some customers are exempt from VAT — exporters claiming refunds, diplomatic entities, certain government bodies.

```sql
CREATE TABLE customer_tax_exemption (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),
  exemption_type    VARCHAR(32)   NOT NULL,   -- EXPORTER / DIPLOMAT / GOVERNMENT / ZERO_RATED
  country_code      CHAR(2)       NOT NULL REFERENCES country(code),
  exemption_ref     VARCHAR(64),              -- certificate or licence number
  valid_from        DATE          NOT NULL,
  valid_to          DATE,                     -- NULL = indefinite
  document_url      TEXT,                     -- scanned exemption certificate
  verified_by       UUID          REFERENCES app_user(id),
  verified_at       DATE,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 6. VAT Filing Report

At the end of each tax period, the system generates a VAT output (sales) and input (purchases) report.

### Output tax (AR invoices — tax collected from customers)

```sql
SELECT
  DATE_TRUNC('month', i.issue_date)   AS tax_period,
  il.tax_code,
  il.tax_rate,
  SUM(il.amount)                       AS taxable_amount,
  SUM(il.tax_amount)                   AS tax_collected,
  COUNT(DISTINCT i.id)                 AS invoice_count
FROM invoice_line il
JOIN invoice i ON il.invoice_id = i.id
WHERE i.type        = 'AR'
  AND i.status     != 'VOID'
  AND i.issue_date  BETWEEN :period_start AND :period_end
GROUP BY DATE_TRUNC('month', i.issue_date), il.tax_code, il.tax_rate
ORDER BY tax_period, il.tax_code;
```

### Input tax (AP bills — tax paid to vendors)

```sql
SELECT
  DATE_TRUNC('month', ab.received_date) AS tax_period,
  abl.tax_code,
  abl.tax_rate,
  SUM(abl.billed_amount)                AS taxable_amount,
  SUM(abl.tax_amount)                   AS tax_paid,
  COUNT(DISTINCT ab.id)                 AS bill_count
FROM ap_bill_line abl
JOIN ap_bill ab ON abl.ap_bill_id = ab.id
WHERE ab.status != 'DISPUTED'
  AND ab.received_date BETWEEN :period_start AND :period_end
GROUP BY DATE_TRUNC('month', ab.received_date), abl.tax_code, abl.tax_rate
ORDER BY tax_period, abl.tax_code;
```

**Net VAT payable = output tax collected − input tax paid**

---

## 7. Tax Registration Numbers

```sql
CREATE TABLE organisation_tax_registration (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),
  country_code      CHAR(2)       NOT NULL REFERENCES country(code),
  tax_type          VARCHAR(16)   NOT NULL,   -- VAT / GST / WITHHOLDING
  registration_no   VARCHAR(64)   NOT NULL,
  is_primary        BOOLEAN       NOT NULL DEFAULT false,
  effective_from    DATE          NOT NULL,
  effective_to      DATE,

  UNIQUE (organisation_id, country_code, tax_type)
);
```

Tax registration numbers appear on invoices and are validated against the customer's registration when creating a tax-compliant invoice.

---

## 8. Withholding Tax

In some countries (Vietnam, Thailand, Philippines), payments to foreign service providers are subject to withholding tax — the payer deducts tax at source and remits it to the tax authority.

```sql
-- On the ap_payment table
ALTER TABLE ap_payment ADD COLUMN withholding_tax_rate   NUMERIC(6,4);
ALTER TABLE ap_payment ADD COLUMN withholding_tax_amount NUMERIC(20,6);
ALTER TABLE ap_payment ADD COLUMN withholding_tax_ref    VARCHAR(64);   -- tax remittance reference

-- Net payment = gross amount - withholding tax
-- The vendor receives (paid_amount - withholding_tax_amount)
-- The withholding_tax_amount is paid to the tax authority separately
```

---

## 9. Country-Specific Invoice Requirements

| Country | Requirement |
|---|---|
| Vietnam | E-invoice (hóa đơn điện tử) — must be issued through a licensed e-invoice provider; sequential numbering required |
| EU | Full VAT number of buyer and seller; reverse charge notation where applicable |
| Singapore | GST registration number; "Tax Invoice" heading required |
| Thailand | Full name and address in Thai; WHT certificate for foreign payments |
| Indonesia | e-Faktur system — must be uploaded to tax authority portal |

These requirements are handled through the document generation template system — each country has a compliant invoice template with the required fields and format.

---

## 10. Golden Rules

1. **Tax is calculated per invoice line, not as a total.** Different charge types on the same invoice may have different tax rates. Never apply a single rate to the total.
2. **Zero-rated and exempt are different.** Zero-rated (0% but on the tax return) and exempt (not on the tax return at all) are distinct statuses. Confusing them causes filing errors.
3. **Tax exemption certificates must be verified and stored.** Never apply a zero rate or exemption without a stored, verified certificate with a valid date range.
4. **Net VAT payable is calculated from filing reports, not from account balances.** Always generate the output and input tax reports and subtract — do not rely on ad hoc calculations.
5. **Country-specific invoice formats are template problems, not schema problems.** The data model is the same everywhere. Only the PDF template and field labelling change per country. Keep the templates in the document generation system.
