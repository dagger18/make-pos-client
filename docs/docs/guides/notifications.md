# Notification & Alert System — Setup Guide

## 1. Overview

The notification system delivers alerts to users via two channels:

- **In-app bell** — persisted in `in_app_notification`, served via `/my-profile/get-notifications/{page}`
- **Email queue** — persisted in `notification_queue`, dispatched via `app:notifications:process-queue`

**3-tier architecture:**

1. **Event tier** — `NotificationEventListener` fires on Doctrine `postPersist` (new `ShipmentMilestone`) and `postUpdate` (`Shipment.status` change). It dispatches an async `NotificationTriggerMessage` via Symfony Messenger.
2. **Generator tier** — `NotificationTriggerMessageHandler` receives the message and calls `NotificationGeneratorService`, which evaluates active `NotificationRule` records and creates `InAppNotification` and/or `NotificationQueue` records.
3. **Delivery tier** — `NotificationQueueProcessorCommand` picks up `PENDING` queue entries and sends them via `MailService::sendRaw()`. `NotificationSchedulerCommand` runs on a cron and generates deadline/financial alerts.

## 2. Database Tables

| Table | Purpose | Primary key |
|---|---|---|
| `in_app_notification` | Per-user bell notifications | `id` (auto-increment) |
| `notification_rule` | Configurable trigger rules | `id` (auto-increment); `rule_key` unique |
| `notification_template` | Email/in-app body templates | `key_col` (string, PK) |
| `notification_queue` | Email delivery queue | `id` (auto-increment) |
| `user_notification_preference` | Per-user opt-in/out per rule+channel | composite: `user_id` + `rule_key` + `channel` |

### Key fields

**`in_app_notification`:** `user_id`, `shipment_id` (nullable), `rule_key`, `title`, `body`, `priority` (NORMAL/HIGH/URGENT), `is_read`, `read_at`, `action_url`, `created_date`

**`notification_rule`:** `rule_key` (unique slug), `trigger_type`, `trigger_config` (JSON), `recipient_config` (JSON array), `channels` (JSON array), `template_key`, `is_active`, `scope_type`, `priority`

**`notification_queue`:** `status` (PENDING/SENT/FAILED/CANCELLED/SKIPPED), `channel`, `recipient_email`, `scheduled_at`, `attempt_count`, `last_error`

## 3. Notification Rule Configuration

### `triggerType` values

| Value | When it fires |
|---|---|
| `MILESTONE` | After a new `ShipmentMilestone` is persisted |
| `STATUS_CHANGE` | After `Shipment.status` changes |
| `DEADLINE` | Run by `app:notifications:schedule-deadlines` cron |
| `FINANCIAL` | Run by `app:notifications:schedule-deadlines` cron |

### `triggerConfig` format

```json
// MILESTONE — filter by milestone code
{"milestone_code": "VESSEL_DEPARTED"}

// STATUS_CHANGE — filter by new status (omit to match all)
{"new_status": "DELIVERED"}

// DEADLINE — SI cutoff window
{"deadline_field": "booking.cutoff_si", "hours_before": 48}

// FINANCIAL — invoice overdue
{"event": "INVOICE_OVERDUE", "days_overdue": 7}
```

### `recipientConfig` format

```json
[
  {"type": "JOB_OPERATOR"},
  {"type": "FIXED_EMAIL", "email": "ops@example.com"}
]
```

`JOB_OPERATOR` resolves to `Shipment::getCreatedBy()`.

### `channels` format

```json
["IN_APP", "EMAIL"]
```

## 4. Adding a New Notification Rule

```sql
-- 1. Add the rule
INSERT INTO notification_rule
  (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date)
VALUES
  ('MILESTONE_CUSTOMS_RELEASED', 'Customs Released', 'MILESTONE',
   '{"milestone_code":"CUSTOMS_RELEASED"}',
   '[{"type":"JOB_OPERATOR"}]',
   '["IN_APP","EMAIL"]',
   'email_milestone_customs_released', 1, 'GLOBAL', 'NORMAL', NOW());

-- 2. Add the email template
INSERT INTO notification_template
  (key_col, name, channel, subject_template, body_template, language, created_date)
VALUES
  ('email_milestone_customs_released', 'Customs Released Email', 'EMAIL',
   'Customs Cleared — {{ shipment_code }}',
   '<p>Shipment <strong>{{ shipment_code }}</strong> has been customs cleared on {{ actual_date }}.</p>',
   'en', NOW());
```

To disable a rule without deleting it: `UPDATE notification_rule SET is_active = 0 WHERE rule_key = 'RULE_KEY';`

## 5. Template Variable Syntax

Templates use `{{ variable }}` placeholders (spaces optional: `{{variable}}` also works).

### Available variables by trigger type

**MILESTONE:**
- `{{ shipment_code }}` — shipment job code
- `{{ milestone_code }}` — raw enum value (e.g. `VESSEL_DEPARTED`)
- `{{ milestone_label }}` — customer-facing label (e.g. `Vessel departed`)
- `{{ actual_date }}` — `Y-m-d` formatted actual date

**STATUS_CHANGE:**
- `{{ shipment_code }}`
- `{{ old_status }}` — previous status value
- `{{ new_status }}` — new status value

**DEADLINE (booking.cutoff_si):**
- `{{ shipment_code }}`
- `{{ hours_remaining }}` — hours until cutoff (from `hours_before` config)
- `{{ cutoff_si }}` — formatted cutoff datetime (`Y-m-d H:i`)

**FINANCIAL (INVOICE_OVERDUE):**
- `{{ shipment_code }}`
- `{{ invoice_code }}` — EbitNote code
- `{{ days_overdue }}` — days overdue from `days_overdue` config

## 6. How Event Triggering Works

```
ShipmentMilestone persisted
    ↓
NotificationEventListener::postPersist()
    ↓
MessageBus::dispatch(NotificationTriggerMessage('milestone.created', $id))
    ↓ [async transport — Messenger worker]
NotificationTriggerMessageHandler::__invoke()
    ↓
NotificationGeneratorService::handleMilestone()
    ↓
Finds matching active NotificationRule records
    ↓
For each rule → InAppNotification + NotificationQueue records created
```

The same flow applies for `Shipment.status` changes via `postUpdate`.

Start the Messenger worker with:
```bash
php bin/console messenger:consume async --time-limit=3600
```

## 7. Email Delivery

Email entries in `notification_queue` with `status = PENDING` and `scheduled_at <= now` are processed by:

```bash
php bin/console app:notifications:process-queue
```

The command:
- Fetches up to 50 due records
- Calls `MailService::sendRaw($email, $subject, $htmlBody)` via the Brevo/SMTP transport
- Marks records `SENT` (success) or retries up to 3 times before marking `FAILED`

## 8. Deadline Scheduler

The scheduler command generates notifications for time-based triggers:

```bash
php bin/console app:notifications:schedule-deadlines
```

### Supported `deadline_field` values

| Value | Entity field | Description |
|---|---|---|
| `booking.cutoff_si` | `Booking::getSiCutOff()` | SI (Shipping Instruction) submission cutoff |

To add support for additional deadline fields (e.g. `booking.cutoff_cy`), add a new `if ($field !== '...')` branch in `NotificationSchedulerCommand::processDeadlineRules()` with the appropriate query builder logic.

### Financial events

| Event | Query | Description |
|---|---|---|
| `INVOICE_OVERDUE` | `EbitNote` where `type = InvoiceDebit`, `status NOT IN (Pending, Done)`, `createdDate <= now - days_overdue` | Unpaid AR invoice past due |

## 9. In-App Notification Bell

### API endpoints

| Method | URL | Description |
|---|---|---|
| `GET` | `/my-profile/get-notifications/{page}` | Paginated list (20/page), ordered newest first |
| `POST` | `/my-profile/mark-notifications-read` | Mark notifications read. Body: `{"ids": [1,2,3]}` or `{}` to mark all |
| `GET` | `/my-profile/ping` | Includes `notification: <unread_count>` in response |

### Response format for `get-notifications`

```json
{
  "currentPage": 1,
  "totalPages": 3,
  "total": 42,
  "list": [
    {
      "id": 123,
      "title": "Vessel Departed",
      "body": "Shipment JOB-001: Vessel departed",
      "priority": "NORMAL",
      "isRead": false,
      "actionUrl": null,
      "shipmentId": 45,
      "shipment": {"code": "JOB-001"},
      "ruleKey": "MILESTONE_VESSEL_DEPARTED",
      "createdDate": "2026-06-24T09:00:00+00:00"
    }
  ]
}
```

### How the BO bell works

1. `ping()` is called on login / periodically. The response includes `notification: N` (unread count).
2. `appStore.newEntities.notification` holds the badge number — the bell badge is bound to this.
3. When the bell is opened (`onOpenNotification`), `NotificationService.getNotifications(1)` is called. After load, `NotificationService.markRead()` is called to clear all, resetting the badge to 0.
4. Infinite scroll in `UserProfile.vue` calls subsequent pages via `endIntersect()`.
5. `NavBarNotifications.vue` uses a separate `Notifications` component and loads via `NotificationService.getNotifications(1)` on mount.

## 10. User Preference API

Users can opt-in/out of specific rule+channel combinations.

### Endpoints

| Method | URL | Description |
|---|---|---|
| `GET` | `/my-profile/notification-preferences` | Returns all active rules with user's current preferences |
| `POST` | `/my-profile/notification-preferences` | Upsert preferences array |

### GET response format

```json
[
  {
    "ruleKey": "MILESTONE_VESSEL_DEPARTED",
    "name": "Vessel Departed",
    "priority": "NORMAL",
    "channels": [
      {"channel": "IN_APP", "isEnabled": true, "digestMode": false},
      {"channel": "EMAIL", "isEnabled": true, "digestMode": false}
    ]
  }
]
```

### POST payload format

```json
[
  {"ruleKey": "MILESTONE_VESSEL_DEPARTED", "channel": "EMAIL", "isEnabled": false, "digestMode": false},
  {"ruleKey": "CUTOFF_SI_48H", "channel": "IN_APP", "isEnabled": true, "digestMode": false}
]
```

**Note:** `UserNotificationPreference` records are NOT currently checked by `NotificationGeneratorService` — preferences are stored for display only in this MVP. To enforce them, add a lookup in `NotificationGeneratorService::dispatchToUser()` before creating `InAppNotification`/`NotificationQueue` entries.

## 11. Cron Schedule

Add these to the server crontab (or Supervisor):

```cron
# Process email queue every 5 minutes
*/5 * * * * /usr/bin/php /var/www/app/bin/console app:notifications:process-queue >> /var/log/notifications-queue.log 2>&1

# Generate deadline and financial alerts every hour
0 * * * * /usr/bin/php /var/www/app/bin/console app:notifications:schedule-deadlines >> /var/log/notifications-scheduler.log 2>&1

# Symfony Messenger worker (run as a persistent process via Supervisor)
# php bin/console messenger:consume async --time-limit=3600
```

## 12. Channels Not Yet Implemented

The following channels are reserved for future implementation:

- **SMS** — requires Twilio or similar; not configured
- **WhatsApp** — requires WhatsApp Business API
- **Webhook** — push to external URL on event
- **Digest mode** — batch multiple notifications into a single daily/weekly email

To add a new channel, extend `NotificationGeneratorService::dispatchToUser()` and `NotificationQueueProcessorCommand::execute()` with a new `if ($channel === 'YOUR_CHANNEL')` branch.
