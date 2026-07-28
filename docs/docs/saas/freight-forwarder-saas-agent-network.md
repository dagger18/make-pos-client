# Freight Forwarder SaaS — Overseas Agent Network

## 1. What the Overseas Agent Network Is

A freight forwarder's ability to offer door-to-door service globally depends entirely on its network of overseas agents — partner forwarding companies at origins and destinations where the forwarder has no own office.

The overseas agent network module manages:
- The agent directory (which agents cover which countries and modes)
- Inter-office job creation (when both origin and destination are your own offices)
- Agent job instructions (structured pre-alerts and job assignments to external agents)
- Agent cost settlement (invoices received from and paid to agents)
- Agent commission tracking (for network membership agreements)
- Agent performance scoring

---

## 2. Agent Directory

Built on top of the `agent_profile` table (defined in the address book document), the agent directory provides a searchable registry of which agents cover which routes and modes.

```sql
-- Find agents covering a destination country for a given mode
SELECT
  o.id,
  o.name,
  o.code,
  ap.agent_code,
  ap.network,
  ap.performance_score,
  ap.commission_rate,
  ap.settlement_currency,
  ap.settlement_terms,
  o.credit_status
FROM agent_profile ap
JOIN organisation o ON ap.organisation_id = o.id
WHERE o.credit_status != 'BLACKLISTED'
  AND ap.modes_handled @> ARRAY[:mode]          -- agent handles this mode
  AND ap.coverage_countries @> ARRAY[:country]  -- agent covers destination country
ORDER BY ap.performance_score DESC NULLS LAST, o.name;
```

---

## 3. Agent Job Assignment

When a job has a destination that requires an overseas agent, the system generates a **job instruction** — a structured message containing everything the agent needs to handle their side.

```sql
CREATE TABLE agent_job_instruction (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  agent_id          UUID          NOT NULL REFERENCES organisation(id),
  instruction_type  VARCHAR(32)   NOT NULL,   -- PRE_ALERT / BOOKING_REQUEST / DO_REQUEST / FINAL_DOCS
  status            VARCHAR(16)   NOT NULL DEFAULT 'DRAFT',   -- DRAFT / SENT / ACKNOWLEDGED / COMPLETED
  agent_job_ref     VARCHAR(64),              -- the agent's own job reference (filled when acknowledged)
  sent_at           TIMESTAMPTZ,
  acknowledged_at   TIMESTAMPTZ,
  payload           JSONB         NOT NULL,   -- full instruction data (job details, charges, docs)
  notes             TEXT,
  created_by        UUID          REFERENCES app_user(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Instruction types and when they are sent

| Type | Trigger | Contains |
|---|---|---|
| `PRE_ALERT` | VESSEL_DEPARTED milestone | HBL, MBL, cargo details, destination charges, ETD/ETA |
| `BOOKING_REQUEST` | Quote accepted (cross-trade) | Request agent to make the origin booking |
| `DO_REQUEST` | Customs cleared | Request agent to issue D/O to consignee |
| `FINAL_DOCS` | Job closed | All original documents, final invoice, settlement request |

---

## 4. Inter-Office Job Linking

When both origin and destination are your own branches, the same shipment generates two linked jobs — one for each branch. This is distinct from the overseas agent scenario because both jobs are managed inside your own system.

```sql
CREATE TABLE inter_office_link (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  export_job_id     UUID          NOT NULL REFERENCES shipment(id),   -- origin branch job
  import_job_id     UUID          NOT NULL REFERENCES shipment(id),   -- destination branch job
  linking_type      VARCHAR(16)   NOT NULL DEFAULT 'INTER_OFFICE',
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE UNIQUE INDEX idx_iol_export ON inter_office_link (export_job_id);
CREATE UNIQUE INDEX idx_iol_import ON inter_office_link (import_job_id);
```

When an inter-office link exists:
- The export job (origin branch) owns all ORIGIN charge lines and invoices the shipper
- The import job (destination branch) owns all DESTINATION charge lines and invoices the consignee
- Neither branch's operators can see each other's charge lines (RLS applies per profit center)
- Milestones are shared — a milestone written on either job appears in the combined timeline

### Inter-office cost transfer

When the export branch incurs a cost on behalf of the import branch (or vice versa), an internal transfer is created rather than an external invoice:

```sql
CREATE TABLE inter_office_transfer (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  from_branch_id    UUID          NOT NULL REFERENCES branch(id),
  to_branch_id      UUID          NOT NULL REFERENCES branch(id),
  from_job_id       UUID          NOT NULL REFERENCES shipment(id),
  to_job_id         UUID          NOT NULL REFERENCES shipment(id),
  charge_line_id    UUID          NOT NULL REFERENCES charge_line(id),
  amount            NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  base_amount       NUMERIC(20,6) NOT NULL,
  fx_rate           NUMERIC(20,6) NOT NULL,
  status            VARCHAR(16)   NOT NULL DEFAULT 'PENDING',   -- PENDING / SETTLED
  settled_at        TIMESTAMPTZ,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 5. Agent Cost Settlement

The overseas agent invoices the forwarder for their services on the destination leg. This is an AP bill from an external party but with special handling because:
- The bill covers charges that were estimated on the job at quote time
- Variance between estimated and actual agent charges is common
- Settlement may be in a different currency than the job's base currency

```python
def process_agent_invoice(job_id: str, agent_id: str, agent_invoice: dict) -> str:
    """
    Process an invoice received from an overseas agent.
    Matches each line to the corresponding BUY charge line on the job.
    """
    ap_bill_id = create_ap_bill(
        job_id       = job_id,
        vendor_id    = agent_id,
        vendor_ref   = agent_invoice['invoice_number'],
        currency     = agent_invoice['currency'],
        total_amount = agent_invoice['total'],
        received_date = date.today()
    )

    for line in agent_invoice['lines']:
        # Find the matching BUY charge line
        charge_line = find_matching_charge_line(
            job_id      = job_id,
            charge_code = normalise_charge_code(line['description']),
            type        = 'BUY',
            party_role  = 'OVERSEAS_AGENT'
        )

        create_ap_bill_line(
            ap_bill_id     = ap_bill_id,
            charge_line_id = charge_line.id if charge_line else None,
            charge_code    = line.get('code', 'AGENT_MISC'),
            billed_amount  = line['amount'],
            expected_amount = charge_line.orig_amount if charge_line else None
        )

    return ap_bill_id
```

---

## 6. Agent Commission

When a job is cross-trade or involves a network partner, a commission may be due to the agent (or from the agent to your company). Commission is tracked as a separate charge line type.

```sql
CREATE TABLE agent_commission (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  agent_id          UUID          NOT NULL REFERENCES organisation(id),
  direction         VARCHAR(16)   NOT NULL,   -- PAYABLE (you pay agent) / RECEIVABLE (agent pays you)
  commission_basis  VARCHAR(32)   NOT NULL,   -- PCT_REVENUE / PCT_PROFIT / FLAT
  commission_rate   NUMERIC(6,4),             -- for PCT types
  flat_amount       NUMERIC(20,6),            -- for FLAT type
  base_amount       NUMERIC(20,6) NOT NULL,   -- amount the commission is calculated on
  commission_amount NUMERIC(20,6) NOT NULL,   -- calculated commission
  currency          CHAR(3)       NOT NULL,
  status            VARCHAR(16)   NOT NULL DEFAULT 'CALCULATED',   -- CALCULATED / INVOICED / PAID
  invoice_id        UUID          REFERENCES invoice(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 7. Agent Performance Scoring

Agent performance is scored based on measured outcomes — not opinions. The score (0.00 to 5.00) feeds the agent directory ranking.

```sql
CREATE VIEW agent_performance_metrics AS
SELECT
  jp.organisation_id                    AS agent_id,
  o.name                                AS agent_name,
  COUNT(DISTINCT s.id)                  AS total_jobs,

  -- On-time delivery rate
  ROUND(AVG(CASE
    WHEN m_del.actual_date <= m_del.planned_date + INTERVAL '24 hours' THEN 1.0
    ELSE 0.0
  END) * 100, 1)                        AS on_time_delivery_pct,

  -- Average milestone response time (hours between PRE_ALERT sent and acknowledged)
  ROUND(AVG(EXTRACT(EPOCH FROM (
    aji.acknowledged_at - aji.sent_at
  )) / 3600), 1)                        AS avg_response_hours,

  -- AP bill variance rate
  ROUND(AVG(ABS(abl.variance) / NULLIF(abl.expected_amount, 0)) * 100, 2)
                                        AS avg_cost_variance_pct,

  -- Dispute rate (jobs with disputed agent invoices)
  ROUND(SUM(CASE WHEN ab.status = 'DISPUTED' THEN 1 ELSE 0 END)::numeric
        / NULLIF(COUNT(DISTINCT s.id), 0) * 100, 1)
                                        AS dispute_rate_pct

FROM job_party jp
JOIN organisation o  ON jp.organisation_id = o.id
JOIN shipment s      ON jp.job_id          = s.id
JOIN milestone m_del ON m_del.job_id       = s.id AND m_del.milestone_code = 'DELIVERED'
JOIN agent_job_instruction aji ON aji.job_id = s.id AND aji.agent_id = jp.organisation_id
JOIN ap_bill ab ON ab.job_id = s.id AND ab.vendor_id = jp.organisation_id
JOIN ap_bill_line abl ON abl.ap_bill_id = ab.id
WHERE jp.role = 'OVERSEAS_AGENT'
  AND s.closed_at >= CURRENT_DATE - INTERVAL '12 months'
GROUP BY jp.organisation_id, o.name;
```

The `performance_score` on `agent_profile` is updated monthly from this view.

---

## 8. Golden Rules

1. **Agent directory coverage is maintained by your team.** Agents do not self-register — your operations team maintains which agents cover which countries and modes, and updates coverage as agent relationships change.
2. **Inter-office jobs are two separate jobs sharing one shipment identity.** The export job and import job each have their own shipment_id, charge lines, and profit center. They are linked via `inter_office_link` but managed independently.
3. **Agent pre-alerts are sent automatically on VESSEL_DEPARTED.** Manual pre-alerts are a common failure point. Automate this — the milestone triggers the instruction generation and email.
4. **Agent AP bills are matched to estimated charge lines.** The estimated cost at quote time is the benchmark. Variances are tracked and used to improve future estimates for that agent on that lane.
5. **Commission is calculated and stored immediately when the job is closed.** Do not calculate commission at payment time — it should be a fixed fact tied to the job, not subject to FX movements after the fact.
