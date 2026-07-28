# Freight Forwarder SaaS — Transport Modes and Service Types

## 1. Two Different Classification Layers

Transport mode and service type are frequently conflated in freight SaaS design. They are a parent-child relationship — two separate fields on the shipment record, not one combined field.

> **Transport mode** answers: *what physical network does the cargo move on?*
> **Service type** answers: *how much of that network's capacity does this shipment use?*

| Layer | Field | Example values |
|---|---|---|
| Transport mode | `transport_mode` | OCN, AIR, RD, RAL, COU, MMD |
| Service type | `service_type` | FCL, LCL, FTL, LTL, CONSOL, DIRECT, BLOCK |

Having them as two separate columns matters for querying:

```sql
-- All ocean shipments regardless of how the container was used
SELECT * FROM shipment WHERE transport_mode = 'OCN';

-- All full-load shipments regardless of which mode they used
SELECT * FROM shipment WHERE service_type IN ('FCL', 'FTL', 'FCL-RAIL');

-- Ocean FCL specifically
SELECT * FROM shipment WHERE transport_mode = 'OCN' AND service_type = 'FCL';
```

A combined field like `OCN-FCL` makes all three queries awkward and breaks every index.

---

## 2. Transport Mode Codes

### OCN — Ocean / Sea Freight

Cargo moves on a vessel over sea routes. The dominant international freight mode by volume.

**Child service types:**

| Code | Full name | Description |
|---|---|---|
| `FCL` | Full Container Load | One customer fills an entire container. Charged per container regardless of actual cargo weight. |
| `LCL` | Less than Container Load | Multiple customers share one container. Charged per W/M (revenue ton). |
| `BULK` | Bulk / break-bulk | Loose cargo loaded directly into the vessel hold without a container. Grain, coal, ore, timber. |
| `RORO` | Roll-on Roll-off | Wheeled cargo (vehicles, machinery) driven onto the vessel. |
| `OOG` | Out of Gauge | Cargo that exceeds standard container dimensions. Requires open-top or flat-rack container. |

FCL and LCL are the dominant service types for general cargo. BULK, RORO, and OOG are specialised and require separate rate structures.

**Key data objects per service type:**

| service_type | Primary cargo object | Charge basis |
|---|---|---|
| FCL | `container` (one row per box) | Per container (20GP / 40GP / 40HC etc.) |
| LCL | `cargo_detail` (weight + volume) | Per W/M — `MAX(gross_kg/1000, volume_cbm)` |
| BULK | `cargo_detail` (tonnes + stowage factor) | Per freight ton |
| RORO | `vehicle` (make, model, VIN, dimensions) | Per unit or per lane metre |
| OOG | `cargo_detail` + `special_equipment` | Per container type + OOG surcharge |

---

### AIR — Air Freight

Cargo moves on passenger or freighter aircraft. Fastest international mode; most expensive per kg.

**Child service types:**

| Code | Full name | Description |
|---|---|---|
| `DIRECT` | Direct / nominated booking | Your customer's cargo fills the booking. You issue HAWB; carrier issues MAWB. |
| `CONSOL` | Air consolidation | You group multiple shippers under one MAWB. You issue HAWBs; you are the consolidator. |
| `CHARTER` | Air charter | Full aircraft chartered for time-critical or oversized cargo. Rare. |
| `EXPRESS` | Express / priority | Fast-track handling with guaranteed flight uplift. Premium rate. |

Air freight does **not** use FCL or LCL terminology. The equivalent concepts are:

| Air concept | Ocean equivalent | Description |
|---|---|---|
| DIRECT booking | FCL | One customer's cargo, one booking |
| CONSOL / HAWB | LCL | Multiple customers, one MAWB |
| ULD | Container | Unit Load Device — the physical unit loaded onto the aircraft |

**Chargeable weight formula (IATA standard):**

```
Volumetric weight (kg) = (length_cm × width_cm × height_cm) / 6000
Chargeable weight      = MAX(gross_weight_kg, volumetric_weight_kg)
```

**IATA rate break tiers:**

| Band | Label | Typical behaviour |
|---|---|---|
| Minimum charge | M | Applied when total is below the carrier's floor |
| Under 45 kg | N | Normal / general cargo rate |
| 45 kg and above | +45 | First break — lower per-kg rate |
| 100 kg and above | +100 | |
| 300 kg and above | +300 | |
| 500 kg and above | +500 | |
| 1,000 kg and above | +1000 | Lowest per-kg rate |

Higher weight bands have lower per-kg rates. The system must check whether routing the shipment into a higher weight band (break-even calculation) results in a lower total charge.

---

### RD — Road Freight

Cargo moves on road networks by truck. Used for domestic haulage, cross-border trucking (ASEAN, EU), and first/last-mile legs of international shipments.

**Child service types:**

| Code | Full name | Description |
|---|---|---|
| `FTL` | Full Truck Load | One customer's cargo fills an entire truck. Charged as a flat lane rate per trip. |
| `LTL` | Less than Truck Load | Multiple customers share a truck. Charged per pallet, LDM, kg, or CBM. |
| `GROUPAGE` | Groupage / part load | European term for LTL — multiple consignments consolidated in one truck. |
| `COURIER-RD` | Road courier / express van | Small shipments, same-day or next-day, van delivery. |

FTL and LTL follow exactly the same pattern as ocean FCL and LCL:

| Road | Ocean equivalent | Shared logic |
|---|---|---|
| FTL | FCL | One customer, one vehicle, flat rate |
| LTL / Groupage | LCL | Multiple customers, shared vehicle, rate per unit |
| Road consolidation | Ocean consol | Groups multiple LTL consignments under one truck trip |

**LTL charge basis options (varies by carrier):**

| Method | Description |
|---|---|
| Per pallet | Fixed rate per euro-pallet |
| Per LDM | Rate per loading metre — floor space the cargo occupies in the truck |
| Per kg | Rate per kg with a minimum charge |
| Per CBM | Rate per cubic metre |
| Zone matrix | Origin zone × destination zone = base rate, scaled by weight or volume |

**Key data objects per service type:**

| service_type | Primary cargo object | Charge basis |
|---|---|---|
| FTL | `truck` (vehicle, driver, plate, haulier) | Flat per trip (lane rate) |
| LTL | `cargo_detail` + `road_consol` | Per pallet / LDM / kg |
| GROUPAGE | `cargo_detail` + `road_consol` | Per pallet / LDM / kg |

---

### RAL — Rail Freight

Cargo moves on rail networks. Increasingly significant for China–Europe Belt and Road routes and domestic rail in large countries. Borrows FCL/LCL terminology for containerised cargo.

**Child service types:**

| Code | Full name | Description |
|---|---|---|
| `FCL-RAIL` | Full container on rail | ISO container loaded onto a rail flatcar. One customer per container. |
| `LCL-RAIL` | Shared rail container | Multiple customers share one container on rail. Less common than ocean LCL. |
| `BLOCK` | Block train | A chartered train carrying containers or wagons for a single operator. Equivalent to a full charter. |
| `WAGON` | Full wagon load | A full rail wagon (not containerised) — used for bulk commodities. |

Rail uses the same container types as ocean (20GP, 40GP, 40HC) for containerised cargo, so `container` objects are reused. The difference is the booking object — a `rail_booking` references a train service and departure terminal (ICD) rather than a vessel and port.

**Key document:** CIM (Convention Internationale concernant le transport des Marchandises par chemin de fer) — the international rail consignment note, equivalent to the CMR for road.

---

### COU — Courier / Express

Small shipments handled by integrators (FedEx, DHL Express, UPS, TNT) or domestic courier networks.

**No FCL / LCL / FTL / LTL concept applies.** Courier is always per-piece or per-kg. The service type dimension is the **delivery speed tier**:

| Code | Description |
|---|---|
| `ECONOMY` | Standard delivery, 3–7 business days |
| `EXPRESS` | 1–3 business days |
| `OVERNIGHT` | Next business day |
| `SAME-DAY` | Same-day delivery within a city or region |

The forwarder either resells integrator services (API booking + tracking) or runs their own courier consolidation where small parcels are grouped into a bag or pallet for airport injection.

**Charge basis:** Per kg or per piece, with a minimum charge and a stack of surcharges (remote area, fuel, residential delivery, dangerous goods, oversize).

---

### MMD — Multimodal

Cargo moves on two or more modes under a single contract and a single document (Multimodal Bill of Lading / MTD).

**Child service types are combinations — each leg inherits its own mode's service type:**

| Code | Legs | Service type per leg |
|---|---|---|
| `SEA-AIR` | Ocean → Air | FCL or LCL on ocean leg; DIRECT or CONSOL on air leg |
| `SEA-ROAD` | Ocean → Road | FCL or LCL on ocean leg; FTL or LTL on road leg |
| `AIR-ROAD` | Air → Road | DIRECT or CONSOL on air leg; FTL or LTL on road leg |
| `RAIL-ROAD` | Rail → Road | FCL-RAIL on rail leg; FTL or LTL on road leg |
| `SEA-RAIL-ROAD` | Ocean → Rail → Road | Combination — common for China–Europe |

The MMD job is a parent record containing sub-legs. Each sub-leg is a job of its own mode type with its own `transport_mode` and `service_type`:

```
MMD Job (parent)
├── Sub-leg 1: transport_mode=OCN, service_type=FCL  → container object
├── Sub-leg 2: transport_mode=RAL, service_type=FCL-RAIL → rail_booking object
└── Sub-leg 3: transport_mode=RD,  service_type=FTL → truck object
```

Charge lines exist at the sub-leg level, each with the appropriate charge basis for that mode. The parent MMD job aggregates all sub-leg charges into one customer quote and one consolidated invoice.

---

## 3. The Full Relationship Map

```
transport_mode    service_type              charge_basis              key_object
─────────────────────────────────────────────────────────────────────────────────
OCN               FCL                       per container             container
OCN               LCL                       per W/M                   cargo_detail
OCN               BULK                      per freight ton           cargo_detail
OCN               RORO                      per unit / lane metre     vehicle
OCN               OOG                       per container + surcharge cargo_detail

AIR               DIRECT                    per chargeable kg         cargo_detail
AIR               CONSOL                    per chargeable kg         cargo_detail + air_consol
AIR               EXPRESS                   per chargeable kg         cargo_detail
AIR               CHARTER                   flat per flight           charter_booking

RD                FTL                       flat per trip             truck
RD                LTL                       per pallet / LDM / kg    cargo_detail + road_consol
RD                GROUPAGE                  per pallet / LDM / kg    cargo_detail + road_consol
RD                COURIER-RD                per kg / piece            cargo_detail

RAL               FCL-RAIL                  per container             container + rail_booking
RAL               LCL-RAIL                  per W/M                   cargo_detail + rail_booking
RAL               BLOCK                     flat per train            block_booking
RAL               WAGON                     per wagon                 wagon

COU               ECONOMY / EXPRESS /       per kg / piece            parcel
                  OVERNIGHT / SAME-DAY

MMD               SEA-AIR / SEA-ROAD /      inherited per sub-leg     sub-leg objects
                  AIR-ROAD / RAIL-ROAD /
                  SEA-RAIL-ROAD
```

---

## 4. Data Model

### Schema

```sql
-- Transport mode reference table
CREATE TABLE transport_mode (
  code          VARCHAR(8)    PRIMARY KEY,  -- OCN / AIR / RD / RAL / COU / MMD
  name          VARCHAR(64)   NOT NULL,
  description   TEXT,
  is_active     BOOLEAN       NOT NULL DEFAULT true
);

-- Service type reference table
CREATE TABLE service_type (
  code              VARCHAR(16)   PRIMARY KEY,  -- FCL / LCL / FTL / LTL / CONSOL ...
  name              VARCHAR(64)   NOT NULL,
  transport_mode    VARCHAR(8)    NOT NULL REFERENCES transport_mode(code),
  charge_basis      VARCHAR(32)   NOT NULL,     -- PER_CONTAINER / PER_WM / PER_KG / FLAT / PER_PIECE
  cargo_object_type VARCHAR(32)   NOT NULL,     -- container / cargo_detail / truck / wagon / parcel
  is_active         BOOLEAN       NOT NULL DEFAULT true
);

-- Shipment carries both fields as separate columns
ALTER TABLE shipment ADD COLUMN transport_mode VARCHAR(8)  NOT NULL REFERENCES transport_mode(code);
ALTER TABLE shipment ADD COLUMN service_type   VARCHAR(16) NOT NULL REFERENCES service_type(code);

-- Enforce that the service_type belongs to the selected transport_mode
ALTER TABLE shipment ADD CONSTRAINT chk_service_mode_match
  CHECK (
    service_type IN (
      SELECT code FROM service_type st
      WHERE st.transport_mode = shipment.transport_mode
    )
  );
```

### Seed data: `transport_mode`

| code | name |
|---|---|
| OCN | Ocean / Sea freight |
| AIR | Air freight |
| RD | Road freight |
| RAL | Rail freight |
| COU | Courier / Express |
| MMD | Multimodal |

### Seed data: `service_type`

| code | name | transport_mode | charge_basis | cargo_object_type |
|---|---|---|---|---|
| FCL | Full Container Load | OCN | PER_CONTAINER | container |
| LCL | Less than Container Load | OCN | PER_WM | cargo_detail |
| BULK | Bulk / break-bulk | OCN | PER_FREIGHT_TON | cargo_detail |
| RORO | Roll-on Roll-off | OCN | PER_UNIT | vehicle |
| OOG | Out of Gauge | OCN | PER_CONTAINER | cargo_detail |
| DIRECT | Direct air booking | AIR | PER_CHARGEABLE_KG | cargo_detail |
| CONSOL | Air consolidation | AIR | PER_CHARGEABLE_KG | cargo_detail |
| EXPRESS | Air express | AIR | PER_CHARGEABLE_KG | cargo_detail |
| CHARTER | Air charter | AIR | FLAT | charter_booking |
| FTL | Full Truck Load | RD | FLAT | truck |
| LTL | Less than Truck Load | RD | PER_PALLET_LDM_KG | cargo_detail |
| GROUPAGE | Groupage | RD | PER_PALLET_LDM_KG | cargo_detail |
| COURIER-RD | Road courier | RD | PER_KG | cargo_detail |
| FCL-RAIL | Full container on rail | RAL | PER_CONTAINER | container |
| LCL-RAIL | Shared rail container | RAL | PER_WM | cargo_detail |
| BLOCK | Block train | RAL | FLAT | block_booking |
| WAGON | Full wagon load | RAL | PER_WAGON | wagon |
| ECONOMY | Courier economy | COU | PER_KG | parcel |
| EXPRESS-COU | Courier express | COU | PER_KG | parcel |
| OVERNIGHT | Courier overnight | COU | PER_KG | parcel |
| SAME-DAY | Courier same-day | COU | PER_PIECE | parcel |
| SEA-AIR | Sea then air | MMD | INHERITED | sub_leg |
| SEA-ROAD | Sea then road | MMD | INHERITED | sub_leg |
| AIR-ROAD | Air then road | MMD | INHERITED | sub_leg |
| RAIL-ROAD | Rail then road | MMD | INHERITED | sub_leg |
| SEA-RAIL-ROAD | Sea then rail then road | MMD | INHERITED | sub_leg |

---

## 5. How Service Type Drives the Rate Card

The `service_type` field on the shipment determines which rate card structure applies. This is the link between the mode/service classification and the pricing system.

```sql
-- Rate card is keyed by transport_mode + service_type at the header level
SELECT *
FROM rate_card
WHERE pol_code      = :pol
  AND pod_code      = :pod
  AND transport_mode = :mode       -- OCN
  AND service_type  = :service     -- FCL
  AND effective_date <= :date
  AND (expiry_date IS NULL OR expiry_date >= :date)
ORDER BY customer_id NULLS LAST, effective_date DESC
LIMIT 1;

-- Rate card lines differ by service type:
-- FCL:   one line per container_type (20GP / 40GP / 40HC)
-- LCL:   one line per W/M tier (min 1 W/M, then per W/M above)
-- AIR:   one line per weight band (N, +45, +100, +300, +500, +1000)
-- FTL:   one line per truck type (box truck / curtainsider / reefer)
-- LTL:   one line per LDM tier or per-kg rate
```

### Rate line structure per service type

**OCN FCL:**
```
rate_card_line: container_type=20GP, base_rate=350.00 USD
rate_card_line: container_type=40GP, base_rate=520.00 USD
rate_card_line: container_type=40HC, base_rate=540.00 USD
```

**OCN LCL:**
```
rate_card_line: basis=PER_WM, base_rate=28.00 USD, min_charge=1.0 WM
```

**AIR DIRECT / CONSOL:**
```
rate_card_line: weight_band=N,    rate_per_kg=8.50 USD
rate_card_line: weight_band=+45,  rate_per_kg=6.80 USD
rate_card_line: weight_band=+100, rate_per_kg=5.50 USD
rate_card_line: weight_band=+300, rate_per_kg=4.20 USD
rate_card_line: weight_band=+500, rate_per_kg=3.80 USD
```

**RD FTL:**
```
rate_card_line: truck_type=CURTAINSIDER, base_rate=420.00 USD (HCM→HAN)
rate_card_line: truck_type=REEFER,       base_rate=580.00 USD (HCM→HAN)
```

**RD LTL:**
```
rate_card_line: basis=PER_LDM, base_rate=35.00 USD, min_charge=1.0 LDM
```

---

## 6. How Service Type Drives the Shipment Data Objects

The `cargo_object_type` from the service type seed data determines which cargo detail table is populated when a job is created. The application uses this to decide which form to show the operator and which objects to create.

### Container object (FCL, FCL-RAIL)

```sql
CREATE TABLE container (
  id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID        NOT NULL REFERENCES shipment(id),
  container_number  VARCHAR(16),               -- ISO format e.g. MSCU1234567
  container_type    VARCHAR(8)  NOT NULL,      -- 20GP / 40GP / 40HC / 40RF / 45HC
  seal_number       VARCHAR(32),
  tare_weight_kg    NUMERIC(10,2),
  cargo_weight_kg   NUMERIC(10,2),
  vgm               NUMERIC(10,2),
  vgm_method        VARCHAR(4),                -- M1 / M2
  temperature_set   NUMERIC(5,2),              -- reefer only (°C)
  humidity_set      NUMERIC(5,2),              -- reefer only (%)
  is_empty          BOOLEAN     NOT NULL DEFAULT false
);
```

### Cargo detail object (LCL, AIR, LTL, GROUPAGE, LCL-RAIL, COU)

```sql
CREATE TABLE cargo_detail (
  id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID        NOT NULL REFERENCES shipment(id),
  pieces            INT         NOT NULL,
  gross_weight_kg   NUMERIC(12,3) NOT NULL,
  volume_cbm        NUMERIC(12,4) NOT NULL,
  chargeable_weight NUMERIC(12,3),             -- computed: MAX(gross_kg/1000, cbm) for ocean; MAX(gross_kg, vol_kg) for air
  length_cm         NUMERIC(8,2),
  width_cm          NUMERIC(8,2),
  height_cm         NUMERIC(8,2),
  ldm               NUMERIC(8,3),              -- loading metres — road LTL only
  pallets           INT,                        -- road LTL only
  commodity         VARCHAR(128),
  hs_code           VARCHAR(16),
  marks_numbers     TEXT
);
```

### Truck object (FTL)

```sql
CREATE TABLE truck (
  id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID        NOT NULL REFERENCES shipment(id),
  truck_type        VARCHAR(32) NOT NULL,       -- BOX / CURTAINSIDER / FLATBED / REEFER / TANKER
  payload_kg        NUMERIC(10,2),
  truck_plate       VARCHAR(16),
  driver_name       VARCHAR(64),
  haulier_id        UUID        REFERENCES organisation(id),
  pickup_address    TEXT,
  delivery_address  TEXT,
  scheduled_pickup  TIMESTAMPTZ,
  scheduled_delivery TIMESTAMPTZ,
  actual_pickup     TIMESTAMPTZ,
  actual_delivery   TIMESTAMPTZ,
  pod_signed_by     VARCHAR(64),
  pod_image_url     TEXT
);
```

### Parcel object (COU)

```sql
CREATE TABLE parcel (
  id                UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID        NOT NULL REFERENCES shipment(id),
  tracking_number   VARCHAR(64),               -- integrator tracking reference
  service_level     VARCHAR(16) NOT NULL,      -- ECONOMY / EXPRESS / OVERNIGHT / SAME-DAY
  integrator        VARCHAR(32),               -- FEDEX / DHL / UPS / TNT
  pieces            INT         NOT NULL DEFAULT 1,
  gross_weight_kg   NUMERIC(10,3) NOT NULL,
  declared_value    NUMERIC(20,6),
  declared_currency CHAR(3)
);
```

---

## 7. Chargeable Weight Calculation by Service Type

The system must calculate chargeable weight differently per service type. This should be a computed field or a service-layer function — not hardcoded in multiple places.

```python
def calculate_chargeable_weight(service_type: str, cargo: dict) -> float:
    gross_kg = cargo['gross_weight_kg']
    cbm      = cargo['volume_cbm']

    if service_type in ('LCL', 'LCL-RAIL'):
        # Ocean W/M: 1 CBM = 1,000 kg equivalent
        weight_ton = gross_kg / 1000
        return max(weight_ton, cbm)                       # result in W/M (revenue tons)

    elif service_type in ('DIRECT', 'CONSOL', 'EXPRESS', 'EXPRESS-COU', 'ECONOMY',
                          'OVERNIGHT', 'SAME-DAY', 'COURIER-RD'):
        # IATA volumetric: 1 CBM = 167 kg (divisor 6,000 cm³/kg)
        l, w, h   = cargo['length_cm'], cargo['width_cm'], cargo['height_cm']
        vol_kg    = (l * w * h) / 6000
        return max(gross_kg, vol_kg)                      # result in kg

    elif service_type in ('LTL', 'GROUPAGE'):
        # Road: depends on carrier agreement — LDM, kg, or CBM
        # Return all three; pricing engine picks the applicable basis
        ldm = cargo.get('ldm')
        return {'kg': gross_kg, 'cbm': cbm, 'ldm': ldm}

    elif service_type in ('FCL', 'FCL-RAIL', 'FTL', 'BLOCK', 'WAGON', 'CHARTER'):
        # Full-load modes: no chargeable weight concept — flat rate per unit
        return None

    elif service_type.startswith('SEA-') or service_type in ('AIR-ROAD', 'RAIL-ROAD',
                                                               'SEA-RAIL-ROAD'):
        # Multimodal: calculate per sub-leg using that leg's service_type
        raise ValueError("Calculate chargeable weight at the sub-leg level for MMD")

    else:
        raise ValueError(f"Unknown service_type: {service_type}")
```

---

## 8. Summary: The Golden Rules

1. **`transport_mode` and `service_type` are two separate columns** — never combine them into a single field like `OCN-FCL`. You need to filter by each independently.

2. **FCL/LCL apply to OCN only.** FTL/LTL apply to RD only. RAL borrows FCL/LCL terminology for containerised rail. AIR and COU have their own equivalent concepts (DIRECT vs CONSOL, speed tiers) that are not named FCL/LCL.

3. **`service_type` determines charge basis.** FCL = per container. LCL = per W/M. AIR = per chargeable kg by weight band. FTL = flat per trip. LTL = per LDM/pallet/kg. This drives which rate card line structure is used.

4. **`service_type` determines which cargo object is created on the job.** FCL → `container`. LCL/AIR/LTL → `cargo_detail`. FTL → `truck`. COU → `parcel`. MMD → sub-leg objects of the appropriate type per leg.

5. **Chargeable weight formula differs per service type.** Ocean LCL uses W/M (1 CBM = 1,000 kg). Air uses IATA volumetric (divisor 6,000). Road LTL uses LDM, kg, or CBM depending on the carrier. Full-load modes (FCL, FTL, BLOCK) have no chargeable weight — they are flat-rate per unit.

6. **MMD inherits the service type of each sub-leg.** A SEA-AIR job has FCL or LCL on the ocean leg and DIRECT or CONSOL on the air leg. The MMD parent is the commercial container; the sub-legs are the operational and rate-card containers.
