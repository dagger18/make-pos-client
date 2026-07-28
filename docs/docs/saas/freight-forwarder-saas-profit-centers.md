# Freight Forwarder SaaS — Profit Centers, Charge Visibility, and Departmental P&L

## 1. The Core Problem

A single shipment can span two countries, two branches, and two departments. Each department must:

- See only the charges they are responsible for
- Invoice only their own customers or vendors
- Contribute their own margin to their own P&L independently

The total shipment profit is the sum of all profit centers involved. These three requirements are solved by two separate mechanisms that are often confused:

| Mechanism | Field | Controls |
|---|---|---|
| **Visibility** | `visible_to` / `payable_at` on `charge_line` | Which department's users can see this charge in the UI |
| **P&L attribution** | `profit_center_id` on `charge_line` | Whose revenue and cost ledger this charge contributes to |

These two fields usually point to the same department — but for DDP shipments and cross-trade jobs they deliberately diverge. Keeping them separate is the key design decision.

---

## 2. The Profit Center Table

A profit center is the unit of P&L measurement. It maps to a branch + direction combination.

```sql
CREATE TABLE profit_center (
  id            UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  branch_id     UUID        NOT NULL REFERENCES branch(id),
  direction     VARCHAR(16),              -- EXP / IMP / XTD / DOM / TSH — NULL means all directions
  name          VARCHAR(64) NOT NULL,     -- "HCM Export Dept", "HAN Import Dept"
  currency      CHAR(3)     NOT NULL,     -- P&L reporting currency for this center
  is_active     BOOLEAN     NOT NULL DEFAULT true,
  created_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX idx_pc_branch_dir ON profit_center (branch_id, direction)
  WHERE is_active = true;
```

Example seed data:

| name | branch | direction |
|---|---|---|
| HCM Export | Ho Chi Minh City | EXP |
| HCM Import | Ho Chi Minh City | IMP |
| HCM Cross-trade | Ho Chi Minh City | XTD |
| HCM Domestic | Ho Chi Minh City | DOM |
| HAN Export | Hanoi | EXP |
| HAN Import | Hanoi | IMP |
| SIN Hub | Singapore | TSH |

---

## 3. The Two Fields on Every Charge Line

```sql
ALTER TABLE charge_line ADD COLUMN payable_at       VARCHAR(16) NOT NULL DEFAULT 'ORIGIN';
ALTER TABLE charge_line ADD COLUMN visible_to       VARCHAR(16) NOT NULL DEFAULT 'ORIGIN';
ALTER TABLE charge_line ADD COLUMN profit_center_id UUID        NOT NULL REFERENCES profit_center(id);
```

### `payable_at`

Determines which side of the shipment raises the AR invoice or receives the AP bill.

| Value | Meaning |
|---|---|
| `ORIGIN` | Charge is billed/paid at the origin side |
| `DESTINATION` | Charge is billed/paid at the destination side |
| `BOTH` | Charge spans both sides — used for domestic, transshipment, and cross-trade freight |

This field drives **invoice routing**: when an AR invoice is generated, the system groups charge lines by `payable_at` to determine which branch office raises the invoice and which customer is billed.

### `visible_to`

Determines which department's users can query and see this charge line in the UI.

| Value | Meaning |
|---|---|
| `ORIGIN` | Only export / origin department users |
| `DESTINATION` | Only import / destination department users |
| `ALL` | Managers, finance staff, or admin roles |

### `profit_center_id`

The profit center whose revenue and cost ledger this charge line contributes to. This is a foreign key — not a string enum — so P&L reports join directly to `profit_center` without string matching.

### Why `visible_to` and `profit_center_id` can differ

**Example: DDP shipment (Delivered Duty Paid)**

The shipper pays everything, including destination import duty and customs clearance. The AR invoice for these charges goes to the origin party (`payable_at = ORIGIN`). But the import department arranged the customs clearance and earned the margin on those charges — so `profit_center_id` points to the import/destination profit center.

```
Charge: Import customs clearance fee
  payable_at       = ORIGIN       (invoice goes to the shipper at origin)
  visible_to       = DESTINATION  (import dept manages this charge)
  profit_center_id = HCM_IMPORT   (import dept's P&L gets the margin)
```

---

## 4. Charge Routing Rules by Direction

### Export (EXP)

One profit center: the origin/export department.

| Charge | payable_at | visible_to | profit_center |
|---|---|---|---|
| Origin trucking | ORIGIN | ORIGIN | EXP dept |
| Export customs fee | ORIGIN | ORIGIN | EXP dept |
| THC origin | ORIGIN | ORIGIN | EXP dept |
| Ocean/air freight (if CFR+) | ORIGIN | ORIGIN | EXP dept |
| Insurance (if CIF/CIP) | ORIGIN | ORIGIN | EXP dept |
| Carrier cost (buy rate) | ORIGIN | ORIGIN | EXP dept |
| Destination charges | DESTINATION | ALL | — not in this job |

The export department's AR invoice targets the shipper for all charges within the seller's Incoterm scope. Destination charges are flagged `payable_at = DESTINATION` and either excluded from the export job or passed to the overseas agent's import job.

### Import (IMP)

One profit center: the destination/import department.

| Charge | payable_at | visible_to | profit_center |
|---|---|---|---|
| THC destination | DESTINATION | DESTINATION | IMP dept |
| Import customs fee | DESTINATION | DESTINATION | IMP dept |
| Import duty + VAT | DESTINATION | DESTINATION | IMP dept |
| D/O fee | DESTINATION | DESTINATION | IMP dept |
| Delivery trucking | DESTINATION | DESTINATION | IMP dept |
| Freight (if collect) | DESTINATION | DESTINATION | IMP dept |
| Customs broker cost (buy) | DESTINATION | DESTINATION | IMP dept |
| DDP charges (special case) | ORIGIN | DESTINATION | IMP dept |

Under **collect freight terms** (EXW, FCA, FOB): the import department also invoices ocean/air freight to the consignee. Under **DDP**: all charges including duty are invoiced to the origin shipper, but `profit_center_id` still points to the import profit center.

### Cross-trade (XTD)

One profit center: your coordinating office. But `payable_at` is still set per charge so AR/AP invoices route correctly to each side.

| Charge | payable_at | visible_to | profit_center |
|---|---|---|---|
| Origin agent fee | ORIGIN | ALL | YOUR office |
| Origin local charges | ORIGIN | ALL | YOUR office |
| Export customs cost | ORIGIN | ALL | YOUR office |
| Ocean/air freight (buy) | BOTH | ALL | YOUR office |
| Freight sell to customer | BOTH | ALL | YOUR office |
| Destination agent fee | DESTINATION | ALL | YOUR office |
| Destination local charges | DESTINATION | ALL | YOUR office |
| Import duty (if DDP) | DESTINATION | ALL | YOUR office |

Your office is the only profit center even though charges exist at both ends. There is no origin department and no destination department — your coordinating team owns all cost and all revenue. The margin is total sell minus total buy across both legs.

### Domestic (DOM)

One profit center: the operating branch. No origin/destination split is meaningful.

| Charge | payable_at | visible_to | profit_center |
|---|---|---|---|
| Trucking base rate | BOTH | ALL | Branch |
| Fuel surcharge | BOTH | ALL | Branch |
| Toll charges | BOTH | ALL | Branch |
| Additional stop | BOTH | ALL | Branch |
| Haulier cost (buy) | BOTH | ALL | Branch |

No customs charges, no BL/AWB fees, no freight terms distinction. One AR invoice to whoever contracted the transport. One AP bill to the haulier.

### Transshipment (TSH)

One profit center: the hub branch. The customer is the origin agent or carrier — not the end shipper/consignee.

| Charge | payable_at | visible_to | profit_center |
|---|---|---|---|
| THC inbound | BOTH | ALL | Hub branch |
| THC outbound | BOTH | ALL | Hub branch |
| Storage at hub | BOTH | ALL | Hub branch |
| Inspection fee | BOTH | ALL | Hub branch |
| Terminal cost (buy) | BOTH | ALL | Hub branch |

Transshipment is a B2B service job. No HBL is issued. The MBL covers through-transport and was issued by the carrier. Your hub charges the carrier or the origin forwarder directly.

---

## 5. When Your Company Owns Both Origin and Destination

When a shipment moves between two of your own offices (e.g. HCM export → HAN import), both profit centers exist within your own system. The job generates two sub-jobs — one owned by each branch — and two separate P&L records.

```
Shipment: HCM → HAN (both are your offices)

Export job (HCM):
  Revenue:  $1,200  (billed to shipper)
  Cost:     $900    (paid to carrier)
  Profit:   $300    → recorded to HCM EXP profit center

Import job (HAN):
  Revenue:  $850    (billed to consignee)
  Cost:     $620    (paid to customs broker + trucker)
  Profit:   $230    → recorded to HAN IMP profit center

Total shipment profit: $530
```

Neither department can see the other's cost sheet or margin. An HCM export operator querying the job sees only `visible_to = ORIGIN` charge lines. An HAN import operator sees only `visible_to = DESTINATION` lines. A manager with `visible_to = ALL` access sees both.

The inter-office relationship (that HCM is coordinating with HAN) is recorded on the shipment header but does not affect how each department's charge lines are stored or reported.

---

## 6. Row-Level Security (Database Layer)

The safest implementation enforces visibility at the database level using Row-Level Security (RLS), so the filter cannot be accidentally omitted by application code.

```sql
-- Enable RLS on the charge_line table
ALTER TABLE charge_line ENABLE ROW LEVEL SECURITY;

-- Policy: users see charge lines matching their department, or ALL lines if admin/manager
CREATE POLICY charge_line_dept_visibility ON charge_line
  AS PERMISSIVE FOR SELECT
  USING (
    visible_to = 'ALL'
    OR visible_to = current_setting('app.user_dept', true)
    OR current_setting('app.user_role', true) IN ('ADMIN', 'MANAGER', 'FINANCE')
  );

-- Write policy: only the owning department can insert/update their charge lines
CREATE POLICY charge_line_dept_write ON charge_line
  AS PERMISSIVE FOR INSERT
  WITH CHECK (
    visible_to = current_setting('app.user_dept', true)
    OR current_setting('app.user_role', true) = 'ADMIN'
  );
```

At session start, the application sets the department context:

```sql
-- For an export operator at HCM
SET LOCAL app.user_dept = 'ORIGIN';
SET LOCAL app.user_role = 'OPERATOR';

-- For an import operator
SET LOCAL app.user_dept = 'DESTINATION';
SET LOCAL app.user_role = 'OPERATOR';

-- For a branch manager (sees all charge lines in their branch)
SET LOCAL app.user_dept = 'ALL';
SET LOCAL app.user_role = 'MANAGER';
```

Every subsequent `SELECT` on `charge_line` within that session is automatically filtered. No application-level `WHERE visible_to = ?` clause is required — and cannot be forgotten.

---

## 7. Application-Layer Permission Model

In addition to RLS, the application layer enforces permissions at the feature level. The two dimensions are:

**Dimension 1 — Role:** what actions a user can perform (read, create, edit, approve, close)
**Dimension 2 — Scope:** which profit centers a user can access

```sql
CREATE TABLE user_profit_center_access (
  user_id           UUID NOT NULL REFERENCES app_user(id),
  profit_center_id  UUID NOT NULL REFERENCES profit_center(id),
  access_level      VARCHAR(16) NOT NULL,  -- READ / WRITE / APPROVE / ALL
  PRIMARY KEY (user_id, profit_center_id)
);
```

A user with `WRITE` access to `HCM Export` can create and edit charge lines with `profit_center_id = HCM_EXPORT`. They cannot create charge lines for `HAN Import` even if they can read them through a shared shipment view.

A finance user with `READ` access to all profit centers can pull the consolidated P&L report but cannot create or edit any charge line.

---

## 8. P&L Queries

### Per profit center for a reporting period

```sql
SELECT
  pc.name                                                        AS profit_center,
  pc.currency                                                    AS currency,
  COUNT(DISTINCT cl.job_id)                                      AS job_count,
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END) AS revenue,
  SUM(CASE WHEN cl.type = 'BUY'  THEN cl.base_amount ELSE 0 END) AS cost,
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type = 'BUY' THEN cl.base_amount ELSE 0 END) AS profit,
  ROUND(
    (SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END)
    - SUM(CASE WHEN cl.type = 'BUY'  THEN cl.base_amount ELSE 0 END))
    / NULLIF(SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END), 0) * 100,
    2
  )                                                              AS margin_pct
FROM charge_line cl
JOIN profit_center pc ON cl.profit_center_id = pc.id
JOIN shipment s ON cl.job_id = s.id
WHERE s.created_at BETWEEN :date_from AND :date_to
GROUP BY pc.id, pc.name, pc.currency
ORDER BY profit DESC;
```

### Total company P&L (sum of all profit centers)

```sql
SELECT
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END) AS total_revenue,
  SUM(CASE WHEN cl.type = 'BUY'  THEN cl.base_amount ELSE 0 END) AS total_cost,
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type = 'BUY' THEN cl.base_amount ELSE 0 END) AS total_profit
FROM charge_line cl
JOIN shipment s ON cl.job_id = s.id
WHERE s.created_at BETWEEN :date_from AND :date_to;
```

### Per-shipment P&L breakdown (multi-profit-center shipments)

```sql
SELECT
  s.shipment_id,
  pc.name                                                        AS profit_center,
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END) AS revenue,
  SUM(CASE WHEN cl.type = 'BUY'  THEN cl.base_amount ELSE 0 END) AS cost,
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type = 'BUY' THEN cl.base_amount ELSE 0 END) AS profit
FROM charge_line cl
JOIN profit_center pc ON cl.profit_center_id = pc.id
JOIN shipment s ON cl.job_id = s.id
WHERE s.id = :shipment_id
GROUP BY s.shipment_id, pc.id, pc.name
ORDER BY pc.name;
```

For a shipment owned by two of your offices (e.g. HCM export + HAN import), this returns two rows — one per profit center. Summing them gives total shipment profit.

### Per direction P&L (across all branches)

```sql
SELECT
  pc.direction,
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END) AS revenue,
  SUM(CASE WHEN cl.type = 'BUY'  THEN cl.base_amount ELSE 0 END) AS cost,
  SUM(CASE WHEN cl.type = 'SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type = 'BUY' THEN cl.base_amount ELSE 0 END) AS profit
FROM charge_line cl
JOIN profit_center pc ON cl.profit_center_id = pc.id
JOIN shipment s ON cl.job_id = s.id
WHERE s.created_at BETWEEN :date_from AND :date_to
GROUP BY pc.direction
ORDER BY profit DESC;
```

This answers "how much did all export jobs make vs. all import jobs vs. all cross-trade jobs across the entire company."

---

## 9. Invoice Generation Rules

The `payable_at` field drives which office raises which invoice, and which party receives it:

| Direction | payable_at | Invoice raised by | Billed to |
|---|---|---|---|
| EXP | ORIGIN | Origin branch | Shipper |
| IMP (prepaid freight) | DESTINATION | Destination branch | Consignee |
| IMP (collect freight) | DESTINATION | Destination branch | Consignee (incl. freight) |
| IMP (DDP) | ORIGIN | Origin branch | Shipper |
| XTD | BOTH | Your coordinating office | Shipper or buyer per Incoterm |
| DOM | BOTH | Operating branch | Contract party |
| TSH | BOTH | Hub branch | Carrier or origin agent |

When a job has charge lines with different `payable_at` values, the system generates separate invoices — one per `payable_at` group — rather than combining everything into one invoice. This prevents a single invoice from containing charges that belong to different billing parties.

---

## 10. Quote Visibility Rules

The same `visible_to` and `profit_center_id` fields that govern job charge lines also apply to quote rate lines. This means:

- An export operator building a quote only sees and can edit origin-side rate lines
- An import operator reviewing the same quote (if shared) only sees destination-side rate lines
- The quote total shown to each department is their portion only — not the full quote value
- When the quote converts to a job, each charge line inherits the same `visible_to` and `profit_center_id` from the quote rate line

This ensures the quote P&L estimate matches the job P&L attribution — no re-categorisation needed on conversion.

---

## 11. Charge Line Full Schema (Consolidated)

Combining all fields discussed across this document and the currency system document:

```sql
CREATE TABLE charge_line (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  charge_code       VARCHAR(16)   NOT NULL REFERENCES charge_master(code),
  description       VARCHAR(255),
  category          VARCHAR(16)   NOT NULL,   -- FREIGHT / LOCAL / CUSTOMS / SERVICE
  calc_basis        VARCHAR(32)   NOT NULL,   -- PER_CONTAINER / PER_WM / PER_KG / FLAT / PCT_VALUE

  -- Visibility and attribution
  payable_at        VARCHAR(16)   NOT NULL,   -- ORIGIN / DESTINATION / BOTH
  visible_to        VARCHAR(16)   NOT NULL,   -- ORIGIN / DESTINATION / ALL
  profit_center_id  UUID          NOT NULL REFERENCES profit_center(id),

  -- Buy or sell
  type              VARCHAR(8)    NOT NULL,   -- BUY / SELL
  quantity          NUMERIC(12,4) NOT NULL DEFAULT 1,
  unit_rate         NUMERIC(20,6),

  -- Original currency (what the customer sees or vendor charges)
  orig_currency     CHAR(3)       NOT NULL REFERENCES currency(code),
  orig_amount       NUMERIC(20,6) NOT NULL,

  -- Base currency snapshot (frozen at creation — never recalculated)
  base_currency     CHAR(3)       NOT NULL,
  fx_rate_snapshot  NUMERIC(20,6) NOT NULL,
  base_amount       NUMERIC(20,6) NOT NULL,   -- orig_amount * fx_rate_snapshot
  fx_rate_source    VARCHAR(32)   NOT NULL,   -- SPOT / FIXED / MANUAL
  fx_locked_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),

  -- Freight terms (drives AR invoice routing for ocean/air freight lines)
  freight_terms     VARCHAR(16),              -- PREPAID / COLLECT — only set on freight lines

  -- Flags
  is_estimate       BOOLEAN       NOT NULL DEFAULT false,
  is_locked         BOOLEAN       NOT NULL DEFAULT false,  -- true after invoice is raised

  -- Audit
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  created_by        UUID          REFERENCES app_user(id),
  updated_at        TIMESTAMPTZ,
  updated_by        UUID          REFERENCES app_user(id)
);

-- Indexes for common query patterns
CREATE INDEX idx_cl_job        ON charge_line (job_id);
CREATE INDEX idx_cl_pc         ON charge_line (profit_center_id);
CREATE INDEX idx_cl_visible    ON charge_line (visible_to);
CREATE INDEX idx_cl_payable    ON charge_line (payable_at);
CREATE INDEX idx_cl_type       ON charge_line (type);
```

---

## 12. Summary: The Four Rules

1. **`payable_at` routes invoices. `profit_center_id` routes P&L.** They usually match — but keep them separate because DDP, cross-trade, and inter-office shipments require them to differ.

2. **Visibility is enforced at the database layer via RLS**, not in application code. Every session sets `app.user_dept` before querying — the database filters automatically.

3. **Every direction produces at least one profit center record.** Export = origin PC. Import = destination PC. Cross-trade = coordinating office PC. Domestic = branch PC. Transshipment = hub PC. A shipment between two of your own offices produces two PC records and two separate P&L rows.

4. **Quote rate lines carry the same attribution fields as job charge lines.** The P&L estimate on the quote must match the actual P&L on the job. No re-categorisation happens at conversion time.
