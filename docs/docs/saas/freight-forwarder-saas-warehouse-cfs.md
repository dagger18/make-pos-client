# Freight Forwarder SaaS — Warehouse and CFS Management

## 1. What This Module Covers

Many freight forwarders operate their own Container Freight Stations (CFS) — warehouses where LCL cargo is consolidated into containers (stuffing) and de-consolidated out of containers (stripping). The warehouse module manages cargo receipts, storage locations, stuffing and stripping instructions, and inventory tracking per job.

This is distinct from the consolidation management module (which manages the shipping documents and charges) — this module manages the physical cargo at the facility level.

---

## 2. Warehouse / CFS Facility

```sql
CREATE TABLE warehouse_facility (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),
  name              VARCHAR(128)  NOT NULL,
  location_code     VARCHAR(10)   REFERENCES location(code),   -- which port/airport this CFS serves
  address           TEXT,
  total_area_sqm    NUMERIC(10,2),
  reefer_capacity   INT,                             -- reefer points
  bonded            BOOLEAN       NOT NULL DEFAULT false,   -- customs bonded warehouse
  dangerous_goods_approved BOOLEAN NOT NULL DEFAULT false,
  operating_hours   JSONB,                           -- {"mon": "08:00-18:00", ...}
  contact_phone     VARCHAR(32),
  contact_email     VARCHAR(128),
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 3. Cargo Receipt — Inbound

When cargo arrives at the CFS, a goods receipt is created against the job.

```sql
CREATE TABLE warehouse_receipt (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  receipt_number    VARCHAR(64)   UNIQUE NOT NULL,   -- WR-HCM-202604-00045
  facility_id       UUID          NOT NULL REFERENCES warehouse_facility(id),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  consol_id         UUID          REFERENCES consolidation(id),
  receipt_type      VARCHAR(16)   NOT NULL,   -- INBOUND / RETURN_EMPTY / TRANSFER_IN
  received_from     UUID          REFERENCES organisation(id),   -- trucker / shipper
  vehicle_plate     VARCHAR(32),
  driver_name       VARCHAR(64),
  driver_id_ref     VARCHAR(64),

  -- Cargo received
  pieces_received   INT           NOT NULL,
  pieces_expected   INT,
  gross_weight_kg   NUMERIC(12,3) NOT NULL,
  volume_cbm        NUMERIC(10,4),
  condition         VARCHAR(16)   NOT NULL DEFAULT 'GOOD',   -- GOOD / DAMAGED / SHORT / EXCESS
  damage_notes      TEXT,
  temperature_c     NUMERIC(5,2),            -- for reefer cargo

  -- Location assigned
  storage_zone      VARCHAR(16),             -- A / B / REEFER / HAZMAT
  storage_location  VARCHAR(32),             -- rack: A-03-02 (zone-row-bay)

  received_at       TIMESTAMPTZ   NOT NULL DEFAULT now(),
  received_by       UUID          REFERENCES app_user(id),

  -- Milestone trigger
  milestone_written BOOLEAN       NOT NULL DEFAULT false
);
```

### Cargo condition codes

| Condition | Meaning |
|---|---|
| `GOOD` | Cargo received in expected condition |
| `DAMAGED` | Visible damage noted on receipt — photo taken, damage report created |
| `SHORT` | Fewer pieces received than expected — short delivery note issued |
| `EXCESS` | More pieces received than on the packing list |
| `WET` | Moisture damage noted |
| `CONTAMINATED` | Cargo contaminated — quarantine zone |

---

## 4. Storage Location Model

```sql
CREATE TABLE storage_location (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  facility_id       UUID          NOT NULL REFERENCES warehouse_facility(id),
  zone              VARCHAR(16)   NOT NULL,   -- GENERAL / REEFER / HAZMAT / BONDED / OVERFLOW
  row_code          VARCHAR(4)    NOT NULL,   -- A / B / C ...
  bay_code          VARCHAR(4)    NOT NULL,   -- 01 / 02 / 03 ...
  level_code        VARCHAR(4),               -- G / 1 / 2 (ground / rack levels)
  max_weight_kg     NUMERIC(10,2),
  max_volume_cbm    NUMERIC(8,4),
  is_reefer         BOOLEAN       NOT NULL DEFAULT false,
  is_hazmat_approved BOOLEAN      NOT NULL DEFAULT false,
  is_occupied       BOOLEAN       NOT NULL DEFAULT false,
  current_receipt_id UUID         REFERENCES warehouse_receipt(id),

  UNIQUE (facility_id, zone, row_code, bay_code, level_code)
);
```

---

## 5. Stuffing Instruction (Export — CFS to Container)

When enough cargo has been received at the CFS to fill a consol, the stuffing instruction tells warehouse staff which jobs' cargo goes into which container.

```sql
CREATE TABLE stuffing_instruction (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  instruction_number VARCHAR(64)  UNIQUE NOT NULL,
  facility_id       UUID          NOT NULL REFERENCES warehouse_facility(id),
  consol_id         UUID          NOT NULL REFERENCES consolidation(id),
  container_id      UUID          NOT NULL REFERENCES container(id),
  status            VARCHAR(16)   NOT NULL DEFAULT 'PENDING',  -- PENDING / IN_PROGRESS / COMPLETED
  scheduled_at      TIMESTAMPTZ,
  started_at        TIMESTAMPTZ,
  completed_at      TIMESTAMPTZ,
  forklift_operator VARCHAR(64),
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE stuffing_instruction_line (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  stuffing_id       UUID          NOT NULL REFERENCES stuffing_instruction(id),
  receipt_id        UUID          NOT NULL REFERENCES warehouse_receipt(id),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  pieces_to_stuff   INT           NOT NULL,
  weight_kg         NUMERIC(12,3) NOT NULL,
  volume_cbm        NUMERIC(10,4),
  load_sequence     SMALLINT,                 -- loading order (heavy items first)
  is_stuffed        BOOLEAN       NOT NULL DEFAULT false,
  stuffed_at        TIMESTAMPTZ
);
```

---

## 6. Stripping Instruction (Import — Container to CFS)

On the import side, when a container arrives at the CFS, it is stripped — cargo is unloaded and sorted by HBL/HAWB.

```sql
CREATE TABLE stripping_instruction (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  instruction_number VARCHAR(64)  UNIQUE NOT NULL,
  facility_id       UUID          NOT NULL REFERENCES warehouse_facility(id),
  consol_id         UUID          NOT NULL REFERENCES consolidation(id),
  container_id      UUID          NOT NULL REFERENCES container(id),
  container_arrival TIMESTAMPTZ,             -- when container arrived at CFS
  status            VARCHAR(16)   NOT NULL DEFAULT 'PENDING',
  started_at        TIMESTAMPTZ,
  completed_at      TIMESTAMPTZ,
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE TABLE stripping_result (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  stripping_id      UUID          NOT NULL REFERENCES stripping_instruction(id),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  hbl_number        VARCHAR(32),
  pieces_stripped   INT           NOT NULL,
  weight_kg         NUMERIC(12,3),
  condition         VARCHAR(16)   NOT NULL DEFAULT 'GOOD',
  damage_notes      TEXT,
  storage_location  VARCHAR(32),             -- where cargo is placed after stripping
  stripped_at       TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 7. Storage Charges

Cargo that remains at the CFS beyond the free storage period incurs storage charges — similar to D&D but at the warehouse level.

```sql
CREATE TABLE warehouse_storage_charge (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  receipt_id        UUID          NOT NULL REFERENCES warehouse_receipt(id),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  free_days         SMALLINT      NOT NULL,
  free_end_date     DATE          NOT NULL,
  release_date      DATE,                    -- when cargo left the facility
  chargeable_days   SMALLINT,
  rate_per_day      NUMERIC(20,6) NOT NULL,
  rate_per_day_uom  VARCHAR(16)   NOT NULL,  -- PER_CBM / PER_TON / PER_PALLET / FLAT
  total_amount      NUMERIC(20,6),
  currency          CHAR(3)       NOT NULL,
  is_invoiced       BOOLEAN       NOT NULL DEFAULT false,
  invoice_id        UUID          REFERENCES invoice(id)
);
```

---

## 8. Delivery Order Release

Before cargo can be released from the CFS to the consignee's trucker, a valid Delivery Order must be presented and verified.

```python
def release_cargo_to_trucker(receipt_id: str, do_number: str,
                               trucker_plate: str, driver_name: str) -> None:
    receipt = fetch_receipt(receipt_id)
    job     = fetch_job(receipt.job_id)

    # Verify D/O
    do = fetch_delivery_order(job_id=job.id, do_number=do_number)
    if not do or do.status != 'VALID':
        raise InvalidDOError(f"Delivery Order {do_number} is not valid")

    if not job.customs_released:
        raise CustomsHoldError("Cargo has not been customs released")

    # Record the release
    db.execute("""
        UPDATE warehouse_receipt SET
          released_at     = now(),
          released_to     = :trucker_plate,
          release_driver  = :driver_name,
          release_do_ref  = :do_number
        WHERE id = ?
    """, trucker_plate, driver_name, do_number, receipt_id)

    # Free up the storage location
    db.execute("UPDATE storage_location SET is_occupied = false WHERE current_receipt_id = ?",
               receipt_id)

    # Finalise storage charges
    finalise_storage_charge(receipt_id)

    # Write milestone
    write_milestone(job.id, 'CARGO_RELEASED_FROM_CFS', actual_date=datetime.now())
```

---

## 9. Inventory Report

Real-time view of what cargo is currently in the facility.

```sql
SELECT
  sl.zone,
  sl.row_code,
  sl.bay_code,
  wr.receipt_number,
  s.shipment_id,
  jp.address_snapshot ->> 'name' AS consignee,
  wr.pieces_received,
  wr.gross_weight_kg,
  wr.volume_cbm,
  wr.received_at,
  wsc.free_end_date,
  CURRENT_DATE - wsc.free_end_date AS storage_days_chargeable
FROM warehouse_receipt wr
JOIN storage_location sl ON sl.current_receipt_id = wr.id
JOIN shipment s          ON wr.job_id             = s.id
JOIN job_party jp        ON jp.job_id             = s.id AND jp.role = 'CONSIGNEE'
LEFT JOIN warehouse_storage_charge wsc ON wsc.receipt_id = wr.id
WHERE wr.facility_id = :facility_id
  AND sl.is_occupied = true
  AND wr.released_at IS NULL
ORDER BY sl.zone, sl.row_code, sl.bay_code;
```

---

## 10. Golden Rules

1. **Every cargo movement is recorded.** Receipt in, move between locations, stuff into container, strip out, release to trucker — every physical action writes a record.
2. **Cargo cannot be released without a valid D/O and customs release.** The warehouse system must enforce this check — never rely on a manual process.
3. **Damaged cargo must be photographed and reported immediately.** A damage noted at receipt without documentation creates liability uncertainty. Require a photo and damage report on the receipt screen.
4. **Storage charges start after the free period, automatically.** Do not wait for someone to remember to bill storage. The nightly job calculates chargeable days just like D&D.
5. **Inventory is location-based, not just job-based.** Being able to say "the cargo for shipment HCM-001 is in zone A, row 03, bay 02" is operationally essential in a large CFS. Always assign and record a storage location on receipt.
