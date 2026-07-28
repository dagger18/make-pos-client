# Freight Forwarder SaaS — Carrier Performance Scoring

## 1. What Carrier Performance Scoring Is

Carrier performance scoring is a systematic, data-driven way to evaluate how well each carrier delivers on time, honours bookings, and prices consistently. The scores feed the rate card lookup and booking recommendation engine — favouring reliable carriers over cheap but unreliable ones.

Without scoring, carrier selection is based on price alone or on personal relationships. With scoring, it becomes a measured, defensible commercial decision.

---

## 2. Performance Dimensions

| Dimension | What is measured | Weight |
|---|---|---|
| On-time departure | % of sailings that departed within 24h of scheduled ETD | 25% |
| On-time arrival | % of sailings that arrived within 24h of scheduled ETA | 25% |
| Booking acceptance | % of booking requests confirmed (vs rolled or rejected) | 20% |
| Schedule reliability | % of sailings that operated as scheduled (vs cancelled) | 15% |
| Rate consistency | % of AP bills within 5% of quoted rate | 10% |
| Claims rate | Number of cargo claims per 100 shipments | 5% |

---

## 3. Carrier Score Table

```sql
CREATE TABLE carrier_performance_score (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  period_year       SMALLINT      NOT NULL,
  period_month      SMALLINT      NOT NULL,
  transport_mode    VARCHAR(8)    NOT NULL,   -- OCN / AIR / RD

  -- Raw metrics
  sailings_total          INT     NOT NULL DEFAULT 0,
  sailings_on_time_dep    INT     NOT NULL DEFAULT 0,
  sailings_on_time_arr    INT     NOT NULL DEFAULT 0,
  sailings_cancelled      INT     NOT NULL DEFAULT 0,
  bookings_total          INT     NOT NULL DEFAULT 0,
  bookings_confirmed      INT     NOT NULL DEFAULT 0,
  bookings_rolled         INT     NOT NULL DEFAULT 0,
  ap_bills_total          INT     NOT NULL DEFAULT 0,
  ap_bills_within_tolerance INT   NOT NULL DEFAULT 0,
  cargo_claims            INT     NOT NULL DEFAULT 0,
  shipments_total         INT     NOT NULL DEFAULT 0,

  -- Calculated rates (%)
  on_time_dep_pct         NUMERIC(5,2),
  on_time_arr_pct         NUMERIC(5,2),
  schedule_reliability_pct NUMERIC(5,2),
  booking_acceptance_pct  NUMERIC(5,2),
  rate_consistency_pct    NUMERIC(5,2),
  claims_per_100          NUMERIC(6,3),

  -- Composite score (0.00 to 5.00)
  composite_score         NUMERIC(4,2),
  score_band              VARCHAR(8),    -- A / B / C / D / F

  calculated_at           TIMESTAMPTZ   NOT NULL DEFAULT now(),

  UNIQUE (carrier_id, period_year, period_month, transport_mode)
);
```

---

## 4. Score Calculation

```python
def calculate_carrier_score(carrier_id: str, year: int, month: int,
                             mode: str) -> CarrierScore:
    """
    Calculates composite carrier performance score for a given month.
    All source data comes from the live operational tables.
    """
    # On-time departure
    sailing_data = db.fetch_one("""
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN atd <= etd + INTERVAL '24 hours' THEN 1 ELSE 0 END) AS on_time_dep,
          SUM(CASE WHEN ata <= eta + INTERVAL '24 hours' THEN 1 ELSE 0 END) AS on_time_arr,
          SUM(CASE WHEN status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
        FROM vessel_sailing vs
        JOIN vessel_service vsr ON vs.service_id = vsr.id
        WHERE vsr.carrier_id = :carrier_id
          AND EXTRACT(YEAR FROM vs.etd)  = :year
          AND EXTRACT(MONTH FROM vs.etd) = :month
          AND vs.atd IS NOT NULL
    """, carrier_id=carrier_id, year=year, month=month)

    # Booking acceptance
    booking_data = db.fetch_one("""
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN status = 'CONFIRMED' THEN 1 ELSE 0 END) AS confirmed,
          SUM(CASE WHEN status = 'ROLLED'    THEN 1 ELSE 0 END) AS rolled
        FROM booking b
        JOIN shipment s ON b.job_id = s.id
        JOIN vessel_sailing vs ON b.sailing_id = vs.id
        JOIN vessel_service vsr ON vs.service_id = vsr.id
        WHERE vsr.carrier_id = :carrier_id
          AND EXTRACT(YEAR FROM b.created_at)  = :year
          AND EXTRACT(MONTH FROM b.created_at) = :month
    """, carrier_id=carrier_id, year=year, month=month)

    # Rate consistency
    billing_data = db.fetch_one("""
        SELECT
          COUNT(*) AS total,
          SUM(CASE WHEN ABS(abl.variance_pct) <= 5 THEN 1 ELSE 0 END) AS within_tolerance
        FROM ap_bill ab
        JOIN ap_bill_line abl ON abl.ap_bill_id = ab.id
        WHERE ab.vendor_id = :carrier_id
          AND EXTRACT(YEAR FROM ab.received_date)  = :year
          AND EXTRACT(MONTH FROM ab.received_date) = :month
    """, carrier_id=carrier_id, year=year, month=month)

    # Calculate rates
    otd_pct  = pct(sailing_data.on_time_dep, sailing_data.total)
    ota_pct  = pct(sailing_data.on_time_arr, sailing_data.total)
    srel_pct = pct(sailing_data.total - sailing_data.cancelled, sailing_data.total)
    bkng_pct = pct(booking_data.confirmed, booking_data.total)
    rate_pct = pct(billing_data.within_tolerance, billing_data.total)

    # Weighted composite score (0–100, then scale to 0–5)
    raw_score = (
        otd_pct  * 0.25 +
        ota_pct  * 0.25 +
        bkng_pct * 0.20 +
        srel_pct * 0.15 +
        rate_pct * 0.10 +
        max(0, 100 - (claims_per_100 * 20)) * 0.05
    )
    composite = round(raw_score / 20, 2)  # 0–5 scale

    band = 'A' if composite >= 4.5 else \
           'B' if composite >= 3.5 else \
           'C' if composite >= 2.5 else \
           'D' if composite >= 1.5 else 'F'

    return CarrierScore(composite_score=composite, score_band=band, ...)
```

---

## 5. Score Band Definitions

| Band | Score | Meaning | Commercial action |
|---|---|---|---|
| A | 4.5 – 5.0 | Excellent — consistently reliable | Preferred carrier, first allocation |
| B | 3.5 – 4.4 | Good — minor issues only | Standard carrier, second allocation |
| C | 2.5 – 3.4 | Average — noticeable reliability issues | Use when A/B unavailable |
| D | 1.5 – 2.4 | Poor — frequent delays and rate discrepancies | Escalate to procurement review |
| F | 0.0 – 1.4 | Failing — systematic problems | Suspend business pending improvement plan |

---

## 6. Using Scores in Booking Recommendations

When an operator selects a sailing, the carrier's current score band appears next to the rate:

```sql
SELECT
  vs.voyage_number,
  v.name          AS vessel,
  o.name          AS carrier,
  cps.score_band  AS carrier_score,
  cps.composite_score,
  cps.on_time_arr_pct,
  vs.etd,
  vs.eta,
  vs.transit_days,
  rc.base_rate    AS rate_20gp,
  rc.currency
FROM vessel_sailing vs
JOIN vessel_service vsr  ON vs.service_id  = vsr.id
JOIN organisation o      ON vsr.carrier_id  = o.id
JOIN vessel v            ON vs.vessel_id    = v.id
LEFT JOIN carrier_performance_score cps
  ON cps.carrier_id    = o.id
  AND cps.period_year  = EXTRACT(YEAR  FROM CURRENT_DATE - INTERVAL '1 month')
  AND cps.period_month = EXTRACT(MONTH FROM CURRENT_DATE - INTERVAL '1 month')
  AND cps.transport_mode = 'OCN'
LEFT JOIN rate_card rc
  ON rc.carrier_id    = o.id
  AND rc.pol_code     = vs.pol_code
  AND rc.pod_code     = vs.pod_code
  AND CURRENT_DATE    BETWEEN rc.effective_date AND COALESCE(rc.expiry_date, '9999-12-31')
WHERE vs.pol_code = :pol AND vs.pod_code = :pod
  AND vs.etd BETWEEN :earliest AND :latest
  AND vs.status = 'SCHEDULED'
ORDER BY cps.composite_score DESC NULLS LAST, vs.etd ASC;
```

---

## 7. Cargo Claims Tracking

```sql
CREATE TABLE cargo_claim (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  claim_type        VARCHAR(32)   NOT NULL,   -- LOSS / DAMAGE / DELAY / SHORT_DELIVERY
  claim_date        DATE          NOT NULL,
  claim_amount      NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  description       TEXT,
  status            VARCHAR(16)   NOT NULL DEFAULT 'OPEN',   -- OPEN / SETTLED / REJECTED / WITHDRAWN
  settlement_amount NUMERIC(20,6),
  settled_at        DATE,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 8. Golden Rules

1. **Scores are calculated monthly and stored — never computed on the fly.** Performance data is expensive to aggregate across thousands of jobs. Run a scheduled monthly job and store results in `carrier_performance_score`.
2. **Minimum data threshold required.** Do not assign a score to a carrier with fewer than 10 sailings or 5 jobs in the period — the sample is too small to be meaningful. Show "Insufficient data" instead.
3. **Scores are per mode.** An ocean carrier's reliability score is independent of its road subsidiary's performance. Always score per transport_mode.
4. **Scores are one input, not the only input.** A C-band carrier on a specific trade lane may be the only option. The score is a recommendation tool, not an automatic block.
5. **Claims feed the score with a one-month lag.** Claims take time to be confirmed and quantified. Use prior-month claims data in the current month's score calculation.
