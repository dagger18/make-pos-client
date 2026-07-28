# Vessel & Flight Schedule Feature Guide

## Overview

This feature adds ocean vessel sailing schedules and air flight schedules to the platform. Operators can search for sailings/flights from within the booking form and auto-populate ETD, ETA, vessel name, voyage number, and cutoff dates. Vessel rolls (when cargo is bumped to a later sailing) are tracked per-shipment with customer notification status.

---

## Architecture

```
Third-party APIs
  ├── SeaRates API (ocean schedules)
  └── AviationStack API (air schedules)
         │
         ▼
Master API (d:\Projects\make-cargo)
  ├── VesselSailing, Vessel, CutoffRule entities (MySQL)
  ├── FlightSchedule, FlightItinerary entities (MySQL)
  ├── ScheduleService — fetches, caches (24h TTL), calculates cutoffs
  └── Public endpoints:
        GET /api/public/vessel-sailing/search
        GET /api/public/flight-schedule/search
         │
         ▼  (X-Service-Token auth)
Client API (d:\Projects\make-cargo-client)
  ├── MasterSyncService.searchVesselSailings()
  ├── MasterSyncService.searchFlightSchedules()
  ├── VesselSailingController → GET /vessel-sailing/search
  ├── FlightScheduleController → GET /flight-schedule/search
  ├── VesselRollController → GET/POST /vessel-roll, PUT /vessel-roll/{id}/notify
  ├── Booking entity: sailingRef, flightRef fields
  └── VesselRoll entity
         │
         ▼
Client BO (d:\Projects\make-cargo-client-bo)
  ├── BookingForm.vue — "Search Sailing" / "Search Flight" button + picker dialogs
  └── ShipmentDetail.vue — new "Vessel Rolls" tab
```

**Caching:** The master API stores schedules in MySQL with a `fetchedAt` timestamp. Results fresher than 24 hours are returned from DB; older results trigger a fresh fetch from the third-party API. No schedule data is stored in the client API.

**Stub data:** When API keys are not configured, both services return hardcoded demo sailings/flights so the feature works in development without credentials.

---

## Setup

### 1. Run database migrations

**Master API** (`d:\Projects\make-cargo`):
```bash
php bin/console doctrine:migrations:migrate --env=prod
```
New tables: `vessel`, `vessel_sailing`, `cutoff_rule`, `flight_schedule`, `flight_itinerary`

**Client API** (`d:\Projects\make-cargo-client`):
```bash
# MySQL
php bin/console doctrine:migrations:migrate --env=prod

# SQLite (test env)
php bin/console doctrine:migrations:migrate --env=test
```
New columns: `booking.sailing_ref`, `booking.flight_ref`
New table: `vessel_roll`

---

### 2. Configure API keys (optional — stub data works without them)

#### SeaRates (Ocean Schedules)
1. Register at [https://www.searates.com](https://www.searates.com) → Developer → API Access
2. Copy your Bearer token
3. Add to `d:\Projects\make-cargo\.env.local`:
```dotenv
SEARATES_API_KEY=your_bearer_token_here
```

#### AviationStack (Flight Schedules)
1. Register at [https://aviationstack.com](https://aviationstack.com) → free tier gives 100 calls/month
2. Copy your access key
3. Add to `d:\Projects\make-cargo\.env.local`:
```dotenv
AVIATIONSTACK_API_KEY=your_access_key_here
```

> **Without keys:** Both services return 2 stub records each (MSC + Maersk for ocean, SQ321 + EK408 for air). This is enough for UI testing without spending API credits.

---

### 3. Verify the master API endpoints work

```bash
# Ocean: replace SGSIN/NLRTM with real port UN/LOCODEs
curl "http://local.maca-land.com/api/public/vessel-sailing/search?pol=SGSIN&pod=NLRTM&etd_from=2026-07-01&etd_to=2026-08-31" \
  -H "X-Service-Token: <token>"

# Air: replace SIN/LHR with IATA airport codes
curl "http://local.maca-land.com/api/public/flight-schedule/search?origin=SIN&destination=LHR&date=2026-07-15" \
  -H "X-Service-Token: <token>"
```

Expected response shape:
```json
{ "list": [{ "carrier": "MSCU", "vessel": "MSC LORETO", "etd": "2026-07-15T00:00:00", ... }] }
```

---

## How It Works

### Booking Form — Sailing Picker

1. Open or create a shipment booking (Shipment → Info → Booking tab)
2. A **Search Sailing** button (ocean) or **Search Flight** button (air) appears above the form
   - Ocean button shows for all modes except `AIR`
   - Air button shows only for `AIR` mode
3. Click the button → a dialog opens with POL/POD pre-filled from the booking's ports
4. Adjust the date and click **Search**
5. Click **Select** on any result → the following fields auto-fill:
   - ETD, ETA
   - Vessel number, mother vessel, mother voyage (ocean) / flight number (air)
   - SI Cutoff, VGM Cutoff, CY Cutoff (ocean) / Doc Cutoff, Cargo Cutoff (air)
   - `sailingRef` / `flightRef` — internal reference stored on the booking for traceability
6. Save the booking normally

### Vessel Roll Recording

1. Open a shipment → **Vessel Rolls** tab
2. Click **Record Roll**
3. Fill in: original sailing ref, original ETD, new sailing ref, new ETD, reason
4. Click **Record Roll** → roll is saved
5. When the customer has been informed, click **Mark Notified** to record the notification timestamp

### Cutoff Rules

Default cutoffs applied when persisting sailings (configurable in the `cutoff_rule` table):
- **CY Cutoff:** ETD − 72 hours
- **SI Cutoff:** ETD − 120 hours
- **VGM Cutoff:** ETD − 96 hours

To add a carrier-specific or port-specific override, insert a row into `cutoff_rule` in the master API's database with the carrier SCAC code and/or POL UN/LOCODE.

---

## Files Changed

### Master API (`d:\Projects\make-cargo`)
| File | Purpose |
|---|---|
| `src/Entity/Vessel.php` | Ship registry (IMO, name, type, TEU) |
| `src/Entity/VesselSailing.php` | Scheduled departure with ETD/ETA/cutoffs |
| `src/Entity/CutoffRule.php` | Configurable cutoff rules |
| `src/Entity/FlightSchedule.php` | Flight with STD/STA/cargo/doc cutoffs |
| `src/Entity/FlightItinerary.php` | Multi-leg air routing |
| `src/Service/SeaRatesService.php` | SeaRates API client + stub fallback |
| `src/Service/AviationStackService.php` | AviationStack API client + stub fallback |
| `src/Service/ScheduleService.php` | Cache-first orchestration + cutoff calculation |
| `src/Controller/Http/VesselSailingController.php` | `GET /api/public/vessel-sailing/search` |
| `src/Controller/Http/FlightScheduleController.php` | `GET /api/public/flight-schedule/search` |
| `migrations/Version20260623150000.php` | vessel, vessel_sailing, cutoff_rule tables |
| `migrations/Version20260623160000.php` | flight_schedule, flight_itinerary tables |

### Client API (`d:\Projects\make-cargo-client`)
| File | Purpose |
|---|---|
| `src/Service/MasterSyncService.php` | Added `searchVesselSailings()`, `searchFlightSchedules()` |
| `src/Controller/Api/VesselSailingController.php` | `GET /vessel-sailing/search` proxy |
| `src/Controller/Api/FlightScheduleController.php` | `GET /flight-schedule/search` proxy |
| `src/Entity/Booking.php` | Added `sailingRef`, `flightRef` fields |
| `src/Entity/VesselRoll.php` | Roll tracking entity |
| `src/Repository/VesselRollRepository.php` | `findByShipment()` |
| `src/Controller/Api/VesselRollController.php` | `GET/POST /vessel-roll`, `PUT /vessel-roll/{id}/notify` |
| `config/serializer_groups/Booking.yaml` | Added `sailingRef`, `flightRef` to list group |
| `config/serializer_groups/VesselRoll.yaml` | New serializer group |
| `migrations/mysql/Version20260624010000.php` | booking.sailing_ref, booking.flight_ref |
| `migrations/mysql/Version20260624020000.php` | vessel_roll table |
| `migrations/sqlite/Version20260624010000.php` | SQLite equivalent |
| `migrations/sqlite/Version20260624020000.php` | SQLite equivalent |

### Client BO (`d:\Projects\make-cargo-client-bo`)
| File | Purpose |
|---|---|
| `src/services/VesselSailingService.js` | `search(pol, pod, etdFrom, etdTo)` |
| `src/services/FlightScheduleService.js` | `search(origin, destination, date)` |
| `src/services/VesselRollService.js` | `list()`, `create()`, `markNotified()` |
| `src/views/shipment/info/BookingForm.vue` | Sailing/flight picker buttons + dialogs |
| `src/views/shipment/VesselRolls.vue` | Vessel roll list + record dialog |
| `src/views/shipment/ShipmentDetail.vue` | Added "Vessel Rolls" tab |
