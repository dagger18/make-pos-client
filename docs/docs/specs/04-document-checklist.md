# Feature 4: Document Checklist (ShipmentDocument)

## Overview

Replaces the generic `Media` attachment model for shipment documents with a typed `ShipmentDocument` entity. Each row represents one required or optional document type for the shipment, tracking whether it has been received, its own reference number, issue date, expiry, and the file attachment. The required document set is auto-generated from the `transport_mode × direction` matrix when a shipment is activated.

Reference systems: CargoWise `eDocument`, Magaya Document Manager, Descartes document checklist.

---

## Data Model

```sql
CREATE TABLE shipment_document (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id     INT           NOT NULL REFERENCES shipment(id) ON DELETE CASCADE,

  -- Classification
  doc_type        VARCHAR(32)   NOT NULL,   -- see DocType enum below
  doc_reference   VARCHAR(64)   DEFAULT NULL,   -- the document's own ref (e.g. BL number, CO number)

  -- Status
  is_required     TINYINT(1)    NOT NULL DEFAULT 1,
  is_received     TINYINT(1)    NOT NULL DEFAULT 0,
  received_at     DATETIME      DEFAULT NULL,

  -- Parties
  received_from   INT           DEFAULT NULL REFERENCES client(id) ON DELETE SET NULL,
  issued_by       INT           DEFAULT NULL REFERENCES client(id) ON DELETE SET NULL,

  -- Dates
  issue_date      DATE          DEFAULT NULL,
  expiry_date     DATE          DEFAULT NULL,

  -- File
  media_id        INT           DEFAULT NULL REFERENCES media(id) ON DELETE SET NULL,

  -- Notes
  remarks         TEXT          DEFAULT NULL,

  -- Audit
  uploaded_by     INT           DEFAULT NULL REFERENCES user(id) ON DELETE SET NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_doc_shipment (shipment_id)
);
```

### DocType enum

| Code | Description |
|---|---|
| `COMMERCIAL_INVOICE` | Commercial Invoice |
| `PACKING_LIST` | Packing List |
| `HBL` | House Bill of Lading |
| `MBL` | Master Bill of Lading |
| `HAWB` | House Air Waybill |
| `MAWB` | Master Air Waybill |
| `CMR` | CMR Waybill (road) |
| `EXPORT_DECLARATION` | Export Customs Declaration |
| `IMPORT_ENTRY` | Import Customs Entry |
| `CERTIFICATE_OF_ORIGIN` | Certificate of Origin |
| `DG_DECLARATION` | Dangerous Goods Declaration |
| `VGM_CERTIFICATE` | Verified Gross Mass Certificate |
| `PHYTOSANITARY` | Phytosanitary / Fumigation Certificate |
| `INSURANCE_CERT` | Cargo Insurance Certificate |
| `ARRIVAL_NOTICE` | Arrival Notice |
| `DELIVERY_ORDER` | Delivery Order |
| `SHIPPING_INSTRUCTION` | Shipping Instruction |
| `LETTER_OF_CREDIT` | Letter of Credit |
| `OTHER` | Other (free text via doc_reference) |

---

## Required Documents Matrix

Auto-generated when a shipment is created or when `transport_mode` + `direction` changes.

| Doc Type | OCN EXP | OCN IMP | AIR EXP | AIR IMP | RD EXP |
|---|---|---|---|---|---|
| Commercial Invoice | ✓ | ✓ | ✓ | ✓ | ✓ |
| Packing List | ✓ | ✓ | ✓ | ✓ | ✓ |
| HBL / HAWB | ✓ | ✓ | ✓ | ✓ | — |
| MBL / MAWB | ✓ | ✓ | ✓ | ✓ | — |
| CMR Waybill | — | — | — | — | ✓ |
| Export Declaration | ✓ | — | ✓ | — | ✓ (cross-border) |
| Import Entry | — | ✓ | — | ✓ | ✓ (cross-border) |
| VGM Certificate | ✓ (FCL) | — | — | — | — |
| Shipping Instruction | ✓ | — | ✓ | — | — |

DG Declaration is added dynamically when any `dangerous_goods` record exists (Feature 3 integration).

---

## Business Rules

1. When a shipment transitions Pending → Active the system auto-creates required `ShipmentDocument` rows with `is_required = true, is_received = false`.
2. Auto-created rows are not duplicated if they already exist (idempotent).
3. Operators can manually add extra rows for optional documents.
4. `is_received` is set to `true` automatically when a file is uploaded (`media_id` is populated) unless the operator explicitly marks it received without a file (e.g. for physical originals).
5. The job close checklist (future Feature 8) checks that all `is_required = true AND is_received = false` rows are zero.
6. `expiry_date` is tracked for certificates and permits (phytosanitary, insurance). The system flags expired documents.

---

## API

```
GET    /shipment/{id}/documents               — list all document rows
POST   /shipment/{id}/documents               — add document row (manual)
PATCH  /shipment/{id}/documents/{docId}       — update (mark received, upload file, set reference)
DELETE /shipment/{id}/documents/{docId}       — remove (only non-required rows, or manager)
POST   /shipment/{id}/documents/{docId}/upload — attach file (multipart), sets media_id + is_received
```

### POST body

```json
{
  "docType": "CERTIFICATE_OF_ORIGIN",
  "docReference": "CO-2026-00123",
  "isRequired": true,
  "issueDate": "2026-06-15",
  "expiryDate": null,
  "remarks": "Issued by Vietnam Chamber of Commerce"
}
```

### Serializer groups

- `shipment_document:list` → id, docType, docReference, isRequired, isReceived, receivedAt, issueDate, expiryDate, media (filename + url)
- `shipment_document:detail` → all fields + receivedFrom, issuedBy, remarks, uploadedBy
- `shipment_document:write` → docType, docReference, isRequired, isReceived, issueDate, expiryDate, receivedFromId, issuedById, remarks

---

## BO UI

### Documents tab in ShipmentDetail

Replace current generic `ShipmentDocument.vue` attachment uploader with a structured checklist view.

**Required Documents section**
- Table rows showing: Type | Reference | Status chip | Issue Date | Expiry | Actions
- Status chip: `Received` (green) / `Pending` (amber) / `Overdue` (red, if expiry passed)
- Upload icon on each row → file picker → uploads file and marks received
- Check icon on each row → marks received without file (physical originals)
- Reference field is inline-editable

**Additional Documents section**
- Same table structure but for optional/manually added docs
- "Add Document" button → dialog with DocType select + reference + upload

**Checklist summary widget** (top of tab)
- `X / Y required documents received` progress bar

---

## Migration

### Schema

```sql
-- MySQL
CREATE TABLE shipment_document (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id     INT NOT NULL,
  doc_type        VARCHAR(32) NOT NULL,
  doc_reference   VARCHAR(64) DEFAULT NULL,
  is_required     TINYINT(1) NOT NULL DEFAULT 1,
  is_received     TINYINT(1) NOT NULL DEFAULT 0,
  received_at     DATETIME DEFAULT NULL,
  received_from   INT DEFAULT NULL,
  issued_by       INT DEFAULT NULL,
  issue_date      DATE DEFAULT NULL,
  expiry_date     DATE DEFAULT NULL,
  media_id        INT DEFAULT NULL,
  remarks         TEXT DEFAULT NULL,
  uploaded_by     INT DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_doc_shipment (shipment_id),
  CONSTRAINT FK_doc_shipment  FOREIGN KEY (shipment_id)  REFERENCES shipment(id) ON DELETE CASCADE,
  CONSTRAINT FK_doc_media     FOREIGN KEY (media_id)     REFERENCES media(id) ON DELETE SET NULL,
  CONSTRAINT FK_doc_uploader  FOREIGN KEY (uploaded_by)  REFERENCES user(id) ON DELETE SET NULL
);
```

### Data migration

Existing `shipment.documents` (Media join table) rows are preserved as-is. They are not migrated into `shipment_document` automatically — the two systems coexist initially. The `shipment.documents` many-to-many is deprecated once all document uploads go through the new entity.

---

## Reference: Industry Patterns

- **CargoWise One** has a `Documents` panel with a required document checklist generated from the job type. Each document type has a `Received` checkbox and a file attachment. Outstanding required documents block job closure.
- **Magaya** shows a document grid with colour-coded status (green = uploaded, red = missing required). Document types are configurable per mode.
- **Descartes** auto-generates the checklist at job creation based on origin/destination country rules and mode.
- **Flexport** uses a document tracker visible to both the operator and the shipper, showing what has been submitted and what is still needed — a key differentiator in their customer experience.
