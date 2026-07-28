# Freight Forwarder SaaS — Sales CRM

## 1. What Sales CRM Covers

The sales CRM manages the commercial pipeline — from first contact with a prospect through to a won customer generating live jobs. It tracks leads, opportunities, quote activities, win/loss outcomes, and sales rep targets. It is the layer that sits above the operational system and explains *why* certain customers have jobs and others do not.

Without a CRM, the sales team works from spreadsheets, sales activity is invisible to management, and there is no structured way to understand why quotes are won or lost.

---

## 2. Lead and Account Management

```sql
-- A prospect who is not yet an organisation in the system
CREATE TABLE crm_lead (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  company_name      VARCHAR(255)  NOT NULL,
  contact_name      VARCHAR(128),
  contact_email     VARCHAR(128),
  contact_phone     VARCHAR(32),
  country_code      CHAR(2)       REFERENCES country(code),
  industry          VARCHAR(64),
  estimated_volume  VARCHAR(32),              -- TEU/month estimate: "5-10 TEU"
  primary_mode      VARCHAR(8),               -- OCN / AIR / RD
  primary_trade     VARCHAR(64),              -- "Vietnam-US Imports"
  source            VARCHAR(32),              -- REFERRAL / LINKEDIN / COLD_CALL / TRADE_SHOW / INBOUND
  status            VARCHAR(16)   NOT NULL DEFAULT 'NEW',  -- NEW / CONTACTED / QUALIFIED / CONVERTED / DEAD
  assigned_to       UUID          REFERENCES app_user(id),
  converted_org_id  UUID          REFERENCES organisation(id),   -- set when lead becomes a customer
  converted_at      TIMESTAMPTZ,
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  created_by        UUID          REFERENCES app_user(id)
);

CREATE INDEX idx_lead_assigned ON crm_lead (assigned_to);
CREATE INDEX idx_lead_status   ON crm_lead (status);
```

---

## 3. Opportunity Pipeline

An opportunity is a specific commercial engagement — a customer considering a particular trade lane or service. One customer can have multiple simultaneous opportunities.

```sql
CREATE TABLE crm_opportunity (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  lead_id           UUID          REFERENCES crm_lead(id),
  organisation_id   UUID          REFERENCES organisation(id),    -- existing customer
  title             VARCHAR(255)  NOT NULL,                       -- "IKEA Vietnam OCN Import 2026"
  transport_mode    VARCHAR(8),
  service_type      VARCHAR(16),
  pol_code          VARCHAR(10)   REFERENCES location(code),
  pod_code          VARCHAR(10)   REFERENCES location(code),
  estimated_volume  NUMERIC(10,2),             -- estimated TEU or tons per month
  volume_uom        VARCHAR(8),                -- TEU / TON / CBM / SHIPMENTS
  estimated_revenue NUMERIC(20,6),
  currency          CHAR(3)       REFERENCES currency(code),
  stage             VARCHAR(32)   NOT NULL DEFAULT 'PROSPECTING',
  -- PROSPECTING / QUALIFICATION / PROPOSAL / NEGOTIATION / CLOSED_WON / CLOSED_LOST
  probability_pct   SMALLINT      NOT NULL DEFAULT 10,  -- 0-100
  expected_close    DATE,
  assigned_to       UUID          REFERENCES app_user(id),
  competitor        VARCHAR(128),                -- which forwarder they are currently using
  loss_reason       VARCHAR(64),                -- PRICE / SERVICE / RELATIONSHIP / CAPACITY / OTHER
  win_reason        VARCHAR(64),
  quote_id          UUID          REFERENCES quote(id),           -- linked when proposal is sent
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ,
  closed_at         TIMESTAMPTZ
);

CREATE INDEX idx_opp_assigned ON crm_opportunity (assigned_to);
CREATE INDEX idx_opp_stage    ON crm_opportunity (stage);
CREATE INDEX idx_opp_close    ON crm_opportunity (expected_close);
```

### Pipeline stage definitions

| Stage | Probability | Definition |
|---|---|---|
| `PROSPECTING` | 10% | Identified as a prospect — initial contact not yet made |
| `QUALIFICATION` | 25% | Contact made, freight volumes and requirements understood |
| `PROPOSAL` | 50% | Quote or rate proposal submitted to the prospect |
| `NEGOTIATION` | 75% | Rates agreed in principle, commercial terms being finalised |
| `CLOSED_WON` | 100% | Customer has committed, first job created |
| `CLOSED_LOST` | 0% | Lost to competitor or prospect decided not to ship |

---

## 4. Sales Activity Log

Every interaction with a prospect or customer is logged — calls, emails, meetings, visits.

```sql
CREATE TABLE crm_activity (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  opportunity_id    UUID          REFERENCES crm_opportunity(id),
  organisation_id   UUID          REFERENCES organisation(id),
  activity_type     VARCHAR(32)   NOT NULL,   -- CALL / EMAIL / MEETING / VISIT / QUOTE_SENT / FOLLOW_UP
  subject           VARCHAR(255),
  description       TEXT,
  outcome           VARCHAR(32),              -- POSITIVE / NEUTRAL / NEGATIVE / NO_ANSWER
  next_action       TEXT,
  next_action_date  DATE,
  performed_by      UUID          REFERENCES app_user(id),
  performed_at      TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 5. Sales Rep Targets

```sql
CREATE TABLE sales_target (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  sales_rep_id      UUID          NOT NULL REFERENCES app_user(id),
  period_year       SMALLINT      NOT NULL,
  period_month      SMALLINT,                 -- NULL = annual target
  target_type       VARCHAR(32)   NOT NULL,   -- REVENUE / PROFIT / NEW_CUSTOMERS / QUOTES_SENT
  target_value      NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       REFERENCES currency(code),
  branch_id         UUID          REFERENCES branch(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Sales rep performance vs target

```sql
SELECT
  u.name                                    AS sales_rep,
  st.period_year,
  st.period_month,
  st.target_type,
  st.target_value                           AS target,
  CASE st.target_type
    WHEN 'REVENUE' THEN
      SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
    WHEN 'PROFIT' THEN
      SUM(CASE WHEN cl.type='SELL' THEN cl.base_amount ELSE 0 END)
      - SUM(CASE WHEN cl.type='BUY' THEN cl.base_amount ELSE 0 END)
    WHEN 'NEW_CUSTOMERS' THEN
      COUNT(DISTINCT jp.organisation_id)
  END                                       AS achieved,
  ROUND(
    achieved / NULLIF(st.target_value, 0) * 100, 1
  )                                         AS achievement_pct
FROM sales_target st
JOIN app_user u        ON st.sales_rep_id = u.id
LEFT JOIN shipment s   ON s.sales_rep_id  = u.id
  AND EXTRACT(YEAR FROM s.closed_at)  = st.period_year
  AND (st.period_month IS NULL OR EXTRACT(MONTH FROM s.closed_at) = st.period_month)
LEFT JOIN charge_line cl ON cl.job_id = s.id
LEFT JOIN job_party jp   ON jp.job_id = s.id AND jp.role = 'SHIPPER'
GROUP BY u.name, st.period_year, st.period_month, st.target_type, st.target_value;
```

---

## 6. Quote Win/Loss Analysis

```sql
SELECT
  DATE_TRUNC('quarter', q.created_at)       AS period,
  q.transport_mode,
  q.direction,
  COUNT(*)                                   AS total_quotes,
  SUM(CASE WHEN q.status='ACCEPTED' THEN 1 ELSE 0 END) AS won,
  SUM(CASE WHEN q.status='DECLINED' THEN 1 ELSE 0 END) AS lost,
  SUM(CASE WHEN q.status='EXPIRED'  THEN 1 ELSE 0 END) AS expired,
  ROUND(SUM(CASE WHEN q.status='ACCEPTED' THEN 1 ELSE 0 END)::numeric
        / COUNT(*) * 100, 1)                AS win_rate_pct,

  -- Win/loss reasons (from linked opportunities)
  MODE() WITHIN GROUP (ORDER BY o.win_reason)  AS top_win_reason,
  MODE() WITHIN GROUP (ORDER BY o.loss_reason) AS top_loss_reason
FROM (
  SELECT DISTINCT ON (quote_group_id) * FROM quote
  ORDER BY quote_group_id, version DESC
) q
LEFT JOIN crm_opportunity o ON o.quote_id = q.id
GROUP BY DATE_TRUNC('quarter', q.created_at), q.transport_mode, q.direction
ORDER BY period DESC;
```

---

## 7. Sales Commission

If sales reps earn commission on the jobs they bring in:

```sql
CREATE TABLE sales_commission (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  sales_rep_id      UUID          NOT NULL REFERENCES app_user(id),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  commission_basis  VARCHAR(32)   NOT NULL,   -- PCT_REVENUE / PCT_PROFIT / FLAT
  commission_rate   NUMERIC(6,4),
  base_amount       NUMERIC(20,6) NOT NULL,
  commission_amount NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  status            VARCHAR(16)   NOT NULL DEFAULT 'CALCULATED',  -- CALCULATED / APPROVED / PAID
  period_month      CHAR(7),                  -- YYYY-MM — commission period
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 8. Golden Rules

1. **Leads and organisations are separate objects.** A lead becomes an organisation only when they are converted — not before. Never create an org record for a prospect who has not committed.
2. **Opportunity probability drives pipeline forecasting.** The weighted pipeline value (`estimated_revenue × probability_pct`) is the key sales management metric — not the nominal total of all open opportunities.
3. **Win and loss reasons are mandatory on close.** The insight is worthless if sales reps close opportunities without recording why. Make loss_reason required when moving to CLOSED_LOST.
4. **Quote win rate is tracked per mode and trade lane.** A 30% win rate on Asia-Europe may be excellent; 30% on intra-Asia may be poor. Segment the analysis to be useful.
5. **Commission is calculated at job close, not at quote acceptance.** Commission should reflect actual delivered revenue and profit — not the quote estimate.
