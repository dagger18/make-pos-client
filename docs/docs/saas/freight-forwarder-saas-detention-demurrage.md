# Freight Forwarder SaaS — Detention and Demurrage Tracking

## 1. What Detention and Demurrage Are

Detention and demurrage (D&D) are time-based charges applied when containers are not returned or vacated within an agreed free period. They are a significant and frequently disputed revenue and cost item for freight forwarders.

| Term | Definition | Who charges it |
|---|---|---|
| **Demurrage** | Container remains at the terminal beyond the free period | The carrier / terminal operator |
| **Detention** | Container is off-terminal (at customer premises) beyond the free period | The carrier |
| **Combined D&D** | Some carriers charge a single combined rate for both | The carrier |
| **Port storage** | Cargo stored at the terminal after a separate storage free period | The terminal operator |

---

## 2. Free Time Agreements

Free time is the number of days the container can be at the terminal (demurrage) or at the customer's premises (detention) before charges begin. It is agreed per carrier per port and stored in the system.

```sql
CREATE TABLE free_time_agreement (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  location_code     VARCHAR(10)   REFERENCES location(code),   -- NULL = applies to all ports for this carrier
  direction         VARCHAR(16)   NOT NULL,   -- IMPORT / EXPORT
  container_type    VARCHAR(8),               -- NULL = all container types
  free_type         VARCHAR(16)   NOT NULL,   -- DEMURRAGE / DETENTION / COMBINED
  free_days         SMALLINT      NOT NULL,   -- number of calendar days
  rate_tier         JSONB         NOT NULL,   -- progressive rate tiers (see below)
  currency          CHAR(3)       NOT NULL,
  effective_from    DATE          NOT NULL,
  effective_to      DATE,
  source            VARCHAR(32)   NOT NULL DEFAULT 'MANUAL',   -- MANUAL / CARRIER_API / CONTRACT

  UNIQUE (carrier_id, location_code, direction, container_type, free_type, effective_from)
);
```

### Rate tier structure (JSONB)

```json
{
  "tiers": [
    {"from_day": 1,  "to_day": 5,  "rate_per_day": 50},
    {"from_day": 6,  "to_day": 10, "rate_per_day": 80},
    {"from_day": 11, "to_day": null, "rate_per_day": 120}
  ]
}
```

Rates escalate progressively — the longer the overstay, the higher the daily rate. `to_day: null` means the final tier applies indefinitely.

---

## 3. Container D&D Tracking Record

Each container on each job gets a D&D tracking record created when the container is gated out from the terminal (for detention) or when the vessel arrives (for demurrage).

```sql
CREATE TABLE container_dd_tracking (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  container_id      UUID          NOT NULL REFERENCES container(id),
  free_time_id      UUID          NOT NULL REFERENCES free_time_agreement(id),
  dd_type           VARCHAR(16)   NOT NULL,   -- DEMURRAGE / DETENTION / COMBINED

  -- Free period calculation
  free_start_date   DATE          NOT NULL,   -- vessel arrival (demurrage) or gate-out (detention)
  free_end_date     DATE          NOT NULL,   -- free_start_date + free_days - 1
  free_days         SMALLINT      NOT NULL,

  -- Actual return / vacate
  actual_return_date DATE,                    -- when empty returned (detention) or cargo collected (demurrage)
  days_used         SMALLINT,                 -- actual days from free_start_date to return/vacate
  chargeable_days   SMALLINT,                 -- MAX(0, days_used - free_days)

  -- Accrued charges
  accrued_amount    NUMERIC(20,6) NOT NULL DEFAULT 0,
  currency          CHAR(3)       NOT NULL,
  is_final          BOOLEAN       NOT NULL DEFAULT false,   -- false while accruing; true when return confirmed
  last_accrual_date DATE,

  -- Invoice
  invoice_id        UUID          REFERENCES invoice(id),   -- populated when D&D invoice is raised
  is_invoiced       BOOLEAN       NOT NULL DEFAULT false,
  is_disputed       BOOLEAN       NOT NULL DEFAULT false,
  dispute_reason    TEXT,

  -- Audit
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ
);

CREATE INDEX idx_dd_job       ON container_dd_tracking (job_id);
CREATE INDEX idx_dd_not_final ON container_dd_tracking (is_final) WHERE is_final = false;
```

---

## 4. Daily Accrual Calculation

A scheduled job runs daily and updates the accrued D&D amount for all open (non-final) tracking records.

```python
def run_daily_dd_accrual():
    open_records = db.fetch_all("""
        SELECT * FROM container_dd_tracking
        WHERE is_final = false
          AND free_end_date < CURRENT_DATE
    """)

    for record in open_records:
        chargeable_days = max(0, (date.today() - record.free_end_date).days)
        accrued = calculate_dd_charge(
            free_time_id   = record.free_time_id,
            chargeable_days = chargeable_days
        )

        db.execute("""
            UPDATE container_dd_tracking SET
              chargeable_days   = ?,
              accrued_amount    = ?,
              last_accrual_date = CURRENT_DATE,
              updated_at        = now()
            WHERE id = ?
        """, chargeable_days, accrued, record.id)

        # Alert if chargeable days exceed threshold
        if chargeable_days >= 3 and chargeable_days % 3 == 0:
            create_alert(
                job_id     = record.job_id,
                alert_type = 'DD_ACCRUING',
                severity   = 'HIGH' if chargeable_days >= 7 else 'NORMAL',
                message    = f"{record.dd_type}: {chargeable_days} chargeable days, "
                             f"accrued {accrued:.2f} {record.currency}"
            )


def calculate_dd_charge(free_time_id: str, chargeable_days: int) -> float:
    """Calculate total D&D charge using progressive tier rates."""
    agreement = fetch_free_time_agreement(free_time_id)
    tiers     = agreement.rate_tier['tiers']
    total     = 0.0
    days_left = chargeable_days

    for tier in sorted(tiers, key=lambda t: t['from_day']):
        tier_from = tier['from_day']
        tier_to   = tier.get('to_day') or float('inf')
        tier_rate = tier['rate_per_day']
        tier_days = min(days_left, tier_to - tier_from + 1)

        if tier_days <= 0:
            break

        total     += tier_days * tier_rate
        days_left -= tier_days

    return total
```

---

## 5. Empty Return Recording

When the consignee returns the empty container to the depot, the operator records this milestone. This triggers the D&D finalisation.

```python
def record_empty_return(job_id: str, container_id: str, return_date: date) -> None:
    # Write milestone
    write_milestone(job_id, 'EMPTY_RETURNED', actual_date=return_date)

    # Finalise the D&D tracking record
    dd_record = fetch_dd_tracking(job_id=job_id, container_id=container_id,
                                  dd_type='DETENTION')
    if dd_record:
        chargeable_days = max(0, (return_date - dd_record.free_end_date).days)
        final_amount    = calculate_dd_charge(dd_record.free_time_id, chargeable_days)

        db.execute("""
            UPDATE container_dd_tracking SET
              actual_return_date = ?,
              days_used          = ?,
              chargeable_days    = ?,
              accrued_amount     = ?,
              is_final           = true,
              updated_at         = now()
            WHERE id = ?
        """, return_date, (return_date - dd_record.free_start_date).days,
             chargeable_days, final_amount, dd_record.id)

        # If chargeable days > 0, create an AR invoice for D&D charges
        if chargeable_days > 0 and not dd_record.is_invoiced:
            generate_dd_invoice(job_id, dd_record.id)
```

---

## 6. D&D Invoice Generation

D&D invoices are generated separately from the main job invoice. They often arrive after the job has already been invoiced and closed for other charges.

```python
def generate_dd_invoice(job_id: str, dd_record_id: str) -> None:
    dd_record = fetch_dd_tracking(dd_record_id)
    job       = fetch_job(job_id)
    consignee = fetch_job_party(job_id, role='CONSIGNEE')

    # Create a charge line for the D&D
    charge_line_id = create_charge_line(
        job_id       = job_id,
        charge_code  = 'DETENTION' if dd_record.dd_type == 'DETENTION' else 'DEMURRAGE',
        description  = (f"{dd_record.dd_type.title()}: {dd_record.chargeable_days} days "
                        f"x carrier rates"),
        type         = 'SELL',
        orig_amount  = dd_record.accrued_amount,
        orig_currency= dd_record.currency,
        is_estimate  = False,
        payable_at   = 'DESTINATION'
    )

    # Generate the invoice
    invoice = create_invoice(
        job_id         = job_id,
        type           = 'AR',
        billed_to_org  = consignee.organisation_id,
        currency       = dd_record.currency,
        charge_line_ids= [charge_line_id],
        note           = f"Detention charges for container {fetch_container(dd_record.container_id).container_number}"
    )

    # Link the invoice to the D&D record
    db.execute("""
        UPDATE container_dd_tracking SET invoice_id = ?, is_invoiced = true
        WHERE id = ?
    """, invoice.id, dd_record_id)
```

---

## 7. D&D Dispute Workflow

D&D charges are frequently disputed by consignees. The dispute workflow:

```sql
-- Record a dispute
UPDATE container_dd_tracking SET
  is_disputed   = true,
  dispute_reason = :reason,
  updated_at    = now()
WHERE id = :dd_record_id;

-- Log the dispute in the job activity
INSERT INTO job_activity (job_id, object_type, object_id, action, new_value, performed_by)
VALUES (:job_id, 'container_dd_tracking', :dd_record_id, 'DISPUTE_RAISED', :reason, :user_id);
```

Common dispute grounds:
- Free time was agreed differently with the carrier
- The port congestion caused the delay (force majeure claim)
- The container was returned on time but not recorded correctly
- The carrier's invoice includes dates outside the agreed period

When a dispute is resolved, either the carrier issues a credit note (reducing the AP bill) or the consignee pays the invoice as issued.

---

## 8. D&D Dashboard

Finance and operations managers need a live view of accruing D&D exposure.

```sql
SELECT
  s.shipment_id,
  c.container_number,
  dd.dd_type,
  dd.free_end_date,
  CURRENT_DATE - dd.free_end_date      AS days_overdue,
  dd.chargeable_days,
  dd.accrued_amount,
  dd.currency,
  dd.is_invoiced,
  dd.is_disputed,
  o.name                               AS consignee
FROM container_dd_tracking dd
JOIN shipment s    ON dd.job_id       = s.id
JOIN container c   ON dd.container_id = c.id
JOIN job_party jp  ON jp.job_id       = s.id AND jp.role = 'CONSIGNEE'
JOIN organisation o ON jp.organisation_id = o.id
WHERE dd.is_final  = false
  AND dd.free_end_date < CURRENT_DATE
ORDER BY dd.accrued_amount DESC;
```

---

## 9. Golden Rules

1. **Free time is per carrier per port — never assume a default.** Different carriers have different free time agreements at different ports. Always look up the applicable agreement, never use a hardcoded number.
2. **D&D accrual runs daily.** Charges accrue every calendar day — including weekends and public holidays unless the free time agreement specifies business days only.
3. **D&D invoices are separate from the main job invoice.** The main invoice is usually issued at delivery. D&D may not be known until days later when the empty is returned. Never hold the main invoice waiting for D&D to be resolved.
4. **Disputed D&D must still accrue.** Continue recording accrual even when a dispute is open — the dispute may be rejected and the full amount billed.
5. **Free time agreements must be kept current.** Carriers change free time terms seasonally and on contract renewal. Stale agreements lead to wrong accruals and invoice disputes with carriers.
