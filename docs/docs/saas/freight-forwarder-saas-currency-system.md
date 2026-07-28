# Freight Forwarder SaaS — Currency and Money System Design

## 1. The Core Problem: Live Rates vs. Frozen Rates

Currency handling in a freight SaaS must answer two fundamentally different questions at any point in time:

- **"What is the current exchange rate?"** — for new quotes and jobs
- **"What was the rate when this charge was created?"** — for historical accuracy, audit, and P&L

These two needs pull in opposite directions if the data model is not designed carefully upfront.

### The two mistakes most systems make early on

**Mistake 1 — Store amounts only in one currency, convert on display.**
You lose the original currency context. When USD/VND moves, your historical reports change retroactively — a job that made profit last month now shows a loss. Margin figures become meaningless over time.

**Mistake 2 — Store only the converted amount.**
You can never reconstruct what the original charge was in its billing currency, cannot re-report in a different base currency, and cannot audit whether the correct rate was applied.

**The correct pattern:** Store both — the original amount in its original currency, plus the exchange rate that was in effect at the moment of creation. The converted base-currency amount is then a snapshot, calculated once on insert and never recalculated.

---

## 2. The Three-Table Foundation

### Table 1: `currency` — the registry

One row per supported currency. Never deleted — only deactivated.

```sql
CREATE TABLE currency (
  code           CHAR(3)       PRIMARY KEY,     -- ISO 4217: USD, VND, EUR, SGD
  name           VARCHAR(64)   NOT NULL,         -- "Vietnamese Dong"
  symbol         VARCHAR(8)    NOT NULL,         -- "₫", "$", "€"
  decimal_places SMALLINT      NOT NULL DEFAULT 2, -- VND = 0, USD = 2, KWD = 3
  is_active      BOOLEAN       NOT NULL DEFAULT true,
  rate_source    VARCHAR(32)                     -- 'ECB', 'FIXER', 'XE', 'MANUAL'
);
```

**`decimal_places` is critical.** VND has no fractional unit (0 decimal places). KWD (Kuwaiti Dinar) has 3. Always store monetary amounts as integers in the smallest unit (US cents, Vietnamese đồng) to avoid floating-point drift — or use `NUMERIC(20, 6)` with explicit scale and round on display.

**Seed data example:**

| code | name | symbol | decimal_places |
|---|---|---|---|
| USD | US Dollar | $ | 2 |
| VND | Vietnamese Dong | ₫ | 0 |
| EUR | Euro | € | 2 |
| SGD | Singapore Dollar | S$ | 2 |
| CNY | Chinese Yuan | ¥ | 2 |
| HKD | Hong Kong Dollar | HK$ | 2 |
| KWD | Kuwaiti Dinar | KD | 3 |
| JPY | Japanese Yen | ¥ | 0 |

---

### Table 2: `exchange_rate` — the append-only rate history

Every rate fetched or entered is a new row. This table is append-only — no updates, no deletes.

```sql
CREATE TABLE exchange_rate (
  id             UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  from_currency  CHAR(3)       NOT NULL REFERENCES currency(code),
  to_currency    CHAR(3)       NOT NULL REFERENCES currency(code),
  rate           NUMERIC(20,6) NOT NULL,   -- multiply from_currency by this to get to_currency
  rate_type      VARCHAR(16)   NOT NULL,   -- 'SPOT', 'MID', 'BUY', 'SELL', 'FIXED'
  effective_date DATE          NOT NULL,   -- calendar date this rate is valid for
  source         VARCHAR(32)   NOT NULL,   -- 'ECB', 'FIXER_IO', 'XE', 'SBV', 'MANUAL'
  fetched_at     TIMESTAMPTZ   NOT NULL DEFAULT now(),

  UNIQUE (from_currency, to_currency, rate_type, effective_date)
);

CREATE INDEX idx_er_lookup
  ON exchange_rate (from_currency, to_currency, rate_type, effective_date DESC);
```

**`rate_type` values:**

| Value | Meaning |
|---|---|
| `SPOT` | Live market mid-rate from an API feed |
| `MID` | Mid-point between buy and sell (common for reporting) |
| `BUY` | Bank buy rate (what the bank pays you for foreign currency) |
| `SELL` | Bank sell rate (what the bank charges you for foreign currency) |
| `FIXED` | Contractually agreed rate — overrides SPOT for a customer or period |
| `BUDGET` | Internal budget rate set at start of financial year for P&L planning |

**Finding the rate in effect on a given date:**

```sql
SELECT rate
FROM exchange_rate
WHERE from_currency  = 'USD'
  AND to_currency    = 'VND'
  AND rate_type      = 'SPOT'
  AND effective_date <= :shipment_date
ORDER BY effective_date DESC
LIMIT 1;
```

**Rate priority logic (when multiple types exist for the same date):**
`FIXED` > `SELL` / `BUY` > `MID` > `SPOT`

The application layer enforces priority — not a database constraint — because priority may differ by context (invoicing uses SELL, cost accounting uses MID, contract customers use FIXED).

---

### Table 3: `charge_line` — where value is preserved

This is the most important table. Every charge on every job stores three things together: the original amount, the exchange rate at the moment of creation, and the converted base-currency amount — all frozen at insert time.

```sql
CREATE TABLE charge_line (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES job(id),
  charge_code       VARCHAR(16)   NOT NULL,
  description       VARCHAR(255),
  category          VARCHAR(16)   NOT NULL,  -- FREIGHT / LOCAL / CUSTOMS / SERVICE
  direction         VARCHAR(16)   NOT NULL,  -- ORIGIN / DESTINATION / BOTH
  calc_basis        VARCHAR(32)   NOT NULL,  -- PER_CONTAINER / PER_WM / PER_KG / FLAT / PCT_VALUE

  -- Original amount (billing currency — what the customer sees or what the vendor charges)
  orig_currency     CHAR(3)       NOT NULL REFERENCES currency(code),
  orig_amount       NUMERIC(20,6) NOT NULL,

  -- Base currency snapshot (company reporting currency — frozen at creation time)
  base_currency     CHAR(3)       NOT NULL,   -- company's configured base currency
  fx_rate_snapshot  NUMERIC(20,6) NOT NULL,   -- rate copied from exchange_rate at insert time
  base_amount       NUMERIC(20,6) NOT NULL,   -- orig_amount * fx_rate_snapshot (computed, never changed)
  fx_rate_source    VARCHAR(32)   NOT NULL,   -- which rate_type was used: SPOT, FIXED, MANUAL
  fx_locked_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),

  -- Buy or sell side
  type              VARCHAR(8)    NOT NULL,   -- 'BUY' (cost) or 'SELL' (revenue)
  quantity          NUMERIC(12,4) NOT NULL DEFAULT 1,
  unit_rate         NUMERIC(20,6),            -- rate per unit before quantity
  is_estimate       BOOLEAN       NOT NULL DEFAULT false,
  payable_at        VARCHAR(16),              -- ORIGIN / DESTINATION

  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  created_by        UUID          REFERENCES app_user(id)
);
```

**`fx_rate_snapshot` is copied from `exchange_rate` at the moment the charge line is created and never touched again.** Even if the market rate moves the next day, this row always carries the rate at the time it was born.

**`base_amount` is calculated on insert:**

```
base_amount = orig_amount × fx_rate_snapshot
```

This is either stored as a computed/generated column or calculated in the application before insert. It is never recalculated after the fact — not even if the user corrects the exchange rate later. A correction creates a new charge line with an offsetting entry (a credit note pattern), not an update to the original row.

---

## 3. Answering Every Financial Question

| Question | How it is answered |
|---|---|
| What is today's rate for USD → VND? | Query `exchange_rate` for `effective_date = today`, order by DESC, LIMIT 1 |
| What was the rate when this charge was created? | Read `fx_rate_snapshot` directly off `charge_line` — no join needed |
| What was the total job cost in base currency at the time? | `SUM(base_amount) WHERE type = 'BUY'` on the job's charge lines |
| What is this job worth at today's rate? | `SUM(orig_amount)` × today's rate — clearly labelled as a **revaluation**, separate from historical P&L |
| How much margin did we make? | `SUM(base_amount) WHERE type='SELL'` − `SUM(base_amount) WHERE type='BUY'` using the snapshots already on each line |
| What is the per-currency invoice breakdown? | `SELECT orig_currency, SUM(orig_amount) GROUP BY orig_currency WHERE type='SELL'` |
| Did the rate applied match what was agreed with the customer? | Compare `fx_rate_snapshot` on the charge line against the contract rate in `exchange_rate WHERE rate_type='FIXED'` |

---

## 4. Rate Sources and Update Strategies

### Automatic daily market rates

A scheduled job (cron, Celery beat, pg_cron, etc.) runs once per day — typically at midnight or at a configured business-day start time — and inserts new rows into `exchange_rate` for every active currency pair.

**Common API providers:**

| Provider | Notes |
|---|---|
| European Central Bank (ECB) | Free, EUR-based, updates daily at ~16:00 CET |
| Fixer.io | Freemium, 170+ currencies, good uptime |
| Open Exchange Rates | USD-based, widely used |
| XE.com | Commercial, high reliability |
| State Bank of Vietnam (SBV) | Official VND rates — mandatory for Vietnamese compliance |
| OANDA | FX history API, good for audits |

**Rate fetch pseudocode:**

```python
def fetch_and_store_rates(base_currency: str, date: date, source: str):
    rates = call_fx_api(base=base_currency, date=date)
    rows = []
    for target_currency, rate in rates.items():
        rows.append({
            'from_currency': base_currency,
            'to_currency': target_currency,
            'rate': rate,
            'rate_type': 'SPOT',
            'effective_date': date,
            'source': source,
            'fetched_at': now()
        })
    # INSERT ... ON CONFLICT DO NOTHING (idempotent — safe to re-run)
    db.bulk_insert('exchange_rate', rows, on_conflict='do_nothing')
```

**Fallback logic:** If today's rate fetch fails, fall back to the most recent available rate for that pair. Log a warning. Never silently use a stale rate without flagging it.

### Manual rates

Finance staff can enter rates directly via the admin UI. These are inserted with `source = 'MANUAL'` and `rate_type = 'SPOT'` (or `FIXED`). A `created_by` field tracks who entered it for audit purposes.

### Contract / fixed rates

When a rate is agreed contractually with a customer or carrier:

```sql
INSERT INTO exchange_rate (
  from_currency, to_currency, rate, rate_type,
  effective_date, source
)
VALUES (
  'USD', 'VND', 24500.000000, 'FIXED',
  '2026-01-01',  -- rate applies from this date
  'MANUAL'
);
-- A second row with effective_date = end_date + 1 day and the next rate ends the contract period
```

The charge-line lookup checks for a `FIXED` rate first. If one exists and covers the shipment date, it is used and `fx_rate_source = 'FIXED'` is stored on the charge line. If not, fall back to `SPOT`.

---

## 5. Multi-Currency Job Totals

When a single job has charges in multiple currencies (ocean freight in USD, local charges in VND, customs duty in EUR), the job record carries a `base_currency` field. All internal P&L is computed through the frozen snapshots on each charge line.

```sql
-- Job-level P&L in base currency
SELECT
  SUM(CASE WHEN type = 'SELL' THEN base_amount ELSE 0 END) AS total_revenue,
  SUM(CASE WHEN type = 'BUY'  THEN base_amount ELSE 0 END) AS total_cost,
  SUM(CASE WHEN type = 'SELL' THEN base_amount ELSE 0 END)
  - SUM(CASE WHEN type = 'BUY' THEN base_amount ELSE 0 END) AS margin
FROM charge_line
WHERE job_id = :job_id;

-- Per-currency breakdown for the customer invoice
SELECT
  orig_currency,
  SUM(orig_amount) AS total_in_original_currency,
  MIN(fx_rate_snapshot) AS rate_applied   -- all lines for same currency on same job should share the same rate
FROM charge_line
WHERE job_id = :job_id
  AND type   = 'SELL'
GROUP BY orig_currency;
```

The customer invoice shows each charge in its original billing currency with the exchange rate noted beside it. The internal cost sheet and P&L always work in base currency using the frozen snapshots.

---

## 6. Revaluation vs. Historical P&L

There are two valid but completely different numbers for "what is this job worth in VND":

| Concept | Definition | When to use |
|---|---|---|
| **Historical P&L** | `SUM(base_amount)` using frozen snapshots | Job closing, AR/AP ledger, margin reporting |
| **Mark-to-market / revaluation** | `SUM(orig_amount)` × today's rate | Unrealised FX gain/loss on open jobs, treasury reporting |

These must never be mixed in the same report without explicit labelling. Most SaaS platforms keep them in separate report sections:

- **Closed jobs**: always show historical P&L only
- **Open jobs**: show historical cost (charges incurred so far at snapshot rates) plus a separate "unrealised FX exposure" line showing the revaluation delta

---

## 7. FX Gain and Loss on Settlement

When a customer pays an invoice, the payment amount in base currency may differ from the invoice amount in base currency — because the bank rate on the payment date differs from the rate that was frozen on the invoice charge lines.

This difference is an **FX gain or loss** and must be recorded separately:

```sql
CREATE TABLE payment (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  invoice_id        UUID          NOT NULL REFERENCES invoice(id),
  payment_date      DATE          NOT NULL,
  orig_currency     CHAR(3)       NOT NULL,
  orig_amount       NUMERIC(20,6) NOT NULL,  -- amount received
  base_currency     CHAR(3)       NOT NULL,
  fx_rate_at_payment NUMERIC(20,6) NOT NULL, -- bank rate on payment date
  base_amount       NUMERIC(20,6) NOT NULL,  -- orig_amount * fx_rate_at_payment
  fx_gain_loss      NUMERIC(20,6) NOT NULL   -- base_amount - invoice.base_amount_outstanding
);
```

`fx_gain_loss` = amount received in base currency (using payment-date rate) minus amount expected in base currency (using invoice snapshot rate). Positive = gain, negative = loss. This posts to an FX gain/loss account in the general ledger.

---

## 8. Rounding Rules

Rounding errors compound across large volumes if not handled consistently. Establish these rules explicitly in the codebase:

| Rule | Recommended approach |
|---|---|
| Intermediate calculations | Use `NUMERIC(20,6)` — never round mid-calculation |
| Display to users | Round to `decimal_places` of the display currency at the last moment |
| Storage | Store full precision (6 decimal places) always |
| Invoice line items | Round each line to currency decimal places, then sum the rounded lines |
| VND specifically | Round to nearest whole đồng (no decimal) — common to round to nearest 1,000 VND for invoices |
| Totals vs. sum of lines | Use `SUM(rounded_line_items)` not `ROUND(SUM(unrounded_lines))` — avoids penny discrepancies |

---

## 9. Audit Trail Requirements

Every change to a monetary value must be traceable. Key rules:

- Charge lines are **insert-only** after the job is accepted. No updates to `orig_amount`, `fx_rate_snapshot`, or `base_amount`.
- Corrections are made via **credit note + new charge line**, not by editing the original row.
- Every row carries `created_at` and `created_by`.
- The `exchange_rate` table is append-only — old rates are never overwritten.
- `fx_locked_at` on `charge_line` records the exact timestamp the rate was snapped, so it can be reconstructed against the `exchange_rate` history even years later.
- Regulatory environments (Vietnam, EU) may require using the official central bank rate. The `source` field on `exchange_rate` and `fx_rate_source` on `charge_line` provide the evidence trail for tax audits.

---

## 10. Rate Fetching Service Design

### Scheduler architecture

```
┌─────────────────────────────────────────┐
│           Rate Fetch Scheduler           │
│  (cron / Celery beat / pg_cron)         │
│  Runs: daily at 08:00 local time        │
└──────────────┬──────────────────────────┘
               │
       ┌───────▼────────┐
       │  Rate Fetcher  │
       │  Service       │
       └───────┬────────┘
               │
    ┌──────────┼──────────────┐
    ▼          ▼              ▼
 ECB API   SBV API       Fixer.io
(EUR base) (VND/USD)   (fallback)
    │          │              │
    └──────────┴──────────────┘
               │
    INSERT INTO exchange_rate
    ON CONFLICT DO NOTHING
               │
    ┌──────────▼──────────────┐
    │  Alert if any pair      │
    │  missing after fetch    │
    └─────────────────────────┘
```

### Failure handling

| Scenario | Action |
|---|---|
| API timeout | Retry up to 3 times with exponential backoff, then alert |
| API returns stale date | Reject the row — compare returned date to today |
| No rate available for pair | Fall back to previous day's rate; flag charge line with `fx_rate_source = 'STALE'` |
| Rate moves more than ±5% from previous day | Alert finance team — may indicate API error or genuine market event |
| Weekend / public holiday | Most APIs return Friday's rate for Saturday/Sunday — acceptable; log it |

### Rate cache

The fetcher writes to the database. The application reads from the database — never from an in-memory cache for monetary calculations. An in-memory cache is acceptable for display-only conversions (e.g. showing estimated totals in a quote UI), but the authoritative rate used in any persisted charge line must always come from the database's `exchange_rate` table.

---

## 11. Configuration

The following should be configurable per company/branch, not hardcoded:

| Setting | Description | Example |
|---|---|---|
| `base_currency` | Company's reporting currency | VND |
| `default_rate_type` | Which rate type to use when snapping | SPOT |
| `fx_source_priority` | Ordered list of sources to prefer | ['FIXED', 'SBV', 'ECB'] |
| `rate_staleness_threshold_days` | How many days old a rate can be before it is flagged | 3 |
| `auto_fetch_enabled` | Whether the daily fetcher runs | true |
| `fetch_time_utc` | Time of day to run the fetch job | 01:00 |
| `alert_threshold_pct` | % rate movement that triggers a finance alert | 5.0 |
| `rounding_mode` | HALF_UP / HALF_EVEN (banker's rounding) | HALF_UP |

---

## 12. Summary: The Golden Rules

1. **Never store only a converted amount.** Always store the original currency and original amount alongside the snapshot.
2. **Never recalculate historical snapshots.** A frozen rate is frozen forever. Corrections go through credit notes.
3. **The `exchange_rate` table is append-only.** Rate history is never overwritten — only new rows are added.
4. **`FIXED` rates take priority over `SPOT`.** Contract rates must override market rates when they exist for the relevant date.
5. **Keep historical P&L and revaluation completely separate.** Never mix frozen snapshots with today's rates in the same P&L figure without labelling.
6. **Round at the last moment, at full precision in storage.** All intermediate math uses `NUMERIC(20,6)`.
7. **Every rate application leaves an evidence trail.** `fx_rate_snapshot`, `fx_rate_source`, and `fx_locked_at` on every charge line support tax audits years later.
