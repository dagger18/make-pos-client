# Detention & Demurrage — Setup & Operations Guide

## Overview

The D&D module tracks per-container detention and demurrage exposure for ocean shipments.
It stores tiered rate agreements per carrier/port, calculates accruing charges daily, and
provides a dashboard for monitoring outstanding exposure.

## Architecture

```
FreeTimeAgreement (library)
  └─ Carrier + optional Port + rate tiers (JSON)
  └─ direction: IMPORT | EXPORT
  └─ freeType: DETENTION | DEMURRAGE | COMBINED

ContainerDdTracking (per shipment container)
  └─ links to Shipment (CASCADE delete)
  └─ links to FreeTimeAgreement (nullable)
  └─ freeStartDate / freeEndDate / freeDays
  └─ accrued_amount updated nightly by RunDdAccrualCommand

DdCalculatorService
  └─ calculateCharge(rateTiers, chargeableDays) → float
  └─ computeChargeableDays(freeEndDate, asOf) → int
  └─ updateAccrual(record, asOf) → void
  └─ finalise(record, returnDate) → void (sets isFinal=true)

RunDdAccrualCommand (app:dd:run-accrual — daily cron)
  └─ finds all is_final=false WHERE free_end_date < today
  └─ calls DdCalculatorService::updateAccrual per record
  └─ bulk flushes
```

## Rate Tier Format

`rate_tiers` is a JSON array stored in `free_time_agreement.rate_tiers`:

```json
[
  { "from_day": 1,  "to_day": 5,   "rate_per_day": 50 },
  { "from_day": 6,  "to_day": 10,  "rate_per_day": 75 },
  { "from_day": 11, "to_day": null, "rate_per_day": 120 }
]
```

- `to_day: null` means open-ended (applies to all remaining days).
- `from_day` / `to_day` are **1-indexed** counting from the first chargeable day (day after free period ends).

## D&D Types

| Value | Meaning |
|-------|---------|
| `DETENTION` | Charge for keeping the container at your premises |
| `DEMURRAGE` | Charge for keeping the container at the port/terminal |
| `COMBINED` | Single agreement covering both |

## Running the Accrual Command

```bash
# Run manually
php bin/console app:dd:run-accrual

# Cron (every night at 03:00)
0 3 * * * /path/to/project/bin/console app:dd:run-accrual >> /var/log/dd-accrual.log 2>&1
```

The command:
1. Queries all `container_dd_tracking` rows where `is_final = 0` AND `free_end_date < TODAY`
2. For each record, calls `DdCalculatorService::updateAccrual()` with today's date
3. Updates `chargeable_days`, `accrued_amount`, `last_accrual_date`
4. Bulk-flushes all changes

## API Endpoints

### Free Time Agreements

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/free-time-agreement` | GET | List all (optionally `?carrierId=X`) |
| `GET /api/free-time-agreement/{id}` | GET | Get one |
| `POST /api/free-time-agreement` | POST | Create |
| `PUT /api/free-time-agreement/{id}` | PUT | Update |
| `DELETE /api/free-time-agreement/{id}` | DELETE | Delete |

**POST/PUT body example:**
```json
{
  "carrierId": 42,
  "portId": null,
  "direction": "IMPORT",
  "containerType": "40HC",
  "freeType": "DETENTION",
  "freeDays": 7,
  "rateTiers": [
    { "from_day": 1, "to_day": 5, "rate_per_day": 50 },
    { "from_day": 6, "to_day": null, "rate_per_day": 100 }
  ],
  "currency": "USD",
  "effectiveFrom": "2026-01-01",
  "effectiveTo": null
}
```

### Container D&D Tracking

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/dd/dashboard` | GET | All accruing records, ordered by accrued amount |
| `GET /api/dd/shipment/{id}` | GET | D&D records for one shipment |
| `POST /api/dd/shipment/{id}` | POST | Create D&D record for a shipment container |
| `PUT /api/dd/{id}` | PUT | Update dates / currency / FTA link |
| `POST /api/dd/{id}/return` | POST | Record empty return (finalises accrual) |
| `POST /api/dd/{id}/dispute` | POST | Mark record as disputed |
| `DELETE /api/dd/{id}` | DELETE | Delete record |

**POST /api/dd/shipment/{id} body:**
```json
{
  "containerNumber": "MSKU1234567",
  "ddType": "DETENTION",
  "freeTimeAgreementId": 5,
  "freeStartDate": "2026-06-01",
  "freeEndDate": "2026-06-07",
  "freeDays": 7,
  "currency": "USD"
}
```

**POST /api/dd/{id}/return body:**
```json
{ "returnDate": "2026-06-15" }
```

**POST /api/dd/{id}/dispute body:**
```json
{ "reason": "Carrier miscounted free days" }
```

## Back-Office Features

### Library → Free Time Agreements

- Lists all free-time agreements grouped by carrier
- Add/Edit dialog with dynamic rate-tier rows (add/remove tiers inline)
- Container type can be left blank (= applies to all types)
- Port can be left blank (= applies to all ports for that carrier)

### Shipment Detail → D&D Tab

Each shipment has a **D&D** tab showing containers tracked for that shipment.

- **Add D&D Record** — opens a dialog to create a tracking record for a container on this shipment. Select a Free Time Agreement to auto-fill Free Days and Currency. The Free End Date is computed as `Free Start Date + Free Days − 1`.
- **Edit** (pencil icon) — update dates or FTA while the record is not yet final.
- **Record Return** (checkbox icon) — enter the actual return date; the record is finalised and the final chargeable amount is locked.
- **Mark Disputed** (triangle icon) — flag the record with a reason. Accrual continues even when disputed.
- **Delete** (trash icon) — remove the tracking record entirely.

### Reports → D&D Dashboard

- Shows all accruing (non-final) D&D records sorted by `accrued_amount` DESC
- "Return" button opens a date-picker dialog → calls `/dd/{id}/return` → marks `isFinal=true`
- "Dispute" button opens a reason dialog → calls `/dd/{id}/dispute` → shows yellow chip
- Disputed rows are still accrued nightly until finalised

## Database Tables

### `free_time_agreement`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `carrier_id` | INT FK | `partner.id` ON DELETE CASCADE |
| `port_id` | INT FK | `port.id` ON DELETE SET NULL; null = any port |
| `direction` | VARCHAR(16) | `IMPORT` or `EXPORT` |
| `container_type` | VARCHAR(8) | `20DC`, `40HC`, etc.; null = any type |
| `free_type` | VARCHAR(16) | `DETENTION`, `DEMURRAGE`, `COMBINED` |
| `free_days` | SMALLINT | Number of free days included |
| `rate_tiers` | JSON | Array of `{from_day, to_day, rate_per_day}` |
| `currency` | VARCHAR(3) | ISO 4217 |
| `effective_from` | DATE | |
| `effective_to` | DATE | null = open-ended |

### `container_dd_tracking`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `shipment_id` | INT FK | `shipment.id` ON DELETE CASCADE |
| `container_number` | VARCHAR(32) | e.g. `MSKU1234567` |
| `free_time_agreement_id` | INT FK | nullable; `free_time_agreement.id` ON DELETE SET NULL |
| `dd_type` | VARCHAR(16) | `DETENTION`, `DEMURRAGE`, `COMBINED` |
| `free_start_date` | DATE | First day of free period |
| `free_end_date` | DATE | Last day of free period |
| `free_days` | SMALLINT | Snapshot of free days at creation |
| `actual_return_date` | DATE | Set when container is returned |
| `days_used` | SMALLINT | Total calendar days used |
| `chargeable_days` | SMALLINT | Days beyond free period (updated nightly) |
| `accrued_amount` | NUMERIC(20,6) | Updated nightly by accrual command |
| `currency` | VARCHAR(3) | |
| `is_final` | BOOL | True once container returned or manually closed |
| `last_accrual_date` | DATE | When accrual was last computed |
| `is_invoiced` | BOOL | Set externally when included in an invoice |
| `is_disputed` | BOOL | |
| `dispute_reason` | TEXT | |

## Files Created / Modified

### Client API (`make-cargo-client`)

| File | What |
|------|------|
| `migrations/mysql/Version20260624270000.php` | New — `free_time_agreement` table |
| `migrations/sqlite/Version20260624270000.php` | New — SQLite |
| `migrations/mysql/Version20260624280000.php` | New — `container_dd_tracking` table |
| `migrations/sqlite/Version20260624280000.php` | New — SQLite |
| `src/Entity/FreeTimeAgreement.php` | New |
| `src/Repository/FreeTimeAgreementRepository.php` | New |
| `src/Entity/ContainerDdTracking.php` | New |
| `src/Repository/ContainerDdTrackingRepository.php` | New |
| `src/Service/DdCalculatorService.php` | New |
| `src/Controller/Api/FreeTimeAgreementController.php` | New |
| `src/Controller/Api/ContainerDdController.php` | New |
| `src/Command/RunDdAccrualCommand.php` | New |

### Client BO (`make-cargo-client-bo`)

| File | What |
|------|------|
| `src/services/DdService.js` | New |
| `src/pages/library/free-time-agreement.vue` | New |
| `src/pages/report/dd-dashboard.vue` | New |
| `src/views/shipment/DdPanel.vue` | New — per-shipment D&D tracking panel |
| `src/views/shipment/ShipmentDetail.vue` | Modified — added D&D tab |
| `src/config/navigation/index.js` | Added Library + Reports nav entries |
