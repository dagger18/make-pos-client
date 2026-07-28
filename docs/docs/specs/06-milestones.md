# Feature 6: Milestone System (ShipmentMilestone)

## Overview

Milestones are the operational event timeline of a shipment. Every significant logistics event is recorded as a row with a `planned_date` and an `actual_date`. The gap between them drives exception management and SLA dashboards. Milestones are also the gate that Tasks (Feature 7) check before they allow a status to advance.

Reference: CargoWise milestone events, Magaya milestone tracking, Descartes event management, Shipsy milestone engine.

---

## Data Model

```sql
CREATE TABLE shipment_milestone (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id     INT           NOT NULL REFERENCES shipment(id) ON DELETE CASCADE,
  milestone_code  VARCHAR(32)   NOT NULL,   -- see MilestoneCode enum
  planned_date    DATETIME      DEFAULT NULL,
  actual_date     DATETIME      DEFAULT NULL,
  is_exception    TINYINT(1)    NOT NULL DEFAULT 0,
  exception_hours DECIMAL(8,2)  DEFAULT NULL,  -- positive = late, negative = early
  source          VARCHAR(16)   NOT NULL DEFAULT 'MANUAL',  -- MANUAL | SYSTEM | EDI | API | CARRIER_TRACKING
  remarks         TEXT          DEFAULT NULL,
  updated_by      INT           DEFAULT NULL REFERENCES user(id) ON DELETE SET NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_ms_shipment (shipment_id, actual_date DESC),
  INDEX idx_ms_code     (milestone_code)
);
```

---

## MilestoneCode enum

### Ocean FCL / LCL

| Code | Description | Automated trigger |
|---|---|---|
| `JOB_CREATED` | Shipment created in system | Auto on shipment creation |
| `CARGO_BOOKED` | Booking confirmed with carrier | Manual / EDI booking confirmation |
| `CARGO_READY` | Cargo ready at origin warehouse | Manual |
| `EMPTY_RELEASED` | Empty container released from depot | Manual / EDI |
| `GATE_IN` | Container gated in at origin terminal | Manual / EDI |
| `VGM_SUBMITTED` | VGM declared to carrier | Manual |
| `SI_SUBMITTED` | Shipping instruction sent to carrier | Manual / system on SI generation |
| `ON_BOARD` | Cargo loaded on vessel — this is the BL date | Manual / EDI |
| `VESSEL_DEPARTED` | Vessel departed POL | Manual / carrier tracking |
| `AT_TRANSSHIPMENT` | At transshipment port | Carrier tracking |
| `VESSEL_ARRIVED` | Vessel arrived at POD | Manual / carrier tracking |
| `DISCHARGED` | Container discharged from vessel | Manual / EDI |
| `AVAILABLE` | Container available for pickup | Manual |
| `ENTRY_FILED` | Customs entry submitted | Manual |
| `CUSTOMS_RELEASED` | Customs cleared | Manual |
| `GATE_OUT` | Container gated out from terminal | Manual / EDI |
| `DELIVERED` | Cargo delivered to consignee | Manual |
| `POD_RECEIVED` | Signed proof of delivery received | Manual |
| `EMPTY_RETURNED` | Empty container returned to depot | Manual / EDI |
| `JOB_CLOSED` | All charges settled, job locked | Auto on job close |

### Air

| Code | Description |
|---|---|
| `CARGO_ACCEPTED` | Cargo accepted at origin airport |
| `FLIGHT_DEPARTED` | Flight departed origin |
| `FLIGHT_ARRIVED` | Flight arrived at destination |
| `CUSTOMS_RELEASED` | Import customs cleared |
| `DELIVERED` | Delivered to consignee |
| `POD_RECEIVED` | Proof of delivery received |

### Road

| Code | Description |
|---|---|
| `PICKUP_COMPLETED` | Cargo picked up from shipper |
| `IN_TRANSIT` | Truck in transit |
| `BORDER_CROSSED` | Border crossing completed |
| `DELIVERED` | Delivered to consignee |
| `POD_RECEIVED` | Proof of delivery received |

---

## Business Rules

1. A milestone code may appear only **once** per shipment. Setting `actual_date` on an existing row updates it; it does not create a duplicate.
2. When `actual_date` is set and `planned_date` is also set, the system auto-calculates: `exception_hours = (actual - planned) / 3600`. If `exception_hours > 0`, `is_exception` is set to `true`.
3. The `JOB_CREATED` milestone is written automatically (source=SYSTEM) when a shipment is created. It cannot be edited or deleted.
4. Shipment status transitions auto-write milestones:
   - Draft → Pending: no milestone
   - Pending → Active: writes `ON_BOARD` (source=SYSTEM) if not already set
   - Active → Completed: writes `JOB_CLOSED` (source=SYSTEM)
5. `planned_date` is populated from the Booking dates where available: ETD → `VESSEL_DEPARTED`, ETA → `VESSEL_ARRIVED`, SI cutoff → `SI_SUBMITTED`, VGM cutoff → `VGM_SUBMITTED`.
6. `source = CARRIER_TRACKING` is reserved for future AIS / airline tracking API integration.

---

## API

```
GET    /shipment/{id}/milestones                  — list all milestone rows ordered by actual_date DESC
POST   /shipment/{id}/milestones                  — create or update milestone (upsert by code)
PATCH  /shipment/{id}/milestones/{milestoneId}    — update planned_date, actual_date, remarks
DELETE /shipment/{id}/milestones/{milestoneId}    — remove (only non-SYSTEM milestones)
```

### POST/PATCH body (upsert)

```json
{
  "milestoneCode": "ON_BOARD",
  "plannedDate": "2026-07-15T00:00:00Z",
  "actualDate": "2026-07-16T14:30:00Z",
  "source": "MANUAL",
  "remarks": "Vessel MV Pacific Dream, Voyage 126N"
}
```

### GET response

```json
[
  {
    "id": 1,
    "milestoneCode": "JOB_CREATED",
    "description": "Shipment created in system",
    "plannedDate": null,
    "actualDate": "2026-06-22T09:00:00Z",
    "isException": false,
    "exceptionHours": null,
    "source": "SYSTEM",
    "updatedBy": null
  },
  {
    "id": 3,
    "milestoneCode": "ON_BOARD",
    "description": "Cargo loaded on vessel",
    "plannedDate": "2026-07-15T00:00:00Z",
    "actualDate": "2026-07-16T14:30:00Z",
    "isException": true,
    "exceptionHours": 38.5,
    "source": "MANUAL",
    "updatedBy": { "id": 5, "name": "Nguyen Van A" }
  }
]
```

---

## BO UI

### Milestones tab in ShipmentDetail

**Timeline view** — vertical stepper with completed (green), planned (grey), and exception (red/amber) states.

Each milestone row shows:
- Milestone name
- Planned date (editable, click to pick)
- Actual date (editable, click to pick)
- Exception badge ("+38.5 hrs late") if `is_exception = true`
- Source chip (MANUAL / SYSTEM)
- Remarks (expandable)

**Mark Actual button** on each planned milestone — opens a datetime picker with optional remarks. Auto-calculates and shows exception immediately.

**Add Milestone** button — for adding non-standard milestones (OTHER type).

**Exception summary** at the top: number of exception milestones / total milestones.

**Colour coding:**
- Completed on time: green check
- Completed late: red exclamation
- Completed early: blue exclamation  
- Planned (no actual yet): grey circle
- Overdue (planned date passed, no actual): amber warning

---

## Migration

### Schema

```sql
-- MySQL
CREATE TABLE shipment_milestone (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id     INT NOT NULL,
  milestone_code  VARCHAR(32) NOT NULL,
  planned_date    DATETIME DEFAULT NULL,
  actual_date     DATETIME DEFAULT NULL,
  is_exception    TINYINT(1) NOT NULL DEFAULT 0,
  exception_hours DECIMAL(8,2) DEFAULT NULL,
  source          VARCHAR(16) NOT NULL DEFAULT 'MANUAL',
  remarks         TEXT DEFAULT NULL,
  updated_by      INT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ms_shipment (shipment_id, actual_date DESC),
  INDEX idx_ms_code (milestone_code),
  CONSTRAINT FK_ms_shipment  FOREIGN KEY (shipment_id) REFERENCES shipment(id) ON DELETE CASCADE,
  CONSTRAINT FK_ms_updater   FOREIGN KEY (updated_by)  REFERENCES user(id) ON DELETE SET NULL
);

-- Seed JOB_CREATED milestone for all existing shipments
INSERT INTO shipment_milestone (shipment_id, milestone_code, actual_date, source, created_at)
SELECT id, 'JOB_CREATED', created_at, 'SYSTEM', created_at
FROM shipment;
```

---

## Reference: Industry Patterns

- **CargoWise One** has a comprehensive Milestone (Events) system with ~80 standard event codes. Each event has a planned date (entered at booking) and actual date (entered operationally or received via EDI). The gap drives the SLA performance dashboard.
- **Magaya** calls these "Milestones" and generates them automatically from booking data. Actual dates are entered by the operator or received from carrier APIs.
- **Descartes** has an Event Manager where milestones are chained — completing one milestone can auto-trigger the creation of the next planned milestone.
- **Flexport** surfaces milestones directly to customers in their tracking portal — each milestone is visible to the shipper with status: Completed / On Track / At Risk / Late.
- **Shipsy** uses milestones for SLA breach alerts — any milestone that passes its planned date without an actual triggers an automated notification to the account manager.
