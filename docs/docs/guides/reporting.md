# Reporting — Setup & Operations Guide

## Overview

The reporting module provides operational and financial analytics across KPIs, customer
profitability, top trade lanes, milestone exceptions, and a live operational dashboard.
All reports use read-only DBAL raw SQL against existing tables — no additional migrations required.

## Architecture

```
KpiRepository (DBAL raw SQL)
  └─ getOnTimeRate(from, to)           → {total, on_time, delayed, on_time_pct}
  └─ getConversionRate(from, to)       → [{month, total, converted, rejected, conversion_pct}]
  └─ getOperatorProductivity(from, to) → [{first_name, last_name, jobs_count, revenue_base, cost_base, profit_base}]
  └─ getDso(from, to)                  → float (avg days)

ReportRepository (DBAL raw SQL)
  └─ getCustomerProfitability(from, to) → [{client_name, jobs_count, revenue_base, cost_base, profit_base, margin_pct}]
  └─ getTopLanes(from, to, limit)       → [{origin, destination, shipments, revenue_base, profit_base}]
  └─ getExceptions(from, to)            → [{shipment_code, milestone_code, planned_date, actual_date, exception_hours, ...}]
  └─ getOperationalDashboard()          → {active_count, exception_count, shipments: [...]}
```

## API Endpoints

All endpoints require `ROLE_USER`.

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/report/kpi/on-time-rate` | GET | Shipment on-time delivery rate |
| `GET /api/report/kpi/conversion-rate` | GET | Quote-to-booking conversion by month |
| `GET /api/report/kpi/operator-productivity` | GET | Closed jobs + revenue per account manager |
| `GET /api/report/kpi/dso` | GET | Average days sales outstanding |
| `GET /api/report/analytics/customer-profitability` | GET | Revenue/cost/profit by client |
| `GET /api/report/analytics/top-lanes` | GET | Top origin-destination pairs by volume |
| `GET /api/report/analytics/exceptions` | GET | Milestone exceptions list |
| `GET /api/report/analytics/operational-dashboard` | GET | Live active shipment summary |

### Query Parameters

All date-range endpoints accept:
- `from` (YYYY-MM-DD, default: first day of current month)
- `to` (YYYY-MM-DD, default: today)

`/top-lanes` additionally accepts:
- `limit` (integer, default: 20, max: 100)

### Example: On-Time Rate

```json
GET /api/report/kpi/on-time-rate?from=2026-01-01&to=2026-06-30

{
  "total": 142,
  "on_time": 118,
  "delayed": 24,
  "on_time_pct": 83.1
}
```

### Example: Operational Dashboard

```json
GET /api/report/analytics/operational-dashboard

{
  "active_count": 23,
  "exception_count": 3,
  "shipments": [
    {
      "id": 101,
      "code": "SHP-2026-0101",
      "status": "AC",
      "origin": "Shanghai",
      "destination": "Los Angeles",
      "etd": "2026-06-25T00:00:00",
      "eta": "2026-07-15T00:00:00",
      "operator_first_name": "John",
      "operator_last_name": "Smith",
      "exception_count": 1
    }
  ]
}
```

## Back-Office Pages

| Page | Route | Description |
|------|-------|-------------|
| Dashboard | `/` | Operational dashboard — active shipments, ETD/ETA, exceptions |
| KPI Dashboard | `/report/kpi` | On-time rate, DSO, conversion by month, operator productivity |
| Customer Profitability | `/report/customer-profitability` | Revenue / cost / margin per client |
| Top Lanes | `/report/top-lanes` | Top 20 origin-destination pairs by shipment count |
| Exception Report | `/report/exception` | All milestone exceptions with delay hours |

All pages except Dashboard have a date-range picker and "Run Report" button.
The Dashboard auto-loads on mount and has a manual refresh button.

## Metric Definitions

| Metric | Definition |
|--------|-----------|
| **On-Time Rate** | % of VESSEL_DEPARTED / FLIGHT_DEPARTED milestones where `actual_date <= planned_date` |
| **Conversion Rate** | % of quotes with status `B` (Booked) out of all quotes created in the period |
| **Operator Productivity** | Completed shipments (`status = CO`) grouped by account manager, using `updated_at` as close date |
| **DSO** | `AVG(DATEDIFF(receipt.created_date, invoice.created_date))` for AR invoices (type=ID) linked to receipts (type=RPT) via `parent_note_id` |
| **Customer Profitability** | `SUM(ID amount) - SUM(IC amount)` per client on completed shipments, base currency |
| **Top Lanes** | Completed shipments grouped by `(port_loading_id, port_discharge_id)` |
| **Exceptions** | Shipment milestones where `is_exception = true` (auto-set when `actual_date > planned_date`) |

## Files Created

### Client API (`make-cargo-client`)

| File | What it does |
|------|-------------|
| `src/Repository/KpiRepository.php` | KPI queries (on-time, conversion, productivity, DSO) |
| `src/Repository/ReportRepository.php` | Analytics queries (profitability, top lanes, exceptions, dashboard) |
| `src/Controller/Api/KpiController.php` | 4 GET endpoints under `/report/kpi/` |
| `src/Controller/Api/ReportAnalyticsController.php` | 4 GET endpoints under `/report/analytics/` |

### Client BO (`make-cargo-client-bo`)

| File | What it does |
|------|-------------|
| `src/services/ReportAnalyticsService.js` | All 8 API call wrappers |
| `src/pages/dashboard.vue` | Replaced placeholder — live operational shipment table |
| `src/pages/report/kpi.vue` | KPI dashboard page |
| `src/pages/report/customer-profitability.vue` | Customer profitability table |
| `src/pages/report/top-lanes.vue` | Top lanes table |
| `src/pages/report/exception.vue` | Exceptions table |
| `src/config/navigation/index.js` | Added 4 nav items under Reports |
