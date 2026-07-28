# Freight Forwarder SaaS — Consolidation Management

## 1. What Consolidation Management Is

A consolidation (consol) is the physical grouping of multiple customers' cargo into a single container (LCL ocean) or ULD (air). The forwarder acts as the consolidator — booking the full container from the carrier on an MBL, then issuing individual HBLs to each customer for their portion of the cargo.

Consolidation management covers:
- Building and managing the consol (adding/removing individual jobs)
- Calculating each job's proportional share of port-level charges
- Producing the cargo manifest
- Managing the weight and volume limits of the consol
- Handling short-shipped cargo and partial releases

---

## 2. The Consolidation Object

```sql
CREATE TABLE consolidation (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  consol_id         VARCHAR(64)   UNIQUE NOT NULL,   -- HCM-CONSOL-OCN-202604-001
  transport_mode    VARCHAR(8)    NOT NULL,            -- OCN / AIR
  service_type      VARCHAR(16)   NOT NULL,            -- LCL / CONSOL (air)

  -- Carrier and vessel
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  sailing_id        UUID          REFERENCES vessel_sailing(id),    -- ocean
  flight_id         UUID          REFERENCES flight_schedule(id),   -- air
  mbl_number        VARCHAR(32),
  mawb_number       VARCHAR(32),
  booking_ref       VARCHAR(64),   -- carrier booking reference

  -- Route
  pol_code          VARCHAR(10)   NOT NULL REFERENCES location(code),
  pod_code          VARCHAR(10)   NOT NULL REFERENCES location(code),
  cfs_origin        UUID          REFERENCES organisation(id),      -- origin CFS / co-loader
  cfs_destination   UUID          REFERENCES organisation(id),      -- destination CFS

  -- Dates
  etd               DATE,
  eta               DATE,
  cfs_cutoff        TIMESTAMPTZ,   -- cargo receiving cutoff at origin CFS
  doc_cutoff        TIMESTAMPTZ,   -- HBL issuance cutoff

  -- Capacity and utilisation
  container_type    VARCHAR(8),    -- 20GP / 40HC (for LCL)
  container_id      UUID          REFERENCES container(id),
  uld_number        VARCHAR(32),   -- for air consols
  max_weight_kg     NUMERIC(12,2),
  max_volume_cbm    NUMERIC(10,4),
  booked_weight_kg  NUMERIC(12,2) NOT NULL DEFAULT 0,   -- sum of all job cargo weights
  booked_volume_cbm NUMERIC(10,4) NOT NULL DEFAULT 0,   -- sum of all job volumes

  -- Status
  status            VARCHAR(32)   NOT NULL DEFAULT 'OPEN',
  -- OPEN: accepting new jobs
  -- CLOSED: no more jobs can be added (at CFS cutoff)
  -- DEPARTED: cargo loaded and vessel/flight departed
  -- ARRIVED: at POD
  -- COMPLETED: all jobs delivered and documents issued

  -- Ownership
  branch_id         UUID          NOT NULL REFERENCES branch(id),
  operator_id       UUID          REFERENCES app_user(id),

  -- Audit
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  created_by        UUID          REFERENCES app_user(id),
  updated_at        TIMESTAMPTZ
);

CREATE INDEX idx_consol_pol_pod ON consolidation (pol_code, pod_code, etd);
CREATE INDEX idx_consol_status  ON consolidation (status);
```

---

## 3. Adding a Job to a Consolidation

When an operator assigns a job to a consol, the system must:
1. Validate the job fits (mode, route, and available capacity)
2. Update the consol's booked weight and volume
3. Set `shipment.consol_id`
4. Recalculate proportional charges for all jobs in the consol

```python
def add_job_to_consol(consol_id: str, job_id: str) -> None:
    consol = fetch_consol(consol_id)
    job    = fetch_job(job_id)

    # Validation
    if consol.status != 'OPEN':
        raise ConsolClosedError(f"Consol {consol_id} is closed")
    if consol.pol_code != job.pol_code or consol.pod_code != job.pod_code:
        raise RouteMismatchError("Job route does not match consolidation route")
    if consol.transport_mode != job.transport_mode:
        raise ModeMismatchError("Job mode does not match consolidation mode")

    new_weight = consol.booked_weight_kg + job.cargo_detail.gross_weight_kg
    new_volume = consol.booked_volume_cbm + job.cargo_detail.volume_cbm

    if new_weight > consol.max_weight_kg or new_volume > consol.max_volume_cbm:
        raise CapacityExceededError("Consolidation capacity would be exceeded")

    # Assign job to consol
    db.execute("UPDATE shipment SET consol_id = ? WHERE id = ?", consol_id, job_id)

    # Update consol utilisation
    db.execute("""
        UPDATE consolidation SET
          booked_weight_kg  = ?,
          booked_volume_cbm = ?,
          updated_at        = now()
        WHERE id = ?
    """, new_weight, new_volume, consol_id)

    # Recalculate proportional charges for all jobs in this consol
    recalculate_consol_charges(consol_id)
```

---

## 4. Proportional Charge Allocation

Port-level charges (THC, ORC, DDF, CFS handling) belong to the consol as a whole. Each job receives a proportional share based on its W/M (revenue ton) relative to the total consol W/M.

```python
def recalculate_consol_charges(consol_id: str) -> None:
    """
    Recalculates and updates proportional charge lines for all jobs in the consol.
    Called whenever a job is added or removed.
    """
    jobs = fetch_jobs_in_consol(consol_id)

    # Calculate each job's chargeable weight (W/M)
    total_wm = sum(
        max(j.cargo_detail.gross_weight_kg / 1000, j.cargo_detail.volume_cbm)
        for j in jobs
    )

    # Consol-level charges (THC, ORC, CFS fees etc.)
    consol_charges = fetch_consol_level_charges(consol_id)

    for job in jobs:
        job_wm     = max(job.cargo_detail.gross_weight_kg / 1000, job.cargo_detail.volume_cbm)
        job_ratio  = job_wm / total_wm if total_wm > 0 else 0

        for consol_charge in consol_charges:
            job_share = consol_charge.total_amount * job_ratio

            # Upsert proportional charge line on the job
            upsert_charge_line(
                job_id       = job.id,
                charge_code  = consol_charge.charge_code,
                orig_amount  = job_share,
                orig_currency= consol_charge.currency,
                type         = consol_charge.type,   # BUY or SELL
                is_estimate  = True,
                source       = 'CONSOL_PROPORTIONAL',
                consol_id    = consol_id
            )
```

---

## 5. Consol Utilisation Display

The operator needs to see how full the consol is at a glance.

```sql
SELECT
  c.consol_id,
  c.max_weight_kg,
  c.max_volume_cbm,
  c.booked_weight_kg,
  c.booked_volume_cbm,
  ROUND(c.booked_weight_kg  / NULLIF(c.max_weight_kg,  0) * 100, 1) AS weight_pct,
  ROUND(c.booked_volume_cbm / NULLIF(c.max_volume_cbm, 0) * 100, 1) AS volume_pct,
  c.max_weight_kg  - c.booked_weight_kg  AS available_weight_kg,
  c.max_volume_cbm - c.booked_volume_cbm AS available_volume_cbm,
  COUNT(s.id)                            AS job_count
FROM consolidation c
LEFT JOIN shipment s ON s.consol_id = c.id
WHERE c.id = :consol_id
GROUP BY c.id;
```

---

## 6. Cargo Manifest

The cargo manifest is a summary of all cargo in the consol — sent to the carrier and destination CFS.

```sql
CREATE VIEW consol_manifest AS
SELECT
  c.consol_id,
  c.mbl_number,
  c.pol_code,
  c.pod_code,
  c.etd,
  s.shipment_id,
  s.hbl_number,
  jp_shipper.address_snapshot ->> 'name'    AS shipper,
  jp_consignee.address_snapshot ->> 'name'  AS consignee,
  cd.marks_numbers,
  cd.pieces,
  cd.gross_weight_kg,
  cd.volume_cbm,
  GREATEST(cd.gross_weight_kg / 1000, cd.volume_cbm) AS chargeable_wm,
  cd.commodity,
  cd.hs_code
FROM consolidation c
JOIN shipment s          ON s.consol_id = c.id
JOIN cargo_detail cd     ON cd.job_id   = s.id
LEFT JOIN job_party jp_shipper   ON jp_shipper.job_id  = s.id AND jp_shipper.role   = 'SHIPPER'
LEFT JOIN job_party jp_consignee ON jp_consignee.job_id = s.id AND jp_consignee.role = 'CONSIGNEE'
ORDER BY s.shipment_id;
```

---

## 7. Removing a Job from a Consol (Short-Ship)

When a job's cargo is not loaded (short-shipped) or the customer cancels, the job must be removed from the consol cleanly.

```python
def remove_job_from_consol(consol_id: str, job_id: str, reason: str) -> None:
    consol = fetch_consol(consol_id)
    job    = fetch_job(job_id)

    if consol.status == 'DEPARTED':
        raise ConsolDepartedError("Cannot remove a job from a departed consolidation")

    # Remove the assignment
    db.execute("UPDATE shipment SET consol_id = NULL WHERE id = ?", job_id)

    # Remove proportional charge lines sourced from this consol
    db.execute("""
        DELETE FROM charge_line
        WHERE job_id = ? AND source = 'CONSOL_PROPORTIONAL' AND consol_id = ?
    """, job_id, consol_id)

    # Update consol utilisation
    db.execute("""
        UPDATE consolidation SET
          booked_weight_kg  = booked_weight_kg  - ?,
          booked_volume_cbm = booked_volume_cbm - ?,
          updated_at        = now()
        WHERE id = ?
    """, job.cargo_detail.gross_weight_kg, job.cargo_detail.volume_cbm, consol_id)

    # Recalculate remaining jobs' shares
    recalculate_consol_charges(consol_id)

    # Write milestone and activity log
    write_milestone(job_id, 'SHORT_SHIPPED', reason=reason)
    log_activity(consol_id, 'consolidation', consol_id, 'JOB_REMOVED',
                new_value=f"Job {job.shipment_id} removed: {reason}")
```

---

## 8. Consol Closing Workflow

When the CFS cutoff passes, the consol is closed — no more jobs can be added.

```
OPEN → CLOSED (at CFS cutoff)
  — No more jobs can be added
  — Finalise all proportional charges
  — Issue all HBLs
  — Submit shipping instruction on MBL

CLOSED → DEPARTED (when vessel departs)
  — Write VESSEL_DEPARTED milestone on all child jobs
  — Send pre-alert to destination CFS / overseas agent

DEPARTED → ARRIVED (when vessel arrives at POD)
  — Write VESSEL_ARRIVED milestone on all child jobs
  — Send arrival notice to all consignees (via destination agent)

ARRIVED → COMPLETED (when all jobs are delivered and invoiced)
```

---

## 9. Golden Rules

1. **Proportional charges must be recalculated every time a job is added or removed.** Each job's share changes when the total W/M changes. Always recalculate all jobs, not just the new one.
2. **The consol owns port-level charges. Individual jobs own their own service charges.** THC, ORC, DDF belong to the consol. Customs brokerage, inland trucking, and document fees belong to the individual job.
3. **Proportional charge lines are flagged with `source = CONSOL_PROPORTIONAL`.** This makes them easy to identify and recalculate without touching other charge lines on the job.
4. **A departed consol cannot have jobs removed or added.** Any short-ship discovered after departure creates a separate amendment job and a credit note on the affected customer's invoice.
5. **One MBL per consol. Multiple HBLs.** The MBL is between the carrier and your company. Each job generates one HBL between your company and the customer. These are always separate documents even if only one job is in the consol.
