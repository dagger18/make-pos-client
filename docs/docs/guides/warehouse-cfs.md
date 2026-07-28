# Warehouse / CFS Setup Guide

## Overview

The Warehouse module manages physical cargo operations at Container Freight Stations (CFS). It covers:

- **Facility management** — define your CFS locations with capacities and approvals
- **Warehouse receipts** — record inbound cargo against shipments
- **Stuffing instructions** — direct export stuffing of LCL cargo into containers
- **Stripping instructions** — manage import de-consolidation (container to CFS)
- **Inventory view** — real-time view of unreleased cargo per facility

---

## Database Migration

Run the warehouse migration to create all required tables:

```bash
# MySQL
php bin/console doctrine:migrations:execute --up DoctrineMigrations\\Version20260625200000

# SQLite (test environment)
php bin/console doctrine:migrations:execute --up SqlEngineMigrations\\Version20260625200000 --em=sqlite
```

This creates six tables: `warehouse_facility`, `warehouse_receipt`, `stuffing_instruction`, `stuffing_instruction_line`, `stripping_instruction`, `stripping_result`.

---

## API Endpoints

### Facility Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/warehouse-facility` | List all facilities |
| POST | `/warehouse-facility` | Create facility |
| GET | `/warehouse-facility/{id}` | Get facility |
| PUT | `/warehouse-facility/{id}` | Update facility |
| DELETE | `/warehouse-facility/{id}` | Delete facility |
| GET | `/warehouse-facility/{id}/inventory` | Current inventory (unreleased cargo) |

### Warehouse Receipts (per Shipment)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/shipment/{id}/warehouse-receipt` | List receipts for shipment |
| POST | `/shipment/{id}/warehouse-receipt` | Create receipt |
| PUT | `/shipment/{id}/warehouse-receipt/{receiptId}` | Update receipt |
| DELETE | `/shipment/{id}/warehouse-receipt/{receiptId}` | Delete receipt |

### Stuffing Instructions (per Consolidation)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/consolidation/{id}/stuffing` | List stuffing instructions |
| POST | `/consolidation/{id}/stuffing` | Create instruction (with lines array) |
| GET | `/consolidation/{id}/stuffing/{sid}` | Get instruction with lines |
| PUT | `/consolidation/{id}/stuffing/{sid}` | Update instruction header |
| DELETE | `/consolidation/{id}/stuffing/{sid}` | Delete instruction |

### Stripping Instructions (per Consolidation)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/consolidation/{id}/stripping` | List stripping instructions |
| POST | `/consolidation/{id}/stripping` | Create instruction (with results array) |
| GET | `/consolidation/{id}/stripping/{sid}` | Get instruction with results |
| PUT | `/consolidation/{id}/stripping/{sid}` | Update instruction |
| DELETE | `/consolidation/{id}/stripping/{sid}` | Delete instruction |

---

## Creating a Warehouse Facility

```json
POST /warehouse-facility
{
  "name": "HCM CFS Terminal 1",
  "locationCode": "VNSGN",
  "address": "123 Port Road, District 4, Ho Chi Minh City",
  "totalAreaSqm": 5000,
  "reeferCapacity": 20,
  "bonded": true,
  "dangerousGoodsApproved": false,
  "contactPhone": "+84-28-1234-5678",
  "contactEmail": "cfs@terminal1.vn",
  "isActive": true
}
```

---

## Recording a Cargo Receipt

When cargo arrives at the CFS from a shipper/trucker:

```json
POST /shipment/{shipmentId}/warehouse-receipt
{
  "facilityId": 1,
  "receiptNumber": "WR-HCM-2026-00045",
  "receiptType": "INBOUND",
  "vehiclePlate": "51A-12345",
  "driverName": "Nguyen Van A",
  "piecesReceived": 10,
  "piecesExpected": 10,
  "grossWeightKg": "1250.500",
  "volumeCbm": "8.2500",
  "conditionCode": "GOOD",
  "storageZone": "A",
  "storageLocation": "A-03-02",
  "receivedAt": "2026-06-25T09:30:00Z"
}
```

**Condition codes:**
- `GOOD` — cargo in expected condition
- `DAMAGED` — visible damage noted (add `damageNotes`)
- `SHORT` — fewer pieces than expected
- `EXCESS` — more pieces than expected
- `WET` — moisture damage
- `CONTAMINATED` — cargo contaminated (quarantine)

---

## Stuffing Instructions (Export LCL)

When consolidating LCL cargo into a container for export:

```json
POST /consolidation/{consolId}/stuffing
{
  "facilityId": 1,
  "instructionNumber": "STF-2026-001",
  "containerNumber": "TCKU3456789",
  "status": "PENDING",
  "scheduledAt": "2026-06-26T08:00:00Z",
  "forkliftOperator": "Tran Van B",
  "notes": "Heavy items first, max stack height 2m",
  "lines": [
    {
      "shipmentId": 42,
      "receiptId": 12,
      "piecesToStuff": 10,
      "weightKg": "1250.500",
      "volumeCbm": "8.2500",
      "loadSequence": 1
    }
  ]
}
```

---

## Stripping Instructions (Import LCL)

When a container arrives at the CFS for import de-consolidation:

```json
POST /consolidation/{consolId}/stripping
{
  "facilityId": 1,
  "instructionNumber": "STR-2026-001",
  "containerNumber": "MSCU1234567",
  "containerArrival": "2026-06-25T14:00:00Z",
  "status": "PENDING",
  "notes": "Priority: SGN-001 for urgent customs clearance",
  "results": [
    {
      "shipmentId": 55,
      "hblNumber": "HBL-2026-0055",
      "piecesStripped": 8,
      "weightKg": "950.000",
      "conditionCode": "GOOD",
      "storageLocation": "B-01-01"
    }
  ]
}
```

---

## Inventory Query

The inventory endpoint returns all unreleased cargo at a facility:

```bash
GET /warehouse-facility/{id}/inventory
```

Returns rows with zone, location, receipt number, shipment ID, pieces, weight, volume, condition, and received date. Sort order is `storage_zone → storage_location`.

---

## BO Navigation

After running migrations, the Warehouse section appears in the BO sidebar between Consolidations and Clients:

- **Warehouse → Facilities** — manage CFS locations (`/warehouse/facility`)
- **Warehouse → CFS Inventory** — real-time inventory per facility (`/warehouse/inventory`)

Within a **Shipment detail**, the **Warehouse** tab shows all receipts for that shipment.

Within a **Consolidation detail**, the **Stuffing** and **Stripping** tabs show the respective instructions.

---

## Operational Flow

### Export (Stuffing)

1. Client ships cargo to CFS → record `WarehouseReceipt` against the shipment
2. When enough cargo is accumulated for a container → create `StuffingInstruction` on the consolidation
3. Warehouse staff loads cargo, marking each line `isStuffed = true`
4. Instruction status → `IN_PROGRESS` → `COMPLETED`

### Import (Stripping)

1. Container arrives at CFS → create `StrippingInstruction` on the consolidation
2. Warehouse staff strips container, creating `StrippingResult` per HBL
3. Each result records pieces stripped, condition, and storage location assigned
4. Instruction status → `COMPLETED`
5. Cargo released to consignee trucker — update `WarehouseReceipt.releasedAt`
