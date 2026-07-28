# EDI Integration

## Overview

The EDI Integration feature provides an **integration message audit log** and a **connector registry** that lets operators track all inbound and outbound electronic messages exchanged with carriers, customs authorities, ports, and agents.

The connector registry stores connection metadata (endpoint, auth type, capabilities). Actual protocol-level message handling (EDIFACT, X12, API calls) lives in the **master API**, which routes messages and writes the results back to `integration_message` records via the client API.

---

## Accessing the Feature

### EDI Log (Reports)

Navigation → **Reports** → **EDI Log**

Displays all integration messages across all shipments. Use the filter bar to narrow by direction, message type, status, partner type, and date range. Click any row to view the raw message payload.

### EDI Connectors (Settings)

Navigation → **Settings** → **EDI Connectors**

Manage the list of partner connectors. Create connectors for each external system you exchange messages with (carriers, customs platforms, port systems, aggregators, agents).

---

## Connector Registry

A connector represents one external partner endpoint. Fields:

| Field | Description |
|-------|-------------|
| Connector Type | `CARRIER`, `CUSTOMS`, `PORT`, `AGENT`, `AGGREGATOR` |
| Partner Name | Display name of the external system (e.g. `MSC`, `TradeNet`) |
| Protocol | `REST`, `EDIFACT`, `X12`, `SOAP`, `FTP`, `SFTP`, `EMAIL` |
| Base URL | Endpoint URL for REST/SOAP connectors |
| Auth Type | `NONE`, `API_KEY`, `OAUTH2`, `CERT`, `BASIC` |
| Credentials Ref | Key name in the secrets store — **never store the secret itself** |
| Capabilities | List of supported message types (e.g. `BOOKING`, `SI`, `TRACKING`) |
| Test Mode | Routes messages to sandbox/test endpoints when enabled |
| Active | Inactive connectors are excluded from routing |

### Creating a Connector

1. Go to **Settings → EDI Connectors**.
2. Click **Add Connector**.
3. Fill in the required fields: Partner Name, Connector Type, Protocol.
4. Add the Base URL and Auth Type if the connector uses REST or SOAP.
5. Set **Credentials Ref** to the key name of the secret in your secrets store (Vault, AWS Secrets Manager, etc.) — do not paste raw credentials here.
6. Select **Capabilities** — the message types this partner supports.
7. Click **Save**.

### Editing / Disabling a Connector

Click the pencil icon on any connector row to edit. To temporarily disable a connector without deleting it, toggle the **Active** switch to off.

---

## Message Log

Every integration message is recorded as an `integration_message` record. Fields:

| Field | Description |
|-------|-------------|
| Direction | `INBOUND` (received from partner) or `OUTBOUND` (sent to partner) |
| Message Type | `BOOKING`, `SI`, `CUSTOMS_DECL`, `TRACKING`, `RATE_CARD`, `STATUS` |
| Protocol | Protocol used (mirrors connector protocol) |
| Partner Type | Partner category |
| Partner Name | Sending/receiving partner name |
| Shipment | Linked shipment (if applicable) |
| Message Ref | Partner-assigned reference number |
| Status | See lifecycle below |
| Retry Count | Number of delivery attempts |
| Sent At / Received At / ACK At | Timestamps for each lifecycle event |
| Error Code / Error Message | Set on `FAILED` or `REJECTED` messages |
| Payload | Raw message body (EDIFACT segment, JSON body, XML, etc.) |

### Message Lifecycle

```
PENDING → SENT → ACK
               ↘ REJECTED
         RECEIVED (for inbound)
PENDING → FAILED (after retry exhaustion)
```

| Status | Meaning |
|--------|---------|
| PENDING | Message queued, not yet sent or received |
| SENT | Outbound message delivered to partner |
| RECEIVED | Inbound message received from partner |
| ACK | Partner acknowledged receipt |
| REJECTED | Partner rejected the message content |
| FAILED | Delivery failed after all retry attempts |

### Filtering the Log

| Filter | Options |
|--------|---------|
| Direction | INBOUND / OUTBOUND |
| Message Type | BOOKING, SI, CUSTOMS_DECL, TRACKING, RATE_CARD, STATUS |
| Status | PENDING, SENT, RECEIVED, ACK, REJECTED, FAILED |
| Partner Type | CARRIER, CUSTOMS, PORT, AGENT, AGGREGATOR |
| From / To | Date range (by `created_at`) |

Returns up to 50 most recent messages per query by default (max 200 with `?limit=N`).

### Viewing the Raw Payload

Click any row in the EDI Log table to open the detail panel. The **Raw Payload** section shows the full message body — useful for debugging malformed EDIFACT segments, failed JSON schema validations, or X12 transaction sets.

---

## Shipment-Level Message Tab

Integration messages linked to a shipment are also accessible from the shipment record via the `/shipment/{id}/integration-messages` API endpoint. This returns up to 100 messages ordered by `created_at DESC`.

---

## API Reference

### Connectors

| Method | Path | Description |
|--------|------|-------------|
| GET | `/integration/connectors` | List connectors. Filters: `connector_type`, `is_active` |
| POST | `/integration/connectors` | Create connector. Required: `connectorType`, `partnerName`, `protocol` |
| PATCH | `/integration/connectors/{id}` | Update connector fields |
| DELETE | `/integration/connectors/{id}` | Remove connector |

### Messages

| Method | Path | Description |
|--------|------|-------------|
| GET | `/integration/messages` | List messages with filters + pagination (`limit`, `offset`) |
| GET | `/integration/messages/{id}` | Full detail including payload |
| POST | `/integration/messages` | Create message record. Required: `direction`, `protocol`, `messageType`, `partnerType`, `payload` |
| GET | `/shipment/{id}/integration-messages` | Messages scoped to a shipment |

All routes require `ROLE_USER` and the `integration` feature module.

---

## Architecture Notes

**Credentials are never stored in the database.** The `credentials_ref` column stores only the key name of a secret in the external secrets store (Vault, AWS Secrets Manager, Parameter Store, etc.). The master API resolves the actual credential at send time.

**The client API is the audit layer only.** It stores connector metadata and message records. All actual protocol-level communication (opening SFTP sessions, sending EDIFACT envelopes, calling carrier REST APIs) is handled by the master API. The master API writes the resulting `integration_message` records back to the client API after dispatch.

**Retry logic is managed by the master API.** The `retry_count` field reflects how many delivery attempts the master API made before writing the final status.

**Test mode is per-connector.** When `test_mode = true`, the master API routes outbound messages to the partner's sandbox environment instead of production. Inbound test messages are tagged accordingly.

**Feature flag:** `EdiIntegration` (ID 69). Both the connector settings page and the EDI log report are gated behind this flag. The flag also controls the "Add Connector" button within the settings page.

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `integration_connector` | Connector registry — one row per external partner endpoint |
| `integration_message` | Message audit log — one row per inbound or outbound message |

The `integration_message.shipment_id` column uses `ON DELETE SET NULL` — deleting a shipment does not delete its message history.
