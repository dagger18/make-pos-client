# Letter of Credit Guide

Covers the LC record, document checklist, compliance checker, discrepancy log, and bank presentation tracking.

---

## Architecture

| Component | Location |
|---|---|
| Entities | `src/Module/Lc/Entity/` |
| Repositories | `src/Module/Lc/Repository/` |
| Service | `src/Module/Lc/Service/LcComplianceService.php` |
| Controllers | `src/Module/Lc/Controller/` |
| MySQL migrations | `migrations/mysql/Version202606261[6-7]0000.php` |
| SQLite migrations | `migrations/sqlite/Version202606261[6-7]0000.php` |
| BO Panel | `src/views/shipment/LcPanel.vue` |
| BO Service | `src/services/LcService.js` |

### Entities

| Entity | Table | Purpose |
|---|---|---|
| `LetterOfCredit` | `letter_of_credit` | Master LC record per shipment |
| `LcDocumentRequirement` | `lc_document_requirement` | Checklist of required documents per LC |
| `LcDiscrepancy` | `lc_discrepancy` | Failed compliance checks logged as discrepancies |
| `LcPresentation` | `lc_presentation` | Bank presentation records |

---

## LC Record

### Status lifecycle

```
OPEN → DOCUMENTS_PREPARED → PRESENTED → NEGOTIATED → PAID
                                     ↘ EXPIRED
                                     ↘ CANCELLED
```

Status advances automatically when a presentation is recorded (`DOCUMENTS_PREPARED → PRESENTED`) and when a compliant bank response is entered with a payment date (`NEGOTIATED`).

### LC types

`IRREVOCABLE` (default) · `REVOLVING` · `STANDBY` · `TRANSFERABLE`

### Critical deadlines

The LC tracks two time-critical deadlines. Both are shown as countdown chips in the LC sidebar:

| Deadline | Field | Alert threshold |
|---|---|---|
| **Shipment by date** | `shipmentBy` | Red at ≤ 7 days |
| **Presentation deadline** | `presentationDeadline` | Red at ≤ 5 days |

**Setting the presentation deadline:** Call `POST /lc/{id}/set-bl-date` once the Bill of Lading is issued with the actual on-board date. The API calculates: `min(blDate + presentationDays, expiryDate)`.

---

## API Endpoints

### Letter of Credit

| Method | Path | Description |
|---|---|---|
| `GET` | `/lc` | List LCs (filter by `shipmentId`, `status`, `lcNumber`) |
| `GET` | `/lc/{id}` | Get single LC |
| `POST` | `/lc` | Create LC |
| `PUT` | `/lc/{id}` | Update LC |
| `DELETE` | `/lc/{id}` | Delete LC |
| `POST` | `/lc/{id}/set-bl-date` | Calculate and save presentation_deadline from BL on-board date |
| `POST` | `/lc/{id}/compliance-check` | Run compliance checks and log discrepancies |

### Document Requirements

| Method | Path | Description |
|---|---|---|
| `GET` | `/lc/{lcId}/documents` | List requirements |
| `POST` | `/lc/{lcId}/documents` | Add requirement |
| `PUT` | `/lc/{lcId}/documents/{id}` | Update requirement |
| `DELETE` | `/lc/{lcId}/documents/{id}` | Remove requirement |
| `POST` | `/lc/{lcId}/documents/{id}/mark-ready` | Mark document as ready + compliance-checked |

### Discrepancies

| Method | Path | Description |
|---|---|---|
| `GET` | `/lc/{lcId}/discrepancies` | List discrepancies |
| `POST` | `/lc/{lcId}/discrepancies` | Log a manual discrepancy |
| `POST` | `/lc/{lcId}/discrepancies/{id}/resolve` | Resolve with reason |
| `POST` | `/lc/{lcId}/discrepancies/{id}/waive` | Waive (with bank or applicant consent) |

### Presentations

| Method | Path | Description |
|---|---|---|
| `GET` | `/lc/{lcId}/presentations` | List presentations |
| `POST` | `/lc/{lcId}/presentations` | Record presentation |
| `PUT` | `/lc/{lcId}/presentations/{id}` | Update bank response / payment date |

---

## Compliance Checker

`POST /lc/{id}/compliance-check` runs the checks defined in the spec. The request body accepts:

```json
{
  "blOnBoardDate": "2026-07-01",
  "invoiceAmount": 50000,
  "insuranceAmount": 56000,
  "portNamesConsistent": true
}
```

All fields are optional — only checks for provided fields are run. The response:

```json
{
  "status": "FAIL",
  "checks": [
    {
      "code": "bl_on_board_date",
      "severity": "FATAL",
      "passed": false,
      "message": "BL on-board date 2026-07-12 is AFTER shipment-by 2026-07-10 — payment will be refused"
    },
    {
      "code": "insurance_coverage",
      "severity": "FATAL",
      "passed": true,
      "message": "Insurance amount 56000 meets 110% CIF requirement (55000)"
    }
  ],
  "discrepanciesCreated": 1
}
```

Failed checks are automatically persisted as `LcDiscrepancy` records.

### Compliance checks

| Code | Severity | Rule |
|---|---|---|
| `bl_on_board_date` | FATAL | BL on-board date must be ≤ LC `shipmentBy` |
| `invoice_amount_matches` | FATAL | Invoice amount must not exceed LC amount |
| `presentation_deadline` | FATAL | Today must be ≤ presentation deadline |
| `insurance_coverage` | FATAL | Insurance must be ≥ 110% of invoice amount |
| `port_name_consistency` | WARNING | Port names must be identical across all documents |

### Overall status values

| Status | Meaning |
|---|---|
| `PASS` | All checks passed |
| `WARNING` | At least one WARNING failed, no FATAL failures |
| `FAIL` | At least one FATAL check failed |

---

## Document Requirements

Standard document types: `BL` · `INVOICE` · `PACKING_LIST` · `COO` · `INSURANCE` · `INSPECTION` · `OTHER`

Each requirement stores:
- `specificWording` — the exact text required on the document as stated in the LC
- `quantityOriginals` / `quantityCopies` — number of originals and copies required
- `isReady` — toggled when the document is physically ready
- `complianceChecked` / `complianceCheckedBy` — set when a compliance officer verifies the document

### Typical BL requirement entry

```json
{
  "docType": "BL",
  "quantityOriginals": 3,
  "quantityCopies": 0,
  "specificWording": "Full set (3/3) original clean on board Bills of Lading marked FREIGHT PREPAID consigned to order of ABC BANK, notify applicant, showing port of loading SHANGHAI, port of discharge ROTTERDAM"
}
```

---

## Discrepancy Resolution

Each discrepancy is either **resolved** (document corrected/re-issued) or **waived** (bank/applicant acceptance).

```
POST /lc/{lcId}/discrepancies/{id}/resolve
{ "resolution": "BL re-issued with corrected consignee wording after carrier approval" }

POST /lc/{lcId}/discrepancies/{id}/waive
{ "waivedByBank": true, "resolution": "Applicant (importer) agreed to waive minor port name discrepancy" }
```

---

## Bank Presentation

Recording a presentation:

```json
POST /lc/{lcId}/presentations
{
  "presentedToBankName": "ABC BANK - SINGAPORE BRANCH",
  "presentedAt": "2026-07-15",
  "documentsPresented": ["BL", "INVOICE", "PACKING_LIST", "COO", "INSURANCE"],
  "hasDiscrepancies": false,
  "bankResponse": "PENDING",
  "notes": "Originals couriered via DHL, tracking AWB 1234567890"
}
```

When the bank responds, update the presentation with `bankResponse: COMPLIANT` and `paymentDate` to automatically advance the LC to `NEGOTIATED` status.

---

## Back-Office Panel

The LC panel is embedded in `ShipmentDetail.vue` as the **Letter of Credit** tab (`tabler-file-certificate` icon).

The panel has five internal tabs:

| Tab | Purpose |
|---|---|
| Details | Full LC terms view with deadline countdown |
| Documents | Document checklist — mark ready, compliance-check |
| Compliance Check | Run the 5-point checker, view pass/fail per rule |
| Discrepancies | List open discrepancies, resolve or waive |
| Presentations | Record bank presentations, enter bank response and payment |

The LC sidebar shows a colour-coded countdown for both **Shipment By** and **Presentation Deadline**: green → yellow (≤7 days) → red (≤3 days).

---

## Golden Rules (implemented as compliance checks)

1. **BL date ≤ Shipment By date** — `bl_on_board_date` FATAL check
2. **Invoice amount ≤ LC amount** — `invoice_amount_matches` FATAL check
3. **Documents within presentation window** — `presentation_deadline` FATAL check
4. **Insurance ≥ 110% of invoice (for CIF)** — `insurance_coverage` FATAL check
5. **Port name consistency** — `port_name_consistency` WARNING check

Run the compliance checker before every document set is finalised. A FATAL failure means the bank will refuse payment.
