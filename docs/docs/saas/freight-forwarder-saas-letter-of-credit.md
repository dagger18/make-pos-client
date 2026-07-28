# Freight Forwarder SaaS — Letter of Credit Module

## 1. What a Letter of Credit Is

A Letter of Credit (LC) is a financial instrument issued by the importer's bank, guaranteeing that the exporter will receive payment provided they present a compliant set of shipping documents within the stipulated terms. The LC defines exactly which documents must be presented, in what format, by what deadline.

The freight forwarder plays a critical role in LC transactions:
- The Bill of Lading issued by the forwarder must exactly match LC terms
- All documents (BL, invoice, packing list, certificate of origin, insurance) must be presented as a set
- Even a minor discrepancy — a comma in the wrong place, a different spelling of the port name — can result in the bank refusing payment (a "discrepancy")

---

## 2. LC Record

```sql
CREATE TABLE letter_of_credit (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  lc_number         VARCHAR(64)   NOT NULL,
  lc_type           VARCHAR(16)   NOT NULL,   -- IRREVOCABLE / REVOLVING / STANDBY / TRANSFERABLE
  issuing_bank_id   UUID          NOT NULL REFERENCES organisation(id),
  advising_bank_id  UUID          REFERENCES organisation(id),
  negotiating_bank_id UUID        REFERENCES organisation(id),
  applicant_id      UUID          NOT NULL REFERENCES organisation(id),   -- importer
  beneficiary_id    UUID          NOT NULL REFERENCES organisation(id),   -- exporter

  -- Terms
  lc_amount         NUMERIC(20,6) NOT NULL,
  lc_currency       CHAR(3)       NOT NULL,
  issue_date        DATE          NOT NULL,
  expiry_date       DATE          NOT NULL,
  expiry_place      VARCHAR(64)   NOT NULL,   -- country/bank where docs must be presented
  shipment_by       DATE          NOT NULL,   -- latest date of shipment (on-board BL date)
  presentation_days SMALLINT      NOT NULL DEFAULT 21,  -- days after BL date to present docs
  presentation_deadline DATE,                -- calculated: BL date + presentation_days

  -- Special conditions (free text from LC)
  special_conditions TEXT,
  partial_shipments VARCHAR(8)    NOT NULL DEFAULT 'NOT_ALLOWED',  -- ALLOWED / NOT_ALLOWED
  transhipment      VARCHAR(8)    NOT NULL DEFAULT 'NOT_ALLOWED',

  status            VARCHAR(16)   NOT NULL DEFAULT 'OPEN',
  -- OPEN / DOCUMENTS_PREPARED / PRESENTED / NEGOTIATED / PAID / EXPIRED / CANCELLED

  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 3. LC Document Requirements

The LC specifies exactly which documents are required. These are stored as a checklist.

```sql
CREATE TABLE lc_document_requirement (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  lc_id             UUID          NOT NULL REFERENCES letter_of_credit(id),
  doc_type          VARCHAR(32)   NOT NULL,   -- BL / INVOICE / PACKING_LIST / COO / INSURANCE / INSPECTION
  quantity_originals SMALLINT     NOT NULL DEFAULT 1,
  quantity_copies   SMALLINT      NOT NULL DEFAULT 0,
  specific_wording  TEXT          NOT NULL,   -- exact text required on the document per LC terms
  doc_reference     UUID          REFERENCES job_document(id),   -- linked when document is ready
  is_ready          BOOLEAN       NOT NULL DEFAULT false,
  compliance_checked BOOLEAN      NOT NULL DEFAULT false,
  compliance_checked_by UUID      REFERENCES app_user(id),
  compliance_notes  TEXT
);
```

### Standard LC document set

| Document | Typical LC requirement |
|---|---|
| Bill of Lading | 3/3 originals, "clean on board", "freight prepaid", consignee = "to order of [issuing bank]" |
| Commercial Invoice | Original + 2 copies, in English, exactly matching LC amount and description |
| Packing List | Original + 2 copies, must reconcile with invoice quantities |
| Certificate of Origin | 1 original, issued by Chamber of Commerce, stating country of origin |
| Insurance Certificate | For CIF trades — coverage ≥ 110% of invoice value, issued in negotiable form |
| Inspection Certificate | Original from named inspection company (SGS, Bureau Veritas) |

---

## 4. Compliance Checker

The LC compliance checker compares the actual documents against the LC requirements and flags discrepancies.

```python
COMPLIANCE_CHECKS = [
    {
        "check":   "bl_on_board_date",
        "rule":    "BL on-board date must be on or before LC shipment_by date",
        "test":    lambda lc, docs: docs['BL'].on_board_date <= lc.shipment_by,
        "severity": "FATAL"  -- payment will be refused
    },
    {
        "check":   "bl_consignee_matches",
        "rule":    "BL consignee must match LC consignee field exactly",
        "test":    lambda lc, docs: normalise(docs['BL'].consignee) == normalise(lc.consignee_wording),
        "severity": "FATAL"
    },
    {
        "check":   "invoice_amount_matches",
        "rule":    "Invoice amount must match LC amount (or be less for partial shipment)",
        "test":    lambda lc, docs: docs['INVOICE'].total_amount <= lc.lc_amount,
        "severity": "FATAL"
    },
    {
        "check":   "invoice_description_matches",
        "rule":    "Invoice goods description must match LC description exactly",
        "test":    lambda lc, docs: lc_description_in_invoice(lc, docs['INVOICE']),
        "severity": "FATAL"
    },
    {
        "check":   "presentation_deadline",
        "rule":    "Documents must be presented within {presentation_days} days of BL date",
        "test":    lambda lc, docs: date.today() <= lc.presentation_deadline,
        "severity": "FATAL"
    },
    {
        "check":   "insurance_coverage",
        "rule":    "Insurance must be at least 110% of CIF invoice value",
        "test":    lambda lc, docs: docs['INSURANCE'].insured_amount >= docs['INVOICE'].total * 1.10,
        "severity": "FATAL"
    },
    {
        "check":   "port_name_consistency",
        "rule":    "Port names must be identical across all documents",
        "test":    lambda lc, docs: all_ports_consistent(lc, docs),
        "severity": "WARNING"  -- often waived by banks
    },
]
```

---

## 5. Discrepancy Log

When a compliance check fails, a discrepancy is logged.

```sql
CREATE TABLE lc_discrepancy (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  lc_id             UUID          NOT NULL REFERENCES letter_of_credit(id),
  check_code        VARCHAR(64)   NOT NULL,
  severity          VARCHAR(8)    NOT NULL,   -- FATAL / WARNING
  description       TEXT          NOT NULL,
  document_type     VARCHAR(32),
  detected_at       TIMESTAMPTZ   NOT NULL DEFAULT now(),
  resolved_at       TIMESTAMPTZ,
  resolution        TEXT,
  is_waived         BOOLEAN       NOT NULL DEFAULT false,
  waived_by_bank    BOOLEAN       NOT NULL DEFAULT false
);
```

### Discrepancy resolution paths

| Discrepancy | Resolution |
|---|---|
| Wrong consignee wording | Re-issue BL with corrected text (requires carrier approval) |
| Late presentation | Request bank waiver — may be accepted if importer agrees |
| Wrong port name | Amend the document if not yet presented; or request LC amendment |
| Invoice description mismatch | Re-issue invoice with matching description |
| Insurance coverage short | Increase insured amount and re-issue certificate |

---

## 6. Presentation Tracking

```sql
CREATE TABLE lc_presentation (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  lc_id             UUID          NOT NULL REFERENCES letter_of_credit(id),
  presented_to_bank UUID          NOT NULL REFERENCES organisation(id),
  presented_at      TIMESTAMPTZ   NOT NULL,
  documents_presented TEXT[]      NOT NULL,   -- list of doc types submitted
  has_discrepancies BOOLEAN       NOT NULL DEFAULT false,
  bank_response     VARCHAR(32),              -- COMPLIANT / DISCREPANT / PENDING
  bank_response_date DATE,
  payment_date      DATE,
  payment_amount    NUMERIC(20,6),
  notes             TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 7. Critical Deadlines

The LC module maintains two critical deadlines that must be monitored with URGENT alerts:

| Deadline | Field | Alert |
|---|---|---|
| **Shipment by date** | `lc.shipment_by` | Alert 7 days before — cargo must be loaded by this date |
| **Presentation deadline** | `lc.presentation_deadline` | Alert 5 days before — documents must reach the bank |

```python
def calculate_lc_deadlines(lc_id: str, on_board_date: date) -> None:
    lc = fetch_lc(lc_id)
    presentation_deadline = on_board_date + timedelta(days=lc.presentation_days)
    expiry_deadline       = min(presentation_deadline, lc.expiry_date)

    db.execute("""
        UPDATE letter_of_credit SET
          presentation_deadline = ?,
          updated_at = now()
        WHERE id = ?
    """, expiry_deadline, lc_id)

    create_scheduled_alert(
        job_id    = lc.job_id,
        fire_at   = expiry_deadline - timedelta(days=5),
        severity  = 'URGENT',
        message   = f"LC documents must be presented to {lc.presenting_bank} by {expiry_deadline}"
    )
```

---

## 8. Golden Rules

1. **LC terms override everything.** If the LC says "freight prepaid" and the Incoterm is FOB (normally collect), the BL must still say "freight prepaid" to comply with the LC. The LC is the payment instrument — it takes precedence.
2. **Every discrepancy must be resolved before presentation.** A discrepant document set is almost always refused by the bank. Run the compliance checker before every document set is finalised.
3. **BL date is the most critical date.** The on-board BL date must be on or before the LC's "Shipment By" date. Missing this by one day means no payment. Alert 7 days before.
4. **Never amend a BL without carrier consent.** Re-issuing or amending a BL is a formal process with the carrier — it cannot be done unilaterally. Allow enough time before the presentation deadline.
5. **Discrepancy waivers are the importer's decision, not the bank's.** If documents are discrepant, the issuing bank refers to the applicant (importer). The importer chooses to waive or reject. This can take days — plan accordingly.
