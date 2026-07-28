# Freight Forwarder SaaS — Vessel and Flight Schedule

## 1. Why Schedule Data Is Critical

Without schedule data, the ETD and ETA fields on every job are just manually typed guesses. With schedule data, the system can:

- Suggest valid sailings at booking time
- Auto-populate ETD, ETA, and vessel name
- Calculate SI and VGM cutoff deadlines from the sailing
- Alert operators when a selected vessel is rolled or delayed
- Show transit time options when building a quote

---

## 2. Ocean Vessel Schedule

### Schedule source types

| Source | Data quality | Cost | Update frequency |
|---|---|---|---|
| Carrier direct API | Highest — authoritative | Varies (some free) | Real-time |
| Schedule aggregator (Descartes, BlueX, Portchain) | High — consolidated | Subscription | Daily |
| Port community system (e.g. PortNet Singapore) | Port-specific | Local registration | Daily |
| Manual entry by operator | Lowest | Free | On demand |

### Schedule tables

```sql
-- A vessel service loop (e.g. "AEX1 — Asia-Europe Express 1")
CREATE TABLE vessel_service (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  service_code    VARCHAR(32)   UNIQUE NOT NULL,
  service_name    VARCHAR(128)  NOT NULL,
  carrier_id      UUID          NOT NULL REFERENCES organisation(id),
  trade_lane      VARCHAR(64),                   -- ASIA-EUROPE / TRANSPACIFIC / INTRA-ASIA
  frequency       VARCHAR(16),                   -- WEEKLY / BIWEEKLY / MONTHLY
  is_active       BOOLEAN       NOT NULL DEFAULT true
);

-- A named vessel (ship)
CREATE TABLE vessel (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  imo_number      VARCHAR(10)   UNIQUE NOT NULL,  -- IMO unique vessel identifier
  name            VARCHAR(128)  NOT NULL,
  flag            CHAR(2)       REFERENCES country(code),
  operator_id     UUID          REFERENCES organisation(id),
  vessel_type     VARCHAR(32),                    -- CONTAINER / BULK / TANKER / RORO
  teu_capacity    INT,                            -- for container vessels
  gross_tonnage   NUMERIC(12,2),
  loa_metres      NUMERIC(8,2),                  -- length overall
  beam_metres     NUMERIC(6,2),
  is_active       BOOLEAN       NOT NULL DEFAULT true
);

-- One departure of a service from a specific port
CREATE TABLE vessel_sailing (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  service_id      UUID          NOT NULL REFERENCES vessel_service(id),
  vessel_id       UUID          NOT NULL REFERENCES vessel(id),
  voyage_number   VARCHAR(32)   NOT NULL,
  pol_code        VARCHAR(10)   NOT NULL REFERENCES location(code),
  pod_code        VARCHAR(10)   NOT NULL REFERENCES location(code),
  via_port        VARCHAR(10)   REFERENCES location(code),   -- transshipment point if any
  etd             TIMESTAMPTZ   NOT NULL,         -- estimated time of departure (UTC)
  eta             TIMESTAMPTZ   NOT NULL,         -- estimated time of arrival (UTC)
  atd             TIMESTAMPTZ,                    -- actual time of departure (updated in real-time)
  ata             TIMESTAMPTZ,                    -- actual time of arrival
  cutoff_si       TIMESTAMPTZ,                    -- shipping instruction cutoff
  cutoff_vgm      TIMESTAMPTZ,                    -- VGM submission cutoff
  cutoff_cargo    TIMESTAMPTZ,                    -- cargo receiving cutoff at terminal
  transit_days    SMALLINT,
  status          VARCHAR(16)   NOT NULL DEFAULT 'SCHEDULED',  -- SCHEDULED / DEPARTED / ARRIVED / CANCELLED / ROLLED
  source          VARCHAR(32)   NOT NULL DEFAULT 'MANUAL',     -- MANUAL / CARRIER_API / AGGREGATOR
  source_ref      VARCHAR(64),                    -- carrier's own schedule reference
  fetched_at      TIMESTAMPTZ,
  created_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),

  UNIQUE (service_id, vessel_id, voyage_number, pol_code)
);

CREATE INDEX idx_sailing_pol_pod ON vessel_sailing (pol_code, pod_code, etd);
CREATE INDEX idx_sailing_etd     ON vessel_sailing (etd);
CREATE INDEX idx_sailing_status  ON vessel_sailing (status);
```

### Sailing lookup — used at booking time

```sql
-- Find available sailings for a trade lane in a date range
SELECT
  vs.voyage_number,
  v.name                    AS vessel_name,
  vsr.service_name,
  o.name                    AS carrier,
  vs.etd,
  vs.eta,
  vs.cutoff_si,
  vs.cutoff_vgm,
  vs.cutoff_cargo,
  vs.transit_days,
  vs.via_port,
  vs.status
FROM vessel_sailing vs
JOIN vessel v          ON vs.vessel_id  = v.id
JOIN vessel_service vsr ON vs.service_id = vsr.id
JOIN organisation o    ON vsr.carrier_id = o.id
WHERE vs.pol_code = :pol
  AND vs.pod_code = :pod
  AND vs.etd      BETWEEN :earliest_etd AND :latest_etd
  AND vs.status   = 'SCHEDULED'
ORDER BY vs.etd ASC;
```

---

## 3. Cutoff Calculation

Cutoffs are calculated relative to ETD and stored as absolute timestamps. They vary by carrier, terminal, and trade lane. The system stores cutoff offsets per carrier per route:

```sql
CREATE TABLE cutoff_rule (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  pol_code          VARCHAR(10)   REFERENCES location(code),   -- NULL = all ports for this carrier
  terminal_code     VARCHAR(16)   REFERENCES terminal(code),   -- NULL = all terminals
  cutoff_type       VARCHAR(16)   NOT NULL,   -- SI / VGM / CARGO / HAZMAT / REEFER
  hours_before_etd  SMALLINT      NOT NULL,   -- e.g. 48 = 48 hours before ETD
  day_of_week_only  BOOLEAN       NOT NULL DEFAULT false,   -- true = round to business days
  notes             TEXT,
  effective_from    DATE          NOT NULL,
  effective_to      DATE
);

-- Calculate a specific cutoff from ETD
SELECT
  vs.etd - (cr.hours_before_etd * INTERVAL '1 hour') AS cutoff_datetime
FROM vessel_sailing vs
JOIN cutoff_rule cr ON cr.carrier_id = vs.service_id  -- via service → carrier
WHERE vs.id = :sailing_id
  AND cr.cutoff_type = 'SI';
```

---

## 4. Air Flight Schedule

### Air schedule tables

```sql
CREATE TABLE flight_schedule (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  flight_number   VARCHAR(10)   NOT NULL,
  carrier_id      UUID          NOT NULL REFERENCES organisation(id),   -- airline
  origin_code     VARCHAR(10)   NOT NULL REFERENCES location(code),     -- IATA airport
  destination_code VARCHAR(10)  NOT NULL REFERENCES location(code),
  via_airport     VARCHAR(10)   REFERENCES location(code),
  std             TIMESTAMPTZ   NOT NULL,    -- scheduled time of departure (UTC)
  sta             TIMESTAMPTZ   NOT NULL,    -- scheduled time of arrival (UTC)
  atd             TIMESTAMPTZ,              -- actual departure
  ata             TIMESTAMPTZ,              -- actual arrival
  aircraft_type   VARCHAR(16),              -- B777F / B747F / A330F / bellyhold
  is_freighter    BOOLEAN       NOT NULL DEFAULT false,
  cargo_cutoff    TIMESTAMPTZ,             -- cargo acceptance cutoff
  doc_cutoff      TIMESTAMPTZ,             -- AWB document cutoff
  status          VARCHAR(16)   NOT NULL DEFAULT 'SCHEDULED',
  source          VARCHAR(32)   NOT NULL DEFAULT 'MANUAL',
  created_at      TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE INDEX idx_flight_route ON flight_schedule (origin_code, destination_code, std);
```

### Multi-leg air routing

For indirect routing (e.g. HCM → SIN → LHR), the system needs to connect flight legs:

```sql
CREATE TABLE flight_itinerary (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  name            VARCHAR(128),              -- "HCM-SIN-LHR via Silk Air + SQ"
  total_transit_hours SMALLINT,
  legs            JSONB          NOT NULL    -- ordered array of flight_schedule IDs
  -- [{"leg": 1, "flight_id": "uuid-1"}, {"leg": 2, "flight_id": "uuid-2"}]
);
```

---

## 5. Real-Time Status Updates

Vessel and flight status changes throughout the job lifecycle. The tracking system (covered in the container tracking document) writes updates back to these tables:

```sql
-- Update sailing status when carrier reports a delay
UPDATE vessel_sailing SET
  etd    = :new_etd,
  eta    = :new_eta,
  status = 'SCHEDULED',
  fetched_at = now()
WHERE id = :sailing_id;

-- When a delay is detected, trigger alerts for all affected jobs
SELECT s.shipment_id, s.operator_id, s.eta
FROM shipment s
WHERE s.booking_id IN (
  SELECT id FROM booking WHERE sailing_id = :sailing_id
)
AND s.status NOT IN ('DELIVERED', 'CLOSED', 'CANCELLED');
```

---

## 6. Vessel Roll Handling

A vessel "roll" happens when cargo is not loaded on the planned vessel and is moved to the next sailing. This is one of the most disruptive events in freight forwarding.

```sql
CREATE TABLE vessel_roll (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  original_sailing  UUID          NOT NULL REFERENCES vessel_sailing(id),
  new_sailing       UUID          REFERENCES vessel_sailing(id),    -- NULL if not yet rebooked
  roll_reason       VARCHAR(64)   NOT NULL,   -- NO_SPACE / EQUIPMENT_SHORT / WEATHER / CUSTOMS_HOLD / CARRIER_DECISION
  reported_by       VARCHAR(32)   NOT NULL,   -- CARRIER / OPERATOR / AGENT
  roll_date         DATE          NOT NULL,
  rebooking_status  VARCHAR(16)   NOT NULL DEFAULT 'PENDING',   -- PENDING / CONFIRMED / CUSTOMER_NOTIFIED
  customer_notified_at TIMESTAMPTZ,
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

When a roll is recorded, the system automatically:
1. Updates the job's ETD and ETA to the new sailing's dates
2. Recalculates all cutoffs
3. Writes a milestone: `VESSEL_ROLLED`
4. Sends a notification to the shipper and overseas agent
5. Updates the job sub-status to `ROLLED_OVER`

---

## 7. Golden Rules

1. **ETD and ETA are always stored in UTC.** Display is converted to port local time using the location's timezone.
2. **Cutoffs are absolute timestamps, not offsets.** Calculate from ETD at sailing creation time and store the result. Do not recalculate on every display.
3. **Sailings are never deleted — only cancelled or rolled.** Historical sailing records must be preserved for audit purposes.
4. **Vessel rolls must trigger customer notification automatically.** A roll without customer notification is a service failure. The system must make this automatic.
5. **Schedule data has a shelf life.** Carrier schedules change frequently. Any sailing data older than 24 hours from a carrier API source should be re-fetched before being presented to an operator at booking time.
