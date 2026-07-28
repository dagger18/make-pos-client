# Feature 8: Consolidation Layer

## Overview

For LCL ocean and air groupage shipments, multiple individual jobs are grouped under a single `Consolidation` record. The consolidation owns the Master BL / MAWB, the vessel or flight, and the container or ULD. Each child shipment owns its House BL / HAWB and its own charge lines. Port-level charges (THC, ORC) sit on the consolidation and are apportioned to child jobs by weight or volume ratio.

Also adds `parent_job_id` (self-referential FK) on `Shipment` for multimodal sub-legs (e.g. a sea leg + road leg under one MMD job).

Reference: CargoWise consolidation, Magaya co-load, Descartes LCL master.

---

## Data Model

### Consolidation entity

```sql
CREATE TABLE consolidation (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(64)   UNIQUE NOT NULL,   -- HCM-CONSOL-OCN-202604-001
  transport_mode  VARCHAR(8)    NOT NULL,           -- OCN | AIR
  service_type    VARCHAR(16)   NOT NULL,           -- LCL | CONSOL (air groupage)
  status          VARCHAR(32)   NOT NULL DEFAULT 'OPEN',  -- OPEN | CLOSED | CANCELLED
  branch_id       INT           NOT NULL REFERENCES branch(id),
  carrier_id      INT           DEFAULT NULL REFERENCES client(id) ON DELETE SET NULL,  -- airline or shipping line

  -- Ocean
  vessel          VARCHAR(64)   DEFAULT NULL,
  voyage          VARCHAR(32)   DEFAULT NULL,
  mbl_number      VARCHAR(32)   DEFAULT NULL,

  -- Air
  flight_number   VARCHAR(16)   DEFAULT NULL,
  mawb_number     VARCHAR(32)   DEFAULT NULL,

  -- Routing
  pol_id          INT           DEFAULT NULL REFERENCES port(id) ON DELETE SET NULL,
  pod_id          INT           DEFAULT NULL REFERENCES port(id) ON DELETE SET NULL,

  -- Dates
  etd             DATE          DEFAULT NULL,
  eta             DATE          DEFAULT NULL,

  -- Container / ULD (single container for LCL; ULD for air)
  container_number VARCHAR(16)  DEFAULT NULL,
  uld_number      VARCHAR(32)   DEFAULT NULL,

  -- Apportionment method for shared charges
  apportionment_basis VARCHAR(16) NOT NULL DEFAULT 'WEIGHT',  -- WEIGHT | VOLUME | CBM | REVENUE

  -- Financial summary (sum of child job financials)
  total_buy       DECIMAL(20,6) DEFAULT NULL,
  total_sell      DECIMAL(20,6) DEFAULT NULL,
  margin          DECIMAL(20,6) DEFAULT NULL,
  base_currency   CHAR(3)       DEFAULT NULL,

  -- Audit
  created_by      INT           DEFAULT NULL REFERENCES user(id) ON DELETE SET NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_consol_branch (branch_id),
  INDEX idx_consol_status (status)
);
```

### Shipment additions

```sql
ALTER TABLE shipment
  ADD COLUMN consol_id      INT DEFAULT NULL REFERENCES consolidation(id) ON DELETE SET NULL,
  ADD COLUMN parent_job_id  INT DEFAULT NULL REFERENCES shipment(id) ON DELETE SET NULL;
```

### ConsolidationStatus enum

| Value | Description |
|---|---|
| `OPEN` | Accepting new child jobs |
| `CLOSED` | Manifest finalised, accepting no more jobs |
| `DEPARTED` | Vessel / flight has departed |
| `ARRIVED` | Vessel / flight has arrived |
| `CANCELLED` | Cancelled |

---

## Business Rules

### Consolidation

1. A child shipment can belong to only one consolidation (`consol_id` is a single FK, not a join table).
2. `service_type` on the consolidation determines what child `service_type` values are valid: OCN LCL consol accepts only shipments with `serviceType = LCL`. AIR consol accepts `CONSOL`.
3. When a child job is added to a consolidation, the consolidation's `total_buy/sell/margin` are recomputed.
4. Port-level charge apportionment: when a charge is added to the consolidation, it is split across child jobs. The split formula:
   - `WEIGHT`: child_charge = (child_gross_weight / total_gross_weight) × consol_charge
   - `VOLUME`: child_charge = (child_cbm / total_cbm) × consol_charge
   - Apportioned charge lines appear on child jobs with `origin = 'CONSOL'` for traceability.
5. The consolidation's `mbl_number` / `mawb_number` is written to each child shipment's `masterBill` when the manifest is closed.
6. A consolidation cannot be closed while any child shipment has status `DRAFT` or `CANCELLED`.

### Parent job (multimodal)

7. A multimodal shipment (transport_mode = MMD) can have child sub-legs. Each sub-leg is a normal `Shipment` row with `parent_job_id` pointing to the MMD parent.
8. Sub-legs are created with the same `code` prefix + a suffix: `HCM-MMD-202604-00001-SEA`, `HCM-MMD-202604-00001-ROD`.
9. The parent job's financial summary rolls up from all sub-legs.

---

## API

### Consolidation CRUD

```
GET    /consolidation                     — list (filter by status, branch, mode)
POST   /consolidation                     — create
GET    /consolidation/{id}                — detail + child shipment list
PUT    /consolidation/{id}                — update header fields
PATCH  /consolidation/{id}/close          — close manifest
DELETE /consolidation/{id}                — cancel (only if no active child jobs)
```

### Child job management

```
POST   /consolidation/{id}/shipments          — add shipment to consol
DELETE /consolidation/{id}/shipments/{shipId} — remove shipment from consol
POST   /consolidation/{id}/apportion          — recalculate and write apportioned charges to all child jobs
```

### Shipment endpoint extensions

```
GET  /shipment/{id}  — now includes consolId, parentJobId, childJobs[] in detail response
```

### POST body (create consolidation)

```json
{
  "transportMode": "OCN",
  "serviceType": "LCL",
  "branchId": 1,
  "carrierId": 12,
  "vessel": "MV Pacific Dream",
  "voyage": "126N",
  "polId": 45,
  "podId": 88,
  "etd": "2026-07-20",
  "eta": "2026-08-15",
  "apportionmentBasis": "WEIGHT"
}
```

---

## BO UI

### Consolidation list page

New page at `/consolidation` (or `/co-load`).

Columns: Code | Mode | Service Type | Vessel / Flight | POL → POD | ETD | ETA | Child Jobs | Status

**Create Consolidation** button → dialog form.

### Consolidation detail page

Tabs:
1. **Header** — vessel/flight info, routing, dates, MBL/MAWB
2. **Manifest** — table of child shipments with: Code | Client | Cargo | Gross Weight | CBM | HBL | Status
   - Add Shipment button → search/autocomplete for Active shipments with matching mode + service_type
   - Remove Shipment button per row
3. **Charges** — consolidation-level charge lines (THC, ORC, etc.)
   - Apportion Charges button → recalculates and writes child job charges
4. **Documents** — MBL / MAWB upload

### Shipment detail — consol indicator

When `consol_id` is set, show a "Part of Consol: HCM-CONSOL-OCN-202604-001" banner in the shipment header (clickable link to consolidation detail).

When `parent_job_id` is set, show "Sub-leg of: HCM-MMD-202604-00001" banner.

---

## Migration

```sql
-- MySQL
CREATE TABLE consolidation (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  code            VARCHAR(64) UNIQUE NOT NULL,
  transport_mode  VARCHAR(8) NOT NULL,
  service_type    VARCHAR(16) NOT NULL,
  status          VARCHAR(32) NOT NULL DEFAULT 'OPEN',
  branch_id       INT NOT NULL,
  carrier_id      INT DEFAULT NULL,
  vessel          VARCHAR(64) DEFAULT NULL,
  voyage          VARCHAR(32) DEFAULT NULL,
  mbl_number      VARCHAR(32) DEFAULT NULL,
  flight_number   VARCHAR(16) DEFAULT NULL,
  mawb_number     VARCHAR(32) DEFAULT NULL,
  pol_id          INT DEFAULT NULL,
  pod_id          INT DEFAULT NULL,
  etd             DATE DEFAULT NULL,
  eta             DATE DEFAULT NULL,
  container_number VARCHAR(16) DEFAULT NULL,
  uld_number      VARCHAR(32) DEFAULT NULL,
  apportionment_basis VARCHAR(16) NOT NULL DEFAULT 'WEIGHT',
  total_buy       DECIMAL(20,6) DEFAULT NULL,
  total_sell      DECIMAL(20,6) DEFAULT NULL,
  margin          DECIMAL(20,6) DEFAULT NULL,
  base_currency   CHAR(3) DEFAULT NULL,
  created_by      INT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT FK_consol_branch  FOREIGN KEY (branch_id)  REFERENCES branch(id),
  CONSTRAINT FK_consol_carrier FOREIGN KEY (carrier_id) REFERENCES client(id) ON DELETE SET NULL,
  CONSTRAINT FK_consol_pol     FOREIGN KEY (pol_id)     REFERENCES port(id)   ON DELETE SET NULL,
  CONSTRAINT FK_consol_pod     FOREIGN KEY (pod_id)     REFERENCES port(id)   ON DELETE SET NULL,
  CONSTRAINT FK_consol_user    FOREIGN KEY (created_by) REFERENCES user(id)   ON DELETE SET NULL
);

ALTER TABLE shipment
  ADD COLUMN consol_id     INT DEFAULT NULL,
  ADD COLUMN parent_job_id INT DEFAULT NULL,
  ADD CONSTRAINT FK_shipment_consol  FOREIGN KEY (consol_id)     REFERENCES consolidation(id) ON DELETE SET NULL,
  ADD CONSTRAINT FK_shipment_parent  FOREIGN KEY (parent_job_id) REFERENCES shipment(id)      ON DELETE SET NULL;

-- SQLite
CREATE TABLE consolidation (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  code             TEXT UNIQUE NOT NULL,
  transport_mode   TEXT NOT NULL,
  service_type     TEXT NOT NULL,
  status           TEXT NOT NULL DEFAULT 'OPEN',
  branch_id        INTEGER NOT NULL,
  carrier_id       INTEGER DEFAULT NULL,
  vessel           TEXT DEFAULT NULL,
  voyage           TEXT DEFAULT NULL,
  mbl_number       TEXT DEFAULT NULL,
  flight_number    TEXT DEFAULT NULL,
  mawb_number      TEXT DEFAULT NULL,
  pol_id           INTEGER DEFAULT NULL,
  pod_id           INTEGER DEFAULT NULL,
  etd              TEXT DEFAULT NULL,
  eta              TEXT DEFAULT NULL,
  container_number TEXT DEFAULT NULL,
  uld_number       TEXT DEFAULT NULL,
  apportionment_basis TEXT NOT NULL DEFAULT 'WEIGHT',
  total_buy        REAL DEFAULT NULL,
  total_sell       REAL DEFAULT NULL,
  margin           REAL DEFAULT NULL,
  base_currency    TEXT DEFAULT NULL,
  created_by       INTEGER DEFAULT NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT NULL
);

ALTER TABLE shipment ADD COLUMN consol_id     INTEGER DEFAULT NULL;
ALTER TABLE shipment ADD COLUMN parent_job_id INTEGER DEFAULT NULL;
```

---

## Consolidation Code Format

Generated the same way as shipment codes using the config-driven format:
`$Branch-CONSOL-$TransType-$YearMonth-$MonthlyCounter`
Example: `HCM-CONSOL-OCN-202607-001`

---

## Reference: Industry Patterns

- **CargoWise One** has a dedicated Co-load module. The co-load master holds the MBL and container. Each house job links to it. THC and other port charges are apportioned via a single click using weight/CBM ratio.
- **Magaya** calls this a "Consolidation" — you create a master shipment then add individual customer shipments as children. The master owns the carrier booking; the children own the client charges.
- **Descartes** uses "Master Jobs" and "House Jobs" terminology. The master job consolidates financials and generates the MBL from house job data.
- **Flexport** internally uses consol grouping for their LCL product where multiple small shippers are combined into one container booking with individual HBLs and separate invoicing.
