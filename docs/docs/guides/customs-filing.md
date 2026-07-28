# Customs Filing Integration

## Overview

Customs Filing Integration lets operators create, manage, and submit customs declarations directly from the shipment record — without re-entering data in a separate customs system. Submission to the customs authority happens through the **master API**, which handles country-specific protocol connectors (VNACCS, ASYCUDA, TradeNet, etc.). Status updates are polled back on demand.

Customs entries live as sub-objects of a shipment and appear on the **Customs** tab in the Compliance group of the shipment detail view.

---

## Accessing the Panel

Open any shipment → click **Compliance** in the tab bar → select **Customs**.

The panel has a master/detail layout:
- **Left sidebar** — lists all customs entries for the shipment, each showing its status, entry type, declaration number, and country
- **Right panel** — shows full details for the selected entry, including all financial values and commodity lines

---

## Customs Entry Lifecycle

```
DRAFT → SUBMITTED → ACKNOWLEDGED → ASSESSMENT → RELEASED
                                              ↘ EXAMINATION
                  ↘ REJECTED (can re-submit)
```

| Status | Meaning |
|--------|---------|
| DRAFT | Entry created but not yet sent to customs |
| SUBMITTED | Declaration sent; awaiting customs acknowledgement |
| ACKNOWLEDGED | Customs received and assigned a declaration number |
| ASSESSMENT | Duty assessment in progress by customs officer |
| EXAMINATION | Cargo held for physical inspection |
| RELEASED | Customs cleared; cargo may be delivered |
| REJECTED | Declaration rejected; correct and re-submit |

---

## Creating a Customs Entry

1. Click **Add Entry** in the top-right of the Customs panel.
2. Fill in the form:
   - **Entry Type** — IMPORT / EXPORT / TRANSIT / RE_EXPORT
   - **Entry Mode** — FORMAL / INFORMAL / SIMPLIFIED / TIR
   - **Country Code** — 2-letter ISO country code of the customs authority (e.g. `VN`, `SG`)
   - **System Code** — the customs software identifier (e.g. `VNACCS`, `TRADENET`, `ASYCUDA`)
   - **Customs Office** — optional port/office identifier
   - **CIF Value** — Customs value (cost + insurance + freight) in the selected currency
   - **Notes** — free-text notes for this declaration
3. Click **Save**. The entry is created with status **DRAFT**.

---

## Adding Commodity Lines

Each entry must have at least one commodity line (HS code level). With an entry selected:

1. Click **Add Line** in the Commodity Lines section.
2. Fill in the form:
   - **HS Code** — 6–10 digit tariff classification code
   - **Description** — goods description
   - **Country of Origin** — 2-letter ISO country code
   - **Packages / Net Weight / Gross Weight / Quantity** — physical cargo details
   - **UOM** — unit of measure (KG, PCS, LITRE, M2, M3, SET)
   - **Unit Price / Line Value / Currency** — financial value of this line
   - **Duty Rate / Duty Amount** — applicable tariff rate and calculated duty
   - **VAT Rate / VAT Amount** — applicable VAT
   - **Restricted Goods** — flag if this HS code requires a licence or permit
3. Click **Save**. Line number is auto-assigned if not provided.

---

## Submitting a Declaration

With a **DRAFT** or **REJECTED** entry selected, click **Submit**. The system:

1. Sends the entry data to the master API (`POST /public/customs/submit`)
2. The master API routes the declaration to the country-specific connector
3. On success, the entry is updated with the **declaration number**, **submission reference**, and status changes to **SUBMITTED** or **ACKNOWLEDGED**

If submission fails (network issue, master API unavailable), the entry stays in its current status. Check the master API logs and retry.

---

## Syncing Status

Once an entry has a declaration number, click **Sync** to poll the latest status from the customs authority:

1. The system calls the master API (`GET /public/customs/status?declaration_number=...&system_code=...`)
2. Status, total duty, and timestamp fields (released_at, acknowledged_at) are updated from the response

Use **Sync** when you expect the entry to have progressed (e.g. checking if cargo has been released after paying duty).

---

## Deleting an Entry

Only entries in **DRAFT** status can be deleted. Submitted declarations cannot be deleted — they become part of the customs audit trail.

---

## API Reference

All endpoints require `ROLE_USER`. Module: `tax`. Base path: `/api/shipment/{shipmentId}/customs-entries`.

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/shipment/{id}/customs-entries` | GET | List all customs entries for a shipment |
| `/shipment/{id}/customs-entries` | POST | Create a new customs entry (entryType required) |
| `/shipment/{id}/customs-entries/{entryId}` | PATCH | Update an entry |
| `/shipment/{id}/customs-entries/{entryId}` | DELETE | Delete a DRAFT entry |
| `/shipment/{id}/customs-entries/{entryId}/submit` | POST | Submit to customs via master API |
| `/shipment/{id}/customs-entries/{entryId}/sync-status` | POST | Poll status from master API |
| `/shipment/{id}/customs-entries/{entryId}/lines` | POST | Add a commodity line |
| `/shipment/{id}/customs-entries/{entryId}/lines/{lineId}` | PATCH | Update a commodity line |
| `/shipment/{id}/customs-entries/{entryId}/lines/{lineId}` | DELETE | Remove a commodity line |

**Response — entry object:**
```json
{
  "id": 1,
  "entryType": "IMPORT",
  "entryMode": "FORMAL",
  "declarationNumber": "VN2026-00123",
  "entryNumber": null,
  "status": "RELEASED",
  "customsOffice": "HOCHIMINHCITY-PORT",
  "countryCode": "VN",
  "systemCode": "VNACCS",
  "cifValue": "15000.000000",
  "valueCurrency": "USD",
  "totalDuty": "750.000000",
  "totalVat": "1500.000000",
  "totalTax": "2250.000000",
  "submittedAt": "2026-07-03T10:00:00+07:00",
  "acknowledgedAt": "2026-07-03T10:05:00+07:00",
  "releasedAt": "2026-07-04T14:30:00+07:00",
  "submissionRef": "VNACCS-REF-789",
  "notes": null,
  "createdAt": "2026-07-03T09:45:00+07:00",
  "lines": [...]
}
```

---

## Architecture Notes

- Third-party customs authority calls are **never made directly** from the client API. All submissions and status polls go through the master API, which manages country-specific connectors, authentication, and protocol translation.
- The client API proxies customs calls via `MasterSyncService::submitCustomsDeclaration()` and `::checkCustomsStatus()` using the `X-Service-Token` inter-service auth header.
- Both proxy methods return `[]` on failure (network errors, master API unavailable) — the entry is saved without status change, allowing the operator to retry.
- Declaration numbers are always assigned by the customs authority — they are never generated internally.

---

## Feature Flag

This feature requires `Feature.CustomsFiling` (id: 68) to be enabled in the module configuration. Tier: **Pro**.
