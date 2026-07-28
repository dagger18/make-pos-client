# Freight Forwarder SaaS — Accounting, Invoicing, and P&L After Shipment Completion

## 1. Overview: The Accounting Flow

After a shipment is operationally complete (POD received, cargo delivered), the financial lifecycle begins its close-out phase. Every operational event that happened during the job — charges quoted, costs incurred, cargo delivered — must now translate into formal financial records.

The flow has six layers:

```
Charge lines (SELL + BUY)
        ↓
AR Invoices + AP Bills
        ↓
Credit Notes (if needed)
        ↓
Payments (AR received + AP paid)
        ↓
FX Gain / Loss on settlement
        ↓
General Ledger journal entries
        ↓
Period P&L by profit center
```

Each layer is independent and immutable once posted. Corrections at any layer flow forward as new documents — never as edits to existing records.

---

## 2. The Cost Sheet — The Job's Internal P&L View

The cost sheet is not a separate table. It is a **view** assembled from the charge lines on the job. It shows buy vs. sell per charge code, estimated vs. actual, and variance per line. It is the operator's real-time P&L window into the job.

```sql
CREATE VIEW job_cost_sheet AS
SELECT
  s.shipment_id,
  s.base_currency,
  cl.charge_code,
  cl.description,
  cl.category,                                            -- FREIGHT / LOCAL / CUSTOMS / SERVICE
  cl.payable_at,                                          -- ORIGIN / DESTINATION
  pc.name                                    AS profit_center,

  -- Sell side (revenue)
  SUM(CASE WHEN cl.type='SELL' THEN cl.orig_amount  ELSE 0 END) AS sell_orig_amount,
  MAX(CASE WHEN cl.type='SELL' THEN cl.orig_currency END)        AS sell_currency,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount  ELSE 0 END) AS sell_base_amount,

  -- Buy side (cost)
  SUM(CASE WHEN cl.type='BUY'  THEN cl.orig_amount  ELSE 0 END) AS buy_orig_amount,
  MAX(CASE WHEN cl.type='BUY'  THEN cl.orig_currency END)        AS buy_currency,
  SUM(CASE WHEN cl.type='BUY'  THEN cl.base_amount  ELSE 0 END) AS buy_base_amount,

  -- Margin per charge line
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) AS line_margin,

  -- Estimate flag
  BOOL_OR(cl.is_estimate)                               AS has_estimate,

  -- AP matching status
  MAX(abl.status)                                        AS ap_status  -- MATCHED / VARIANCE / UNMATCHED

FROM charge_line cl
JOIN shipment s          ON cl.job_id           = s.id
JOIN profit_center pc    ON cl.profit_center_id  = pc.id
LEFT JOIN ap_bill_line abl ON abl.charge_line_id = cl.id
GROUP BY s.shipment_id, s.base_currency, cl.charge_code,
         cl.description, cl.category, cl.payable_at, pc.name;
```

### Cost sheet summary per job

```sql
SELECT
  SUM(sell_base_amount)                              AS total_revenue,
  SUM(buy_base_amount)                               AS total_cost,
  SUM(sell_base_amount) - SUM(buy_base_amount)       AS gross_profit,
  ROUND(
    (SUM(sell_base_amount) - SUM(buy_base_amount))
    / NULLIF(SUM(sell_base_amount), 0) * 100, 2
  )                                                  AS margin_pct
FROM job_cost_sheet
WHERE shipment_id = :id;
```

---

## 3. AR Invoicing — The Revenue Side

### When invoices are generated

| Mode          | Trigger                            | Use case                                         |
| ------------- | ---------------------------------- | ------------------------------------------------ |
| **Manual**    | Operator clicks "Generate Invoice" | Full control — common for export jobs            |
| **Automatic** | POD milestone recorded             | Import jobs where delivery triggers billing      |
| **Scheduled** | End of month batch                 | High-volume accounts with monthly billing cycles |

### Invoice schema

```sql
CREATE TABLE invoice (
  id               UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  invoice_number   VARCHAR(64)   UNIQUE NOT NULL,    -- INV-HCM-202604-00234
  job_id           UUID          NOT NULL REFERENCES shipment(id),
  type             VARCHAR(8)    NOT NULL,            -- AR / AP
  status           VARCHAR(16)   NOT NULL,            -- DRAFT / ISSUED / PARTIALLY_PAID / PAID / VOID / WRITTEN_OFF

  -- Billing parties
  billed_to_org    UUID          NOT NULL REFERENCES organisation(id),
  billed_by_org    UUID          NOT NULL REFERENCES organisation(id),

  -- Amounts
  currency         CHAR(3)       NOT NULL,
  subtotal         NUMERIC(20,6) NOT NULL,
  tax_amount       NUMERIC(20,6) NOT NULL DEFAULT 0,
  total_amount     NUMERIC(20,6) NOT NULL,
  paid_amount      NUMERIC(20,6) NOT NULL DEFAULT 0,
  outstanding      NUMERIC(20,6) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,

  -- Base currency snapshot — frozen at issue time
  base_currency    CHAR(3)       NOT NULL,
  fx_rate          NUMERIC(20,6) NOT NULL,
  base_amount      NUMERIC(20,6) NOT NULL,

  -- Dates
  issue_date       DATE          NOT NULL,
  due_date         DATE          NOT NULL,
  payment_terms    VARCHAR(32),                       -- NET30 / NET60 / CIA / COD

  -- Attribution
  payable_at       VARCHAR(16)   NOT NULL,            -- ORIGIN / DESTINATION
  profit_center_id UUID          NOT NULL REFERENCES profit_center(id),

  -- Audit
  issued_by        UUID          REFERENCES app_user(id),
  voided_by        UUID          REFERENCES app_user(id),
  voided_at        TIMESTAMPTZ,
  void_reason      TEXT,
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE invoice_line (
  id               UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  invoice_id       UUID          NOT NULL REFERENCES invoice(id),
  charge_line_id   UUID          REFERENCES charge_line(id),  -- back-reference to source
  charge_code      VARCHAR(16)   NOT NULL,
  description      VARCHAR(255)  NOT NULL,
  quantity         NUMERIC(12,4) NOT NULL,
  unit_rate        NUMERIC(20,6) NOT NULL,
  amount           NUMERIC(20,6) NOT NULL,
  tax_rate         NUMERIC(6,4)  NOT NULL DEFAULT 0,
  tax_amount       NUMERIC(20,6) NOT NULL DEFAULT 0,
  currency         CHAR(3)       NOT NULL
);
```

### Multiple invoices per job

A single job commonly generates multiple invoices. Charges are billed to different parties or in different currencies depending on direction and Incoterm:

| Scenario               | Invoice(s) generated                                  | Billed to                                       |
| ---------------------- | ----------------------------------------------------- | ----------------------------------------------- |
| Export FOB             | 1 invoice                                             | Shipper (origin charges only)                   |
| Import prepaid freight | 1 invoice                                             | Consignee (destination charges only)            |
| Import collect freight | 1 invoice                                             | Consignee (destination + ocean freight)         |
| DDP                    | 1 invoice                                             | Shipper (all charges incl. destination duty)    |
| Cross-trade            | 1 invoice                                             | Your customer (all charges, both legs)          |
| Multi-currency charges | 1 per currency OR 1 invoice with multi-currency lines | Per customer agreement                          |
| Detention / demurrage  | Separate invoice                                      | Consignee (raised after empty return confirmed) |

### Invoice numbering format

Same configurable template system as shipment IDs:

```
INV-{BRANCH}-{YYYYMM}-{SEQ5}
Example:  INV-HCM-202604-00234

Credit note prefix:
CN-{BRANCH}-{YYYYMM}-{SEQ5}
Example:  CN-HCM-202604-00045
```

---

## 4. AP Billing — The Cost Side

### The AP matching workflow

Every BUY charge line on the job represents an expected cost. When the actual vendor invoice arrives, it is matched against the corresponding charge line. Any difference is a **variance**.

```sql
CREATE TABLE ap_bill (
  id               UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  bill_number      VARCHAR(64)   UNIQUE NOT NULL,    -- BILL-HCM-202604-00089
  job_id           UUID          NOT NULL REFERENCES shipment(id),
  vendor_id        UUID          NOT NULL REFERENCES organisation(id),
  vendor_ref       VARCHAR(64),                      -- vendor's own invoice number
  status           VARCHAR(16)   NOT NULL,            -- RECEIVED / MATCHED / VARIANCE / APPROVED / PAID

  currency         CHAR(3)       NOT NULL,
  subtotal         NUMERIC(20,6) NOT NULL,
  tax_amount       NUMERIC(20,6) NOT NULL DEFAULT 0,
  total_amount     NUMERIC(20,6) NOT NULL,

  base_currency    CHAR(3)       NOT NULL,
  fx_rate          NUMERIC(20,6) NOT NULL,
  base_amount      NUMERIC(20,6) NOT NULL,

  received_date    DATE          NOT NULL,
  due_date         DATE,
  approved_by      UUID          REFERENCES app_user(id),
  approved_at      TIMESTAMPTZ,
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE ap_bill_line (
  id               UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  ap_bill_id       UUID          NOT NULL REFERENCES ap_bill(id),
  charge_line_id   UUID          REFERENCES charge_line(id),   -- matched BUY charge line
  charge_code      VARCHAR(16)   NOT NULL,
  description      VARCHAR(255),
  billed_amount    NUMERIC(20,6) NOT NULL,    -- what the vendor actually charged
  expected_amount  NUMERIC(20,6),             -- what the buy charge line estimated
  variance         NUMERIC(20,6) GENERATED ALWAYS AS (billed_amount - expected_amount) STORED,
  variance_pct     NUMERIC(8,4),
  is_approved      BOOLEAN       NOT NULL DEFAULT false,
  variance_reason  TEXT                       -- required when variance exceeds threshold
);
```

### Variance handling rules

| Variance                                  | System action                                                                   |
| ----------------------------------------- | ------------------------------------------------------------------------------- |
| Zero                                      | Auto-matched — no action required                                               |
| Within tolerance (configurable, e.g. ±2%) | Auto-approved — minor rounding or rate differences                              |
| Above tolerance                           | Requires manual approval — operator reviews and approves or disputes            |
| Disputed                                  | AP bill marked `DISPUTED`, vendor notified, credit note or correction requested |

### Common AP variance causes in freight

| Cause                     | Explanation                                                      |
| ------------------------- | ---------------------------------------------------------------- |
| GRI applied late          | Carrier issued a General Rate Increase after the rate was quoted |
| Weight difference         | Actual weight higher or lower than estimated on LCL or air jobs  |
| Detention / demurrage     | Container returned late — accrued beyond the estimated free time |
| Bunker update             | BAF/FSC changed between quote date and vendor invoice date       |
| Customs duty reassessment | Customs assessed a different CIF value than declared             |
| Additional inspection     | Port authority inspection not anticipated at quote time          |
| Minimum charge applied    | Vendor's minimum higher than estimated per-unit rate             |

---

## 5. Payments

### AR payment — customer pays you

```sql
CREATE TABLE ar_payment (
  id                    UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  invoice_id            UUID          NOT NULL REFERENCES invoice(id),
  payment_date          DATE          NOT NULL,
  payment_method        VARCHAR(16)   NOT NULL,   -- BANK_TRANSFER / CHEQUE / CASH / OFFSET
  reference             VARCHAR(64),              -- bank transaction reference number

  -- What the customer paid
  paid_currency         CHAR(3)       NOT NULL,
  paid_amount           NUMERIC(20,6) NOT NULL,

  -- Base currency conversion at payment date
  base_currency         CHAR(3)       NOT NULL,
  fx_rate_at_payment    NUMERIC(20,6) NOT NULL,   -- market rate on payment date
  base_amount_received  NUMERIC(20,6) NOT NULL,   -- paid_amount × fx_rate_at_payment

  -- FX gain / loss
  base_amount_expected  NUMERIC(20,6) NOT NULL,   -- invoice base_amount (frozen at issue)
  fx_gain_loss          NUMERIC(20,6) GENERATED ALWAYS AS
                        (base_amount_received - base_amount_expected) STORED,
                        -- positive = FX gain (VND strengthened after invoice)
                        -- negative = FX loss (VND weakened after invoice)

  received_by           UUID          REFERENCES app_user(id),
  created_at            TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### AP payment — you pay the vendor

```sql
CREATE TABLE ap_payment (
  id                    UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  ap_bill_id            UUID          NOT NULL REFERENCES ap_bill(id),
  payment_date          DATE          NOT NULL,
  payment_method        VARCHAR(16)   NOT NULL,
  reference             VARCHAR(64),

  paid_currency         CHAR(3)       NOT NULL,
  paid_amount           NUMERIC(20,6) NOT NULL,

  base_currency         CHAR(3)       NOT NULL,
  fx_rate_at_payment    NUMERIC(20,6) NOT NULL,
  base_amount_paid      NUMERIC(20,6) NOT NULL,   -- paid_amount × fx_rate_at_payment

  base_amount_expected  NUMERIC(20,6) NOT NULL,   -- ap_bill base_amount (frozen at receipt)
  fx_gain_loss          NUMERIC(20,6) GENERATED ALWAYS AS
                        (base_amount_expected - base_amount_paid) STORED,
                        -- positive = you paid less in base currency than expected = gain
                        -- negative = you paid more than expected = loss

  paid_by               UUID          REFERENCES app_user(id),
  created_at            TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### FX gain and loss explained

```
Invoice raised:     USD 1,000  at fx_rate 24,500  →  VND 24,500,000  ← frozen at issue time
Customer pays:      USD 1,000  at fx_rate 24,200  →  VND 24,200,000  ← actual bank rate on payment date

FX loss = 24,200,000 − 24,500,000 = −300,000 VND

The VND weakened against USD between invoice date and payment date.
You received less base currency than the invoice said you would.
Post −300,000 VND to FX Loss account in the general ledger.
```

```
AP bill received:   USD 880  at fx_rate 24,500  →  VND 21,560,000  ← frozen at receipt
You pay vendor:     USD 880  at fx_rate 24,200  →  VND 21,296,000  ← actual bank rate on payment date

FX gain = 21,560,000 − 21,296,000 = +264,000 VND

You paid fewer VND than the AP bill said you would.
Post +264,000 VND to FX Gain account in the general ledger.
```

---

## 6. Credit Notes

Credit notes reverse part or all of an issued invoice. The original invoice is immutable — a credit note is a new document that reduces the outstanding balance.

```sql
CREATE TABLE credit_note (
  id               UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  cn_number        VARCHAR(64)   UNIQUE NOT NULL,    -- CN-HCM-202604-00045
  job_id           UUID          NOT NULL REFERENCES shipment(id),
  invoice_id       UUID          NOT NULL REFERENCES invoice(id),
  type             VARCHAR(8)    NOT NULL,            -- AR (reduces AR) / AP (reduces AP)
  reason           VARCHAR(64)   NOT NULL,            -- see reason codes below
  status           VARCHAR(16)   NOT NULL,            -- DRAFT / ISSUED / APPLIED

  currency         CHAR(3)       NOT NULL,
  amount           NUMERIC(20,6) NOT NULL,            -- always positive; reduces invoice balance
  tax_amount       NUMERIC(20,6) NOT NULL DEFAULT 0,
  total_amount     NUMERIC(20,6) NOT NULL,

  base_currency    CHAR(3)       NOT NULL,
  fx_rate          NUMERIC(20,6) NOT NULL,
  base_amount      NUMERIC(20,6) NOT NULL,

  issued_by        UUID          REFERENCES app_user(id),
  issued_at        TIMESTAMPTZ,
  created_at       TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Credit note reason codes

| Reason code         | Typical scenario                                                   |
| ------------------- | ------------------------------------------------------------------ |
| `RATE_ERROR`        | Wrong rate applied at invoicing — corrected after customer query   |
| `DUPLICATE`         | Same charge invoiced twice                                         |
| `WEIGHT_ADJUSTMENT` | Actual weight lower than estimated on LCL or air — revenue reduced |
| `DISPUTE`           | Customer disputes a surcharge — partially conceded by operations   |
| `REBATE`            | Volume rebate applied to a high-value customer at end of month     |
| `CARRIER_CREDIT`    | Carrier issued a credit on the MBL — passed through to consignee   |
| `SHORTFALL`         | Cargo was short-shipped — delivery incomplete, revenue adjusted    |
| `OVERBILLING`       | Quantity or unit rate applied incorrectly                          |

---

## 7. The General Ledger — Double-Entry Bookkeeping

Every financial event generates a journal entry. The general ledger is always in balance — total debits always equal total credits across every journal entry.

### Chart of accounts (freight-specific)

| Code   | Account name              | Type                   |
| ------ | ------------------------- | ---------------------- |
| `1100` | Accounts Receivable       | Asset                  |
| `1110` | AR — Ocean Freight        | Asset                  |
| `1120` | AR — Air Freight          | Asset                  |
| `1130` | AR — Local Charges        | Asset                  |
| `1140` | AR — Customs Charges      | Asset                  |
| `1200` | Cash and Bank             | Asset                  |
| `2100` | Accounts Payable          | Liability              |
| `2110` | AP — Carriers             | Liability              |
| `2120` | AP — Overseas Agents      | Liability              |
| `2130` | AP — Customs Brokers      | Liability              |
| `2140` | AP — Truckers             | Liability              |
| `4100` | Revenue — Ocean Freight   | Revenue                |
| `4110` | Revenue — Air Freight     | Revenue                |
| `4120` | Revenue — Local Charges   | Revenue                |
| `4130` | Revenue — Customs Charges | Revenue                |
| `4140` | Revenue — Service Charges | Revenue                |
| `5100` | COGS — Ocean Freight      | Cost of Sales          |
| `5110` | COGS — Air Freight        | Cost of Sales          |
| `5120` | COGS — Local Charges      | Cost of Sales          |
| `5130` | COGS — Customs / Duty     | Cost of Sales          |
| `5140` | COGS — Service Charges    | Cost of Sales          |
| `6900` | FX Gain / Loss            | Other Income / Expense |

### Journal entries per event

**1. AR invoice issued:**

```
DR  Accounts Receivable (1100)     1,200.00 USD   ← asset increases
CR  Revenue — Ocean    (4100)        800.00 USD   ← revenue recognised
CR  Revenue — Local    (4120)        250.00 USD
CR  Revenue — Customs  (4130)        150.00 USD
```

**2. AP bill received:**

```
DR  COGS — Ocean       (5100)        600.00 USD   ← cost recognised
DR  COGS — Local       (5120)        180.00 USD
DR  COGS — Customs     (5130)        100.00 USD
CR  Accounts Payable   (2100)        880.00 USD   ← liability increases
```

**3. Customer payment received (with FX loss):**

```
DR  Cash / Bank        (1200)   24,200,000 VND   ← actual cash received
DR  FX Loss            (6900)      300,000 VND   ← gap vs invoice rate
CR  Accounts Receivable(1100)   24,500,000 VND   ← AR cleared
```

**4. Vendor payment made (with FX gain):**

```
DR  Accounts Payable   (2100)   21,560,000 VND   ← AP cleared
CR  Cash / Bank        (1200)   21,296,000 VND   ← actual cash paid
CR  FX Gain            (6900)      264,000 VND   ← paid less than expected
```

**5. Credit note issued to customer:**

```
DR  Revenue — Ocean    (4100)        100.00 USD   ← revenue reduced
CR  Accounts Receivable(1100)        100.00 USD   ← AR reduced
```

**6. Credit note received from vendor:**

```
DR  Accounts Payable   (2100)         50.00 USD   ← AP reduced
CR  COGS — Ocean       (5100)         50.00 USD   ← cost reduced
```

### General ledger schema

```sql
CREATE TABLE journal_entry (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  journal_number  VARCHAR(64)   UNIQUE NOT NULL,
  job_id          UUID          REFERENCES shipment(id),
  source_type     VARCHAR(32)   NOT NULL,   -- AR_INVOICE / AP_BILL / AR_PAYMENT / AP_PAYMENT / CREDIT_NOTE / MANUAL
  source_id       UUID          NOT NULL,   -- FK to the originating document
  entry_date      DATE          NOT NULL,
  description     TEXT,
  is_posted       BOOLEAN       NOT NULL DEFAULT false,
  posted_at       TIMESTAMPTZ,
  posted_by       UUID          REFERENCES app_user(id),
  created_at      TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE journal_line (
  id               UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  journal_id       UUID          NOT NULL REFERENCES journal_entry(id),
  account_code     VARCHAR(16)   NOT NULL REFERENCES chart_of_accounts(code),
  debit            NUMERIC(20,6) NOT NULL DEFAULT 0,
  credit           NUMERIC(20,6) NOT NULL DEFAULT 0,
  currency         CHAR(3)       NOT NULL,
  base_currency    CHAR(3)       NOT NULL,
  fx_rate          NUMERIC(20,6) NOT NULL DEFAULT 1,
  base_debit       NUMERIC(20,6) NOT NULL DEFAULT 0,
  base_credit      NUMERIC(20,6) NOT NULL DEFAULT 0,
  profit_center_id UUID          REFERENCES profit_center(id),
  description      TEXT,

  CONSTRAINT chk_debit_or_credit CHECK (
    (debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)
  )
);

-- Trigger to enforce balanced journals before posting
CREATE OR REPLACE FUNCTION check_journal_balance()
RETURNS TRIGGER AS $$
DECLARE
  total_debit  NUMERIC;
  total_credit NUMERIC;
BEGIN
  SELECT SUM(base_debit), SUM(base_credit)
  INTO total_debit, total_credit
  FROM journal_line
  WHERE journal_id = NEW.journal_id;

  IF ABS(total_debit - total_credit) > 0.01 THEN
    RAISE EXCEPTION 'Journal % is unbalanced: debits=% credits=%',
      NEW.journal_id, total_debit, total_credit;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;
```

---

## 8. Job Profit and Loss — The Complete Picture

After a job is closed, its full P&L combines three components:

```
GROSS PROFIT
= Total sell base_amount (frozen fx snapshots at charge line creation)
− Total buy  base_amount (frozen fx snapshots at charge line creation)

FX GAIN / LOSS ON SETTLEMENT
= SUM(fx_gain_loss) across all AR payments and AP payments

NET JOB PROFIT
= Gross profit + FX gain/loss on settlement
```

### The three profit numbers every freight forwarder cares about

| Number                      | Formula                             | What it tells you                            |
| --------------------------- | ----------------------------------- | -------------------------------------------- |
| **Gross margin %**          | `(sell − buy) / sell × 100`         | How efficiently you priced relative to costs |
| **Net margin %**            | `(sell − buy + fx_gl) / sell × 100` | True profit after FX settlement effects      |
| **Contribution (absolute)** | `sell − buy` in base currency       | Absolute contribution to company overhead    |

### Estimated vs. actual variance

At job creation, all BUY charge lines marked `is_estimate = true` are estimates — the actual AP bills have not yet arrived. As AP bills are matched, estimates are replaced with actuals. The **cost estimate accuracy** metric tracks how well the buying team predicts costs at quote time.

```sql
-- Estimated vs actual variance per job
SELECT
  cl.charge_code,
  cl.description,
  SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END)   AS estimated_cost,
  COALESCE(SUM(abl.billed_amount * er.rate), 0)                  AS actual_cost,
  COALESCE(SUM(abl.billed_amount * er.rate), 0)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) AS variance,
  CASE
    WHEN SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) = 0 THEN NULL
    ELSE ROUND(
      (COALESCE(SUM(abl.billed_amount * er.rate), 0)
       - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END))
      / SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) * 100,
    2)
  END                                                             AS variance_pct
FROM charge_line cl
LEFT JOIN ap_bill_line abl ON abl.charge_line_id = cl.id
LEFT JOIN ap_bill ab        ON abl.ap_bill_id = ab.id
LEFT JOIN exchange_rate er  ON er.from_currency = ab.currency
  AND er.to_currency = :base_currency
  AND er.effective_date = ab.received_date
WHERE cl.job_id = :job_id AND cl.type = 'BUY'
GROUP BY cl.charge_code, cl.description
ORDER BY ABS(variance) DESC;
```

---

## 9. Ageing Reports

### AR ageing — what customers owe you

```sql
SELECT
  o.name                                                    AS customer,
  i.currency,
  SUM(i.outstanding)                                        AS total_outstanding,
  SUM(CASE WHEN CURRENT_DATE - i.due_date <= 0
      THEN i.outstanding ELSE 0 END)                        AS current_not_due,
  SUM(CASE WHEN CURRENT_DATE - i.due_date BETWEEN 1  AND 30
      THEN i.outstanding ELSE 0 END)                        AS overdue_1_30,
  SUM(CASE WHEN CURRENT_DATE - i.due_date BETWEEN 31 AND 60
      THEN i.outstanding ELSE 0 END)                        AS overdue_31_60,
  SUM(CASE WHEN CURRENT_DATE - i.due_date BETWEEN 61 AND 90
      THEN i.outstanding ELSE 0 END)                        AS overdue_61_90,
  SUM(CASE WHEN CURRENT_DATE - i.due_date > 90
      THEN i.outstanding ELSE 0 END)                        AS overdue_90plus
FROM invoice i
JOIN organisation o ON i.billed_to_org = o.id
WHERE i.type   = 'AR'
  AND i.status NOT IN ('PAID', 'VOID', 'WRITTEN_OFF')
GROUP BY o.id, o.name, i.currency
ORDER BY total_outstanding DESC;
```

### AP ageing — what you owe vendors

Same query structure, `type = 'AP'`, grouped by vendor. Finance uses this to plan cash outflows and avoid late payment penalties with carriers and agents.

---

## 10. Period-End P&L Report by Profit Center

The primary management report — how much each branch and department made in a given period.

```sql
SELECT
  pc.name                                                          AS profit_center,
  b.name                                                           AS branch,
  pc.direction,
  COUNT(DISTINCT cl.job_id)                                        AS jobs_closed,

  -- Revenue (from frozen sell charge line snapshots)
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)    AS revenue,

  -- Cost (from frozen buy charge line snapshots)
  SUM(CASE WHEN cl.type='BUY'  THEN cl.base_amount ELSE 0 END)    AS cost,

  -- Gross profit
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END)   AS gross_profit,

  -- FX gain / loss on settlement
  COALESCE(SUM(arp.fx_gain_loss), 0)
  + COALESCE(SUM(app2.fx_gain_loss), 0)                           AS fx_gain_loss,

  -- Net profit
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END)
  + COALESCE(SUM(arp.fx_gain_loss), 0)
  + COALESCE(SUM(app2.fx_gain_loss), 0)                           AS net_profit,

  -- Margin %
  ROUND(
    (SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
    - SUM(CASE WHEN cl.type='BUY'  THEN cl.base_amount ELSE 0 END))
    / NULLIF(SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END), 0) * 100,
  2)                                                               AS margin_pct

FROM charge_line cl
JOIN profit_center pc  ON cl.profit_center_id = pc.id
JOIN branch b          ON pc.branch_id         = b.id
JOIN shipment s        ON cl.job_id            = s.id

LEFT JOIN ar_payment arp ON arp.invoice_id IN (
  SELECT id FROM invoice WHERE job_id = s.id AND type = 'AR'
)
LEFT JOIN ap_payment app2 ON app2.ap_bill_id IN (
  SELECT id FROM ap_bill WHERE job_id = s.id
)

WHERE s.closed_at BETWEEN :date_from AND :date_to

GROUP BY pc.id, pc.name, b.id, b.name, pc.direction
ORDER BY net_profit DESC;
```

### Revenue recognition note

Period reporting uses `closed_at`, not `created_at`. A job created in March but closed in April belongs to April's P&L. Always filter period reports by close date. Some companies use `issue_date` on the AR invoice as the recognition date instead — choose one convention and apply it consistently.

---

## 11. The Accounting Close Workflow

After all operational milestones are complete, the accounting close follows this sequence in order:

```
Step 1 — VERIFY COST SHEET
  All BUY charge lines have matched AP bills
  All variances are approved or formally disputed
  No charge lines still flagged is_estimate = true

Step 2 — GENERATE AR INVOICES
  One per billing party per payable_at group
  Lock charge lines: is_locked = true (no further edits)
  Freeze fx_rate on each invoice at today's rate

Step 3 — RECEIVE AND MATCH AP BILLS
  Match each vendor bill to corresponding BUY charge lines
  Auto-approve variances within tolerance
  Flag variances above threshold for manual review
  Approve or dispute — do not leave unresolved

Step 4 — COLLECT AR PAYMENTS
  Record each customer payment with bank FX rate on payment date
  Post FX gain/loss journal entries
  Apply payment to invoice outstanding balance

Step 5 — MAKE AP PAYMENTS
  Pay each vendor, record with bank FX rate on payment date
  Post FX gain/loss journal entries
  Mark AP bill as PAID

Step 6 — RECONCILE
  AR balance for this job = 0 (all paid, voided, or written off)
  AP balance for this job = 0 (all paid)
  Journal entries balanced (total debits = total credits)
  No unposted journal entries

Step 7 — CLOSE JOB
  status = CLOSED
    OR status = CLOSED_WITH_VARIANCE (if unresolved variances remain)
  closed_at = now()
  closed_by = current_user
  All sub-objects become read-only

Step 8 — POST TO PERIOD
  Job P&L included in period-end report for the month of closed_at
  FX gain/loss posted to FX account in general ledger
  Job excluded from all open-job reports and dashboards
```

---

## 12. Key Reports Summary

| Report                               | Primary audience        | Driven by                   | Period filter       |
| ------------------------------------ | ----------------------- | --------------------------- | ------------------- |
| Job cost sheet                       | Operator, supervisor    | charge_line view            | Per job             |
| AR ageing                            | Finance                 | invoice.outstanding         | As of today         |
| AP ageing                            | Finance                 | ap_bill.outstanding         | As of today         |
| Gross profit by profit center        | Branch manager          | charge_line snapshots       | closed_at range     |
| Net profit (incl. FX)                | Finance director        | charge_lines + payments     | closed_at range     |
| Variance report (estimate vs actual) | Operations, procurement | charge_line vs ap_bill_line | Job or period       |
| FX gain/loss report                  | Finance                 | ar_payment + ap_payment     | payment_date range  |
| Revenue by mode / direction          | Management              | charge_line + shipment      | closed_at range     |
| Customer profitability               | Sales                   | charge_line + job_party     | closed_at range     |
| Carrier cost analysis                | Procurement             | ap_bill + charge_line       | received_date range |

---

## 13. Summary: The Golden Rules

1. **Charge lines are the source of truth for P&L.** Invoices, AP bills, and cost sheets all derive from charge lines — never the reverse.
2. **Invoices are immutable once issued.** Corrections go through credit notes. The original invoice number and amount are permanent records.
3. **The cost sheet is a view, not a table.** It is assembled from charge lines on demand and does not store data independently.
4. **Gross profit uses frozen fx snapshots. FX gain/loss is a separate calculation.** Never combine them into a single profit figure without labelling. They answer different questions and occur at different points in time.
5. **Every financial event posts a balanced journal entry.** Total debits must equal total credits across every journal. A trigger enforces this before posting.
6. **AP bills must be matched before the job can close.** Unmatched AP bills mean the cost is still unknown — the job's P&L is incomplete.
7. **Variances are features, not errors.** The difference between the estimated buy rate and the actual AP bill is normal in freight. The system tracks, approves, and reports variances — it does not hide them.
8. **Period reporting uses closed_at, not created_at.** A job created in March but closed in April belongs to April's P&L. Apply one convention consistently across all reports.
9. **AR ageing and AP ageing drive cash management.** Outstanding AR tells you who owes you and when. Outstanding AP tells you what you owe and when. Both are looked at daily by finance.
10. **Written-off AR is not deleted.** When a customer does not pay and the debt is written off, the invoice is marked `WRITTEN_OFF` and a journal entry posts the loss to a bad debt account. The record is permanent.
