# Freight Forwarder SaaS — Container and Shipment Tracking

## 1. Why Tracking Is Critical

Without automated tracking, every milestone on every job requires manual operator entry. An operator managing 50 active FCL jobs would need to check each carrier's website individually and type updates manually — hours of work per day, with inevitable misses and delays.

Automated tracking feeds milestones directly from carrier APIs or tracking aggregators, turning passive data entry into proactive exception management.

---

## 2. Tracking Architecture

```
Tracking Scheduler (runs every 1–4 hours)
        ↓
Tracking Dispatcher
  ├── Carrier API connectors (Maersk, MSC, CMA, OOCL, Evergreen ...)
  ├── Aggregator connectors (project44, FourKites, Shipsgo, Container-xChange)
  └── Scraper fallback (for carriers without APIs)
        ↓
Event Normaliser
  — maps carrier-specific event codes to internal milestone codes
        ↓
Milestone Writer
  — creates or updates milestone records on the job
  — detects exceptions (late events, unexpected holds)
        ↓
Alert Engine
  — notifies operators and customers of significant events
```

---

## 3. Tracking Request Table

The system maintains a list of what needs to be tracked. This table drives the scheduler.

```sql
CREATE TABLE tracking_request (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  tracking_type     VARCHAR(16)   NOT NULL,   -- CONTAINER / MBL / HBL / FLIGHT / TRUCK
  tracking_ref      VARCHAR(64)   NOT NULL,   -- container number / MBL number / flight number
  carrier_id        UUID          REFERENCES organisation(id),
  carrier_scac      VARCHAR(8),               -- for ocean carrier API routing
  iata_code         VARCHAR(4),               -- for airline API routing
  status            VARCHAR(16)   NOT NULL DEFAULT 'ACTIVE',  -- ACTIVE / PAUSED / COMPLETED / FAILED
  last_checked_at   TIMESTAMPTZ,
  last_event_at     TIMESTAMPTZ,
  check_frequency_hours SMALLINT  NOT NULL DEFAULT 4,
  next_check_at     TIMESTAMPTZ,
  error_count       SMALLINT      NOT NULL DEFAULT 0,
  last_error        TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE INDEX idx_tr_next_check ON tracking_request (next_check_at) WHERE status = 'ACTIVE';
CREATE INDEX idx_tr_job        ON tracking_request (job_id);
```

### Tracking frequency rules

| Job phase | Frequency |
|---|---|
| Pre-departure (before ETD) | Every 6 hours |
| In transit | Every 4 hours |
| Approaching ETA (within 48 hours) | Every 2 hours |
| At POD / customs | Every 1 hour |
| Delivered / completed | Paused |

---

## 4. Raw Tracking Event Table

Every raw event received from a carrier or aggregator is stored before normalisation. This preserves the original data for debugging and re-processing.

```sql
CREATE TABLE tracking_event_raw (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  tracking_request_id UUID        NOT NULL REFERENCES tracking_request(id),
  source            VARCHAR(32)   NOT NULL,   -- MAERSK_API / PROJECT44 / SHIPSGO / MANUAL
  raw_payload       JSONB         NOT NULL,   -- full API response
  received_at       TIMESTAMPTZ   NOT NULL DEFAULT now(),
  is_processed      BOOLEAN       NOT NULL DEFAULT false,
  processed_at      TIMESTAMPTZ,
  error             TEXT
);
```

---

## 5. Event Normalisation

Each carrier uses different event codes. The normaliser maps these to internal milestone codes.

```sql
CREATE TABLE carrier_event_mapping (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  carrier_event_code VARCHAR(64)  NOT NULL,   -- carrier's own event code
  carrier_event_desc VARCHAR(255),            -- carrier's description
  milestone_code    VARCHAR(32)   NOT NULL REFERENCES milestone_master(code),
  confidence        VARCHAR(8)    NOT NULL DEFAULT 'HIGH',   -- HIGH / MEDIUM / LOW
  notes             TEXT,

  UNIQUE (carrier_id, carrier_event_code)
);
```

### Common carrier event → internal milestone mapping

| Carrier event (example) | Internal milestone |
|---|---|
| `GATE_IN` / `GI` | `GATE_IN` |
| `VESSEL_DEPARTURE` / `VD` / `DEPARTED` | `VESSEL_DEPARTED` |
| `TRANSSHIPMENT_ARRIVAL` / `TA` | `AT_TRANSSHIPMENT` |
| `VESSEL_ARRIVAL` / `VA` / `ARRIVED` | `VESSEL_ARRIVED` |
| `DISCHARGE` / `DIS` | `DISCHARGED` |
| `CUSTOMS_CLEARANCE` / `CC` | `CUSTOMS_RELEASED` |
| `GATE_OUT` / `GO` / `DELIVERY` | `GATE_OUT` |
| `EMPTY_RETURN` / `ER` | `EMPTY_RETURNED` |

---

## 6. Milestone Writer

After normalisation, the milestone writer creates or updates the milestone record on the job.

```python
def write_tracking_milestone(job_id: str, milestone_code: str, event_datetime: datetime,
                              source: str, raw_event_id: str):
    """
    Idempotent — safe to call multiple times for the same event.
    If the milestone already exists with the same code, update actual_date only
    if the new date is more recent or more precise.
    """
    existing = db.fetch_one(
        "SELECT * FROM milestone WHERE job_id = ? AND milestone_code = ?",
        job_id, milestone_code
    )

    if existing:
        if existing.source == 'MANUAL' and source != 'MANUAL':
            # Do not overwrite a human-entered milestone with an automated one
            return
        if existing.actual_date and existing.actual_date != event_datetime:
            # Date changed — log as update in activity log
            log_activity(job_id, 'milestone', existing.id, 'UPDATE',
                        old_value=existing.actual_date.isoformat(),
                        new_value=event_datetime.isoformat(),
                        source=source)

        db.execute(
            "UPDATE milestone SET actual_date = ?, source = ?, updated_at = now() WHERE id = ?",
            event_datetime, source, existing.id
        )
    else:
        db.execute("""
            INSERT INTO milestone (job_id, milestone_code, actual_date, source, created_at)
            VALUES (?, ?, ?, ?, now())
        """, job_id, milestone_code, event_datetime, source)

    # Check for exception (late vs planned)
    check_milestone_exception(job_id, milestone_code, event_datetime)

    # Trigger downstream actions
    trigger_milestone_actions(job_id, milestone_code)
```

---

## 7. Exception Detection

```python
def check_milestone_exception(job_id: str, milestone_code: str, actual_date: datetime):
    planned = db.fetch_one(
        "SELECT planned_date FROM milestone WHERE job_id = ? AND milestone_code = ?",
        job_id, milestone_code
    )

    if not planned or not planned.planned_date:
        return

    delta_hours = (actual_date - planned.planned_date).total_seconds() / 3600

    if abs(delta_hours) > 24:   # configurable threshold
        db.execute("""
            UPDATE milestone SET
              is_exception    = true,
              exception_hours = ?
            WHERE job_id = ? AND milestone_code = ?
        """, delta_hours, job_id, milestone_code)

        # Positive delta = late; negative delta = early
        severity = 'HIGH' if delta_hours > 72 else 'MEDIUM'
        create_alert(
            job_id      = job_id,
            alert_type  = 'MILESTONE_EXCEPTION',
            severity    = severity,
            message     = f"{milestone_code} is {abs(delta_hours):.0f}h {'late' if delta_hours > 0 else 'early'}",
            milestone   = milestone_code,
            delta_hours = delta_hours
        )
```

---

## 8. Carrier API Connectors

Each carrier requires a separate connector. The connector interface is standardised:

```python
class CarrierTrackingConnector:
    """Base interface — all carrier connectors implement this."""

    def __init__(self, carrier_config: dict):
        self.api_key    = carrier_config['api_key']
        self.base_url   = carrier_config['base_url']

    def track_container(self, container_number: str) -> list[dict]:
        """Returns a list of normalised tracking events."""
        raise NotImplementedError

    def track_mbl(self, mbl_number: str) -> list[dict]:
        """Returns events by MBL number."""
        raise NotImplementedError

    def get_sailing_schedule(self, pol: str, pod: str, date_from: date) -> list[dict]:
        """Returns available sailings."""
        raise NotImplementedError
```

### Available carrier APIs (2026)

| Carrier | API type | Container | Schedule | Notes |
|---|---|---|---|---|
| Maersk | REST (Captain Peter API) | ✓ | ✓ | OAuth2, free tier available |
| MSC | REST | ✓ | ✓ | Requires partnership |
| CMA CGM | REST | ✓ | ✓ | API key registration |
| OOCL | REST | ✓ | ✓ | |
| Evergreen | REST | ✓ | Limited | |
| Hapag-Lloyd | REST | ✓ | ✓ | |
| ONE | REST | ✓ | ✓ | |

### Aggregators (cover multiple carriers in one API)

| Aggregator | Coverage | Strength |
|---|---|---|
| project44 | 1,000+ carriers, ocean + air + road | Most comprehensive |
| FourKites | 1,200+ carriers | Strong real-time, predictive ETA |
| Shipsgo | Ocean focused, 98+ carriers | Cost-effective |
| Container-xChange | Container availability + tracking | |
| Portcast | AI-powered ETA prediction | |

---

## 9. Air Shipment Tracking

Air tracking follows the same pattern but uses flight number and HAWB/MAWB as tracking references.

Key air tracking events:

| Event | Milestone |
|---|---|
| RCS — Received from shipper | `CARGO_RECEIVED` |
| DEP — Departed | `FLIGHT_DEPARTED` |
| TRM — Transfer at hub | Intermediate update |
| ARR — Arrived at destination | `FLIGHT_ARRIVED` |
| NFD — Notified for delivery | `AVAILABLE` |
| DLV — Delivered | `DELIVERED` |
| RCF — Received at customs freight station | |

IATA Cargo 2000 (C2K) standard milestone codes are the basis — most airlines and cargo handlers use these.

---

## 10. Customer-Facing Tracking

Tracking events feed the customer portal directly. The customer sees a simplified version of the milestone chain — operational detail is hidden, key events are presented in plain language.

```sql
CREATE VIEW customer_tracking_view AS
SELECT
  s.shipment_id,
  m.milestone_code,
  mt.customer_label,        -- "Your cargo is on board" instead of "ON_BOARD"
  m.actual_date,
  m.planned_date,
  m.is_exception,
  l_pol.name AS origin_port,
  l_pod.name AS destination_port,
  s.eta
FROM milestone m
JOIN shipment s        ON m.job_id         = s.id
JOIN milestone_master mt ON m.milestone_code = mt.code
JOIN location l_pol    ON s.pol_code        = l_pol.code
JOIN location l_pod    ON s.pod_code        = l_pod.code
WHERE mt.is_customer_visible = true
ORDER BY m.actual_date;
```

---

## 11. Golden Rules

1. **Raw events are always stored before processing.** If the normaliser has a bug, raw events can be re-processed without re-fetching from the carrier.
2. **Milestone writing is idempotent.** The same event can be received multiple times from different sources — the writer must handle duplicates gracefully.
3. **Never overwrite a manual milestone with an automated one.** An operator who manually entered a milestone has ground-truth knowledge the API may not have.
4. **Tracking stops when the job is delivered.** Deactivate tracking requests after `DELIVERED` milestone to avoid unnecessary API calls.
5. **Exception thresholds are configurable per trade lane.** A 24-hour delay on a China–US transpacific route may be normal; the same delay on a Singapore–Vietnam feeder is critical. Thresholds should not be hardcoded.
