# Freight Forwarder SaaS — Document Generation

## 1. Why Document Generation Is Critical

A freight forwarder's entire operation produces and exchanges legal and commercial documents. Operators cannot email a database record — they need formatted, printable, emailable PDFs and Word files. Document generation is what makes the system usable day-to-day.

Key documents that must be generated from job data:

| Document | Legal status | Issued by | Recipient |
|---|---|---|---|
| House Bill of Lading (HBL) | Contract of carriage | Forwarder | Shipper |
| House Air Waybill (HAWB) | Contract of carriage | Forwarder | Shipper |
| Arrival Notice | Commercial | Forwarder | Consignee |
| Delivery Order (D/O) | Release authority | Forwarder | Consignee / Trucker |
| AR Invoice | Financial / tax | Forwarder | Customer |
| Packing List | Customs / commercial | Shipper (via forwarder) | Customs / Carrier |
| Shipping Instruction | Carrier instruction | Forwarder | Carrier |
| Cargo Manifest | Consol summary | Forwarder | Carrier / Agent |
| Certificate of Origin | Customs / FTA | Chamber of Commerce | Customs |
| Dangerous Goods Declaration | Legal / safety | Shipper | Carrier |

---

## 2. Architecture: Template + Data Merge

Every document follows the same generation pattern:

```
Document Template (HTML / DOCX / PDF form)
        +
Job Data (from database)
        ↓
Document Renderer (Puppeteer / WeasyPrint / PDFKit)
        ↓
Generated PDF / DOCX
        ↓
Document Store (stored on job, emailed to parties)
```

The template is stored in the database and can be customised per company, per branch, and per document type. The data is always fetched fresh from the job at generation time.

---

## 3. Document Template Table

```sql
CREATE TABLE document_template (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  template_key      VARCHAR(64)   NOT NULL,   -- HBL / HAWB / INVOICE / ARRIVAL_NOTICE / DO / MANIFEST
  name              VARCHAR(128)  NOT NULL,
  scope_type        VARCHAR(16)   NOT NULL DEFAULT 'GLOBAL',  -- GLOBAL / BRANCH / COMPANY
  scope_id          UUID,                     -- branch_id or company_id if scoped
  format            VARCHAR(8)    NOT NULL,   -- PDF / DOCX / HTML
  renderer          VARCHAR(32)   NOT NULL,   -- PUPPETEER / WEASYPRINT / HANDLEBARS
  template_html     TEXT,                     -- HTML/Handlebars template source
  template_css      TEXT,                     -- stylesheet
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  effective_from    DATE          NOT NULL,
  effective_to      DATE,
  version           SMALLINT      NOT NULL DEFAULT 1,
  created_by        UUID          REFERENCES app_user(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 4. The Document Data Context

When a document is generated, the system assembles a **data context object** from the job and its sub-objects. The template renderer receives this as a structured JSON payload.

```python
def build_document_context(job_id: str, document_type: str) -> dict:
    job     = fetch_job_with_all_relations(job_id)
    parties = {p.role: p for p in job.parties}

    return {
        "document_type": document_type,
        "generated_at":  datetime.utcnow().isoformat(),
        "generated_by":  current_user.name,

        # Job header
        "shipment_id":   job.shipment_id,
        "direction":     job.direction,
        "mode":          job.transport_mode,
        "service_type":  job.service_type,
        "incoterm":      job.incoterm,
        "freight_terms": job.freight_terms,

        # Route
        "pol":           {"code": job.pol_code, "name": job.pol.name, "country": job.pol.country},
        "pod":           {"code": job.pod_code, "name": job.pod.name, "country": job.pod.country},
        "place_of_receipt":   job.place_of_receipt,
        "place_of_delivery":  job.place_of_delivery,
        "etd":           format_date(job.etd),
        "eta":           format_date(job.eta),

        # Carrier and vessel
        "carrier":       job.carrier.name if job.carrier else None,
        "vessel":        job.booking.vessel if job.booking else None,
        "voyage":        job.booking.voyage if job.booking else None,
        "mbl_number":    job.mbl.mbl_number if job.mbl else None,
        "hbl_number":    job.hbl.hbl_number if job.hbl else None,

        # Parties (using address snapshot — not live org data)
        "shipper":       parties.get("SHIPPER", {}).address_snapshot,
        "consignee":     parties.get("CONSIGNEE", {}).address_snapshot,
        "notify_1":      parties.get("NOTIFY_1", {}).address_snapshot,
        "notify_2":      parties.get("NOTIFY_2", {}).address_snapshot,

        # Cargo
        "containers":    [serialize_container(c) for c in job.containers],
        "cargo":         serialize_cargo_detail(job.cargo_detail),
        "goods_description": job.hbl.description if job.hbl else None,
        "marks_numbers": job.cargo_detail.marks_numbers if job.cargo_detail else None,
        "hs_codes":      [c.hs_code for c in job.cargo_lines],

        # Charges (for invoice)
        "charge_lines":  [serialize_charge_line(cl) for cl in job.charge_lines],
        "total_sell":    job.total_sell,
        "base_currency": job.base_currency,

        # Your company
        "issuer":        fetch_own_company_branch(job.branch_id),
    }
```

---

## 5. House Bill of Lading (HBL) — The Most Critical Document

The HBL is the forwarder's contract with the shipper. It is the most legally significant document generated by the system. It must contain specific fields in a specific layout defined by FIATA (International Federation of Freight Forwarders Associations).

### Required HBL fields

| Field | Source |
|---|---|
| HBL number | `hbl.hbl_number` |
| Place and date of issue | Branch address + today |
| Shipper (full name + address) | `job_party.address_snapshot` where role = SHIPPER |
| Consignee (full name + address) | `job_party.address_snapshot` where role = CONSIGNEE |
| Notify party | `job_party.address_snapshot` where role = NOTIFY_1 |
| Pre-carriage by | Inland trucking details (if applicable) |
| Place of receipt | `job.place_of_receipt` |
| Ocean vessel / voyage | `booking.vessel + booking.voyage` |
| Port of loading | `location.name` where code = `job.pol_code` |
| Port of discharge | `location.name` where code = `job.pod_code` |
| Place of delivery | `job.place_of_delivery` |
| Marks and numbers | `cargo_detail.marks_numbers` |
| Number of packages | `cargo_detail.pieces` |
| Description of goods | `hbl.description` |
| Gross weight | `cargo_detail.gross_weight_kg` |
| Measurement (CBM) | `cargo_detail.volume_cbm` |
| Container number(s) | `container.container_number` for each FCL box |
| Seal number(s) | `container.seal_number` |
| Freight terms | `job.freight_terms` (PREPAID / COLLECT) |
| Number of originals | Default 3 (or as agreed) |
| Signature block | Forwarder company name + authorised signatory |

### Original count and release type

The HBL release type determines how the consignee obtains the cargo:

| Release type | Description | Originals |
|---|---|---|
| `OBL` | Original Bill of Lading — physical original must be surrendered | 3 originals printed and couriered |
| `TELEX` | Telex release — originals surrendered at origin, release sent electronically | 0 originals; release instruction sent to destination agent |
| `SEAWAY` | Sea Waybill — non-negotiable; consignee identified by name only | 0 originals; no surrender needed |
| `EXPRESS` | Express release — immediate; no originals | 0 originals |

---

## 6. AR Invoice — Legal and Tax Requirements

The AR invoice must comply with local tax law. In Vietnam (and most countries), a tax-compliant invoice must contain:

| Field | Source |
|---|---|
| Invoice number | `invoice.invoice_number` (sequential, no gaps) |
| Invoice date | `invoice.issue_date` |
| Seller name, address, tax ID | `own_company` branch details |
| Buyer name, address, tax ID | `organisation.address_snapshot` |
| Shipment reference | `job.shipment_id` |
| Description of services | Per charge line: `charge_code + description` |
| Quantity | `invoice_line.quantity` |
| Unit rate | `invoice_line.unit_rate` |
| Amount before tax | `invoice_line.amount` |
| VAT rate | `invoice_line.tax_rate` |
| VAT amount | `invoice_line.tax_amount` |
| Total before VAT | `invoice.subtotal` |
| Total VAT | `invoice.tax_amount` |
| Total payable | `invoice.total_amount` |
| Currency | `invoice.currency` |
| Payment terms | `invoice.payment_terms` |
| Bank details | Branch bank account for payment |
| Authorised signature | Stamped or electronic |

---

## 7. Arrival Notice

Sent to the consignee when the vessel or flight is confirmed departed. It is not a legal document — it is a commercial notification — but its content is time-sensitive.

```python
ARRIVAL_NOTICE_FIELDS = [
    "vessel / flight",
    "voyage / flight number",
    "ETD from POL",
    "ETA at POD",
    "MBL / MAWB number",
    "HBL / HAWB number",
    "container number(s) and type(s)",
    "number of packages",
    "gross weight",
    "volume",
    "goods description",
    "freight terms (prepaid / collect)",
    "destination charges estimate",   # THC, D/O fee, customs, delivery
    "required documents checklist",   # what the consignee must provide for clearance
    "contact at destination office",
]
```

---

## 8. Generated Document Storage

Every generated document is stored in the `job_document` table and in object storage (S3 / GCS / local filesystem).

```sql
-- When a document is generated, update the job_document record
UPDATE job_document SET
  is_received   = true,
  received_at   = now(),
  filename      = :filename,
  file_url      = :storage_url,
  doc_reference = :document_number,
  uploaded_by   = :generated_by
WHERE job_id  = :job_id
  AND doc_type = :doc_type;

-- If no existing record (e.g. arrival notice), insert a new one
INSERT INTO job_document (job_id, doc_type, filename, file_url, is_required, is_received, received_at)
VALUES (:job_id, :doc_type, :filename, :storage_url, false, true, now());
```

### File naming convention

```
{doc_type}-{shipment_id}-{version}-{YYYYMMDD}.pdf

Examples:
  HBL-HCM-EXP-OCN-202604-00123-V1-20260415.pdf
  INV-HCM-202604-00234-V1-20260420.pdf
  ARR-HCM-EXP-OCN-202604-00123-20260418.pdf
```

---

## 9. Email Delivery

Documents are emailed directly from the system to the relevant party contacts.

```sql
CREATE TABLE document_email_log (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID          NOT NULL REFERENCES shipment(id),
  doc_type        VARCHAR(32)   NOT NULL,
  document_id     UUID          REFERENCES job_document(id),
  sent_to         TEXT[]        NOT NULL,   -- array of email addresses
  sent_cc         TEXT[],
  subject         VARCHAR(255)  NOT NULL,
  body_preview    TEXT,
  sent_at         TIMESTAMPTZ   NOT NULL DEFAULT now(),
  sent_by         UUID          REFERENCES app_user(id),
  delivery_status VARCHAR(16)   NOT NULL DEFAULT 'SENT',   -- SENT / DELIVERED / BOUNCED / FAILED
  provider_ref    VARCHAR(128)                              -- email provider message ID for tracking
);
```

---

## 10. Golden Rules

1. **Templates are data, not code.** Every document template is stored in the database and editable by authorised admin users without a code deployment.
2. **Always use the address snapshot, never live org data.** The address printed on a BL or invoice must reflect what it was at the time of issue — not the current address in the org master.
3. **Invoice numbers must be sequential with no gaps.** Most tax authorities require this. Use a database sequence (not application-level MAX+1) and never void an issued invoice without a matching credit note.
4. **Generated documents are immutable.** Once a document is generated and stored, it must not be regenerated with different data. Corrections require a new version (re-issue) with a version suffix, and the original is archived.
5. **Email delivery is logged.** Every email sent from the system writes a `document_email_log` record. This is the evidence trail if a customer later claims they never received an invoice or arrival notice.
