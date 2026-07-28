# Freight Forwarder SaaS — EDI and API Integration

## 1. What EDI and API Integration Covers

Electronic Data Interchange (EDI) and API integration is how the freight forwarding system communicates with the outside world — carriers, customs authorities, port community systems, overseas agents, and partner platforms — without manual data re-entry.

Without integration, every booking confirmation, shipping instruction, customs declaration, and arrival notice requires an operator to re-type data that already exists in the system. Integration eliminates this and reduces errors.

---

## 2. Integration Categories

| Category | Direction | Protocol | Examples |
|---|---|---|---|
| Carrier booking | Outbound | REST API / EDIFACT IFTMBF | Maersk, MSC, CMA booking submission |
| Shipping instruction | Outbound | REST API / EDIFACT IFTMIN | SI submission to carrier |
| Carrier event / status | Inbound | REST API / EDIFACT IFTSTA | Vessel events, container status |
| Customs filing | Outbound | Government API / EDIFACT CUSDEC | Export/import declaration |
| Customs release | Inbound | Government API / EDIFACT CUSRES | Clearance response |
| Port community | Both | EDIFACT / XML | Port pre-arrival, terminal gate |
| Overseas agent | Both | REST API / Email + parse | Job instructions, HBL copy |
| Rate feed | Inbound | REST API / Excel import | Carrier rate cards |
| Track and trace | Inbound | REST API | Container status updates |

---

## 3. Integration Message Table

Every message sent or received through an integration is stored before processing. This is the integration audit trail.

```sql
CREATE TABLE integration_message (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  direction         VARCHAR(8)    NOT NULL,   -- INBOUND / OUTBOUND
  protocol          VARCHAR(16)   NOT NULL,   -- REST / EDIFACT / XML / EMAIL / SFTP
  message_type      VARCHAR(64)   NOT NULL,   -- BOOKING / SI / CUSTOMS_DECL / TRACKING / RATE_CARD
  partner_type      VARCHAR(32)   NOT NULL,   -- CARRIER / CUSTOMS / PORT / AGENT / AGGREGATOR
  partner_id        UUID          REFERENCES organisation(id),
  partner_name      VARCHAR(128),
  job_id            UUID          REFERENCES shipment(id),
  consol_id         UUID          REFERENCES consolidation(id),
  message_ref       VARCHAR(128),             -- partner's own message reference
  payload           TEXT          NOT NULL,   -- full raw message content (EDIFACT / JSON / XML)
  status            VARCHAR(16)   NOT NULL DEFAULT 'PENDING',  -- PENDING / SENT / RECEIVED / ACK / REJECTED / FAILED
  sent_at           TIMESTAMPTZ,
  received_at       TIMESTAMPTZ,
  ack_at            TIMESTAMPTZ,
  error_code        VARCHAR(32),
  error_message     TEXT,
  retry_count       SMALLINT      NOT NULL DEFAULT 0,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

CREATE INDEX idx_im_job  ON integration_message (job_id);
CREATE INDEX idx_im_type ON integration_message (message_type, status);
CREATE INDEX idx_im_dir  ON integration_message (direction, created_at DESC);
```

---

## 4. EDIFACT Messages Used in Freight

EDIFACT (Electronic Data Interchange For Administration, Commerce and Transport) is the UN standard used by most ocean carriers and customs authorities.

| EDIFACT message | Full name | Direction | Use |
|---|---|---|---|
| `IFTMBF` | Firm Booking | Outbound | Booking request to carrier |
| `IFTMBC` | Booking Confirmation | Inbound | Carrier confirms or rejects booking |
| `IFTMIN` | Instruction | Outbound | Shipping instruction to carrier |
| `IFTMCS` | Instruction Response | Inbound | Carrier acknowledges SI |
| `IFTSTA` | Transport Status | Inbound | Container and vessel status events |
| `CUSCAR` | Customs Cargo Report | Outbound | AMS/ENS/AFR advance manifest |
| `CUSDEC` | Customs Declaration | Outbound | Import/export declaration |
| `CUSRES` | Customs Response | Inbound | Customs release or examination notice |
| `CONTRL` | Interchange Control | Both | Functional acknowledgement |

---

## 5. REST API Integration Pattern

Modern carrier APIs use REST + JSON. Each carrier has a connector class implementing a standard interface.

```python
class CarrierIntegrationConnector:
    """Standard interface for all carrier integrations."""

    def submit_booking(self, booking_request: dict) -> dict:
        """
        Submit a booking request to the carrier.
        Returns: {"booking_ref": str, "status": str, "vessel": str, "etd": str}
        """
        raise NotImplementedError

    def submit_si(self, si_data: dict) -> dict:
        """Submit shipping instruction. Returns acknowledgement."""
        raise NotImplementedError

    def get_mbl_status(self, mbl_number: str) -> list[dict]:
        """Fetch latest status events for a Master Bill of Lading."""
        raise NotImplementedError

    def get_container_status(self, container_number: str) -> list[dict]:
        """Fetch latest status events for a container."""
        raise NotImplementedError

    def get_sailing_schedule(self, pol: str, pod: str, date_from: str) -> list[dict]:
        """Fetch available sailings for a trade lane."""
        raise NotImplementedError
```

### Integration connector registry

```sql
CREATE TABLE integration_connector (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  partner_id        UUID          NOT NULL REFERENCES organisation(id),
  connector_type    VARCHAR(32)   NOT NULL,   -- CARRIER / CUSTOMS / AGGREGATOR / AGENT
  protocol          VARCHAR(16)   NOT NULL,   -- REST / EDIFACT / XML / SFTP
  base_url          TEXT,
  auth_type         VARCHAR(16),              -- API_KEY / OAUTH2 / BASIC / CERTIFICATE
  credentials_ref   VARCHAR(128),             -- reference to secrets manager key — never store plaintext here
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  capabilities      TEXT[]        NOT NULL,   -- {BOOKING, SI, TRACKING, SCHEDULE, RATE}
  test_mode         BOOLEAN       NOT NULL DEFAULT false,
  last_ping_at      TIMESTAMPTZ,
  last_ping_status  VARCHAR(16)
);
```

Credentials are **never stored in the application database**. They are stored in a secrets manager (AWS Secrets Manager, HashiCorp Vault, or equivalent) and referenced by a key name.

---

## 6. Customs Integration

Customs filing integration varies by country. Each country's customs authority has its own API or EDI channel.

| Country | System | Protocol |
|---|---|---|
| Vietnam | VNACCS / VCIS | SOAP / XML |
| Singapore | TradeNet | REST API |
| EU | CDS (Customs Declaration Service) | REST API |
| USA | ACE (Automated Commercial Environment) | EDIFACT / REST |
| China | CAMS / Golden Tax | REST API |
| Thailand | e-Customs | REST API |

### Customs message flow

```
System generates customs entry data from job
        ↓
Customs connector formats to country-specific schema
        ↓
Submit to customs authority API
        ↓
Store in integration_message (status = SENT)
        ↓
Poll or receive webhook for response
        ↓
CUSRES / response parsed:
  CLEARED  → write milestone CUSTOMS_RELEASED, update entry status
  HOLD     → write milestone EXAMINATION_REQUESTED, alert operator
  REJECTED → alert operator, error message stored
```

---

## 7. Advance Manifest Filing

Several major trade lanes require advance cargo declarations before vessel departure:

| Filing | Trade lane | Deadline | System |
|---|---|---|---|
| AMS (Automated Manifest System) | Into USA | 24h before departure | US CBP |
| ENS (Entry Summary Declaration) | Into EU | 24h before arrival | EU ICS2 |
| AFR (Advance Filing Rules) | Into Japan | 24h before departure | Japan Customs |
| 24-Hour Rule | Into Canada | 24h before loading | CBSA |

These are automatically triggered when a job's POD country matches the filing requirement and the vessel ETD approaches the filing deadline.

---

## 8. Rate Card Import

Carrier rate cards arrive as Excel files or via API. A rate import pipeline processes them into the rate card tables.

```python
def import_rate_card_from_excel(file_path: str, carrier_id: str, mode: str) -> dict:
    """
    Parses a carrier Excel rate sheet and inserts into rate_card + rate_card_line tables.
    Returns: {"imported": int, "skipped": int, "errors": list}
    """
    rows = parse_excel(file_path)
    results = {"imported": 0, "skipped": 0, "errors": []}

    for row in rows:
        try:
            # Validate required fields
            validate_rate_row(row)

            # Look up or create the rate card header
            rate_card = upsert_rate_card(
                pol_code      = normalise_port_code(row['POL']),
                pod_code      = normalise_port_code(row['POD']),
                carrier_id    = carrier_id,
                mode          = mode,
                effective_date = parse_date(row['VALID_FROM']),
                expiry_date   = parse_date(row['VALID_TO']),
                currency      = row['CURRENCY']
            )

            # Insert rate line per container type
            for container_type in ['20GP', '40GP', '40HC']:
                if row.get(container_type):
                    upsert_rate_card_line(
                        rate_card_id   = rate_card.id,
                        container_type = container_type,
                        base_rate      = parse_decimal(row[container_type])
                    )

            results['imported'] += 1

        except Exception as e:
            results['errors'].append({"row": row, "error": str(e)})
            results['skipped'] += 1

    return results
```

---

## 9. Overseas Agent Communication

When a job has an overseas agent at destination, the system sends a **pre-alert** — a structured job summary — so the agent can prepare for arrival.

```python
PRE_ALERT_FIELDS = {
    "job_reference":      "job.shipment_id",
    "agent_reference":    "job.overseas_agent_ref",
    "shipper":            "party.SHIPPER.address_snapshot",
    "consignee":          "party.CONSIGNEE.address_snapshot",
    "notify":             "party.NOTIFY_1.address_snapshot",
    "hbl_number":         "hbl.hbl_number",
    "mbl_number":         "mbl.mbl_number",
    "vessel":             "booking.vessel",
    "voyage":             "booking.voyage",
    "etd":                "booking.etd",
    "eta":                "booking.eta",
    "pol":                "pol.name",
    "pod":                "pod.name",
    "freight_terms":      "job.freight_terms",
    "containers":         "containers[]",
    "cargo_description":  "hbl.description",
    "gross_weight":       "cargo.gross_weight_kg",
    "volume":             "cargo.volume_cbm",
    "hs_codes":           "cargo.hs_codes[]",
    "incoterm":           "job.incoterm",
    "destination_charges":"charge_lines[payable_at=DESTINATION]",
    "attached_documents": ["hbl_pdf", "commercial_invoice", "packing_list"]
}
```

Pre-alerts are sent automatically when the `VESSEL_DEPARTED` milestone is recorded.

---

## 10. Golden Rules

1. **Every integration message is stored before it is sent and after it is received.** The raw payload is always preserved for debugging and re-processing.
2. **Credentials are never stored in the application database.** Always reference a secrets manager key — never a plaintext password or API key.
3. **Integrations are asynchronous.** Carrier APIs are slow and unreliable. Never block a user action waiting for a carrier API response — submit asynchronously and update the job when the response arrives.
4. **Failed messages must be retried with backoff.** Store `retry_count` and `last_error` on every message. Retry up to a configurable maximum before alerting the operator.
5. **Integration capability is per-connector, not assumed.** Not all carriers support all features (booking, SI, tracking, schedule). The `capabilities` array on the connector record drives which features are available for each carrier.
