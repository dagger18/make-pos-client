# Feature 1: Shipment Header Fields Extension

## Overview

Extends the `Shipment` entity with four operational fields that all major freight forwarder platforms (CargoWise One, Magaya, WiseTech Cargospot) expose at the job header level: a granular `sub_status`, an `is_on_hold` flag with reason, a dedicated `sales_rep` user, and the `overseas_agent` organisation link.

---

## Data Model

### Shipment entity additions

```
sub_status       VARCHAR(32)   nullable   — granular state within the main status
is_on_hold       BOOLEAN       default false
hold_reason      TEXT          nullable   — required when is_on_hold = true
salesRep         ManyToOne → User   nullable
```

### SubStatus enum

Sub-statuses are grouped by their parent `status`. The application enforces that the `sub_status` value belongs to the correct group when the main `status` changes.

```
DRAFT
  PENDING_BOOKING

PENDING                         (maps to: in progress / open)
  AWAITING_SI
  SI_SUBMITTED
  AWAITING_VGM
  VGM_SUBMITTED
  AWAITING_CARGO_RECEIPT
  CARGO_RECEIVED

ACTIVE                          (in transit)
  LOADED_ON_BOARD
  VESSEL_DEPARTED
  AT_TRANSSHIPMENT_PORT
  VESSEL_ARRIVED
  CUSTOMS_CLEARANCE
  CUSTOMS_RELEASED
  OUT_FOR_DELIVERY
  DELIVERED
  POD_RECEIVED

COMPLETED
  INVOICED
  FULLY_PAID
  CLOSED_WITH_VARIANCE

CANCELLED
  CANCELLED_BY_CUSTOMER
  CANCELLED_NO_SPACE
  ROLLED_OVER
```

---

## Business Rules

1. When `status` changes, clear `sub_status` unless the caller explicitly provides one.
2. `hold_reason` must be non-empty when `is_on_hold` is set to `true`. The API returns a 422 if not provided.
3. `is_on_hold` does not block status transitions — it is informational + triggers a UI badge.
4. `sales_rep` is independent of `account_manager`. In large agencies, the person who sold the deal differs from the operator managing the file.
5. On-hold history is written to `ShipmentActivity` with `type = 'HOLD'` or `'UNHOLD'`.

---

## API

### Serializer groups

- `shipment:list` → add `subStatus`, `isOnHold`, `salesRep` (id + name only)
- `shipment:detail` → add all four fields + `holdReason`
- `shipment:write` → all four fields writable

### Endpoints (existing, extended)

```
GET  /shipment/{id}       — returns subStatus, isOnHold, holdReason, salesRep
PUT  /shipment/{id}       — accepts subStatus, isOnHold, holdReason, salesRepId
```

### New dedicated hold endpoint

```
POST /shipment/{id}/hold
Body: { reason: string }
Response: updated shipment

POST /shipment/{id}/unhold
Body: {}
Response: updated shipment
```

Separating hold/unhold into their own endpoints keeps the semantics clean and makes permission granularity possible (e.g. only managers can hold).

---

## BO UI

### Shipment detail header

- **Sub-status chip** displayed next to the main status badge. Editable via dropdown showing only valid sub-statuses for the current main status.
- **On Hold badge** (red) shown in the header when `is_on_hold = true`. Clicking opens a small dialog to enter/view the hold reason.
- **Hold / Unhold button** in the action toolbar.
- **Sales Rep field** added to the shipment info tab alongside Account Manager.

### Sub-status allowed values per status (BO dropdown filter)

The BO `ShipmentStatus.js` enum config is extended with a `subStatuses` array per status so the dropdown is always contextually filtered.

---

## Migration

```sql
-- MySQL
ALTER TABLE shipment
  ADD COLUMN sub_status   VARCHAR(32) DEFAULT NULL,
  ADD COLUMN is_on_hold   TINYINT(1)  NOT NULL DEFAULT 0,
  ADD COLUMN hold_reason  LONGTEXT    DEFAULT NULL,
  ADD COLUMN sales_rep_id INT         DEFAULT NULL,
  ADD CONSTRAINT FK_shipment_sales_rep FOREIGN KEY (sales_rep_id) REFERENCES user(id) ON DELETE SET NULL;

-- SQLite
ALTER TABLE shipment ADD COLUMN sub_status   TEXT DEFAULT NULL;
ALTER TABLE shipment ADD COLUMN is_on_hold   INTEGER NOT NULL DEFAULT 0;
ALTER TABLE shipment ADD COLUMN hold_reason  TEXT DEFAULT NULL;
ALTER TABLE shipment ADD COLUMN sales_rep_id INTEGER DEFAULT NULL;
```

---

## Reference: CargoWise / Magaya Patterns

- **CargoWise One** uses a two-level status: `eJobStatus` (primary) + `eJobSubStatus` (secondary). Sub-status values are configurable per branch in the system setup.
- **Magaya** exposes a `Hold` checkbox with a free-text hold reason on every shipment. The hold flag appears on shipment list views as a lock icon.
- **Descartes** separates `Sales Rep` (CRM user who owns the account) from `File Owner` (ops user managing the dossier). Both appear on the job header.
