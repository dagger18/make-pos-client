# Feature 5: Party Role System (ShipmentParty)

## Overview

Replaces the flat text fields on `Instruction` (shipperName, shipperAddress, consigneeName, etc.) with a role-based `ShipmentParty` join table. Each row represents one organisation playing one role on the shipment. The organisation's address is snapshotted at the time of assignment so that changes to the org master record never alter issued documents. This is Option A: full replacement.

Reference: CargoWise job party model, Magaya Contacts panel, Descartes party role table.

---

## Data Model

```sql
CREATE TABLE shipment_party (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id      INT           NOT NULL REFERENCES shipment(id) ON DELETE CASCADE,
  role             VARCHAR(32)   NOT NULL,   -- see PartyRole enum
  client_id        INT           DEFAULT NULL REFERENCES client(id) ON DELETE SET NULL,
  contact_id       INT           DEFAULT NULL REFERENCES contact(id) ON DELETE SET NULL,
  reference        VARCHAR(64)   DEFAULT NULL,   -- the party's own job ref number
  is_also_notify   TINYINT(1)    NOT NULL DEFAULT 0,
  address_snapshot JSON          NOT NULL,   -- frozen at assignment time
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_party_role (shipment_id, role),
  INDEX idx_party_shipment (shipment_id)
);
```

### address_snapshot structure

```json
{
  "name": "Saigon Trading Co. Ltd.",
  "address": "123 Le Loi Street, District 1",
  "city": "Ho Chi Minh City",
  "country": "VN",
  "taxId": "0123456789",
  "phone": "+84 28 1234 5678",
  "email": "ops@saigontrading.vn"
}
```

### PartyRole enum

| Code | Description | Typical direction |
|---|---|---|
| `SHIPPER` | Exporter / seller | EXP |
| `CONSIGNEE` | Importer / buyer | IMP |
| `NOTIFY_1` | First notify party | IMP |
| `NOTIFY_2` | Second notify party (e.g. issuing bank) | IMP |
| `OVERSEAS_AGENT` | Partner forwarder at origin or destination | Both |
| `CARRIER` | Vessel operator or airline | Both |
| `CO_LOADER` | Consolidator for LCL / air consol | Both |
| `CUSTOMS_BROKER` | Import or export customs broker | Both |
| `HAULIER_O` | Origin inland trucker | EXP |
| `HAULIER_D` | Destination inland trucker | IMP |
| `WAREHOUSE_O` | Origin CFS or warehouse | EXP |
| `WAREHOUSE_D` | Destination CFS or warehouse | IMP |
| `INSURANCE` | Cargo insurer | Both |
| `SURVEYOR` | Pre-shipment inspection body | EXP |
| `BANK` | Issuing or negotiating bank (LC transactions) | Both |
| `FUMIGATION` | Fumigation service provider | EXP |

---

## Business Rules

1. One organisation per role per shipment (`UNIQUE KEY uq_party_role`). To have two notify parties, use `NOTIFY_1` and `NOTIFY_2`.
2. `address_snapshot` is set at assignment time and never updated. If the operator needs to correct it, they must remove and re-add the party row.
3. `client_id` references the `client` table (which currently serves as the general organisation/company record). Future enhancement: split to separate org table.
4. The SHIPPER and CONSIGNEE roles are pre-populated from the quote when a shipment is created from a quote (`quote.client` → SHIPPER or CONSIGNEE depending on direction).
5. OVERSEAS_AGENT, when set, links to the existing `agentName` / `agentIataCode` fields on `Instruction` for document printing. After migration, the Instruction fields are populated from the snapshot when SI is generated.
6. Any role can optionally flag `is_also_notify = true` — this copies the party to the NOTIFY position on the BL without creating a separate party row.

---

## Migration Strategy (Option A: Full Replace)

### Phase 1 — Add new table, keep old fields

Create `shipment_party` table. Both systems run in parallel. New shipments use party roles; existing shipments retain flat fields.

### Phase 2 — Migrate existing data

For each shipment with an Instruction, create party rows from the flat fields:

```sql
-- Example: migrate shipper
INSERT INTO shipment_party (shipment_id, role, address_snapshot, created_at)
SELECT
  s.id,
  'SHIPPER',
  JSON_OBJECT(
    'name',    i.shipper_name,
    'address', i.shipper_address,
    'city',    '',
    'country', '',
    'taxId',   '',
    'phone',   '',
    'email',   ''
  ),
  s.created_at
FROM shipment s
JOIN instruction i ON i.shipment_id = s.id
WHERE i.shipper_name IS NOT NULL AND i.shipper_name != '';
```

### Phase 3 — Remove old fields from Instruction

After migration is verified, drop the flat party columns from `instruction`:
`shipper_name`, `shipper_address`, `shipper_account_number`, `consignee_name`, `consignee_address`, `consignee_account_number`, `notify_name`, `notify_address`, `notify_consignee`, `agent_name`, `agent_address`, `agent_city`, `agent_iata_code`, `account_number`.

---

## API

```
GET    /shipment/{id}/parties                  — list all party roles
POST   /shipment/{id}/parties                  — assign party to role
PUT    /shipment/{id}/parties/{role}           — update (re-assign org, refresh snapshot)
DELETE /shipment/{id}/parties/{role}           — remove party from role
```

### POST body

```json
{
  "role": "SHIPPER",
  "clientId": 42,
  "contactId": 7,
  "reference": "EXP-2026-0055"
}
```

The API reads the client's current address from the `client` table and writes it into `address_snapshot` automatically.

### GET response (per party)

```json
{
  "role": "SHIPPER",
  "client": { "id": 42, "name": "Saigon Trading Co. Ltd." },
  "contact": { "id": 7, "name": "Nguyen Van A", "email": "a@saigontrading.vn" },
  "reference": "EXP-2026-0055",
  "isAlsoNotify": false,
  "addressSnapshot": {
    "name": "Saigon Trading Co. Ltd.",
    "address": "123 Le Loi Street, District 1",
    "city": "Ho Chi Minh City",
    "country": "VN",
    "taxId": "0123456789"
  }
}
```

---

## BO UI

### Parties tab in ShipmentDetail

New dedicated tab replacing the party fields inside the Instruction form.

**Layout:** Two-column grid of party cards.

Each party card shows:
- Role badge (e.g. "SHIPPER")
- Organisation name (clickable → org detail)
- Address snapshot lines
- Contact name + email
- Reference number
- Edit / Remove buttons

**Add Party button** → dialog:
- Role select (filtered to roles not yet assigned)
- Organisation search/autocomplete (existing client search)
- Contact select (filtered by organisation)
- Reference field
- Preview of address snapshot below org selection

**Suggested parties sidebar** — pre-fills SHIPPER/CONSIGNEE from the linked quote's client when opening the tab for the first time.

---

## Reference: Industry Patterns

- **CargoWise One** has a `Parties` section on every job with predefined role slots (Shipper, Consignee, Notify, Agent, Carrier, etc.). The address is snapshotted at job creation. The `Overseas Agent` role also stores the agent's own job reference number for cross-system tracking.
- **Magaya** uses a `Contacts` panel where each contact row has a `Role` dropdown. On document generation, the address is read from the snapshot, not the live record.
- **Descartes** enforces that SHIPPER and CONSIGNEE are mandatory before a BL can be issued. Missing required parties block document generation.
- **Flexport** stores party data with a full audit trail — every time a party's role or details change, the previous state is preserved for compliance.
