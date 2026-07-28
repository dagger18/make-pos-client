# Audit & Compliance Guide

Covers the system audit log, sanctions screening, GDPR erasure, and data retention policy management.

---

## Architecture

| Component | Location |
|---|---|
| Entities | `src/Module/Compliance/Entity/` |
| Repositories | `src/Module/Compliance/Repository/` |
| Services | `src/Module/Compliance/Service/` |
| Controllers | `src/Module/Compliance/Controller/` |
| MySQL migrations | `migrations/mysql/Version202606261[3-5]0000.php` |
| SQLite migrations | `migrations/sqlite/Version202606261[3-5]0000.php` |

### Entities

| Entity | Table | Purpose |
|---|---|---|
| `SystemAuditLog` | `system_audit_log` | Insert-only event log |
| `SanctionsList` | `sanctions_list` | OFAC/UN/EU/UK/Custom watchlists |
| `DataRetentionPolicy` | `data_retention_policy` | Legal retention schedule per data category |

---

## System Audit Log

### Overview

An **insert-only** immutable record of system events. No `UPDATE` or `DELETE` is ever applied to this table.

### Event taxonomy

Events follow a dotted prefix taxonomy:

| Prefix | Examples |
|---|---|
| `AUTH.` | `AUTH.LOGIN`, `AUTH.LOGOUT`, `AUTH.TOKEN_REFRESH`, `AUTH.LOGIN_FAILED` |
| `PERMISSION.` | `PERMISSION.DENIED` |
| `DATA.` | `DATA.READ`, `DATA.CREATED`, `DATA.UPDATED`, `DATA.DELETED` |
| `FINANCIAL.` | `FINANCIAL.INVOICE_ISSUED`, `FINANCIAL.PAYMENT_RECEIVED` |
| `COMPLIANCE.` | `COMPLIANCE.SANCTIONS_CHECK`, `COMPLIANCE.GDPR_REQUEST`, `COMPLIANCE.DATA_ERASED` |

### Result values

`SUCCESS` · `FAILURE` · `BLOCKED`

### Writing an audit event

Inject `AuditService` and call `log()` or `logFromRequest()`:

```php
use App\Module\Compliance\Service\AuditService;

// From a controller with access to the Symfony Request object:
$this->auditService->logFromRequest(
    request:    $request,
    eventType:  'DATA.UPDATED',
    actorType:  'USER',
    actorId:    $user->getId(),
    actorEmail: $user->getEmail(),
    objectType: 'Shipment',
    objectId:   $shipment->getId(),
    objectRef:  $shipment->getCode(),
    detail:     ['field' => 'status', 'from' => 'DRAFT', 'to' => 'CONFIRMED'],
    result:     'SUCCESS',
);
```

The `logFromRequest()` method automatically extracts `actorIp` (handles `X-Forwarded-For`), `actorUserAgent`, and `requestId` from the request.

### API endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/compliance/audit-log` | List log entries with filters |
| `GET` | `/compliance/audit-log/dashboard` | 30-day totals + 12-month stats |
| `POST` | `/compliance/audit-log` | Write a manual log entry |

#### Query parameters for `GET /compliance/audit-log`

| Param | Type | Description |
|---|---|---|
| `eventType` | string | Prefix filter (e.g. `COMPLIANCE`) |
| `actorEmail` | string | Actor email filter |
| `result` | string | `SUCCESS` / `FAILURE` / `BLOCKED` |
| `from` | date | Start of date range (inclusive) |
| `to` | date | End of date range (inclusive) |

#### Dashboard response structure

```json
{
  "totals": {
    "total": 1240,
    "auth_events": 312,
    "compliance_events": 48,
    "financial_events": 205,
    "blocked_events": 9,
    "failure_events": 14
  },
  "complianceStats": [
    { "period": "2026-06", "event_type": "COMPLIANCE.SANCTIONS_CHECK", "result": "SUCCESS", "event_count": 12 }
  ]
}
```

---

## Sanctions Screening

### List names

`OFAC_SDN` · `UN_CONSOLIDATED` · `EU_SANCTIONS` · `UK_SANCTIONS` · `CUSTOM`

### Entity types

`INDIVIDUAL` · `ENTITY` · `VESSEL` · `AIRCRAFT`

### How screening works

`SanctionsScreeningService::check(orgName, countryCode)`:

1. Exact match: `LIKE '%name%'` search on `listed_name` and `aliases` JSON array (filtered by `is_active = true` and optionally `country_code`)
2. Fuzzy match: PHP `similar_text()` comparison against all active entries and their aliases; threshold 75%; capped at 10 results sorted by descending score
3. Result status: `CONFIRMED_HIT` (exact matches found) → `POSSIBLE_MATCH` (fuzzy only) → `CLEAR`
4. Audit log: always writes `COMPLIANCE.SANCTIONS_CHECK` event; result is `BLOCKED` for anything other than `CLEAR`

> **Note:** The fuzzy matching iterates all active entries in PHP. For sanctions lists exceeding ~5,000 entries, consider offloading full-text fuzzy search to Elasticsearch or PostgreSQL `pg_trgm`.

### Clearing a hit

When a compliance officer reviews a match and determines it is a false positive, they call:

```
POST /compliance/sanctions/{id}/clear
{ "reason": "Different entity — different DOB and nationality confirmed" }
```

This writes a `COMPLIANCE.SANCTIONS_HIT_CLEARED` audit event with the reason captured in `action_detail`.

### API endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/compliance/sanctions` | List entries (supports `?q=name&listName=OFAC_SDN`) |
| `POST` | `/compliance/sanctions` | Create entry |
| `PUT` | `/compliance/sanctions/{id}` | Update entry |
| `DELETE` | `/compliance/sanctions/{id}` | Delete entry |
| `POST` | `/compliance/sanctions/check` | Run screening check |
| `POST` | `/compliance/sanctions/{id}/clear` | Clear a confirmed hit |

#### Screening request/response

```json
// POST /compliance/sanctions/check
{ "orgName": "IRAN SHIPPING LINES", "countryCode": "IR" }

// Response
{
  "status": "CONFIRMED_HIT",
  "totalChecked": 8543,
  "exactMatches": [
    { "id": 42, "listName": "OFAC_SDN", "listedName": "ISLAMIC REPUBLIC OF IRAN SHIPPING LINES", "countryCode": "IR", "entityType": "ENTITY" }
  ],
  "fuzzyMatches": []
}
```

---

## GDPR Right-to-Erasure

### What gets erased

Erasure is **anonymisation, not deletion**. Financial and operational records are retained per legal obligation (see Data Retention below).

| Field | Erased value |
|---|---|
| `firstName` | `"ERASED"` |
| `lastName` | `"ERASED"` |
| `email` | `"erased_{id}@deleted.invalid"` |
| `phone` | `null` |
| `mobile` | `null` |
| `isActive` | `false` |

All other Contact fields (company, address, shipment parties) are retained.

### Audit trail

Two events are written:

1. `COMPLIANCE.GDPR_REQUEST` (before erasure — records the intent and `requestRef`)
2. `COMPLIANCE.DATA_ERASED` (after erasure — records `erased` and `retained` field lists)

### API endpoints

| Method | Path | Description |
|---|---|---|
| `POST` | `/compliance/gdpr/erase` | Anonymise a contact by email |
| `GET` | `/compliance/gdpr/export/{contactId}` | Export all data held for a contact |

#### Erasure request/response

```json
// POST /compliance/gdpr/erase
{ "email": "john.doe@example.com", "requestRef": "DSR-2026-001" }

// Response
{
  "status": "ERASED",
  "contactId": 1247,
  "erased": ["firstName", "lastName", "email", "phone", "mobile", "isActive"],
  "retained": ["company", "address", "shipmentParties", "financialRecords"]
}
```

---

## Data Retention Policies

Seven default policies are seeded by the migration:

| Category | Years | Legal basis |
|---|---|---|
| `FINANCIAL` | 10 | Tax law |
| `CUSTOMS` | 7 | Customs post-clearance audit |
| `JOB_DATA` | 7 | Contract / liability law |
| `DOCUMENTS` | 7 | Shipping law (BL, transport documents) |
| `PERSONAL` | 3 | GDPR Article 5(1)(e) |
| `AUDIT_LOG` | 7 | Regulatory compliance |
| `MARKETING` | 2 | GDPR Article 6 (consent-based) |

`auto_delete` defaults to `false` — records are not automatically purged. If `auto_delete` is enabled for a category, a scheduled task (cron) should call the appropriate purge logic. No purge command is included in this release.

### API endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/compliance/retention` | List all policies |
| `POST` | `/compliance/retention` | Create a policy |
| `PUT` | `/compliance/retention/{id}` | Update a policy |
| `DELETE` | `/compliance/retention/{id}` | Delete a policy |

---

## Back-Office Pages

| Page | Route name | Purpose |
|---|---|---|
| Audit Log | `report-audit-log` | Filter and browse immutable event log |
| Compliance Dashboard | `report-compliance-dashboard` | 30-day summary + sanctions check + GDPR erasure tool |
| Sanctions List | `library-sanctions-list` | Manage OFAC/UN/EU/UK/Custom watchlist entries |
| Data Retention | `library-data-retention` | Manage legal retention schedules per data category |

---

## Database Migrations

| File | Purpose |
|---|---|
| `Version20260626130000` | Create `system_audit_log` table with indexes on `logged_at`, `actor_email`, `event_type`, `result` |
| `Version20260626140000` | Create `sanctions_list` table with FULLTEXT index on `listed_name` (MySQL only) |
| `Version20260626150000` | Create `data_retention_policy` table with 7 default freight-forwarding retention policies |
