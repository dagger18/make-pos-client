# Rate Benchmarking

## Overview

Rate benchmarking compares your contracted carrier buy rates and quoted sell rates against published market indices. It answers two key questions:

- **Are we buying competitively?** — Is our carrier's contracted rate above or below the current market spot rate?
- **Are we pricing competitively?** — Are our quotes above or below market, and does that correlate with win rate?

Market rate data (Freightos Baltic Index, Drewry WCI, etc.) is fetched and stored by the **master API**. The client API enriches local rate card and quote data with these market rates on demand.

---

## Accessing the Report

Navigate to **Reports → Rate Benchmarking**. The page has three tabs:

1. **Buy vs Market** — compare your active carrier rate cards against the current market index
2. **Sell vs Market** — compare your average quoted sell rates against market, with win rate context
3. **Market Trend** — view historical market rate movement for a specific trade lane

---

## Tab 1: Buy vs Market

**Filters:**
- **Mode** — OCN, AIR, RD, etc. Leave blank for all modes.
- **Container Type** — 20GP, 40GP, 40HC, etc. Leave blank for all container types.

Click **Run Report** to load your active rate cards (valid today) enriched with the latest available market index rate for each lane.

**Columns:**
| Column | Description |
|--------|-------------|
| Carrier | Provider name from your rate card |
| POL / POD | Port of Loading / Discharge |
| Mode | Transport mode |
| Container | Container type |
| Buy Rate | Your contracted buy rate (buying_currency) |
| Market Rate | Latest market index rate for this lane |
| Index / Date | Which index and when it was last updated |
| vs Market | Buy Rate minus Market Rate (negative = you are below market) |
| Premium % | (Buy Rate − Market Rate) / Market Rate × 100 |

**Reading Premium %:**
- **Negative (green)** — you are buying below market. Strong position; protects your margin.
- **Positive (red)** — you are paying above market. Flag for carrier renegotiation when consistently above 10%.
- **—** — no market data available for this lane yet.

---

## Tab 2: Sell vs Market

**Filters:**
- **From / To** — the quote creation date range to analyse.
- **Mode** — optional mode filter.

Click **Run Report** to see, per trade lane, how your average quoted sell rate compares to the current market rate, and what your win rate was in that period.

**Columns:**
| Column | Description |
|--------|-------------|
| POL / POD | Trade lane |
| Mode | Transport mode |
| Avg Sell | Average total sell amount across all quotes in period (in quote currency) |
| Currency | Quote currency used |
| Market Rate | Current market index rate (USD) |
| Sell Premium % | (Avg Sell − Market Rate) / Market Rate × 100 |
| Quotes | Number of quotes sent in period |
| Won | Quotes with status Booked |
| Win Rate | Won / Quotes × 100% |

**Reading Sell Premium %:**
- **Positive (green)** — pricing above market. Good margin but may lose price-sensitive customers.
- **Negative (orange)** — pricing below market. If win rate is also low, the problem is not price — review service quality, relationships, or credit terms.
- **—** — no market comparison available.

> **Note:** Sell rates are in the quote currency; market rates are in USD. Cross-currency comparison is approximate. For accurate comparison, filter by USD-quoted lanes or normalise manually.

---

## Tab 3: Market Trend

Enter a **POL** and **POD** (UN/LOCODE format, e.g. `SGSIN` / `NLRTM`), select a container type and lookback period (days), then click **Fetch Trend**.

The table shows historical market rate data for that lane from the master index, ordered most recent first.

**Columns:**
| Column | Description |
|--------|-------------|
| Date | Rate date |
| Index | Source index (FBX, DREWRY_WCI, SCFI, etc.) |
| Rate | Market rate for that date |
| Currency | Rate currency (typically USD) |
| Week Change | Rate difference from prior week (red = rising cost, green = falling cost) |
| Change % | Percentage change week-over-week |

**Why use the trend?** Before quoting a large contract, check whether the market is trending up or down. A rising market means your contracted buy rates may become cheaper relative to spot — buy before the market peaks. A falling market means spot may undercut your contracted rates soon.

---

## API Reference

| Endpoint | Description |
|----------|-------------|
| `GET /api/rate-benchmark/buy?mode=OCN&container_type=40GP` | Active buy rates vs market |
| `GET /api/rate-benchmark/sell?from=YYYY-MM-DD&to=YYYY-MM-DD&mode=OCN` | Sell rate comparison by lane |
| `GET /api/rate-benchmark/trend?pol=SGSIN&pod=NLRTM&container_type=40GP&days=180` | Market rate trend (proxied from master API) |

All endpoints require `ROLE_USER`. Module: `quote`.

---

## Golden Rules (from spec)

1. Market indices are benchmarks, not targets — contract rates depend on volume and relationships.
2. Always check the index source and date — stale data is misleading.
3. FBX is spot; Xeneta is contract average — do not mix without labelling.
4. Buy rate consistently 10%+ above spot = carrier renegotiation trigger.
5. Pricing below market with low win rate = service/relationship problem, not price.
