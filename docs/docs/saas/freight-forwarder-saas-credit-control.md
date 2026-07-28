# Freight Forwarder SaaS — Credit Control

## 1. What Credit Control Is

Credit control is the system that manages financial risk from customers — ensuring the forwarder does not take on more financial exposure than it is comfortable with for any single customer. It combines credit limits, payment terms, overdue tracking, and automated holds.

Without credit control, a forwarder might complete months of work for a customer that then fails to pay — with no automatic warnings or blocks along the way.

---

## 2. Credit Limit Model

Credit limits are stored on the organisation record and enforced at job creation and invoice generation time.

```sql
-- On the organisation table (defined in address book document)
credit_limit      NUMERIC(20,6)  -- maximum outstanding AR allowed
credit_currency   CHAR(3)        -- currency of the limit (usually base currency)
credit_terms      VARCHAR(32)    -- NET30 / NET60 / NET90 / CIA / COD
credit_status     VARCHAR(16)    -- ACTIVE / ON_HOLD / BLOCKED / BLACKLISTED
credit_hold_reason TEXT
credit_reviewed_at DATE
credit_reviewed_by UUID
```

### Credit exposure calculation

Current credit exposure = total outstanding AR (unpaid invoices) converted to credit currency.

```sql
CREATE VIEW customer_credit_exposure AS
SELECT
  o.id                                                      AS organisation_id,
  o.name,
  o.credit_limit,
  o.credit_currency,
  o.credit_terms,
  o.credit_status,
  COALESCE(SUM(
    i.outstanding * er.rate
  ), 0)                                                     AS current_exposure,
  o.credit_limit - COALESCE(SUM(
    i.outstanding * er.rate
  ), 0)                                                     AS available_credit,
  ROUND(
    COALESCE(SUM(i.outstanding * er.rate), 0)
    / NULLIF(o.credit_limit, 0) * 100, 1
  )                                                         AS utilisation_pct,
  COUNT(i.id)                                               AS open_invoice_count,
  MAX(CURRENT_DATE - i.due_date) FILTER (
    WHERE i.status NOT IN ('PAID','VOID','WRITTEN_OFF')
    AND CURRENT_DATE > i.due_date
  )                                                         AS max_days_overdue
FROM organisation o
LEFT JOIN invoice i ON i.billed_to_org = o.id
  AND i.type   = 'AR'
  AND i.status NOT IN ('PAID', 'VOID', 'WRITTEN_OFF')
LEFT JOIN exchange_rate er ON er.from_currency = i.currency
  AND er.to_currency   = o.credit_currency
  AND er.effective_date = CURRENT_DATE
WHERE o.org_type @> ARRAY['CUSTOMER']
GROUP BY o.id, o.name, o.credit_limit, o.credit_currency, o.credit_terms, o.credit_status;
```

---

## 3. Credit Check on Job Creation

Before creating a new job for a customer, the system runs a credit check.

```python
def check_credit_before_job_creation(customer_org_id: str, estimated_job_value: float,
                                      job_currency: str) -> CreditCheckResult:
    exposure = fetch_credit_exposure(customer_org_id)

    # Convert estimated job value to credit currency
    job_value_in_credit_currency = convert_currency(
        estimated_job_value, job_currency, exposure.credit_currency
    )

    projected_exposure = exposure.current_exposure + job_value_in_credit_currency

    result = CreditCheckResult(
        passed              = False,
        current_exposure    = exposure.current_exposure,
        credit_limit        = exposure.credit_limit,
        projected_exposure  = projected_exposure,
        available_credit    = exposure.available_credit,
        credit_status       = exposure.credit_status,
        max_days_overdue    = exposure.max_days_overdue
    )

    if exposure.credit_status == 'BLACKLISTED':
        result.reason = 'Customer is blacklisted'
        result.action = 'HARD_BLOCK'
        return result

    if exposure.credit_status == 'BLOCKED':
        result.reason = f"Customer credit is blocked: {exposure.credit_hold_reason}"
        result.action = 'HARD_BLOCK'
        return result

    if exposure.max_days_overdue and exposure.max_days_overdue > 90:
        result.reason = f"Customer has invoices {exposure.max_days_overdue} days overdue"
        result.action = 'REQUIRE_APPROVAL'
        return result

    if projected_exposure > exposure.credit_limit:
        result.reason = (
            f"This job would bring exposure to {projected_exposure:.2f} "
            f"vs limit of {exposure.credit_limit:.2f} {exposure.credit_currency}"
        )
        result.action = 'REQUIRE_APPROVAL'
        return result

    if exposure.utilisation_pct > 80:
        result.reason = f"Credit utilisation is {exposure.utilisation_pct}% — approaching limit"
        result.action = 'WARN'

    result.passed = True
    return result
```

### Credit check outcomes

| Outcome | Action | Who is notified |
|---|---|---|
| `PASS` | Job created normally | Nobody |
| `WARN` | Job created with warning banner | Operator (in-app) |
| `REQUIRE_APPROVAL` | Job created in PENDING_APPROVAL status | Finance manager notified |
| `HARD_BLOCK` | Job creation refused | Operator + finance manager |

---

## 4. Credit Hold Workflow

When a customer's credit status changes to `ON_HOLD` or `BLOCKED`, all in-progress jobs for that customer are flagged.

```sql
-- Find all active jobs for a customer under credit hold
SELECT s.shipment_id, s.status, s.operator_id
FROM shipment s
JOIN job_party jp ON jp.job_id = s.id AND jp.organisation_id = :org_id
WHERE jp.role IN ('SHIPPER', 'CONSIGNEE')
  AND s.status NOT IN ('DELIVERED', 'INVOICED', 'CLOSED', 'CANCELLED');
```

When a hold is applied:
- New jobs: blocked or require approval
- Active jobs in progress: flagged with warning — operator notified, but operations not interrupted (cargo is already in transit)
- Invoices: still issued normally — finance manages collection separately

---

## 5. Automatic Credit Status Updates

The system runs a nightly job that automatically updates credit status based on rules:

```python
def nightly_credit_status_update():
    customers = fetch_all_customers_with_exposure()

    for customer in customers:
        current_status = customer.credit_status
        new_status     = calculate_credit_status(customer)

        if new_status != current_status:
            update_credit_status(
                org_id     = customer.id,
                new_status = new_status,
                reason     = f"Auto-updated by nightly credit review: {new_status}",
                updated_by = SYSTEM_USER_ID
            )
            notify_finance_team(customer, current_status, new_status)

def calculate_credit_status(customer: CustomerExposure) -> str:
    if customer.credit_status == 'BLACKLISTED':
        return 'BLACKLISTED'  # Blacklist is manual-only — never auto-removed

    if customer.max_days_overdue and customer.max_days_overdue > 90:
        return 'BLOCKED'

    if customer.max_days_overdue and customer.max_days_overdue > 30:
        return 'ON_HOLD'

    if customer.utilisation_pct > 100:
        return 'ON_HOLD'

    return 'ACTIVE'
```

---

## 6. Credit Limit History

Credit limits change over time — they are increased as customer relationships grow, or reduced after payment problems. All changes are audited.

```sql
CREATE TABLE credit_limit_history (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),
  previous_limit    NUMERIC(20,6),
  new_limit         NUMERIC(20,6),
  previous_status   VARCHAR(16),
  new_status        VARCHAR(16),
  reason            TEXT          NOT NULL,
  changed_by        UUID          REFERENCES app_user(id),
  changed_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 7. Payment Terms and Due Date Calculation

Payment terms determine when an invoice is due. The due date is calculated at invoice generation time and stored on the invoice.

```python
def calculate_due_date(issue_date: date, payment_terms: str) -> date:
    terms = {
        'NET7':   7,
        'NET15':  15,
        'NET30':  30,
        'NET45':  45,
        'NET60':  60,
        'NET90':  90,
        'CIA':    0,   -- Cash in Advance -- due before job starts
        'COD':    0,   -- Cash on Delivery -- due at delivery
    }
    days = terms.get(payment_terms, 30)
    return issue_date + timedelta(days=days)
```

---

## 8. Overdue Escalation

```
Day 0:   Invoice issued, due date calculated
Day 1:   Automatic payment reminder sent to customer contact (receives_invoice = true)
Day 7:   Follow-up reminder sent — finance team copied
Day 14:  Escalation notification sent to finance manager
Day 30:  Customer credit status reviewed — may move to ON_HOLD
Day 60:  Escalation to senior management — possible legal action flag
Day 90+: Finance decides — payment plan, write-off, or blacklist
```

These escalation rules are stored in the `notification_rule` table with `trigger_type = FINANCIAL` and `event = INVOICE_OVERDUE`.

---

## 9. Golden Rules

1. **Credit limits are enforced at job creation, not at invoicing.** By the time an invoice is raised, the work is already done. Block at the start, not the end.
2. **Hard blocks require manual override by a finance manager.** No operator should be able to bypass a BLOCKED status on their own.
3. **Credit status changes are always audited.** Every status change — including automatic ones — writes a `credit_limit_history` record.
4. **In-transit jobs are never interrupted by a credit hold.** Cargo already on a vessel cannot be turned around. Operational holds only apply to new job creation.
5. **CIA (Cash in Advance) customers have their jobs held until payment is confirmed.** The job is created but stays in `PENDING_PAYMENT` status until the finance team confirms receipt.
