# Freight Forwarder SaaS — Port, Airport, and Location Master

## 1. Why the Location Master Matters

Every rate card, every shipment job, and every milestone references a location. The `pol_code` and `pod_code` on rate cards and jobs are foreign keys — without a backing location master, they are meaningless strings.

The location master provides:
- Standardised codes (UN/LOCODE for ports, IATA for airports)
- Country and timezone information for deadline calculations
- Terminal and CFS names for operational instructions
- Geographic coordinates for distance and transit time estimation

---

## 2. The Location Table

A single unified table covers all location types — seaports, airports, inland container depots (ICDs), rail terminals, and CFS facilities.

```sql
CREATE TABLE location (
  code              VARCHAR(10)   PRIMARY KEY,    -- UN/LOCODE (VNSGN) or IATA (SGN)
  iata_code         VARCHAR(4)    UNIQUE,          -- airport IATA code where applicable
  unlocode          VARCHAR(8)    UNIQUE,          -- UN/LOCODE where applicable
  name              VARCHAR(128)  NOT NULL,
  name_local        VARCHAR(128),                  -- local language name
  location_type     VARCHAR(32)   NOT NULL,        -- SEAPORT / AIRPORT / ICD / RAIL_TERMINAL / CFS / ROAD_BORDER
  country_code      CHAR(2)       NOT NULL REFERENCES country(code),
  subdivision       VARCHAR(64),                   -- state / province
  city              VARCHAR(128),
  timezone          VARCHAR(64)   NOT NULL,        -- IANA timezone: Asia/Ho_Chi_Minh
  latitude          NUMERIC(10,7),
  longitude         NUMERIC(10,7),
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  is_transshipment_hub BOOLEAN    NOT NULL DEFAULT false,
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ
);

CREATE INDEX idx_loc_country ON location (country_code);
CREATE INDEX idx_loc_type    ON location (location_type);
CREATE INDEX idx_loc_name    ON location USING gin(name gin_trgm_ops);
```

---

## 3. Location Types

| Type | Code standard | Examples |
|---|---|---|
| `SEAPORT` | UN/LOCODE | VNSGN (Ho Chi Minh City), SGSIN (Singapore), CNSHA (Shanghai) |
| `AIRPORT` | IATA + UN/LOCODE | SGN (Tan Son Nhat), SIN (Changi), PVG (Pudong) |
| `ICD` | UN/LOCODE | Inland Container Depot — dry port, rail connected |
| `RAIL_TERMINAL` | UN/LOCODE | Rail freight terminal |
| `CFS` | Internal | Container Freight Station — LCL stuffing/stripping |
| `ROAD_BORDER` | Internal | Cross-border road crossing point |

---

## 4. Terminal and CFS Sub-table

A single port or airport may have multiple terminals. Rate cards and bookings sometimes specify a terminal (e.g. Cai Mep vs. Cat Lai in Ho Chi Minh City — different THC rates).

```sql
CREATE TABLE terminal (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  location_code     VARCHAR(10)   NOT NULL REFERENCES location(code),
  code              VARCHAR(16)   NOT NULL,         -- terminal short code: CMIT, VICT, SP-ITC
  name              VARCHAR(128)  NOT NULL,
  operator          VARCHAR(128),                   -- terminal operating company
  terminal_type     VARCHAR(32)   NOT NULL,          -- CONTAINER / BULK / RO_RO / CFS / AIRPORT_CARGO
  max_vessel_loa    NUMERIC(8,2),                   -- max vessel length overall (metres)
  max_vessel_draft  NUMERIC(6,2),                   -- max draft (metres)
  reefer_plugs      INT,                             -- number of reefer power points
  address           TEXT,
  phone             VARCHAR(32),
  email             VARCHAR(128),
  is_active         BOOLEAN       NOT NULL DEFAULT true,

  UNIQUE (location_code, code)
);
```

---

## 5. Country Reference Table

```sql
CREATE TABLE country (
  code              CHAR(2)       PRIMARY KEY,    -- ISO 3166-1 alpha-2
  code3             CHAR(3)       UNIQUE NOT NULL, -- ISO 3166-1 alpha-3
  numeric_code      CHAR(3),                       -- ISO 3166-1 numeric
  name              VARCHAR(128)  NOT NULL,
  name_local        VARCHAR(128),
  region            VARCHAR(64),                   -- Southeast Asia / Europe / ...
  subregion         VARCHAR(64),
  currency_code     CHAR(3)       REFERENCES currency(code),
  timezone_default  VARCHAR(64),                   -- primary IANA timezone
  is_eu_member      BOOLEAN       NOT NULL DEFAULT false,
  is_sanctioned     BOOLEAN       NOT NULL DEFAULT false,   -- OFAC / UN sanctions flag
  calling_code      VARCHAR(8),
  is_active         BOOLEAN       NOT NULL DEFAULT true
);
```

The `is_sanctioned` flag feeds the compliance check — jobs destined to or originating from sanctioned countries require additional approval or are blocked entirely.

---

## 6. Timezone Usage in the System

Timezones matter in freight because cutoffs are always expressed in the **local time of the port**, not the user's timezone.

```python
from zoneinfo import ZoneInfo
from datetime import datetime

def localise_cutoff(cutoff_utc: datetime, pol_code: str) -> datetime:
    """
    Convert a UTC cutoff to the local time at the port of loading.
    Displayed to the operator in the port's local timezone.
    """
    location = db.fetch_one(
        "SELECT timezone FROM location WHERE code = ?", pol_code
    )
    tz = ZoneInfo(location.timezone)
    return cutoff_utc.astimezone(tz)

# Example:
# SI cutoff stored as UTC: 2026-04-15 15:00:00 UTC
# POL = VNSGN (Asia/Ho_Chi_Minh, UTC+7)
# Displayed to operator: 2026-04-15 22:00 (Ho Chi Minh City time)
```

All cutoff datetimes are stored in UTC. All display is converted to the relevant port's local timezone.

---

## 7. Distance and Transit Time

Geographic coordinates enable distance calculation between locations — used for road freight rate estimation and approximate transit time display.

```sql
-- Haversine distance between two locations (in km)
CREATE OR REPLACE FUNCTION location_distance_km(code_a VARCHAR, code_b VARCHAR)
RETURNS NUMERIC AS $$
DECLARE
  lat_a NUMERIC; lon_a NUMERIC;
  lat_b NUMERIC; lon_b NUMERIC;
  dlat NUMERIC; dlon NUMERIC;
  a NUMERIC; c NUMERIC;
BEGIN
  SELECT latitude, longitude INTO lat_a, lon_a FROM location WHERE code = code_a;
  SELECT latitude, longitude INTO lat_b, lon_b FROM location WHERE code = code_b;

  dlat := RADIANS(lat_b - lat_a);
  dlon := RADIANS(lon_b - lon_a);
  a    := SIN(dlat/2)^2 + COS(RADIANS(lat_a)) * COS(RADIANS(lat_b)) * SIN(dlon/2)^2;
  c    := 2 * ATAN2(SQRT(a), SQRT(1-a));
  RETURN 6371 * c;  -- Earth radius = 6371 km
END;
$$ LANGUAGE plpgsql IMMUTABLE;
```

---

## 8. Transit Time Reference

Standard transit times per trade lane are stored separately — used to calculate ETA from ETD when a vessel or flight is not yet confirmed.

```sql
CREATE TABLE transit_time (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  pol_code          VARCHAR(10)   NOT NULL REFERENCES location(code),
  pod_code          VARCHAR(10)   NOT NULL REFERENCES location(code),
  transport_mode    VARCHAR(8)    NOT NULL,
  carrier_id        UUID          REFERENCES organisation(id),  -- NULL = all carriers
  min_days          SMALLINT      NOT NULL,
  max_days          SMALLINT      NOT NULL,
  typical_days      SMALLINT      NOT NULL,
  via_port          VARCHAR(10)   REFERENCES location(code),    -- transshipment hub if applicable
  service_name      VARCHAR(64),                                -- vessel service loop name
  effective_from    DATE          NOT NULL,
  effective_to      DATE,
  source            VARCHAR(32)   NOT NULL DEFAULT 'MANUAL',    -- MANUAL / CARRIER_API / SCHEDULE_FEED

  UNIQUE (pol_code, pod_code, transport_mode, carrier_id, effective_from)
);
```

---

## 9. Seed Data Strategy

The location master requires bulk seed data — there are over 100,000 UN/LOCODE entries and 9,000+ IATA airport codes. Recommended sources:

| Dataset | Source | Update frequency |
|---|---|---|
| UN/LOCODE | United Nations (unece.org/trade/cefact) | Twice per year |
| IATA airport codes | IATA (iata.org) — licensed | As needed |
| Country list | ISO 3166 Maintenance Agency | As needed |
| Timezone database | IANA Time Zone Database (iana.org/time-zones) | Several times per year |
| Vessel schedules | Carrier APIs / Descartes / BlueX | Daily |

For initial deployment, seed only the locations relevant to the company's trade lanes. Provide a UI for adding new locations when operators encounter codes not in the database.

---

## 10. Golden Rules

1. **One canonical code per location.** Use UN/LOCODE as the primary key for seaports and ICDs. Use IATA as the primary key for airports. Never invent internal codes — use standards.
2. **All cutoff datetimes are stored in UTC, displayed in port local time.** The location's IANA timezone drives every deadline display.
3. **Terminals are children of locations, not locations themselves.** THC and terminal-specific charges reference the terminal, not the port.
4. **Country sanctions flags must be checked on job creation.** A job to a sanctioned country should trigger an approval workflow or hard block depending on company policy.
5. **Bulk seed from official sources, update twice yearly.** UN/LOCODE publishes twice-yearly releases. Build an import script — do not maintain manually.
