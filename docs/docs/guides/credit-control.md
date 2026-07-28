# Credit Control — Setup & Operations Guide

## Overview

The credit control system monitors client AR exposure, enforces credit limits, auto-escalates
credit status based on overdue invoices, propagates holds to shipments, and surfaces utilisation
data in the back-office UI.

## Architecture

```
AgeingRepository (DBAL raw SQL)
  └─ getClientExposure(clientId, currency) → outstanding float
  └─ getClientsWithOverdueData() → [{client_id, max_days_overdue, outstanding}]

CreditCheckService
  └─ check(Client) → {decision: PASS|WARN|REQUIRE_APPROVAL|HARD_BLOCK, ...}
  └─ recordHistory(...) → CreditLimitHistory (audit trail)

UpdateClientCreditStatusCommand (nightly cron: app:credit-control:update-statuses)
  └─ ON_HOLD when max_days_overdue > 30
  └─ BLOCKED when max_days_overdue > 90
  └─ Skips BLACKLISTED clients

ClientCreditListener (Doctrine postUpdate on Client)
  └─ ON_HOLD/BLOCKED → sets isOnHold=true on all active shipments (holdReason: "CREDIT_HOLD: ...")
  └─ ACTIVE → clears CREDIT_HOLD: holds on shipments
```

## Credit Status Values

| Status | Description |
|--------|-------------|
| `ACTIVE` | Normal — all operations allowed |
| `ON_HOLD` | Soft hold — auto-escalated at >30 days overdue; new quotes require approval |
| `BLOCKED` | Hard block — auto-escalated at >90 days overdue; no new quotes/shipments |
| `BLACKLISTED` | Permanent block — manually set only; never auto-escalated further |

## Credit Check Decision Logic

| Condition | Decision | BO Behaviour |
|-----------|----------|--------------|
| Status is BLOCKED or BLACKLISTED | `HARD_BLOCK` | Error dialog, submission blocked |
| No credit limit configured | `PASS` | Unlimited, no warning |
| Utilisation > 100% | `REQUIRE_APPROVAL` | Warning dialog with "Proceed Anyway" |
| Utilisation ≥ 80% | `WARN` | Proceeds normally (informational) |
| Utilisation < 80% | `PASS` | Proceeds normally |

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/client/{id}/credit-check` | GET | Live credit check result |
| `GET /api/client/{id}/credit-history` | GET | Audit history of status/limit changes |

### Credit Check Response Example

```json
{
  "decision": "WARN",
  "reason": "Outstanding exposure is above 80% of credit limit",
  "exposure": 84500.00,
  "limit": 100000.00,
  "currency": "USD",
  "utilisation": 84.5,
  "available": 15500.00
}
```

## Running the Nightly Command

```bash
# Run manually
php bin/console app:credit-control:update-statuses

# Cron (every night at 02:00)
0 2 * * * /path/to/project/bin/console app:credit-control:update-statuses >> /var/log/credit-control.log 2>&1
```

The command:
1. Queries AR invoices with `DATEDIFF(CURDATE(), due_date) > 0` grouped by client
2. Picks the worst (max days overdue) per client across currencies
3. Escalates to `ON_HOLD` if max days > 30 (skips `BLACKLISTED` clients)
4. Escalates to `BLOCKED` if max days > 90
5. Records each change in `credit_limit_history` with `changeType = AUTO_ESCALATION`
6. Skips clients already at the target status

## Overdue Invoice Notification Rules

Seeded in migrations `Version20260624240000` (Day 7) and `Version20260624260000` (Day 1, 14, 30, 60):

| Rule Key | Trigger | Priority |
|----------|---------|----------|
| `INVOICE_OVERDUE_1D` | 1 day overdue | NORMAL |
| `INVOICE_OVERDUE_7D` | 7 days overdue | HIGH |
| `INVOICE_OVERDUE_14D` | 14 days overdue | HIGH |
| `INVOICE_OVERDUE_30D` | 30 days overdue | URGENT |
| `INVOICE_OVERDUE_60D` | 60 days overdue | URGENT |

These are triggered by `NotificationSchedulerCommand` (`app:notifications:scheduler`) — schedule it daily.

## Manual Credit Status Override (Back-Office)

On any client detail page → General tab → click the pencil icon next to Credit Status.
Select a new status, optionally enter a hold reason, and save.

**Note:** When you save, `ClientCreditListener` fires immediately. Changing to `ON_HOLD` or
`BLOCKED` will flag all active shipments for this client with `isOnHold=true`. Returning to
`ACTIVE` automatically clears any `CREDIT_HOLD:`-prefixed holds.

## Back-Office Features

### Client Detail → General Tab

- **Credit Limit & Period**: existing display
- **Utilisation bar**: live exposure / limit ratio with colour coding
  - Green: < 80%
  - Yellow: 80–100%
  - Red: > 100% or status BLOCKED
- **Available credit**: remaining credit in client's currency
- **Decision chip**: "Credit Blocked", "Over Limit — Requires Approval", "Approaching Limit"

### Client Detail → Credit History Tab (5th tab)

Timeline of all status and limit changes, showing:
- Change type (Manual / Limit Change / Auto Escalation)
- Status transition (old → new)
- Limit change amounts
- Reason text
- Who triggered it (null = automated)

### Quote Creation

1. When a client is selected, the BO calls `/client/{id}/credit-check` in the background.
2. On form submit, the stored check result is evaluated:
   - `HARD_BLOCK` → error dialog appears; submission blocked
   - `REQUIRE_APPROVAL` → warning dialog with "Proceed Anyway" button; user can override
   - `PASS` / `WARN` → proceeds normally

### Shipment List

"ON HOLD" badge chip appears for any shipment where `isOnHold = true`.

## Database Tables

### `credit_limit_history`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | Auto-increment |
| `client_id` | INT FK | References `partner.id` ON DELETE CASCADE |
| `changed_by_id` | INT FK | References `user.id` ON DELETE SET NULL; null = automated |
| `change_type` | VARCHAR(32) | `STATUS_CHANGE`, `LIMIT_CHANGE`, `AUTO_ESCALATION` |
| `old_status` | VARCHAR(16) | CreditStatus enum value or null |
| `new_status` | VARCHAR(16) | CreditStatus enum value or null |
| `old_limit_amount` | DECIMAL(15,4) | null if not a limit change |
| `new_limit_amount` | DECIMAL(15,4) | null if not a limit change |
| `currency` | VARCHAR(8) | Credit limit currency |
| `reason` | TEXT | Freeform or auto-generated message |
| `created_date` | DATETIME | Set by EntityDateTimeAbleTrait |

## Files Modified / Created

### Client API (`make-cargo-client`)

| File | What changed |
|------|-------------|
| `src/Entity/CreditLimitHistory.php` | New — audit entity |
| `src/Repository/CreditLimitHistoryRepository.php` | New — findForClient, save |
| `src/Repository/AgeingRepository.php` | Added getClientExposure, getClientsWithOverdueData |
| `src/Service/CreditCheckService.php` | New — decision engine + history recorder |
| `src/Controller/Api/ClientController.php` | Added /credit-check, /credit-history endpoints |
| `src/Command/UpdateClientCreditStatusCommand.php` | New — nightly escalation command |
| `src/EventListener/ClientCreditListener.php` | New — shipment hold propagation |
| `src/Repository/ShipmentRepository.php` | Added findActiveByClient |
| `config/services.yaml` | Registered CreditCheckService |
| `migrations/mysql/Version20260624250000.php` | credit_limit_history table |
| `migrations/sqlite/Version20260624250000.php` | credit_limit_history table (SQLite) |
| `migrations/mysql/Version20260624260000.php` | Seed overdue rules Day 1/14/30/60 |
| `migrations/sqlite/Version20260624260000.php` | Seed overdue rules Day 1/14/30/60 (SQLite) |

### Client BO (`make-cargo-client-bo`)

| File | What changed |
|------|-------------|
| `src/services/ClientService.js` | Added getCreditCheck, getCreditHistory |
| `src/views/client/ClientGeneral.vue` | Added utilisation bar + available credit |
| `src/views/client/ClientCreditHistory.vue` | New — credit history timeline component |
| `src/views/client/ClientDetail.vue` | Added Credit History tab (5th tab) |
| `src/components/form/AppForm.vue` | Await entityPreSubmit + null-abort guard |
| `src/views/quote/QuoteForm.vue` | Credit check gate + approval modal |
| `src/config/tables/shipment/Shipment.js` | Added on-hold badge column |
