# Freight Forwarder SaaS — Rate Benchmarking

## 1. What Rate Benchmarking Is

Rate benchmarking compares the forwarder's contracted carrier buy rates against published market indices and spot rates. It answers two questions:

- **Are we buying competitively?** (Buy rate vs. market)
- **Are we pricing competitively?** (Sell rate vs. market)

Without benchmarking, pricing decisions are made on intuition and historical habit. With benchmarking, procurement and sales have data to negotiate with carriers and justify pricing to customers.

---

## 2. Market Index Sources

| Index | Mode | Coverage | Update frequency | Access |
|---|---|---|---|---|
| Drewry World Container Index (WCI) | OCN | 8 major trade lanes | Weekly | Free (summary) / Paid (full) |
| Freightos Baltic Index (FBX) | OCN | 12 global routes | Daily | Free API |
| Shanghai Containerized Freight Index (SCFI) | OCN | Shanghai export | Weekly | Free |
| Xeneta | OCN + AIR | Aggregated contract rates | Daily | Subscription |
| TAC Index | AIR | Air cargo rates | Weekly | Subscription |
| WorldACD | AIR | Air cargo market data | Monthly | Subscription |
| DAT Freight | RD (USA) | Road freight spot rates | Daily | Subscription |

---

## 3. Market Rate Reference Table

```sql
CREATE TABLE market_rate_index (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  index_source      VARCHAR(32)   NOT NULL,   -- DREWRY_WCI / FBX / SCFI / XENETA / TAC
  pol_code          VARCHAR(10)   REFERENCES location(code),
  pod_code          VARCHAR(10)   REFERENCES location(code),
  trade_lane        VARCHAR(64),              -- "Asia-Europe" (for indices without specific ports)
  transport_mode    VARCHAR(8)    NOT NULL,
  container_type    VARCHAR(8),               -- 20GP / 40GP / 40HC / NULL for air (per kg)
  rate_type         VARCHAR(16)   NOT NULL,   -- SPOT / CONTRACT_AVERAGE / INDEX
  rate_amount       NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  rate_date         DATE          NOT NULL,
  fetched_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  source_url        TEXT,
  notes             TEXT,

  UNIQUE (index_source, pol_code, pod_code, container_type, rate_type, rate_date)
);

CREATE INDEX idx_mri_date ON market_rate_index (rate_date DESC);
CREATE INDEX idx_mri_lane ON market_rate_index (pol_code, pod_code, transport_mode);
```

---

## 4. Rate Feed Integration

```python
class FreightosBalticIndexConnector:
    """
    Freightos Baltic Index (FBX) — free daily ocean rate index.
    API returns rates for 12 global routes.
    """
    BASE_URL = "https://fbx.freightos.com/api/v1"

    def fetch_latest_rates(self) -> list[dict]:
        response = requests.get(f"{self.BASE_URL}/rates",
                                headers={"Authorization": f"Bearer {self.api_key}"})
        rates = response.json()

        normalised = []
        for r in rates:
            normalised.append({
                "index_source":   "FBX",
                "trade_lane":     r["route"],
                "pol_code":       self.map_pol(r["origin"]),
                "pod_code":       self.map_pod(r["destination"]),
                "transport_mode": "OCN",
                "container_type": "40GP",
                "rate_type":      "SPOT",
                "rate_amount":    r["rate_usd"],
                "currency":       "USD",
                "rate_date":      r["date"]
            })
        return normalised


def run_rate_index_fetch():
    """Scheduled daily — fetch all configured index sources."""
    connectors = [
        FreightosBalticIndexConnector(),
        DrewryWCIConnector(),
        # SCFIConnector(),  -- weekly, only run on Fridays
    ]
    for connector in connectors:
        rates = connector.fetch_latest_rates()
        bulk_upsert_market_rates(rates)
```

---

## 5. Buy Rate vs. Market Comparison

The core benchmarking query compares the forwarder's active carrier buy rates against the market index for the same trade lane.

```sql
SELECT
  rc.pol_code,
  l_pol.name       AS origin,
  rc.pod_code,
  l_pod.name       AS destination,
  o.name           AS carrier,
  rcl.container_type,

  -- Your buy rate
  rcl.base_rate                                          AS your_buy_rate,
  rc.currency                                            AS buy_currency,

  -- Latest market index rate (most recent available)
  mri.rate_amount                                        AS market_rate,
  mri.index_source,
  mri.rate_date,

  -- Variance
  rcl.base_rate - mri.rate_amount                        AS buy_vs_market,
  ROUND((rcl.base_rate - mri.rate_amount)
        / NULLIF(mri.rate_amount, 0) * 100, 1)          AS buy_premium_pct,
  -- Negative = you are buying below market (good)
  -- Positive = you are paying above market (bad)

  rc.effective_date,
  rc.expiry_date

FROM rate_card rc
JOIN rate_card_line rcl ON rcl.rate_card_id  = rc.id
JOIN organisation o     ON rc.carrier_id      = o.id
JOIN location l_pol     ON rc.pol_code        = l_pol.code
JOIN location l_pod     ON rc.pod_code        = l_pod.code
LEFT JOIN LATERAL (
  SELECT rate_amount, index_source, rate_date
  FROM market_rate_index
  WHERE pol_code       = rc.pol_code
    AND pod_code       = rc.pod_code
    AND container_type = rcl.container_type
    AND transport_mode = rc.transport_mode
    AND rate_type IN ('SPOT', 'INDEX')
  ORDER BY rate_date DESC
  LIMIT 1
) mri ON true
WHERE CURRENT_DATE BETWEEN rc.effective_date AND COALESCE(rc.expiry_date, '9999-12-31')
  AND rc.customer_id IS NULL   -- carrier rates, not customer contracts
ORDER BY buy_premium_pct DESC NULLS LAST;
```

---

## 6. Sell Rate Competitiveness

Compare the sell rates quoted to customers against market spot rates.

```sql
SELECT
  q.pol_code,
  q.pod_code,
  q.transport_mode,
  AVG(ql_sell.rate)                          AS avg_sell_rate,
  AVG(mri.rate_amount)                       AS avg_market_rate,
  ROUND(
    (AVG(ql_sell.rate) - AVG(mri.rate_amount))
    / NULLIF(AVG(mri.rate_amount), 0) * 100, 1
  )                                          AS sell_premium_pct,
  -- Positive = your sell price is above market (good margin, may lose quotes)
  -- Negative = your sell price is below market (competitive, may have thin margin)
  COUNT(DISTINCT q.id)                       AS quotes_in_period,
  SUM(CASE WHEN q.status = 'ACCEPTED' THEN 1 ELSE 0 END) AS won,
  ROUND(SUM(CASE WHEN q.status='ACCEPTED' THEN 1 ELSE 0 END)::numeric
        / COUNT(*) * 100, 1)                AS win_rate_pct
FROM quote q
JOIN quote_line ql_sell ON ql_sell.quote_id = q.id AND ql_sell.type = 'SELL'
  AND ql_sell.charge_code = 'OCEAN_FREIGHT'
LEFT JOIN market_rate_index mri ON mri.pol_code = q.pol_code
  AND mri.pod_code = q.pod_code
  AND mri.rate_date = q.created_at::date
WHERE q.created_at BETWEEN :from AND :to
GROUP BY q.pol_code, q.pod_code, q.transport_mode
ORDER BY sell_premium_pct DESC;
```

---

## 7. Rate Trend Chart Data

Historical market rate trend for a specific trade lane — used to show operators whether rates are rising or falling before they quote.

```sql
SELECT
  rate_date,
  index_source,
  rate_amount,
  currency,
  LAG(rate_amount) OVER (PARTITION BY index_source ORDER BY rate_date) AS prev_rate,
  rate_amount - LAG(rate_amount) OVER (PARTITION BY index_source ORDER BY rate_date) AS week_change,
  ROUND(
    (rate_amount - LAG(rate_amount) OVER (PARTITION BY index_source ORDER BY rate_date))
    / NULLIF(LAG(rate_amount) OVER (PARTITION BY index_source ORDER BY rate_date), 0) * 100, 2
  ) AS week_change_pct
FROM market_rate_index
WHERE pol_code       = :pol
  AND pod_code       = :pod
  AND container_type = '40GP'
  AND rate_date >= CURRENT_DATE - INTERVAL '6 months'
ORDER BY rate_date DESC;
```

---

## 8. Benchmarking Alerts

```sql
-- Alert when a carrier buy rate is more than 15% above market
SELECT
  rc.id    AS rate_card_id,
  o.name   AS carrier,
  rc.pol_code,
  rc.pod_code,
  rcl.base_rate,
  mri.rate_amount AS market_rate,
  ROUND((rcl.base_rate - mri.rate_amount) / mri.rate_amount * 100, 1) AS premium_pct
FROM rate_card rc
JOIN rate_card_line rcl ON rcl.rate_card_id = rc.id
JOIN organisation o     ON rc.carrier_id    = o.id
JOIN LATERAL (
  SELECT rate_amount FROM market_rate_index
  WHERE pol_code = rc.pol_code AND pod_code = rc.pod_code
    AND container_type = rcl.container_type
  ORDER BY rate_date DESC LIMIT 1
) mri ON true
WHERE CURRENT_DATE BETWEEN rc.effective_date AND COALESCE(rc.expiry_date, '9999-12-31')
  AND rcl.base_rate > mri.rate_amount * 1.15  -- 15% above market threshold
ORDER BY premium_pct DESC;
```

---

## 9. Golden Rules

1. **Market indices are benchmarks, not targets.** A forwarder cannot always buy at index prices — contract rates are often higher or lower depending on volume commitments and relationship. Use indices as context, not as a ceiling.
2. **Always note the index source and date.** A rate compared against a 3-month-old index is misleading. Every benchmarking display must show which index and when it was last updated.
3. **Different indices cover different things.** FBX is spot market. Xeneta aggregates contract rates. They answer different questions — do not mix them in the same comparison without labelling.
4. **Buy rate premium is a carrier negotiation trigger.** When a carrier's contracted buy rate is consistently 10%+ above the spot market, that is a signal for the procurement team to renegotiate.
5. **Sell rate below market with a low win rate is a pricing problem, not a volume problem.** If you are quoting below market and still losing, the problem is service, relationship, or credit terms — not price.
