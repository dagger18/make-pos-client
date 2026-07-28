# Freight Forwarder SaaS — Reporting and KPI Dashboards

## 1. What Reporting Covers

Reporting translates the operational and financial data from all other modules into management information. A freight forwarder needs reports across four dimensions: operations (what is happening), finance (what was earned), performance (how well), and customers (who drives value).

---

## 2. Report Categories

| Category | Audience | Frequency | Key questions answered |
|---|---|---|---|
| Operational | Operators, supervisors | Daily / real-time | What jobs need attention today? What is overdue? |
| Financial | Finance, management | Weekly / monthly | Revenue, cost, margin, cash position |
| Performance (KPI) | Management | Monthly | On-time %, operator productivity, quote conversion |
| Customer | Sales, management | Monthly | Top customers, profitability by customer |
| Carrier | Procurement | Monthly | Carrier cost, reliability, volume |

---

## 3. Operational Dashboard — Daily View

The daily operations dashboard is the first screen every operator sees. It shows their active jobs grouped by urgency.

### Jobs needing attention today

```sql
SELECT
  s.shipment_id,
  s.status,
  s.sub_status,
  s.transport_mode,
  s.etd,
  s.eta,
  l_pol.name        AS origin,
  l_pod.name        AS destination,

  -- Upcoming deadlines (hours remaining)
  EXTRACT(EPOCH FROM (s.cutoff_si    - now())) / 3600 AS hours_to_si_cutoff,
  EXTRACT(EPOCH FROM (s.cutoff_vgm   - now())) / 3600 AS hours_to_vgm_cutoff,
  EXTRACT(EPOCH FROM (s.cutoff_cargo - now())) / 3600 AS hours_to_cargo_cutoff,

  -- Overdue tasks
  (SELECT COUNT(*) FROM job_task t
   WHERE t.job_id = s.id AND t.is_mandatory = true
   AND t.completed_at IS NULL AND t.due_date < now())   AS overdue_tasks,

  -- Missing documents
  (SELECT COUNT(*) FROM job_document d
   WHERE d.job_id = s.id AND d.is_required = true
   AND d.is_received = false)                           AS missing_docs

FROM shipment s
JOIN location l_pol ON s.pol_code = l_pol.code
JOIN location l_pod ON s.pod_code = l_pod.code
WHERE s.operator_id = :user_id
  AND s.status NOT IN ('CLOSED', 'CANCELLED')
ORDER BY
  CASE
    WHEN overdue_tasks > 0     THEN 1
    WHEN hours_to_si_cutoff < 24 THEN 2
    WHEN hours_to_vgm_cutoff < 24 THEN 3
    ELSE 4
  END,
  s.etd ASC;
```

---

## 4. Financial KPI Report

### Revenue and margin by period and profit center

```sql
SELECT
  DATE_TRUNC('month', s.closed_at)                        AS period,
  pc.name                                                  AS profit_center,
  b.name                                                   AS branch,
  pc.direction,
  COUNT(DISTINCT s.id)                                     AS jobs_closed,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END) AS revenue,
  SUM(CASE WHEN cl.type='BUY'  THEN cl.base_amount ELSE 0 END) AS cost,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) AS gross_profit,
  ROUND(
    (SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
    - SUM(CASE WHEN cl.type='BUY'  THEN cl.base_amount ELSE 0 END))
    / NULLIF(SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END),0) * 100, 2
  )                                                        AS margin_pct
FROM charge_line cl
JOIN shipment s        ON cl.job_id           = s.id
JOIN profit_center pc  ON cl.profit_center_id = pc.id
JOIN branch b          ON pc.branch_id        = b.id
WHERE s.closed_at BETWEEN :from AND :to
GROUP BY DATE_TRUNC('month', s.closed_at), pc.id, pc.name, b.id, b.name, pc.direction
ORDER BY period DESC, gross_profit DESC;
```

---

## 5. Key Performance Indicators

### 5.1 On-Time Departure Rate

```sql
SELECT
  DATE_TRUNC('month', m_actual.actual_date)  AS period,
  s.transport_mode,
  COUNT(*)                                    AS total_departures,
  SUM(CASE WHEN m_actual.actual_date <= m_planned.planned_date
           + INTERVAL '24 hours' THEN 1 ELSE 0 END) AS on_time,
  ROUND(SUM(CASE WHEN m_actual.actual_date <= m_planned.planned_date
           + INTERVAL '24 hours' THEN 1 ELSE 0 END)::numeric
        / COUNT(*) * 100, 1)                 AS on_time_pct
FROM milestone m_actual
JOIN milestone m_planned ON m_planned.job_id       = m_actual.job_id
  AND m_planned.milestone_code = m_actual.milestone_code
JOIN shipment s ON m_actual.job_id = s.id
WHERE m_actual.milestone_code = 'VESSEL_DEPARTED'
  AND m_actual.actual_date IS NOT NULL
  AND m_planned.planned_date IS NOT NULL
GROUP BY DATE_TRUNC('month', m_actual.actual_date), s.transport_mode
ORDER BY period DESC;
```

### 5.2 Quote-to-Job Conversion Rate

```sql
SELECT
  DATE_TRUNC('month', q.created_at) AS period,
  COUNT(*)                           AS total_quotes,
  SUM(CASE WHEN q.status = 'ACCEPTED' THEN 1 ELSE 0 END) AS converted,
  SUM(CASE WHEN q.status = 'DECLINED' THEN 1 ELSE 0 END) AS declined,
  SUM(CASE WHEN q.status = 'EXPIRED'  THEN 1 ELSE 0 END) AS expired,
  ROUND(SUM(CASE WHEN q.status = 'ACCEPTED' THEN 1 ELSE 0 END)::numeric
        / COUNT(*) * 100, 1)         AS conversion_rate_pct
FROM (
  -- Take only the latest version per quote group
  SELECT DISTINCT ON (quote_group_id) *
  FROM quote
  ORDER BY quote_group_id, version DESC
) q
WHERE q.created_at BETWEEN :from AND :to
GROUP BY DATE_TRUNC('month', q.created_at)
ORDER BY period DESC;
```

### 5.3 Operator Productivity

```sql
SELECT
  u.name                              AS operator,
  b.name                              AS branch,
  DATE_TRUNC('month', s.created_at)   AS period,
  COUNT(DISTINCT s.id)                AS jobs_handled,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END) AS revenue_generated,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) AS profit_generated,
  AVG(EXTRACT(EPOCH FROM (s.closed_at - s.created_at)) / 86400) AS avg_job_duration_days
FROM shipment s
JOIN app_user u     ON s.operator_id    = u.id
JOIN branch b       ON s.branch_id      = b.id
JOIN charge_line cl ON cl.job_id        = s.id
WHERE s.created_at BETWEEN :from AND :to
  AND s.status = 'CLOSED'
GROUP BY u.id, u.name, b.id, b.name, DATE_TRUNC('month', s.created_at)
ORDER BY profit_generated DESC;
```

### 5.4 Average Days to Pay (DSO — Days Sales Outstanding)

```sql
SELECT
  DATE_TRUNC('month', i.issue_date)   AS period,
  o.name                              AS customer,
  AVG(EXTRACT(EPOCH FROM (p.payment_date - i.issue_date)) / 86400) AS avg_days_to_pay,
  SUM(p.paid_amount)                  AS total_paid
FROM ar_payment p
JOIN invoice i ON p.invoice_id   = i.id
JOIN organisation o ON i.billed_to_org = o.id
WHERE i.issue_date BETWEEN :from AND :to
GROUP BY DATE_TRUNC('month', i.issue_date), o.id, o.name
ORDER BY avg_days_to_pay DESC;
```

---

## 6. Top Lanes Report

```sql
SELECT
  s.pol_code,
  l_pol.name     AS origin,
  s.pod_code,
  l_pod.name     AS destination,
  s.transport_mode,
  COUNT(*)       AS shipment_count,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END) AS revenue,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) AS profit
FROM shipment s
JOIN location l_pol ON s.pol_code = l_pol.code
JOIN location l_pod ON s.pod_code = l_pod.code
JOIN charge_line cl ON cl.job_id  = s.id
WHERE s.closed_at BETWEEN :from AND :to
GROUP BY s.pol_code, l_pol.name, s.pod_code, l_pod.name, s.transport_mode
ORDER BY revenue DESC
LIMIT 20;
```

---

## 7. Customer Profitability Report

```sql
SELECT
  o.name                                   AS customer,
  o.tier,
  COUNT(DISTINCT s.id)                     AS total_jobs,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END) AS revenue,
  SUM(CASE WHEN cl.type='BUY'  THEN cl.base_amount ELSE 0 END) AS cost,
  SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
  - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END) AS profit,
  ROUND(
    (SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
    - SUM(CASE WHEN cl.type='BUY'  THEN cl.base_amount ELSE 0 END))
    / NULLIF(SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END), 0) * 100, 2
  )                                        AS margin_pct
FROM organisation o
JOIN job_party jp ON jp.organisation_id = o.id AND jp.role IN ('SHIPPER','CONSIGNEE')
JOIN shipment s   ON jp.job_id          = s.id
JOIN charge_line cl ON cl.job_id        = s.id
WHERE s.closed_at BETWEEN :from AND :to
GROUP BY o.id, o.name, o.tier
ORDER BY profit DESC
LIMIT 20;
```

---

## 8. Exception Report

The exception report shows jobs that have deviated from their planned timeline — the primary tool for operations managers.

```sql
SELECT
  s.shipment_id,
  s.transport_mode,
  m.milestone_code,
  mt.customer_label,
  m.planned_date,
  m.actual_date,
  m.exception_hours,
  CASE WHEN m.exception_hours > 0 THEN 'LATE' ELSE 'EARLY' END AS type,
  u.name   AS operator,
  b.name   AS branch
FROM milestone m
JOIN milestone_master mt ON m.milestone_code  = mt.code
JOIN shipment s          ON m.job_id          = s.id
JOIN app_user u          ON s.operator_id     = u.id
JOIN branch b            ON s.branch_id       = b.id
WHERE m.is_exception = true
  AND m.actual_date BETWEEN :from AND :to
ORDER BY ABS(m.exception_hours) DESC;
```

---

## 9. Report Scheduling and Export

```sql
CREATE TABLE report_schedule (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  report_key        VARCHAR(64)   NOT NULL,   -- FINANCIAL_MONTHLY / KPI_WEEKLY / EXCEPTION_DAILY
  name              VARCHAR(128)  NOT NULL,
  frequency         VARCHAR(16)   NOT NULL,   -- DAILY / WEEKLY / MONTHLY
  run_day           SMALLINT,                 -- day of week (0=Mon) or day of month (1–31)
  run_time          TIME          NOT NULL,
  format            VARCHAR(8)    NOT NULL DEFAULT 'XLSX',  -- XLSX / PDF / CSV
  recipients        TEXT[]        NOT NULL,   -- email addresses
  parameters        JSONB,                    -- default date ranges, profit center filters
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  last_run_at       TIMESTAMPTZ,
  next_run_at       TIMESTAMPTZ,
  created_by        UUID          REFERENCES app_user(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 10. Golden Rules

1. **All financial reports filter by `closed_at`, not `created_at`.** A job's revenue and cost belong to the period when it closed, not when it was created.
2. **Operational dashboards are real-time. Financial reports are period-end.** Never mix the two — a real-time revenue figure is meaningless because open jobs have estimated costs.
3. **Exception reports are the most actionable.** Revenue reports tell you what happened. Exception reports tell you what needs attention right now.
4. **Customer profitability is more useful than customer revenue.** A high-revenue customer with razor-thin margin may be less valuable than a lower-revenue customer with 25% margin.
5. **Reports are scheduled and delivered, not just available.** Managers should receive the reports they need in their inbox — not have to remember to log in and run them.
