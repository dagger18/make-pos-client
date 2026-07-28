# CO2 / Carbon Emissions Tracking Guide

Per-shipment carbon emissions calculation following the **GLEC Framework v3** methodology, storing both Tank-to-Wake (TTW) and Well-to-Wake (WTW) CO2e values.

---

## Methodology

```
CO2e (kg) = Distance (km) × Cargo Weight (t) × Emission Factor (kg CO2e / tonne-km)
```

- **TTW (Tank-to-Wake)**: operational emissions only (fuel combustion)
- **WTW (Well-to-Wake)**: full lifecycle including fuel production — typically 7–25% higher than TTW
- All records store both values; use TTW for operational reporting, WTW for full Scope 3 disclosure

---

## Architecture

```
src/Module/Emissions/
  Entity/
    EmissionFactor.php        — reference emission factors by mode/vehicle
    SeaDistance.php           — port-pair sea distances lookup table
    ShipmentEmission.php      — per-shipment emission calculation records
  Repository/
    EmissionFactorRepository.php   — findBestMatch() by mode + effective date
    SeaDistanceRepository.php      — findDistance() / upsert()
    ShipmentEmissionRepository.php — findByShipment() / findForReport()
  Service/
    EmissionCalculationService.php — orchestrates distance + weight + EF → CO2e
  Controller/
    EmissionFactorController.php   — GET/POST/PUT/DELETE /emissions/factor
    SeaDistanceController.php      — GET/POST/DELETE /emissions/sea-distance
    ShipmentEmissionController.php — calculate / manual / report endpoints
```

---

## Emission Factor Table

Pre-seeded with GLEC Framework v3 defaults. The migration inserts 8 rows covering all major transport modes.

| Mode | Vehicle Type | Size Class | EF TTW | EF WTW | Methodology |
|---|---|---|---|---|---|
| OCN | CONTAINER_SHIP | >8000TEU | 0.005670 | 0.006200 | GLEC_V3 |
| OCN | CONTAINER_SHIP | 4000-8000TEU | 0.008000 | 0.008750 | GLEC_V3 |
| OCN | CONTAINER_SHIP | <4000TEU | 0.011000 | 0.012000 | GLEC_V3 |
| AIR | AIRCRAFT | BELLY_CARGO | 0.602000 | 0.670000 | GLEC_V3 |
| AIR | AIRCRAFT | FREIGHTER | 0.786000 | 0.873000 | GLEC_V3 |
| RD | TRUCK_RIGID | >34T | 0.062000 | 0.072000 | GLEC_V3 |
| RD | TRUCK_RIGID | 7.5-12T | 0.170000 | 0.196000 | GLEC_V3 |
| RAL | TRAIN | FREIGHT | 0.028000 | 0.035000 | GLEC_V3 |

`findBestMatch()` selects the active factor (within `effectiveFrom`/`effectiveTo`) for the requested transport mode, falling back to any factor for the mode if a vehicle-type-specific one isn't found.

---

## Sea Distance Table

Port-pair distances for ocean routing. Populated manually or via import. Used by `EmissionCalculationService` to look up OCN distances from booking port codes.

```http
POST /emissions/sea-distance
Content-Type: application/json

{
  "polCode": "SGSIN",
  "podCode": "NLRTM",
  "distanceKm": 15830,
  "viaCanal": "SUEZ",
  "source": "SEAROUTES"
}
```

The endpoint is an **upsert** — updating an existing pair if it already exists.

---

## API Endpoints

### Emission Factors

| Method | Path | Description |
|---|---|---|
| GET | `/emissions/factor` | List all (optional `?mode=OCN`, `?methodology=GLEC_V3`) |
| GET | `/emissions/factor/{id}` | Get single |
| POST | `/emissions/factor` | Create |
| PUT | `/emissions/factor/{id}` | Update |
| DELETE | `/emissions/factor/{id}` | Delete |

### Sea Distances

| Method | Path | Description |
|---|---|---|
| GET | `/emissions/sea-distance` | List (optional `?pol=SGSIN&pod=NLRTM`) |
| POST | `/emissions/sea-distance` | Upsert port pair |
| DELETE | `/emissions/sea-distance/{id}` | Delete |

### Shipment Emissions

| Method | Path | Description |
|---|---|---|
| GET | `/emissions/shipment/{shipmentId}` | List emission records for a shipment |
| POST | `/emissions/calculate/{shipmentId}` | Auto-calculate from shipment data |
| POST | `/emissions/manual` | Manually enter emission data |
| DELETE | `/emissions/record/{id}` | Delete a record |
| GET | `/emissions/report` | Emissions report (optional `?from=`, `?to=`, `?mode=`) |

### Auto-Calculate Endpoint

```http
POST /emissions/calculate/1234
Content-Type: application/json

{
  "transportMode": "OCN",
  "distanceKm": null,
  "legSequence": 1,
  "legDescription": "Main ocean leg"
}
```

**What happens:**
1. Finds best-matching emission factor for the `transportMode`
2. Resolves distance:
   - OCN: looks up `sea_distance` by booking's POL/POD port codes; falls back to `distanceKm` parameter
   - All modes: uses `distanceKm` parameter if provided
3. Resolves cargo weight:
   - Uses `instruction.gross_weight` (parsed from string, in KG)
   - Falls back to GLEC container defaults: 10t per 20-foot container, 14t per 40-foot
   - Falls back to `instruction.chargeable_weight`
   - Final fallback: 1.0 tonne (is_estimate = true)
4. Calculates: `tonne_km = distance × weight`, then `co2e = tonne_km × ef`
5. Saves and returns the `ShipmentEmission` record

`is_estimate = true` when distance was not found in the sea distance table or weight was inferred from container defaults.

---

## Cargo Weight Resolution (GLEC Rules)

| Weight Source | is_estimate |
|---|---|
| Actual gross weight from Instruction | false |
| GLEC container defaults (20GP = 10t, 40GP = 14t) | true |
| Chargeable weight from Instruction | true |
| Fallback 1.0 tonne | true |

---

## Database Schema

### emission_factor

```sql
id INT PK AUTO_INCREMENT
transport_mode VARCHAR(8) NOT NULL       -- OCN / AIR / RD / RAL
vehicle_type VARCHAR(64) NULL
fuel_type VARCHAR(32) NULL
size_class VARCHAR(32) NULL
load_factor DECIMAL(4,2) NULL
ef_ttw DECIMAL(12,6) NOT NULL           -- kg CO2e per tonne-km, Tank-to-Wake
ef_wtw DECIMAL(12,6) NOT NULL           -- kg CO2e per tonne-km, Well-to-Wake
methodology VARCHAR(32) NOT NULL        -- GLEC_V3 / GHG_PROTOCOL / IMO_DCS
effective_from DATE NOT NULL
effective_to DATE NULL                  -- NULL = currently active
source VARCHAR(128) NOT NULL
created_at DATETIME NOT NULL
```

### sea_distance

```sql
id INT PK AUTO_INCREMENT
pol_code VARCHAR(10) NOT NULL           -- port code (uppercase)
pod_code VARCHAR(10) NOT NULL
distance_km DECIMAL(10,2) NOT NULL
via_canal VARCHAR(16) NULL              -- SUEZ / PANAMA / CAPE / MALACCA
source VARCHAR(32) NOT NULL DEFAULT 'SEAROUTES'
updated_at DATE NULL
UNIQUE (pol_code, pod_code)
```

### shipment_emission

```sql
id INT PK AUTO_INCREMENT
shipment_id INT NOT NULL FK shipment(id) ON DELETE CASCADE
emission_factor_id INT NULL FK emission_factor(id) ON DELETE SET NULL
transport_mode VARCHAR(8) NOT NULL
distance_km DECIMAL(10,2) NOT NULL
cargo_weight_tonnes DECIMAL(12,4) NOT NULL
tonne_km DECIMAL(16,4) NOT NULL
co2e_ttw_kg DECIMAL(16,4) NOT NULL
co2e_wtw_kg DECIMAL(16,4) NOT NULL
methodology VARCHAR(32) NOT NULL
is_estimate TINYINT(1) NOT NULL DEFAULT 1
leg_sequence SMALLINT NOT NULL DEFAULT 1
leg_description VARCHAR(64) NULL
calculated_at DATETIME NOT NULL
calculated_by VARCHAR(32) NOT NULL DEFAULT 'SYSTEM'
```

---

## Back-Office Pages

| Page | Route Name | Path | Purpose |
|---|---|---|---|
| Emission Factors | `library-emission-factor` | `/library/emission-factor` | Admin CRUD for EF reference values |
| CO₂ Emissions Report | `report-co2-emissions` | `/report/co2-emissions` | Date/mode-filtered emissions report with totals |
| Emissions Panel | _(shipment tab)_ | In `ShipmentDetail.vue` | Per-shipment tab: calculate, view, delete emission records |

### Emission Factors Library

Accessible via **Library → Emission Factors**. Shows all emission factors with mode colour chips. Supports mode filtering. Create/edit form covers all fields including effective date range.

### CO₂ Emissions Report

Accessible via **Reports → CO₂ Emissions**. Filters by transport mode and date range. Shows summary cards for total tonne-km, total CO₂e TTW, and total CO₂e WTW (up to 500 records per page).

### Shipment Detail — CO₂ Tab

The **CO₂** tab (tabler-leaf icon) appears on every shipment:

- Lists emission records for the shipment with per-leg breakdown
- **Auto-Calculate** button: user picks transport mode; distance is auto-looked-up for OCN, or entered manually for other modes
- **Enter Manually** button: for when actual fuel consumption data is available
- Records flagged as estimates show an orange "Est." chip
- Delete individual records if recalculation is needed

---

## Adding Sea Distances

To pre-populate the sea distance table with common trade lanes, POST to `/emissions/sea-distance`. The BO sea-distance admin is exposed via the Library, or you can batch-insert via migration/fixture.

Example common routes:

```json
[
  { "polCode": "CNSHA", "podCode": "DEHAM", "distanceKm": 19550, "viaCanal": "SUEZ" },
  { "polCode": "SGSIN", "podCode": "GBFXT", "distanceKm": 15900, "viaCanal": "SUEZ" },
  { "polCode": "CNSHA", "podCode": "USLAX", "distanceKm": 11000, "viaCanal": null },
  { "polCode": "CNSHA", "podCode": "USNYC", "distanceKm": 19800, "viaCanal": "PANAMA" }
]
```

---

## Files Created

### API (`d:/Projects/make-cargo-client/`)

```
src/Module/Emissions/Entity/
  EmissionFactor.php
  SeaDistance.php
  ShipmentEmission.php

src/Module/Emissions/Repository/
  EmissionFactorRepository.php
  SeaDistanceRepository.php
  ShipmentEmissionRepository.php

src/Module/Emissions/Service/
  EmissionCalculationService.php

src/Module/Emissions/Controller/
  EmissionFactorController.php
  SeaDistanceController.php
  ShipmentEmissionController.php

migrations/mysql/
  Version20260626100000.php  (emission_factor + GLEC v3 seed data)
  Version20260626110000.php  (sea_distance)
  Version20260626120000.php  (shipment_emission)

migrations/sqlite/
  Version20260626100000.php
  Version20260626110000.php
  Version20260626120000.php
```

### Back-Office (`d:/Projects/make-cargo-client-bo/`)

```
src/services/
  EmissionsService.js

src/pages/library/
  emission-factor.vue

src/pages/report/
  co2-emissions.vue

src/views/shipment/
  EmissionsPanel.vue

src/views/shipment/ShipmentDetail.vue    (modified — added EmissionsPanel import + CO₂ tab)
src/config/navigation/index.js           (modified — Emission Factors in Library, CO₂ Emissions in Reports)
```
