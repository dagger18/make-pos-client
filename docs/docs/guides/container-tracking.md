# Container / Shipment Tracking Guide

This guide covers the container and shipment tracking feature. It spans three repositories: master API (polling engine), client API (data storage + webhook receiver), and client BO (management UI).

---

## Architecture

```
Client API                    Master API                  Carrier APIs
─────────────────────────────────────────────────────────────────────
[TrackingRequest created]
  │
  ├─ POST /api/public/tracking-job ──────────────────────►
  │    {trackingType, trackingRef,                          [TrackingJob stored]
  │     callbackUrl, callbackSecret}                               │
  │                                                         Scheduler command
  │◄─ { id: 123 }                                           dispatches Messenger
  │                                                         message per due job
[masterJobId=123 saved]                                            │
                                                            Carrier connector
                                                            fetches events
                                                                   │
  POST /tracking-webhook/{id}  ◄────────────────────────────────────
  X-Tracking-Secret: {secret}
  { source, events }
  │
  ├─ Validate secret
  ├─ Store TrackingEventRaw
  ├─ Map events via CarrierEventMapping
  └─ Write ShipmentMilestone (AUTOMATED source, skip MANUAL)
```

---

## Master API (`D:\Projects\make-cargo`)

### Entity: `TrackingJob`

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Auto PK |
| `trackingType` | string(32) | `CONTAINER`, `MBL`, or `FLIGHT` |
| `trackingRef` | string(64) | Container number, MBL number, or flight number |
| `carrierScac` | string(8) | Carrier SCAC code (e.g. `MAEU`), nullable |
| `status` | string(16) | `ACTIVE`, `PAUSED`, `COMPLETED`, `FAILED` |
| `callbackUrl` | string(500) | Client API URL to POST events to |
| `callbackSecret` | string(64) | Sent as `X-Tracking-Secret` on callback |
| `nextCheckAt` | datetime | When to next poll the carrier |
| `checkFrequencyHours` | int | Poll interval (default: 4) |
| `errorCount` | int | Consecutive errors; auto-marks FAILED at ≥10 |
| `lastError` | text | Last error message |
| `createdAt` | datetime | Creation timestamp |

### API Endpoints (requires `X-Service-Token` header)

All at `/api/public/tracking-job` — marked PUBLIC_ACCESS in `security.yaml`; validates the service token manually.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/api/public/tracking-job` | Register a new tracking job |
| `PATCH` | `/api/public/tracking-job/{id}` | Update status or frequency |
| `DELETE` | `/api/public/tracking-job/{id}` | Remove a job |

**Register request body:**
```json
{
  "trackingType": "CONTAINER",
  "trackingRef": "MSCU1234567",
  "carrierScac": "MAEU",
  "callbackUrl": "https://client-api.example.com/tracking-webhook/42",
  "callbackSecret": "abc123secret",
  "checkFrequencyHours": 4
}
```

### Scheduler Command

```bash
php bin/console app:tracking:schedule
php bin/console app:tracking:schedule --limit=100
```

Finds due `TrackingJob` records and dispatches `TrackingJobMessage` to the Symfony Messenger async queue. Run via cron every 5–10 minutes:

```cron
*/5 * * * * php /path/to/make-cargo/bin/console app:tracking:schedule
```

### Carrier Connectors

Add a new carrier by:
1. Create `src/Service/Tracking/YourCarrierConnector.php` implementing `CarrierConnectorInterface`
2. Tag it in `config/services.yaml`:
   ```yaml
   App\Service\Tracking\YourCarrierConnector:
       tags: [ app.tracking.connector ]
   ```
3. Return the carrier's SCAC in `supports()` and fetch events in `fetchEvents()`

`StubCarrierConnector` (SCAC: `STUB`) returns a static `GATE_IN` event for testing without real carrier credentials.

### Required Env Vars (Master API)

| Variable | Description |
|----------|-------------|
| `MESSENGER_TRANSPORT_DSN` | Symfony Messenger transport (e.g. `doctrine://default`) |
| `MASTER_API_KEY` | Shared HMAC key for `X-Service-Token` validation |

---

## Client API (`d:\Projects\make-cargo-client`)

### Entity: `TrackingRequest`

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Auto PK |
| `shipment` | FK → Shipment | CASCADE DELETE |
| `trackingType` | string(32) | `CONTAINER`, `MBL`, `FLIGHT` |
| `trackingRef` | string(64) | Reference number |
| `carrierScac` | string(8) | Carrier SCAC, nullable |
| `status` | string(16) | `ACTIVE`, `PAUSED`, `FAILED` |
| `masterJobId` | int | ID of the job on master API, nullable |
| `webhookSecret` | string(64) | Random secret validated on webhook receive |
| `lastCheckedAt` | datetime | Last webhook received timestamp |
| `lastEventAt` | datetime | Last webhook with non-empty events |
| `errorCount` | int | Error count |
| `lastError` | text | Last error |
| `createdAt` / `updatedAt` | datetime | Via `EntityDateTimeAbleTrait` |

### Entity: `TrackingEventRaw`

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Auto PK |
| `trackingRequest` | FK → TrackingRequest | CASCADE DELETE |
| `source` | string(32) | Carrier SCAC or `UNKNOWN` |
| `rawPayload` | JSON | Full webhook body |
| `isProcessed` | bool | Whether milestone writing succeeded |
| `processedAt` | datetime | When it was processed |
| `error` | text | Processing error, if any |
| `receivedAt` | datetime | Auto-set on persist |

### Entity: `CarrierEventMapping`

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Auto PK |
| `carrierScac` | string(8) | Carrier SCAC |
| `carrierEventCode` | string(64) | Carrier's event code string |
| `carrierEventDescription` | string(255) | Human-readable description |
| `milestoneCode` | MilestoneCode enum | Mapped milestone (nullable) |
| `confidence` | string(8) | `HIGH`, `MEDIUM`, `LOW` |
| `createdAt` / `updatedAt` | datetime | Via `EntityDateTimeAbleTrait` |

Unique constraint: `(carrier_scac, carrier_event_code)`.

### API Endpoints

**Tracking Requests (nested under shipment):**

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/shipment/{id}/tracking-requests` | List tracking subscriptions |
| `POST` | `/shipment/{id}/tracking-requests` | Create and register subscription |
| `PATCH` | `/shipment/{id}/tracking-requests/{rid}/pause` | Pause polling |
| `PATCH` | `/shipment/{id}/tracking-requests/{rid}/resume` | Resume polling |
| `DELETE` | `/shipment/{id}/tracking-requests/{rid}` | Delete and deregister |
| `GET` | `/shipment/{id}/tracking-requests/{rid}/events` | List raw events |

**POST body:**
```json
{
  "trackingType": "CONTAINER",
  "trackingRef": "MSCU1234567",
  "carrierScac": "MAEU"
}
```

**Carrier Event Mappings (library CRUD):**

| Method | Path |
|--------|------|
| `GET` | `/carrier-event-mapping` |
| `POST` | `/carrier-event-mapping` |
| `PUT` | `/carrier-event-mapping` |
| `DELETE` | `/carrier-event-mapping/{id}` |

**Webhook (public, no auth):**

| Method | Path |
|--------|------|
| `POST` | `/tracking-webhook/{trackingRequestId}` |

The `X-Tracking-Secret` header is validated against `TrackingRequest.webhookSecret`.

Webhook body (sent by master API):
```json
{
  "source": "MAEU",
  "events": [
    {
      "eventCode": "GATE_IN",
      "eventDescription": "Container gated in at origin terminal",
      "eventDate": "2026-06-24T10:00:00+00:00",
      "location": "VNSGN"
    }
  ]
}
```

### Idempotency Rule

`TrackingMilestoneWriterService` writes `ShipmentMilestone` records with `source = 'AUTOMATED'`. It **never overwrites** a milestone already marked `source = 'MANUAL'`. Milestones with no matching `CarrierEventMapping` are silently skipped.

### Required Env Vars (Client API)

| Variable | Description |
|----------|-------------|
| `MASTER_API_URL` | Master API base URL (already required for vessel/flight schedule search) |
| `MASTER_API_KEY` | Shared HMAC key (already required) |
| `APP_BASE_URL` | This client API's public base URL — used to build the callback URL (e.g. `https://client-api.example.com`) |

---

## Client BO (`d:\Projects\make-cargo-client-bo`)

### Library: Carrier Event Mappings

Path: **Library → Carrier Event Mappings** (`/library/carrier-event-mapping`)

Use this page to define how carrier-specific event codes map to system `MilestoneCode` values. Create one row per carrier event code you want the system to recognise.

Example:
| Carrier SCAC | Event Code | Milestone | Confidence |
|---|---|---|---|
| `MAEU` | `GATE_IN` | `GATE_IN` | HIGH |
| `MAEU` | `VESSEL_DEPARTURE` | `VESSEL_DEPARTED` | HIGH |
| `STUB` | `GATE_IN` | `GATE_IN` | HIGH |

### Shipment: Tracking Subscriptions

Path: **Shipment Detail → Tracking tab → Subscriptions sub-tab**

The Subscriptions panel lets you:
- Subscribe a shipment to automated tracking (enter type, reference, and optional SCAC)
- View subscription status (`ACTIVE` / `PAUSED` / `FAILED`)
- Expand a subscription row to see raw event history
- Pause / resume / delete subscriptions

When a subscription is created, the client API automatically registers it with the master API. When the master API detects carrier events, it posts them back to the client API webhook, which maps them to milestones.

---

## Migrations

| Migration | Description |
|-----------|-------------|
| `D:\Projects\make-cargo\migrations\Version20260624010000` | Master API: `tracking_job` table |
| `migrations/mysql/Version20260624110000` + sqlite | Client API: `tracking_request` table |
| `migrations/mysql/Version20260624120000` + sqlite | Client API: `tracking_event_raw` table |
| `migrations/mysql/Version20260624130000` + sqlite | Client API: `carrier_event_mapping` table |
