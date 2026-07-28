# Freight Forwarder SaaS — Audit Log and Compliance

## 1. What This Module Covers

The audit and compliance module provides three things:

1. **Complete audit trail** — an immutable record of every action taken in the system, who did it, and when
2. **Regulatory compliance** — GDPR data handling, data retention policies, right to erasure
3. **Sanctions screening** — checking parties against OFAC, UN, EU, and other restricted party lists before they appear on a shipment

These are distinct from the operational activity log (`job_activity` table) which tracks job-level changes. The compliance audit log covers system-level events: logins, permission changes, data exports, deletions, and regulatory checks.

---

## 2. System Audit Log

```sql
CREATE TABLE system_audit_log (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  event_type        VARCHAR(64)   NOT NULL,   -- see event taxonomy below
  actor_type        VARCHAR(16)   NOT NULL,   -- USER / SYSTEM / API / PORTAL_USER
  actor_id          UUID,                     -- app_user.id or portal_user.id
  actor_email       VARCHAR(128),             -- snapshot at time of action
  actor_ip          INET,
  actor_user_agent  TEXT,

  -- What was affected
  object_type       VARCHAR(64),              -- 'organisation' / 'shipment' / 'invoice' / 'user' etc.
  object_id         UUID,
  object_ref        VARCHAR(64),              -- human-readable reference (shipment_id, invoice_number)

  -- Action detail
  action_detail     JSONB,                    -- field-level change detail where applicable
  result            VARCHAR(8)    NOT NULL DEFAULT 'SUCCESS',  -- SUCCESS / FAILURE / BLOCKED

  -- Context
  session_id        UUID,
  branch_id         UUID          REFERENCES branch(id),
  request_id        UUID,                     -- HTTP request ID for correlation

  logged_at         TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- Partitioned by month for performance (audit logs grow very large)
CREATE INDEX idx_sal_actor   ON system_audit_log (actor_id, logged_at DESC);
CREATE INDEX idx_sal_object  ON system_audit_log (object_type, object_id, logged_at DESC);
CREATE INDEX idx_sal_type    ON system_audit_log (event_type, logged_at DESC);
```

This table is **insert-only**. No updates, no deletes. Ever.

---

## 3. Event Taxonomy

### Authentication events
| Event | Logged when |
|---|---|
| `AUTH.LOGIN_SUCCESS` | User successfully logs in |
| `AUTH.LOGIN_FAILURE` | Wrong password or MFA failure |
| `AUTH.LOGOUT` | User logs out |
| `AUTH.PASSWORD_CHANGED` | Password changed |
| `AUTH.MFA_ENABLED` | Two-factor authentication enabled |
| `AUTH.SESSION_EXPIRED` | Inactivity timeout |
| `AUTH.ACCOUNT_LOCKED` | Too many failed attempts |

### Permission events
| Event | Logged when |
|---|---|
| `PERMISSION.ROLE_CHANGED` | User's role in a branch is changed |
| `PERMISSION.BRANCH_ACCESS_GRANTED` | User gains access to a new branch |
| `PERMISSION.BRANCH_ACCESS_REVOKED` | User loses branch access |
| `PERMISSION.CREDIT_OVERRIDE` | Credit block overridden by a manager |

### Data access events
| Event | Logged when |
|---|---|
| `DATA.EXPORT` | User exports data (CSV, Excel, PDF report) |
| `DATA.BULK_DOWNLOAD` | User downloads multiple documents |
| `DATA.REPORT_RUN` | A financial or operational report is generated |
| `DATA.API_ACCESS` | External API key used to access data |

### Financial events
| Event | Logged when |
|---|---|
| `FINANCIAL.INVOICE_VOIDED` | Invoice voided (with reason) |
| `FINANCIAL.CREDIT_NOTE_ISSUED` | Credit note created |
| `FINANCIAL.PAYMENT_RECORDED` | Payment recorded manually |
| `FINANCIAL.RATE_OVERRIDE` | Rate card rate overridden on a quote |
| `FINANCIAL.CREDIT_LIMIT_CHANGED` | Customer credit limit modified |
| `FINANCIAL.DEBT_WRITTEN_OFF` | AR invoice written off |

### Compliance events
| Event | Logged when |
|---|---|
| `COMPLIANCE.SANCTIONS_CHECK` | Sanctions check run on an organisation |
| `COMPLIANCE.SANCTIONS_HIT` | Potential sanctions match found |
| `COMPLIANCE.SANCTIONS_CLEARED` | Hit reviewed and cleared by compliance officer |
| `COMPLIANCE.CUSTOMS_HOLD` | Customs hold recorded on a job |
| `COMPLIANCE.GDPR_REQUEST` | GDPR data subject request received |
| `COMPLIANCE.DATA_ERASED` | Personal data erased per GDPR request |

---

## 4. Sanctions Screening

Before any organisation can be added as a party to a job, the system checks them against restricted party lists.

```sql
CREATE TABLE sanctions_list (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  list_name         VARCHAR(64)   NOT NULL,   -- OFAC_SDN / UN_CONSOLIDATED / EU_SANCTIONS / UK_SANCTIONS
  listed_name       VARCHAR(255)  NOT NULL,
  aliases           TEXT[],
  country_code      CHAR(2)       REFERENCES country(code),
  entity_type       VARCHAR(32),              -- INDIVIDUAL / ENTITY / VESSEL / AIRCRAFT
  programs          TEXT[],                   -- sanction programs this entity is listed under
  reason            TEXT,
  listed_date       DATE,
  source_ref        VARCHAR(128),
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  last_updated      DATE          NOT NULL
);

CREATE INDEX idx_sl_name ON sanctions_list USING gin(to_tsvector('english', listed_name));
CREATE INDEX idx_sl_trgm ON sanctions_list USING gin(listed_name gin_trgm_ops);
```

### Sanctions check function

```python
def check_sanctions(org_name: str, country_code: str,
                    check_type: str = 'FULL') -> SanctionsResult:
    """
    Run before adding a party to a job or creating a new organisation.
    Returns: CLEAR / POSSIBLE_MATCH / CONFIRMED_HIT
    """
    # Exact name match
    exact_matches = db.fetch_all("""
        SELECT * FROM sanctions_list
        WHERE is_active = true
          AND (
            listed_name ILIKE :name
            OR :name = ANY(aliases)
          )
    """, name=org_name)

    # Fuzzy name match (for spelling variations)
    fuzzy_matches = db.fetch_all("""
        SELECT *, similarity(listed_name, :name) AS score
        FROM sanctions_list
        WHERE is_active = true
          AND similarity(listed_name, :name) > 0.75
        ORDER BY score DESC
        LIMIT 5
    """, name=org_name)

    # Country match (organisation in a sanctioned country)
    country_sanctioned = db.fetch_one(
        "SELECT is_sanctioned FROM country WHERE code = ?", country_code
    ).is_sanctioned

    result = SanctionsResult(
        organisation_name = org_name,
        country_code      = country_code,
        exact_matches     = exact_matches,
        fuzzy_matches     = fuzzy_matches,
        country_sanctioned = country_sanctioned,
        status            = 'CONFIRMED_HIT' if exact_matches else
                           'POSSIBLE_MATCH' if (fuzzy_matches or country_sanctioned) else
                           'CLEAR'
    )

    # Log every check
    log_audit_event('COMPLIANCE.SANCTIONS_CHECK', {
        "org_name":    org_name,
        "country":     country_code,
        "result":      result.status,
        "match_count": len(exact_matches) + len(fuzzy_matches)
    })

    return result
```

### Sanctions hit workflow

```
POSSIBLE_MATCH or CONFIRMED_HIT
        ↓
Job creation is blocked — the match is presented to the operator
        ↓
Compliance officer reviews the match:
  FALSE_POSITIVE → cleared by compliance officer → job proceeds
  CONFIRMED_HIT  → job blocked → escalated to management → may be reported
        ↓
All decisions logged in system_audit_log with COMPLIANCE.SANCTIONS_CLEARED or COMPLIANCE.SANCTIONS_HIT
```

---

## 5. GDPR and Data Privacy

### Data retention policy

```sql
CREATE TABLE data_retention_policy (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  data_category     VARCHAR(64)   NOT NULL,   -- JOB_DATA / FINANCIAL / PERSONAL / AUDIT_LOG / DOCUMENTS
  retention_years   SMALLINT      NOT NULL,
  legal_basis       TEXT          NOT NULL,   -- why this retention period is required
  applies_to        TEXT          NOT NULL,   -- which tables / fields
  auto_delete       BOOLEAN       NOT NULL DEFAULT false,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Standard retention periods for freight forwarding

| Data category | Retention | Legal basis |
|---|---|---|
| Financial records (invoices, payments) | 10 years | Tax law in most jurisdictions |
| Customs entry records | 7 years | Customs law |
| Job operational data | 7 years | Contract and liability law |
| Bill of lading copies | 7 years | Shipping law |
| Personal data (contacts) | 3 years after last activity | GDPR |
| Audit logs | 7 years | Regulatory compliance |
| Marketing data | 2 years | GDPR |

### GDPR data subject request handling

```python
def handle_gdpr_erasure_request(contact_email: str, request_ref: str) -> dict:
    """
    Process a GDPR right-to-erasure request.
    Cannot erase data needed for legal/financial records.
    """
    log_audit_event('COMPLIANCE.GDPR_REQUEST',
                   {"type": "ERASURE", "email": contact_email, "ref": request_ref})

    contact = fetch_contact_by_email(contact_email)
    if not contact:
        return {"status": "NOT_FOUND"}

    # Data that CAN be erased
    erasable = []
    if can_erase_contact(contact):
        anonymise_contact(contact)
        erasable.append("contact_personal_data")

    # Data that CANNOT be erased
    cannot_erase = []
    if contact_on_active_jobs(contact):
        cannot_erase.append("job_party_records — active jobs")
    if contact_on_financial_docs(contact):
        cannot_erase.append("invoice_contacts — financial records retention requirement")

    log_audit_event('COMPLIANCE.DATA_ERASED', {
        "contact_id":   str(contact.id),
        "erased":       erasable,
        "retained":     cannot_erase,
        "request_ref":  request_ref
    })

    return {
        "status":       "PARTIAL_ERASURE",
        "erased":       erasable,
        "retained":     cannot_erase,
        "reason":       "Financial and operational records retained per legal requirement"
    }


def anonymise_contact(contact: Contact) -> None:
    """Replace personal data with anonymised placeholders."""
    db.execute("""
        UPDATE contact SET
          first_name = 'ERASED',
          last_name  = 'ERASED',
          email      = concat('erased_', id::text, '@deleted.invalid'),
          phone      = NULL,
          mobile     = NULL,
          is_active  = false
        WHERE id = ?
    """, contact.id)
```

---

## 6. Audit Log Query for Regulators

When a regulatory body requests an audit trail for a specific shipment:

```sql
SELECT
  sal.logged_at,
  sal.event_type,
  sal.actor_email,
  sal.actor_ip,
  sal.object_type,
  sal.object_ref,
  sal.action_detail,
  sal.result
FROM system_audit_log sal
WHERE (
  -- System-level audit events for this job
  (sal.object_type = 'shipment' AND sal.object_id = :job_uuid)
  OR
  -- Job-level activity log
  sal.object_id IN (
    SELECT id FROM job_activity WHERE job_id = :job_uuid
  )
)
ORDER BY sal.logged_at ASC;
```

---

## 7. Compliance Dashboard

```sql
-- Summary for compliance officer
SELECT
  DATE_TRUNC('month', logged_at)  AS period,
  event_type,
  result,
  COUNT(*)                         AS event_count
FROM system_audit_log
WHERE event_type LIKE 'COMPLIANCE.%'
  AND logged_at >= CURRENT_DATE - INTERVAL '12 months'
GROUP BY DATE_TRUNC('month', logged_at), event_type, result
ORDER BY period DESC, event_count DESC;
```

---

## 8. Golden Rules

1. **The audit log is insert-only — forever.** Not even DBAs delete audit log records. Use table partitioning for performance, archive old partitions to cold storage, but never delete.
2. **Sanctions checks are logged regardless of result.** CLEAR results are logged too — you need evidence that you performed the check, not just that you found a hit.
3. **GDPR erasure is anonymisation, not deletion.** Financial and operational records cannot be deleted to meet erasure requests. Anonymise personal fields and retain the record structure.
4. **Sanctions list data must be kept current.** An OFAC SDN list from 6 months ago is not a defence against facilitating a sanctioned transaction. Automate list updates from official sources — minimum weekly.
5. **Compliance officer review of sanctions hits must be documented.** A compliance officer clearing a possible match must leave a written explanation. The audit log entry must include their reasoning.
