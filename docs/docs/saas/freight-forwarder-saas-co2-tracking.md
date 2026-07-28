# Freight Forwarder SaaS — CO2 and Carbon Emissions Tracking

## 1. Why Carbon Tracking Is Becoming a Requirement

Enterprise shippers — particularly those with listed parent companies, EU operations, or sustainability commitments — increasingly require their logistics partners to report CO2 emissions per shipment. The EU Carbon Border Adjustment Mechanism (CBAM), Scope 3 emissions reporting under GHG Protocol, and the EU Corporate Sustainability Reporting Directive (CSRD) are pushing this from "nice to have" to a commercial requirement for winning large accounts.

A freight forwarder that can provide accurate, methodology-compliant emissions data per shipment has a competitive advantage over one that cannot.

---

## 2. Emission Calculation Methodology

The two dominant standards are:

| Standard | Owner | Used by |
|---|---|---|
| GLEC Framework v3 | Smart Freight Centre | Logistics industry, EU preferred |
| GHG Protocol Scope 3 | WBCSD / WRI | Corporate sustainability reporting |

Both use the same basic formula:

```
CO2e (kg) = Activity Data × Emission Factor

Activity Data = distance (km) × weight (tonnes)  → tonne-km
OR
Activity Data = fuel consumed (litres)            → direct fuel method

Emission Factor = kg CO2e per tonne-km (or per litre of fuel)
  — varies by transport mode, fuel type, vehicle/vessel type, load factor
```

---

## 3. Emission Factor Table

```sql
CREATE TABLE emission_factor (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  transport_mode    VARCHAR(8)    NOT NULL,   -- OCN / AIR / RD / RAL
  vehicle_type      VARCHAR(64),              -- CONTAINER_SHIP / BULK / AIRCRAFT / TRUCK_RIGID / TRAIN
  fuel_type         VARCHAR(32),              -- HFO / MDO / LNG / JET_A1 / DIESEL / ELECTRIC
  size_class        VARCHAR(32),              -- for vessels: >8000TEU / 4000-8000TEU etc.
  load_factor       NUMERIC(4,2),             -- assumed load factor: 0.70 = 70% full
  ef_ttw            NUMERIC(12,6) NOT NULL,   -- Tank-to-Wake: kg CO2e per tonne-km
  ef_wtw            NUMERIC(12,6) NOT NULL,   -- Well-to-Wake: includes fuel production emissions
  methodology       VARCHAR(32)   NOT NULL,   -- GLEC_V3 / GHG_PROTOCOL / IMO_DCS
  effective_from    DATE          NOT NULL,
  effective_to      DATE,
  source            VARCHAR(128)  NOT NULL,   -- "GLEC Framework v3 Table 4.2"
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Default emission factors (GLEC Framework v3, TTW)

| Mode | Vehicle type | EF (kg CO2e / tonne-km) |
|---|---|---|
| OCN | Container ship >8000 TEU | 0.00567 |
| OCN | Container ship 4000–8000 TEU | 0.00800 |
| OCN | Container ship <4000 TEU | 0.01100 |
| AIR | Belly cargo (passenger aircraft) | 0.60200 |
| AIR | Freighter aircraft | 0.78600 |
| RD | Articulated truck >34t | 0.06200 |
| RD | Rigid truck 7.5–12t | 0.17000 |
| RAL | Freight train | 0.02800 |

---

## 4. Emissions Record Per Job

```sql
CREATE TABLE shipment_emission (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),

  -- Calculation inputs
  transport_mode    VARCHAR(8)    NOT NULL,
  emission_factor_id UUID         NOT NULL REFERENCES emission_factor(id),
  distance_km       NUMERIC(10,2) NOT NULL,
  cargo_weight_tonnes NUMERIC(12,4) NOT NULL,
  tonne_km          NUMERIC(16,4) NOT NULL,   -- distance_km × cargo_weight_tonnes

  -- Results
  co2e_ttw_kg       NUMERIC(16,4) NOT NULL,   -- Tank-to-Wake (operational emissions)
  co2e_wtw_kg       NUMERIC(16,4) NOT NULL,   -- Well-to-Wake (full lifecycle)
  methodology       VARCHAR(32)   NOT NULL,
  is_estimate       BOOLEAN       NOT NULL DEFAULT true,   -- false when actual fuel data available

  -- For multi-leg jobs
  leg_sequence      SMALLINT      NOT NULL DEFAULT 1,
  leg_description   VARCHAR(64),              -- "Origin trucking" / "Ocean leg" / "Destination trucking"

  -- Audit
  calculated_at     TIMESTAMPTZ   NOT NULL DEFAULT now(),
  calculated_by     VARCHAR(32)   NOT NULL DEFAULT 'SYSTEM'
);

CREATE INDEX idx_emission_job ON shipment_emission (job_id);
```

---

## 5. Distance Calculation

Distance is calculated between the port of loading and port of discharge. For ocean, the standard sea distance database is used; for air, great-circle distance with a detour factor.

```python
def calculate_shipping_distance(pol_code: str, pod_code: str,
                                 transport_mode: str) -> float:
    """Returns distance in km."""

    if transport_mode == 'OCN':
        # Use sea distance database (SeaRates, Searoutes API, or pre-loaded table)
        sea_dist = fetch_sea_distance(pol_code, pod_code)
        return sea_dist

    elif transport_mode == 'AIR':
        # Great-circle distance with GLEC detour factor of 1.09
        gc_dist = haversine_distance(pol_code, pod_code)
        return gc_dist * 1.09  # GLEC Framework detour factor for air

    elif transport_mode == 'RD':
        # Road distance from routing API or pre-calculated table
        return fetch_road_distance(pol_code, pod_code)

    elif transport_mode == 'RAL':
        gc_dist = haversine_distance(pol_code, pod_code)
        return gc_dist * 1.20  # Rail detour factor

def calculate_job_emissions(job_id: str) -> list[dict]:
    job = fetch_job(job_id)
    legs = []

    # Main transport leg
    distance = calculate_shipping_distance(job.pol_code, job.pod_code, job.transport_mode)
    ef       = fetch_best_emission_factor(job.transport_mode, job.booking)
    weight_t = get_cargo_weight_tonnes(job)
    tonne_km = distance * weight_t

    legs.append({
        "leg": "main",
        "distance_km":         distance,
        "cargo_weight_tonnes": weight_t,
        "tonne_km":            tonne_km,
        "co2e_ttw_kg":         tonne_km * ef.ef_ttw,
        "co2e_wtw_kg":         tonne_km * ef.ef_wtw,
        "emission_factor_id":  ef.id
    })

    # Add inland trucking legs if job has place_of_receipt or place_of_delivery
    # (simplified — full implementation would geocode these addresses)

    return legs
```

---

## 6. Cargo Weight for Emission Calculations

```python
def get_cargo_weight_tonnes(job: Job) -> float:
    """
    For LCL/air: use actual gross weight.
    For FCL: use average cargo weight per TEU if cargo weight not declared.
    GLEC default: 10 tonnes per 20GP, 14 tonnes per 40GP.
    """
    if job.cargo_detail and job.cargo_detail.gross_weight_kg:
        return job.cargo_detail.gross_weight_kg / 1000

    # FCL with no declared cargo weight — use GLEC defaults
    total_tonnes = 0
    for container in job.containers:
        if container.container_type.startswith('20'):
            total_tonnes += 10.0   # GLEC default 20GP
        elif container.container_type.startswith('40'):
            total_tonnes += 14.0   # GLEC default 40GP

    return total_tonnes
```

---

## 7. Customer Emissions Report

The emissions report is delivered to customers on request, typically monthly or per shipment.

```sql
SELECT
  s.shipment_id,
  s.transport_mode,
  l_pol.name                    AS origin,
  l_pod.name                    AS destination,
  s.etd,
  SUM(se.distance_km)           AS total_distance_km,
  SUM(se.cargo_weight_tonnes)   AS cargo_weight_t,
  SUM(se.co2e_ttw_kg)           AS co2e_ttw_kg,
  SUM(se.co2e_wtw_kg)           AS co2e_wtw_kg,
  se.methodology
FROM shipment_emission se
JOIN shipment s     ON se.job_id    = s.id
JOIN job_party jp   ON jp.job_id    = s.id AND jp.organisation_id = :customer_org_id
JOIN location l_pol ON s.pol_code   = l_pol.code
JOIN location l_pod ON s.pod_code   = l_pod.code
WHERE s.closed_at BETWEEN :from AND :to
GROUP BY s.id, s.shipment_id, s.transport_mode, l_pol.name, l_pod.name, s.etd, se.methodology
ORDER BY s.etd DESC;
```

---

## 8. Sea Distance Reference Table

Rather than calling an external API for every emission calculation, pre-load a reference table of port-pair sea distances for the top 1,000 trade lanes:

```sql
CREATE TABLE sea_distance (
  pol_code    VARCHAR(10) NOT NULL REFERENCES location(code),
  pod_code    VARCHAR(10) NOT NULL REFERENCES location(code),
  distance_km NUMERIC(10,2) NOT NULL,
  via_canal   VARCHAR(16),   -- SUEZ / PANAMA / CAPE / MALACCA
  source      VARCHAR(32)  NOT NULL DEFAULT 'SEAROUTES',
  updated_at  DATE,
  PRIMARY KEY (pol_code, pod_code)
);
```

---

## 9. Golden Rules

1. **Always state the methodology.** A CO2 number without a methodology is meaningless. Every emissions record must reference which standard and emission factor version was used.
2. **TTW and WTW are both stored.** Tank-to-Wake (operational only) and Well-to-Wake (full lifecycle including fuel production) serve different reporting needs. Store both.
3. **Use actual cargo weight, not container capacity.** Emission calculations based on TEU count without actual cargo weight significantly overstate or understate emissions. Always use declared gross weight where available.
4. **Mark estimates as estimates.** When cargo weight is assumed from GLEC defaults, `is_estimate = true`. When actual weights are confirmed from the shipping instruction, update to `is_estimate = false`.
5. **Emissions data is a retention requirement.** Enterprise customers need historical emissions for annual sustainability reports. Retain emissions records for at least 7 years.
