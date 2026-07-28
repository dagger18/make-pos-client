# Consolidation Management Guide

This guide covers the consolidation (LCL/groupage) management feature: entity structure, API endpoints, status lifecycle, and BO UI.

---

## Architecture Overview

Consolidation is a standalone feature — `ConsolidationController` extends `AbstractController` (not `CrudController`) and serializes manually. There is no BaseService layer. Repositories are injected directly.

**API:** `src/Controller/Api/ConsolidationController.php`
**Entity:** `src/Entity/Consolidation.php`
**Repository:** `src/Repository/ConsolidationRepository.php`
**BO Service:** `src/services/ConsolidationService.js`
**BO View:** `src/views/consolidation/ConsolidationDetail.vue`

---

## Entity Fields

| Field | Type | Notes |
|-------|------|-------|
| `code` | string(64) | Auto-generated: `CONSOL-{MODE}-{YYYYMM}-{NNN}` |
| `transportMode` | string(8) | `SEA`, `AIR`, `ROAD` |
| `serviceType` | string(16) | e.g. `FCL`, `LCL`, `AIR` |
| `status` | ConsolidationStatus | `OPEN` → `CLOSED` → `DEPARTED` → `ARRIVED` |
| `branch` | ManyToOne → Branch | Required |
| `carrier` | ManyToOne → Client | nullable, onDelete SET NULL |
| `pol` / `pod` | ManyToOne → Port | nullable |
| `etd` / `eta` | date | nullable |
| `vessel` / `voyage` | string | SEA fields, nullable |
| `mblNumber` | string(32) | nullable |
| `flightNumber` | string(16) | AIR field, nullable |
| `mawbNumber` | string(32) | AIR field, nullable |
| `containerNumber` | string(16) | SEA field, nullable |
| `uldNumber` | string(32) | AIR field, nullable |
| `apportionmentBasis` | string(16) | `WEIGHT`, `VOLUME`, `REVENUE_WEIGHT`, `UNITS` |
| `cfsCutoff` | datetime | CFS cargo cut-off, nullable |
| `docCutoff` | datetime | Documentation cut-off, nullable |
| `maxWeightKg` | decimal(10,3) | Max capacity in kg, nullable |
| `maxVolumeCbm` | decimal(10,3) | Max capacity in CBM, nullable |

Child shipments reference the consolidation via `Shipment.consolId` (bare integer FK, no Doctrine relation object).

---

## API Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/consolidation` | List all (filterable by `status`, `transportMode`) |
| POST | `/consolidation` | Create new (requires `transportMode`, `serviceType`, `branchId`) |
| GET | `/consolidation/{id}` | Get detail with children array |
| PUT | `/consolidation/{id}` | Update header fields |
| DELETE | `/consolidation/{id}` | Cancel (requires no active children) |
| PATCH | `/consolidation/{id}/close` | OPEN → CLOSED (writes MBL/MAWB to children) |
| PATCH | `/consolidation/{id}/depart` | CLOSED → DEPARTED (fans out departure milestone) |
| PATCH | `/consolidation/{id}/arrive` | DEPARTED → ARRIVED (fans out arrival milestone) |
| POST | `/consolidation/{id}/shipments` | Attach shipment by `{ shipmentId }` |
| DELETE | `/consolidation/{id}/shipments/{shipId}` | Detach shipment |
| GET | `/consolidation/{id}/manifest-pdf/{language}` | Stream manifest PDF |
| GET | `/consolidation/{id}/manifest-pdf-preview/{language}` | HTML preview for iframe |

---

## Status Lifecycle

```
OPEN → (close) → CLOSED → (depart) → DEPARTED → (arrive) → ARRIVED
 ↓ (cancel at any point except ARRIVED)
CANCELLED
```

**Status rules:**
- `OPEN`: Fully editable. Can add/remove shipments.
- `CLOSED`: Read-only. MBL/MAWB has been written to children.
- `DEPARTED`: Read-only. Departure milestone auto-created on children (SEA: `VESSEL_DEPARTED`, AIR: `FLIGHT_DEPARTED`).
- `ARRIVED`: Read-only. Arrival milestone auto-created on children (SEA: `VESSEL_ARRIVED`, AIR: `FLIGHT_ARRIVED`).
- `CANCELLED`: Requires all active children to be removed first.

---

## Milestone Fan-Out

When `depart` or `arrive` is called:
1. All child shipments are fetched via `findBy(['consolId' => $id])`
2. For each child, the milestone record is upserted (`findByShipmentAndCode` or new)
3. `actualDate` is only set if it is currently null (does not overwrite manual entries)
4. `source` is set to `'CONSOL_AUTO'`

Milestone codes:
- SEA depart → `VESSEL_DEPARTED`
- SEA arrive → `VESSEL_ARRIVED`
- AIR depart → `FLIGHT_DEPARTED`
- AIR arrive → `FLIGHT_ARRIVED`
- ROAD → no automatic milestone (status changes only)

---

## Cargo Manifest PDF

**Route:** `GET /consolidation/{id}/manifest-pdf/{language}`

**Template:** `templates/pdf/consolidation-manifest.html.twig`

**Data passed:**

| Key | Source |
|-----|--------|
| `company` | Provider #1 (Magnum::COMPANY_PROVIDER_ID) |
| `consol` | Consolidation entity |
| `children` | All Shipment entities with consolId = id |
| `basePath` | Request URI for base tag |
| `filename` | `Manifest_{code}_{language}.pdf` |

**BO download:** `ConsolidationService.downloadManifestPdf(id, language)` — returns signed URL opened in new tab. Available via the "Manifest PDF" menu in the manifest tab, shown when at least one child shipment exists.

---

## Migrations

| Version | Description |
|---------|-------------|
| `Version20260622140000` | Create consolidation table, add consolId/parentJobId to shipment |
| `Version20260624060000` | Add cfsCutoff, docCutoff, maxWeightKg, maxVolumeCbm to consolidation |

Both MySQL and SQLite migrations exist in `migrations/mysql/` and `migrations/sqlite/`.
