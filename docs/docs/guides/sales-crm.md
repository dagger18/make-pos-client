# Sales CRM Guide

A complete sales pipeline, lead management, activity tracking, target setting, and commission tracking module for freight forwarders.

---

## Architecture

The CRM module extends `src/Module/Crm/` with five new entities:

| Entity | Table | Purpose |
|---|---|---|
| `CrmLead` | `crm_lead` | Prospective clients not yet in the system |
| `CrmOpportunity` | `crm_opportunity` | Trackable deals in the sales pipeline |
| `CrmActivity` | `crm_activity` | Log of sales interactions (calls, emails, meetings) |
| `SalesTarget` | `sales_target` | Revenue / volume goals per rep per period |
| `SalesCommission` | `sales_commission` | Per-shipment commission records with approval workflow |

---

## Lead Management

Leads represent companies that are not yet clients. They move through statuses: `NEW → CONTACTED → QUALIFIED → CONVERTED / DEAD`.

### API Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/crm/lead` | List leads (optional `?status=`, `?assigneeId=`) |
| GET | `/crm/lead/{id}` | Get single lead |
| POST | `/crm/lead` | Create lead |
| PUT | `/crm/lead/{id}` | Update lead |
| POST | `/crm/lead/{id}/convert` | Mark as CONVERTED, optionally link to existing client |
| DELETE | `/crm/lead/{id}` | Delete lead |

### Lead Fields

| Field | Type | Notes |
|---|---|---|
| `companyName` | string | Required |
| `contactName` | string | Primary contact |
| `contactEmail` | string | |
| `contactPhone` | string | |
| `countryCode` | string(2) | ISO 2-letter country |
| `industry` | string | |
| `estimatedVolume` | string | Free text e.g. "5-10 TEU/month" |
| `primaryMode` | enum | OCN / AIR / RD / RAL |
| `primaryTrade` | string | e.g. "Asia–Europe" |
| `source` | enum | REFERRAL / LINKEDIN / COLD_CALL / TRADE_SHOW / INBOUND |
| `status` | enum | NEW / CONTACTED / QUALIFIED / CONVERTED / DEAD |
| `assignedToId` | int | User FK |
| `notes` | text | |

### Convert Lead

```http
POST /crm/lead/{id}/convert
Content-Type: application/json

{ "clientId": null }
```

Sets `status = CONVERTED`, `convertedAt = now`. Pass `clientId` to link to an existing client.

---

## Opportunity Pipeline

Opportunities track active deals with probabilities and weighted revenue.

### Pipeline Stages

| Stage | Default Probability |
|---|---|
| PROSPECTING | 10% |
| QUALIFICATION | 25% |
| PROPOSAL | 50% |
| NEGOTIATION | 75% |
| CLOSED_WON | 100% |
| CLOSED_LOST | 0% |

**Weighted Revenue** = `estimatedRevenue × probabilityPct / 100`

### API Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/crm/opportunity` | List opportunities (optional `?pipeline=1`, `?stage=`, `?assigneeId=`) |
| GET | `/crm/opportunity/pipeline-summary` | Stage summary with counts and weighted revenue |
| GET | `/crm/opportunity/{id}` | Get single opportunity |
| POST | `/crm/opportunity` | Create opportunity |
| PUT | `/crm/opportunity/{id}` | Update opportunity |
| POST | `/crm/opportunity/{id}/close` | Close (WON or LOST) |
| DELETE | `/crm/opportunity/{id}` | Delete opportunity |

### Pipeline Summary Response

```json
[
  { "stage": "PROSPECTING", "count": 3, "total_revenue": 45000, "weighted_revenue": 4500 },
  { "stage": "QUALIFICATION", "count": 2, "total_revenue": 80000, "weighted_revenue": 20000 }
]
```

### Close Opportunity

```http
POST /crm/opportunity/{id}/close
Content-Type: application/json

{
  "stage": "CLOSED_LOST",
  "lossReason": "PRICE"
}
```

Valid `stage`: `CLOSED_WON` or `CLOSED_LOST`.

`lossReason` options: `PRICE / SERVICE / RELATIONSHIP / CAPACITY / OTHER`

`winReason` options: `PRICE / SERVICE / RELATIONSHIP / SPEED / REPUTATION / OTHER`

### Opportunity Fields

| Field | Type | Notes |
|---|---|---|
| `title` | string | Deal name |
| `leadId` | int | Optional link to CrmLead |
| `clientId` | int | Optional link to existing Client |
| `transportMode` | enum | OCN / AIR / RD / RAL / COU / MMD |
| `polName` | string | Origin port/place name |
| `podName` | string | Destination port/place name |
| `estimatedVolume` | decimal | Numeric volume |
| `volumeUom` | enum | TEU / TON / CBM / SHIPMENTS |
| `estimatedRevenue` | decimal | Expected deal revenue |
| `currency` | string(3) | ISO currency code |
| `stage` | enum | See stages above |
| `probabilityPct` | int | 0–100 |
| `expectedClose` | date | |
| `assignedToId` | int | User FK |
| `competitor` | string | Main competing forwarder |
| `notes` | text | |

---

## Activity Log

Activities record all sales interactions linked to opportunities.

### API Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/crm/activity` | List activities (optional `?opportunityId=`, `?assigneeId=`, `?from=`, `?to=`) |
| POST | `/crm/activity` | Log new activity |
| PUT | `/crm/activity/{id}` | Edit activity |
| DELETE | `/crm/activity/{id}` | Delete activity |

### Activity Fields

| Field | Type | Values |
|---|---|---|
| `activityType` | enum | CALL / EMAIL / MEETING / VISIT / QUOTE_SENT / FOLLOW_UP |
| `subject` | string | |
| `description` | text | Notes / conversation summary |
| `outcome` | enum | POSITIVE / NEUTRAL / NEGATIVE / NO_ANSWER |
| `nextAction` | string | Description of next step |
| `nextActionDate` | date | When to follow up |
| `performedById` | int | User FK |
| `performedAt` | datetime | When the activity happened |
| `opportunityId` | int | Optional link to opportunity |
| `clientId` | int | Optional link to client |

---

## Sales Targets

Targets are set per sales rep for a year or specific month.

### API Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/crm/sales-target?year=2026` | List targets for a year |
| POST | `/crm/sales-target` | Create target |
| PUT | `/crm/sales-target/{id}` | Update target |
| DELETE | `/crm/sales-target/{id}` | Delete target |

### Target Fields

| Field | Type | Notes |
|---|---|---|
| `salesRepId` | int | User FK |
| `periodYear` | int | e.g. 2026 |
| `periodMonth` | int | 1–12, or null for annual |
| `targetType` | enum | REVENUE / PROFIT / NEW_CUSTOMERS / QUOTES_SENT |
| `targetValue` | decimal | Goal value |
| `currency` | string(3) | Required for REVENUE / PROFIT targets |
| `branchId` | int | Optional branch scope |

---

## Sales Commissions

Commissions are calculated per shipment and go through an approval workflow: `CALCULATED → APPROVED → PAID`.

### API Endpoints

| Method | Path | Description |
|---|---|---|
| GET | `/crm/commission` | List (optional `?repId=`, `?period=`, `?status=`) |
| POST | `/crm/commission` | Create commission record |
| PUT | `/crm/commission/{id}` | Edit commission |
| POST | `/crm/commission/{id}/approve` | Set status = APPROVED |
| POST | `/crm/commission/{id}/pay` | Set status = PAID |
| DELETE | `/crm/commission/{id}` | Delete |

### Commission Fields

| Field | Type | Notes |
|---|---|---|
| `salesRepId` | int | User FK |
| `shipmentId` | int | Shipment FK |
| `commissionBasis` | enum | PCT_REVENUE / PCT_PROFIT / FLAT |
| `commissionRate` | decimal(6,4) | e.g. 0.05 = 5% |
| `baseAmount` | decimal | Amount the rate applies to |
| `commissionAmount` | decimal | Final commission value |
| `currency` | string(3) | |
| `status` | enum | CALCULATED / APPROVED / PAID |
| `periodMonth` | string | Format: `YYYY-MM` |

---

## Back-Office Pages

| Page | Route Name | Path | Purpose |
|---|---|---|---|
| Leads | `crm-leads` | `/crm/leads` | Lead list with status filter; create/edit; convert |
| Pipeline | `crm-opportunities` | `/crm/opportunities` | Kanban-style summary cards + table; close dialog |
| Activities | `crm-activities` | `/crm/activities` | Activity log with type/rep filter; upcoming action flag |
| Sales Targets | `report-sales-target` | `/report/sales-target` | Year-scoped target management |
| Sales Commissions | `report-sales-commission` | `/report/sales-commission` | Commission list with approve/pay workflow |

### Navigation

The BO navigation has a dedicated **CRM** section (between Providers and Accounting):

```
CRM
  ├─ Leads
  ├─ Pipeline
  └─ Activities
```

Reports section includes **Sales Targets** and **Sales Commissions** entries.

---

## Database Schema

### crm_lead

```sql
id INT PK AUTO_INCREMENT
company_name VARCHAR(200) NOT NULL
contact_name VARCHAR(120)
contact_email VARCHAR(180)
contact_phone VARCHAR(50)
country_code CHAR(2)
industry VARCHAR(100)
estimated_volume VARCHAR(80)
primary_mode VARCHAR(10)
primary_trade VARCHAR(120)
source VARCHAR(30)
status VARCHAR(20) NOT NULL DEFAULT 'NEW'
assigned_to_id INT FK users(id)
created_by_id INT FK users(id)
converted_client_id INT FK clients(id) NULL
converted_at DATETIME NULL
notes TEXT NULL
created_at DATETIME NOT NULL
```

### crm_opportunity

```sql
id INT PK AUTO_INCREMENT
lead_id INT FK crm_lead(id) NULL
client_id INT FK clients(id) NULL
title VARCHAR(200) NOT NULL
transport_mode VARCHAR(10)
service_type VARCHAR(50)
pol_name VARCHAR(120)
pod_name VARCHAR(120)
estimated_volume DECIMAL(10,2)
volume_uom VARCHAR(20) DEFAULT 'TEU'
estimated_revenue DECIMAL(14,2)
currency CHAR(3) DEFAULT 'USD'
stage VARCHAR(20) NOT NULL DEFAULT 'PROSPECTING'
probability_pct SMALLINT NOT NULL DEFAULT 10
expected_close DATE
assigned_to_id INT FK users(id)
competitor VARCHAR(120)
loss_reason VARCHAR(20)
win_reason VARCHAR(20)
quote_id INT NULL  -- plain column, no FK
notes TEXT
created_at DATETIME NOT NULL
updated_at DATETIME
closed_at DATETIME
```

### crm_activity

```sql
id INT PK AUTO_INCREMENT
opportunity_id INT FK crm_opportunity(id) ON DELETE CASCADE NULL
client_id INT FK clients(id) NULL
activity_type VARCHAR(20) NOT NULL
subject VARCHAR(200)
description TEXT
outcome VARCHAR(20)
next_action VARCHAR(200)
next_action_date DATE
performed_by_id INT FK users(id)
performed_at DATETIME NOT NULL
```

### sales_target

```sql
id INT PK AUTO_INCREMENT
sales_rep_id INT FK users(id) ON DELETE CASCADE NOT NULL
period_year SMALLINT NOT NULL
period_month SMALLINT NULL
target_type VARCHAR(20) NOT NULL
target_value DECIMAL(14,2) NOT NULL
currency CHAR(3)
branch_id INT FK branches(id) NULL
created_at DATETIME NOT NULL
```

### sales_commission

```sql
id INT PK AUTO_INCREMENT
sales_rep_id INT FK users(id) ON DELETE CASCADE NOT NULL
shipment_id INT FK shipments(id) ON DELETE CASCADE NOT NULL
commission_basis VARCHAR(20) NOT NULL
commission_rate DECIMAL(6,4)
base_amount DECIMAL(14,2) NOT NULL
commission_amount DECIMAL(14,2) NOT NULL
currency CHAR(3) NOT NULL
status VARCHAR(20) NOT NULL DEFAULT 'CALCULATED'
period_month VARCHAR(7)
created_at DATETIME NOT NULL
```

---

## Files Created

### API (`d:/Projects/make-cargo-client/`)

```
src/Module/Crm/Entity/
  CrmLead.php
  CrmOpportunity.php
  CrmActivity.php
  SalesTarget.php
  SalesCommission.php

src/Module/Crm/Repository/
  CrmLeadRepository.php
  CrmOpportunityRepository.php
  CrmActivityRepository.php
  SalesTargetRepository.php
  SalesCommissionRepository.php

src/Module/Crm/Controller/
  CrmLeadController.php
  CrmOpportunityController.php
  CrmActivityController.php
  SalesTargetController.php
  SalesCommissionController.php

migrations/
  Version20260626050000.php  (crm_lead — MySQL)
  Version20260626060000.php  (crm_opportunity — MySQL)
  Version20260626070000.php  (crm_activity — MySQL)
  Version20260626080000.php  (sales_target — MySQL)
  Version20260626090000.php  (sales_commission — MySQL)
  (+ SQLite equivalents in SqlEngineMigrations namespace)
```

### Back-Office (`d:/Projects/make-cargo-client-bo/`)

```
src/services/
  SalesCrmService.js

src/pages/crm/
  leads.vue
  opportunities.vue
  activities.vue

src/pages/report/
  sales-target.vue
  sales-commission.vue

src/config/navigation/index.js  (modified — CRM section + report entries)
```
