# Freight Forwarder SaaS — Address Book and Organisation Master

## 1. Why the Address Book Is the Most Critical Missing Piece

Every object in the system references an organisation:

- Every job party (shipper, consignee, carrier, agent, broker, trucker) references `organisation.id`
- Every rate card references a carrier and optionally a customer
- Every invoice references a billing party
- Every AP bill references a vendor
- Every user belongs to an organisation (your company's branches)

Without a well-designed organisation master, all of these FK references point at nothing. The address book is the single most foundational reference table in the system — it should be built before any other feature.

---

## 2. The Organisation Record

One row per legal entity. An organisation can play multiple roles across different jobs — the same company can be a shipper on one job and a consignee on another.

```sql
CREATE TABLE organisation (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),

  -- Identity
  code              VARCHAR(32)   UNIQUE NOT NULL,   -- short internal code: IKEA-VN, MAERSK, DHL-HCM
  name              VARCHAR(255)  NOT NULL,
  trading_name      VARCHAR(255),                    -- if different from legal name
  org_type          VARCHAR(32)   NOT NULL,           -- CUSTOMER / CARRIER / AGENT / BROKER / TRUCKER / WAREHOUSE / VENDOR / OWN

  -- Legal
  tax_id            VARCHAR(64),                     -- VAT / GST / TIN registration number
  registration_no   VARCHAR(64),                     -- company registration number
  country_code      CHAR(2)       NOT NULL REFERENCES country(code),

  -- Address (registered / legal)
  address_line_1    VARCHAR(255),
  address_line_2    VARCHAR(255),
  city              VARCHAR(128),
  state             VARCHAR(128),
  postal_code       VARCHAR(32),

  -- Contact defaults
  phone             VARCHAR(32),
  email             VARCHAR(128),
  website           VARCHAR(255),

  -- Classification
  tier              VARCHAR(16),                     -- PLATINUM / GOLD / SILVER / STANDARD
  industry          VARCHAR(64),                     -- RETAIL / MANUFACTURING / PHARMA / AUTOMOTIVE ...
  incoterm_default  VARCHAR(8),                      -- preferred Incoterm for this customer
  currency_default  CHAR(3)       REFERENCES currency(code),

  -- Credit control
  credit_limit      NUMERIC(20,6) DEFAULT 0,
  credit_currency   CHAR(3)       REFERENCES currency(code),
  credit_terms      VARCHAR(32),                     -- NET30 / NET60 / CIA / COD
  credit_status     VARCHAR(16)   NOT NULL DEFAULT 'ACTIVE',  -- ACTIVE / ON_HOLD / BLOCKED / BLACKLISTED
  credit_hold_reason TEXT,
  credit_reviewed_at DATE,
  credit_reviewed_by UUID         REFERENCES app_user(id),

  -- Operational flags
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  is_own_company    BOOLEAN       NOT NULL DEFAULT false,   -- true for your own branches
  requires_approval BOOLEAN       NOT NULL DEFAULT false,   -- new jobs require manager approval
  blacklist_reason  TEXT,

  -- Audit
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  created_by        UUID          REFERENCES app_user(id),
  updated_at        TIMESTAMPTZ,
  updated_by        UUID          REFERENCES app_user(id)
);

CREATE INDEX idx_org_code    ON organisation (code);
CREATE INDEX idx_org_name    ON organisation (name);
CREATE INDEX idx_org_type    ON organisation (org_type);
CREATE INDEX idx_org_country ON organisation (country_code);
CREATE INDEX idx_org_tax     ON organisation (tax_id) WHERE tax_id IS NOT NULL;
```

---

## 3. Organisation Types

An organisation can have multiple types — a company can be both a customer (importing goods) and a vendor (providing trucking services). The `org_type` field accepts multiple values via a join table:

```sql
CREATE TABLE organisation_type (
  organisation_id UUID        NOT NULL REFERENCES organisation(id),
  org_type        VARCHAR(32) NOT NULL,
  PRIMARY KEY (organisation_id, org_type)
);
```

| org_type | Description |
|---|---|
| `CUSTOMER` | Pays you for freight forwarding services — shipper or consignee |
| `CARRIER` | Vessel operator, airline, rail operator |
| `AGENT` | Overseas forwarding partner — your agent at the other end |
| `BROKER` | Customs broker — handles import/export declarations |
| `TRUCKER` | Road haulage company |
| `WAREHOUSE` | CFS, bonded warehouse, cold store |
| `VENDOR` | Other service vendor — fumigation, inspection, insurance |
| `OWN` | Your own company / branch |
| `BANK` | Issuing or negotiating bank for LC transactions |
| `INSURER` | Cargo insurance company |
| `PORT_AUTHORITY` | Port or terminal operator |

---

## 4. Contacts — The People Within Organisations

```sql
CREATE TABLE contact (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),

  -- Identity
  salutation        VARCHAR(16),                     -- Mr / Ms / Dr / ...
  first_name        VARCHAR(64)   NOT NULL,
  last_name         VARCHAR(64),
  job_title         VARCHAR(128),
  department        VARCHAR(64),

  -- Contact details
  email             VARCHAR(128),
  phone             VARCHAR(32),
  mobile            VARCHAR(32),
  whatsapp          VARCHAR(32),
  language          CHAR(2)       DEFAULT 'en',      -- ISO 639-1 language code

  -- Role flags
  is_primary        BOOLEAN       NOT NULL DEFAULT false,   -- main contact for this org
  receives_invoice  BOOLEAN       NOT NULL DEFAULT false,   -- AR invoices emailed here
  receives_tracking BOOLEAN       NOT NULL DEFAULT false,   -- shipment notifications sent here
  receives_arrival  BOOLEAN       NOT NULL DEFAULT false,   -- arrival notices sent here

  -- Audit
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  created_by        UUID          REFERENCES app_user(id)
);

CREATE INDEX idx_contact_org   ON contact (organisation_id);
CREATE INDEX idx_contact_email ON contact (email) WHERE email IS NOT NULL;
```

---

## 5. Multiple Addresses Per Organisation

An organisation may have multiple addresses — registered office, billing address, warehouse address, port pickup address. All are stored separately and linked to the organisation.

```sql
CREATE TABLE organisation_address (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),
  address_type      VARCHAR(32)   NOT NULL,   -- REGISTERED / BILLING / WAREHOUSE / PICKUP / DELIVERY
  label             VARCHAR(64),              -- friendly name: "HCM Warehouse", "HAN Office"
  address_line_1    VARCHAR(255)  NOT NULL,
  address_line_2    VARCHAR(255),
  city              VARCHAR(128)  NOT NULL,
  state             VARCHAR(128),
  postal_code       VARCHAR(32),
  country_code      CHAR(2)       NOT NULL REFERENCES country(code),
  latitude          NUMERIC(10,7),
  longitude         NUMERIC(10,7),
  is_default        BOOLEAN       NOT NULL DEFAULT false,
  notes             TEXT,                     -- access instructions, dock hours, etc.
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 6. Carrier-Specific Fields

When `org_type` includes `CARRIER`, additional carrier-specific attributes apply:

```sql
CREATE TABLE carrier_profile (
  organisation_id   UUID          PRIMARY KEY REFERENCES organisation(id),
  scac_code         VARCHAR(8),               -- Standard Carrier Alpha Code (ocean)
  iata_code         VARCHAR(4),               -- airline code for air carriers
  carrier_type      VARCHAR(16)   NOT NULL,   -- OCEAN / AIR / ROAD / RAIL / COURIER / NVOCC
  alliance          VARCHAR(16),              -- 2M / OCEAN ALLIANCE / THE ALLIANCE (ocean)
  booking_platform  VARCHAR(64),              -- WebCargo / Inttra / direct API
  booking_email     VARCHAR(128),
  si_email          VARCHAR(128),             -- shipping instruction submission email
  ams_filer         VARCHAR(64),              -- AMS filer code (US trades)
  preferred_payment VARCHAR(32)               -- payment terms with this carrier
);
```

---

## 7. Agent-Specific Fields

When `org_type` includes `AGENT`, the overseas agent has a network profile:

```sql
CREATE TABLE agent_profile (
  organisation_id   UUID          PRIMARY KEY REFERENCES organisation(id),
  network           VARCHAR(64),              -- WCA / FIATA / GEODIS / independent
  agent_code        VARCHAR(32),              -- your internal code for this agent
  coverage_countries TEXT[],                 -- ISO country codes this agent covers
  modes_handled     TEXT[],                  -- OCN / AIR / RD / RAL
  commission_rate   NUMERIC(6,4),            -- default commission % on cross-trade jobs
  settlement_currency CHAR(3)     REFERENCES currency(code),
  settlement_terms  VARCHAR(32),             -- NET30 / NET60
  performance_score NUMERIC(4,2)             -- 0.00 to 5.00 — updated by performance module
);
```

---

## 8. Credit Control

Credit control sits on the organisation record and blocks job creation when limits are exceeded.

### Credit status values

| Status | Meaning | System behaviour |
|---|---|---|
| `ACTIVE` | Normal — no restrictions | Jobs created freely |
| `ON_HOLD` | Temporary hold — awaiting payment | New jobs require manager approval |
| `BLOCKED` | Hard block — significant overdue balance | No new jobs can be created |
| `BLACKLISTED` | Permanently blocked — fraud or dispute | No jobs, no quotes |

### Credit limit check on job creation

```sql
-- Check credit exposure before creating a new job
WITH current_exposure AS (
  SELECT
    COALESCE(SUM(i.outstanding * er.rate), 0) AS outstanding_base
  FROM invoice i
  JOIN exchange_rate er ON er.from_currency = i.currency
    AND er.to_currency   = :base_currency
    AND er.effective_date = CURRENT_DATE
  WHERE i.billed_to_org = :customer_org_id
    AND i.type          = 'AR'
    AND i.status NOT IN ('PAID', 'VOID', 'WRITTEN_OFF')
)
SELECT
  o.credit_limit,
  o.credit_currency,
  o.credit_status,
  ce.outstanding_base,
  (o.credit_limit - ce.outstanding_base) AS available_credit
FROM organisation o, current_exposure ce
WHERE o.id = :customer_org_id;
```

If `available_credit < 0` and `credit_status = 'ACTIVE'`, the system automatically moves the org to `ON_HOLD` and notifies the finance team.

---

## 9. The Address Snapshot Pattern

When an organisation's address is used on a legal document (BL, invoice, customs entry), the address is **snapshotted** onto that document. This prevents historical documents from being invalidated by future address changes.

```python
def snapshot_address(organisation_id: str, address_type: str = 'REGISTERED') -> dict:
    """
    Called at job creation time for each party role.
    The snapshot is stored in job_party.address_snapshot (JSONB).
    It is never updated after creation.
    """
    org = db.fetch_one(
        "SELECT * FROM organisation WHERE id = ?", organisation_id
    )
    addr = db.fetch_one(
        "SELECT * FROM organisation_address WHERE organisation_id = ? AND address_type = ? AND is_default = true",
        organisation_id, address_type
    )
    return {
        "name":            org.name,
        "trading_name":    org.trading_name,
        "tax_id":          org.tax_id,
        "address_line_1":  addr.address_line_1 if addr else org.address_line_1,
        "address_line_2":  addr.address_line_2 if addr else org.address_line_2,
        "city":            addr.city if addr else org.city,
        "state":           addr.state if addr else org.state,
        "postal_code":     addr.postal_code if addr else org.postal_code,
        "country_code":    addr.country_code if addr else org.country_code,
        "snapshotted_at":  datetime.utcnow().isoformat()
    }
```

---

## 10. Customer Tier and Pricing Rules

Customer tier drives which sell rate is applied in the quote engine:

| Tier | Sell rate source | Typical criteria |
|---|---|---|
| `PLATINUM` | Individually negotiated contract rates | > $500K annual revenue |
| `GOLD` | Contract rates with preferred markup | > $100K annual revenue |
| `SILVER` | Standard tariff with tier discount | > $30K annual revenue |
| `STANDARD` | Published general tariff | New or low-volume customers |

The tier field feeds the rate lookup priority in the quote engine — a PLATINUM customer's contract rate card is checked before the general tariff.

---

## 11. Duplicate Detection

Organisation records frequently get duplicated (same company entered twice by different operators). Implement a duplicate check on creation:

```sql
-- Find potential duplicates before inserting a new organisation
SELECT id, name, tax_id, country_code,
  similarity(name, :new_name) AS name_score
FROM organisation
WHERE country_code = :country_code
  AND (
    tax_id = :tax_id                            -- exact tax ID match = definite duplicate
    OR similarity(name, :new_name) > 0.7        -- fuzzy name match = probable duplicate
    OR registration_no = :registration_no       -- exact registration match
  )
ORDER BY name_score DESC
LIMIT 5;
```

The `pg_trgm` extension provides the `similarity()` function. If potential duplicates are found, the UI prompts the user to select an existing organisation rather than creating a new one.

---

## 12. Search and Autocomplete

The address book is the most-searched reference in the system — operators type a party name hundreds of times per day. Index for fast full-text and prefix search:

```sql
-- Full-text search index
CREATE INDEX idx_org_fts ON organisation
  USING gin(to_tsvector('english', name || ' ' || COALESCE(trading_name, '') || ' ' || COALESCE(code, '')));

-- Trigram index for fuzzy search / autocomplete
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE INDEX idx_org_trgm ON organisation USING gin(name gin_trgm_ops);

-- Fast autocomplete query
SELECT id, name, trading_name, org_type, country_code, credit_status
FROM organisation
WHERE name ILIKE :query || '%'
   OR code ILIKE :query || '%'
   OR tax_id = :query
ORDER BY
  CASE WHEN name ILIKE :query || '%' THEN 0 ELSE 1 END,
  name
LIMIT 10;
```

---

## 13. Golden Rules

1. **One organisation record per legal entity.** Never create duplicate records — use the duplicate check before inserting. Merge duplicates through a consolidation workflow, not deletion.
2. **Org type is multi-valued.** The same company can be a carrier, a customer, and a vendor. Use the `organisation_type` join table.
3. **Credit status blocks jobs at the database layer.** Do not rely on application code alone — a trigger or constraint should prevent job creation when `credit_status = 'BLOCKED'`.
4. **The address snapshot is immutable.** Once copied to `job_party.address_snapshot` at job creation, it never changes — even if the organisation updates its address later.
5. **Contacts are linked to organisations, not to jobs.** The job references the organisation; the notification system looks up contacts from the organisation at send time.
