# Carrier Performance Scoring — Setup & Operations Guide

## Overview

The Carrier Performance Scoring module provides a data-driven way to evaluate how reliably each carrier delivers. Monthly composite scores (0–5 scale, A–F band) are stored in `carrier_performance_score` and surfaced in the BO report page. Cargo claims are tracked in `cargo_claim` and feed directly into the score calculation.

## Architecture

```
cargo_claim (per shipment × carrier)
  └─ claimType: LOSS / DAMAGE / DELAY / SHORT_DELIVERY
  └─ status: OPEN / SETTLED / REJECTED / WITHDRAWN
  └─ transportMode: OCN / AIR / RD

carrier_performance_score (per carrier × year × month × mode)
  └─ raw metrics: sailings, bookings, AP bills, cargo claims, shipments
  └─ calculated rates: onTimeDepPct, onTimeArrPct, scheduleReliabilityPct,
                       bookingAcceptancePct, rateConsistencyPct, claimsPer100
  └─ composite: compositeScore (0–5), scoreBand (A/B/C/D/F)
```

## Score Dimensions and Weights

| Dimension | Weight | Source |
|---|---|---|
| On-time departure | 25% | vessel_sailing (future integration) |
| On-time arrival | 25% | vessel_sailing (future integration) |
| Booking acceptance | 20% | booking table (future integration) |
| Schedule reliability | 15% | vessel_sailing (future integration) |
| Rate consistency | 10% | ap_bill table (future integration) |
| Claims rate | 5% | cargo_claim table ✓ |

**Weight redistribution:** Dimensions without source data have their weight proportionally redistributed to available dimensions. The composite is always computed over the available data — it does not penalise carriers for missing integrations.

## Score Bands

| Band | Score | Meaning | Action |
|---|---|---|---|
| A | 4.5 – 5.0 | Excellent | Preferred carrier, first allocation |
| B | 3.5 – 4.4 | Good | Standard carrier, second allocation |
| C | 2.5 – 3.4 | Average | Use when A/B unavailable |
| D | 1.5 – 2.4 | Poor | Escalate to procurement review |
| F | 0.0 – 1.4 | Failing | Suspend pending improvement plan |

## Running the Compute Command

Run monthly (typically on the 1st of each month for the previous month):

```bash
# Compute previous month for all modes (OCN, AIR, RD)
php bin/console app:carrier:compute-scores

# Specific period and mode
php bin/console app:carrier:compute-scores --year=2026 --month=5 --mode=OCN
```

Carriers with fewer than 5 distinct shipments in the period are skipped ("insufficient data").

### Scheduling (cron)

Add to your server's crontab to run on the 2nd of each month at 01:00:

```
0 1 2 * * cd /var/www/api && php bin/console app:carrier:compute-scores >> /var/log/carrier-scores.log 2>&1
```

## API Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `GET /api/carrier-performance/scores?year=Y&month=M&mode=OCN` | GET | All scores for a period |
| `GET /api/carrier-performance/{id}/latest?mode=OCN` | GET | Most recent score for a carrier |
| `GET /api/carrier-performance/{id}/history?mode=OCN` | GET | Last 24 months for a carrier |
| `GET /api/cargo-claim?carrierId=X` | GET | Claims for a carrier |
| `POST /api/cargo-claim` | POST | Create claim |
| `PUT /api/cargo-claim/{id}` | PUT | Update claim |
| `DELETE /api/cargo-claim/{id}` | DELETE | Delete claim |

### POST /api/cargo-claim body

```json
{
  "shipmentId": 1234,
  "carrierId": 56,
  "transportMode": "OCN",
  "claimType": "DAMAGE",
  "claimDate": "2026-05-14",
  "claimAmount": 5000.00,
  "currency": "USD",
  "description": "Damaged carton on arrival",
  "status": "OPEN",
  "settlementAmount": null,
  "settledAt": null
}
```

## Claim Types

| Value | Meaning |
|---|---|
| `LOSS` | Cargo entirely lost |
| `DAMAGE` | Cargo arrived damaged |
| `DELAY` | Late delivery caused financial loss |
| `SHORT_DELIVERY` | Partial delivery — missing items |

## Extending the Score with Sailing/Booking Data

When `vessel_sailing` and `booking` tables are added to the system, extend `ComputeCarrierScoresCommand` to query them:

1. Query `vessel_sailing` for the period filtered by carrier → populate `sailingsTotal`, `sailingsOnTimeDep`, `sailingsOnTimeArr`, `sailingsCancelled`
2. Set `onTimeDepPct`, `onTimeArrPct`, `scheduleReliabilityPct` on the score entity
3. Query `booking` for the period → populate `bookingsTotal`, `bookingsConfirmed`, `bookingsRolled`, set `bookingAcceptancePct`
4. Query `ap_bill` for AP variance → populate `apBillsTotal`, `apBillsWithinTolerance`, set `rateConsistencyPct`
5. Pass all six values to `CarrierPerformanceScoreService::computeComposite()` — weight redistribution handles the migration transparently

## BO Pages

- **Reports → Carrier Performance:** Period + mode selector, sortable table with colour-coded bands and all dimension percentages
- **Library → Cargo Claims:** Carrier-filtered list with CRUD dialog for managing claims
