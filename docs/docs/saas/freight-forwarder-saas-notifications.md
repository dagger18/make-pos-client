# Freight Forwarder SaaS — Notification and Alert System

## 1. Why Notifications Are the Operational Nervous System

A freight forwarder's job is fundamentally time-critical. Missing a shipping instruction cutoff costs a vessel slot. Missing an arrival notice delays customs clearance. Missing an overdue invoice follow-up costs cash flow.

The notification system is what turns the database from a record-keeping tool into an active operational assistant. It watches for events and deadlines and delivers the right information to the right person at the right time.

---

## 2. Notification Types

| Type | Trigger | Recipients | Channel |
|---|---|---|---|
| **Deadline alert** | N hours before a cutoff | Operator, supervisor | In-app + email |
| **Milestone notification** | Milestone recorded (vessel departed, delivered) | Operator, customer | In-app + email |
| **Exception alert** | Milestone late vs planned | Operator, supervisor, manager | In-app + email + SMS |
| **Status change** | Job status changes | Operator, customer | In-app + email |
| **Financial alert** | Invoice overdue, credit limit exceeded | Finance, manager | In-app + email |
| **Vessel roll / delay** | ETD changes significantly | Operator, shipper | Email + SMS |
| **Customs hold** | Sub-status = EXAMINATION_REQUESTED | Operator, supervisor, consignee | In-app + email + SMS |
| **Document missing** | Required doc not received by cutoff | Operator | In-app |
| **Task overdue** | Mandatory task past due date | Operator, supervisor | In-app + email |

---

## 3. Notification Rule Table

Rules define what triggers a notification, who receives it, and how far in advance.

```sql
CREATE TABLE notification_rule (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  rule_key          VARCHAR(64)   UNIQUE NOT NULL,   -- SI_CUTOFF_48H / VESSEL_DEPARTED / INVOICE_OVERDUE_7D
  name              VARCHAR(128)  NOT NULL,
  trigger_type      VARCHAR(32)   NOT NULL,   -- DEADLINE / MILESTONE / STATUS_CHANGE / FINANCIAL / SCHEDULE
  trigger_config    JSONB         NOT NULL,   -- see trigger config schema below
  recipients        JSONB         NOT NULL,   -- see recipient config schema below
  channels          TEXT[]        NOT NULL,   -- {EMAIL, IN_APP, SMS, WHATSAPP}
  template_key      VARCHAR(64)   NOT NULL REFERENCES notification_template(key),
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  scope_type        VARCHAR(16)   NOT NULL DEFAULT 'GLOBAL',  -- GLOBAL / BRANCH / MODE
  scope_id          UUID,
  priority          VARCHAR(8)    NOT NULL DEFAULT 'NORMAL',  -- LOW / NORMAL / HIGH / URGENT
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Trigger config examples (JSONB)

**Deadline trigger — SI cutoff approaching:**
```json
{
  "trigger_type": "DEADLINE",
  "deadline_field": "shipment.cutoff_si",
  "hours_before": 48,
  "repeat_hours": 24,
  "stop_after_milestone": "SI_SUBMITTED"
}
```

**Milestone trigger — vessel departed:**
```json
{
  "trigger_type": "MILESTONE",
  "milestone_code": "VESSEL_DEPARTED",
  "delay_minutes": 0
}
```

**Financial trigger — invoice overdue:**
```json
{
  "trigger_type": "FINANCIAL",
  "event": "INVOICE_OVERDUE",
  "days_overdue": 7,
  "repeat_days": 7,
  "stop_after_status": "PAID"
}
```

### Recipient config examples (JSONB)

```json
{
  "recipients": [
    {"type": "JOB_OPERATOR"},
    {"type": "JOB_SUPERVISOR"},
    {"type": "CUSTOMER_CONTACT", "contact_flags": ["receives_tracking"]},
    {"type": "FIXED_EMAIL", "email": "ops-manager@company.com"}
  ]
}
```

---

## 4. Notification Queue

Generated notifications are placed in a queue before delivery. This decouples generation from delivery and allows retries.

```sql
CREATE TABLE notification_queue (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  rule_id           UUID          NOT NULL REFERENCES notification_rule(id),
  job_id            UUID          REFERENCES shipment(id),
  recipient_type    VARCHAR(32)   NOT NULL,   -- USER / CONTACT / EMAIL
  recipient_id      UUID,                     -- app_user.id or contact.id
  recipient_email   VARCHAR(128),
  recipient_phone   VARCHAR(32),
  channel           VARCHAR(16)   NOT NULL,   -- EMAIL / IN_APP / SMS / WHATSAPP
  subject           VARCHAR(255),
  body              TEXT          NOT NULL,
  priority          VARCHAR(8)    NOT NULL DEFAULT 'NORMAL',
  scheduled_at      TIMESTAMPTZ   NOT NULL,
  sent_at           TIMESTAMPTZ,
  status            VARCHAR(16)   NOT NULL DEFAULT 'PENDING',  -- PENDING / SENT / FAILED / CANCELLED / SKIPPED
  attempt_count     SMALLINT      NOT NULL DEFAULT 0,
  last_error        TEXT,
  provider_ref      VARCHAR(128),             -- email/SMS provider message ID
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE INDEX idx_nq_scheduled ON notification_queue (scheduled_at) WHERE status = 'PENDING';
CREATE INDEX idx_nq_job       ON notification_queue (job_id);
```

---

## 5. Notification Templates

Every notification type has a template stored in the database — editable by admin without code deployment.

```sql
CREATE TABLE notification_template (
  key               VARCHAR(64)   PRIMARY KEY,
  name              VARCHAR(128)  NOT NULL,
  channel           VARCHAR(16)   NOT NULL,   -- EMAIL / SMS / IN_APP
  subject_template  TEXT,                     -- Handlebars/Jinja2 template for subject
  body_template     TEXT          NOT NULL,   -- template for body
  language          CHAR(2)       NOT NULL DEFAULT 'en',
  variables         JSONB,                    -- documented variable names available in template
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Template example — SI cutoff approaching (email)

```
Subject: ⚠️ SI Cutoff in {{hours_remaining}}h — {{shipment_id}}

Dear {{operator_name}},

The Shipping Instruction cutoff for job {{shipment_id}} is approaching.

Cutoff:   {{cutoff_si_local}} ({{pol_timezone}})
Vessel:   {{vessel_name}} / Voyage {{voyage_number}}
POL:      {{pol_name}}
POD:      {{pod_name}}
Shipper:  {{shipper_name}}

Action required: Submit the Shipping Instruction to {{carrier_name}} before the cutoff.

{{#if si_submitted}}
✓ SI has been submitted — no action needed.
{{else}}
⚠️ SI has NOT been submitted yet.
{{/if}}

Open job: {{job_url}}
```

---

## 6. In-App Notification Bell

In-app notifications appear in a notification bell/panel in the UI. They are stored per user and marked as read.

```sql
CREATE TABLE in_app_notification (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id           UUID          NOT NULL REFERENCES app_user(id),
  job_id            UUID          REFERENCES shipment(id),
  rule_id           UUID          REFERENCES notification_rule(id),
  title             VARCHAR(255)  NOT NULL,
  body              TEXT          NOT NULL,
  priority          VARCHAR(8)    NOT NULL DEFAULT 'NORMAL',
  is_read           BOOLEAN       NOT NULL DEFAULT false,
  read_at           TIMESTAMPTZ,
  action_url        TEXT,                     -- deep link into the relevant job or invoice
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE INDEX idx_ian_user_unread ON in_app_notification (user_id, created_at DESC)
  WHERE is_read = false;
```

The unread count badge on the notification bell uses:
```sql
SELECT COUNT(*) FROM in_app_notification
WHERE user_id = :user_id AND is_read = false;
```

---

## 7. Alert Severity Levels

| Severity | Examples | Delivery |
|---|---|---|
| `LOW` | Task reminder, document checklist update | In-app only |
| `NORMAL` | Milestone update, arrival notice ready | In-app + email |
| `HIGH` | Cutoff in 24h, invoice overdue 7 days | In-app + email, escalated to supervisor |
| `URGENT` | Customs hold, vessel rolled, credit blocked | In-app + email + SMS, escalated immediately |

---

## 8. Escalation Rules

If a HIGH or URGENT notification is not acknowledged within a configured time window, it escalates to the next level:

```sql
CREATE TABLE escalation_rule (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  notification_rule_id UUID       NOT NULL REFERENCES notification_rule(id),
  escalate_after_minutes SMALLINT NOT NULL,   -- if not acknowledged in this time
  escalate_to       JSONB         NOT NULL,   -- same recipient config format
  max_escalations   SMALLINT      NOT NULL DEFAULT 2,
  is_active         BOOLEAN       NOT NULL DEFAULT true
);
```

Example: A customs hold alert not acknowledged by the operator within 30 minutes escalates to the branch supervisor. Not acknowledged within 60 minutes — escalates to the branch manager.

---

## 9. Digest Mode

High-frequency operators (managing 50+ jobs) can opt for digest mode — instead of individual notifications, they receive a single daily or shift-start summary.

```sql
CREATE TABLE user_notification_preference (
  user_id           UUID          NOT NULL REFERENCES app_user(id),
  rule_key          VARCHAR(64)   NOT NULL REFERENCES notification_rule(rule_key),
  channel           VARCHAR(16)   NOT NULL,
  is_enabled        BOOLEAN       NOT NULL DEFAULT true,
  digest_mode       BOOLEAN       NOT NULL DEFAULT false,
  digest_time       TIME,                     -- e.g. 08:00 — when to send the digest
  PRIMARY KEY (user_id, rule_key, channel)
);
```

URGENT severity notifications always bypass digest mode and are delivered immediately regardless of user preference.

---

## 10. Delivery Channels

| Channel | Provider options | Use case |
|---|---|---|
| Email | SendGrid / AWS SES / Mailgun | All notifications — primary channel |
| In-app | WebSocket / SSE / polling | Real-time bell, action links |
| SMS | Twilio / VHT / FPT Telecom | Urgent only — customs hold, vessel roll |
| WhatsApp | Twilio / Meta Cloud API | Common in Vietnam/ASEAN — arrival notices |
| Webhook | Customer's own endpoint | Enterprise customers who want API push |

---

## 11. Golden Rules

1. **Notification rules are data, not code.** Trigger conditions, recipients, and templates are all configurable in the database without deployment.
2. **Every notification goes through the queue.** Never send directly from application code — the queue provides retry, throttling, and audit.
3. **URGENT notifications bypass all filters.** Digest mode, quiet hours, and channel preferences are overridden for URGENT severity.
4. **Unsubscribe must be honoured.** Customer contacts must be able to opt out of non-essential notifications. Never send to a contact that has unsubscribed.
5. **All sent notifications are logged.** The `document_email_log` (for documents) and `notification_queue` (for operational alerts) together provide the complete evidence trail.
