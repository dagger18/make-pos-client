# Freight Forwarder SaaS — Customs Filing Integration

## 1. What Customs Filing Integration Is

Customs filing integration connects the freight forwarding system directly to national customs authority systems — eliminating manual re-entry of shipment data into separate customs software, reducing declaration errors, and providing real-time clearance status back into the job record.

Without integration, customs declarations are prepared in a separate system (or on paper), submitted manually, and status updates are re-typed back into the job. With integration, the declaration is generated from job data and submitted in one action.

---

## 2. Customs Entry Data Model

The customs entry sub-object was introduced in the job object document. Here it is in full detail.

```sql
CREATE TABLE customs_entry (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  entry_type        VARCHAR(16)   NOT NULL,   -- IMPORT / EXPORT / TRANSIT / RE_EXPORT
  entry_mode        VARCHAR(32)   NOT NULL,   -- FORMAL / INFORMAL / SIMPLIFIED / TIR

  -- Declaration reference
  declaration_number VARCHAR(64),             -- assigned by customs authority after submission
  entry_number       VARCHAR(64),             -- internal sequential entry number

  -- Status
  status            VARCHAR(32)   NOT NULL DEFAULT 'DRAFT',
  -- DRAFT / SUBMITTED / ACKNOWLEDGED / ASSESSMENT / EXAMINATION / RELEASED / REJECTED

  -- Customs authority
  customs_office    VARCHAR(64),              -- which office handles this entry
  country_code      CHAR(2)       NOT NULL REFERENCES country(code),
  system_code       VARCHAR(32)   NOT NULL,   -- VNACCS / TRADENET / ACE / CDS / ASYCUDA

  -- Parties
  declarant_id      UUID          REFERENCES organisation(id),    -- customs broker
  importer_id       UUID          REFERENCES organisation(id),    -- importer of record
  exporter_id       UUID          REFERENCES organisation(id),    -- exporter of record

  -- Cargo summary
  total_packages    INT,
  total_weight_kg   NUMERIC(12,3),
  total_volume_cbm  NUMERIC(10,4),
  marks_numbers     TEXT,

  -- Financial values
  fob_value         NUMERIC(20,6),            -- Free on Board value
  freight_amount    NUMERIC(20,6),
  insurance_amount  NUMERIC(20,6),
  cif_value         NUMERIC(20,6),            -- Customs value (FOB + freight + insurance)
  value_currency    CHAR(3),

  -- Duty and tax
  total_duty        NUMERIC(20,6),
  total_vat         NUMERIC(20,6),
  total_excise      NUMERIC(20,6),
  total_tax         NUMERIC(20,6),
  payment_method    VARCHAR(32),              -- CASH / BANK_TRANSFER / DUTY_ACCOUNT / BOND

  -- Dates
  submitted_at      TIMESTAMPTZ,
  acknowledged_at   TIMESTAMPTZ,
  released_at       TIMESTAMPTZ,
  examination_requested_at TIMESTAMPTZ,
  examination_completed_at TIMESTAMPTZ,

  -- Integration
  submission_ref    VARCHAR(128),             -- customs system reference number
  raw_response      JSONB,                    -- full response from customs API

  -- Audit
  prepared_by       UUID          REFERENCES app_user(id),
  submitted_by      UUID          REFERENCES app_user(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  updated_at        TIMESTAMPTZ
);

CREATE INDEX idx_ce_job    ON customs_entry (job_id);
CREATE INDEX idx_ce_status ON customs_entry (status);
CREATE INDEX idx_ce_decl   ON customs_entry (declaration_number) WHERE declaration_number IS NOT NULL;
```

---

## 3. Customs Entry Line (HS Code Level)

Each customs entry contains one or more commodity lines, each with its own HS code and duty calculation.

```sql
CREATE TABLE customs_entry_line (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  customs_entry_id  UUID          NOT NULL REFERENCES customs_entry(id),
  line_number       SMALLINT      NOT NULL,
  hs_code           VARCHAR(12)   NOT NULL REFERENCES hs_code(code),
  description       TEXT          NOT NULL,
  country_of_origin CHAR(2)       NOT NULL REFERENCES country(code),
  packages          INT,
  net_weight_kg     NUMERIC(12,3),
  gross_weight_kg   NUMERIC(12,3),
  quantity          NUMERIC(12,4),
  uom               VARCHAR(16),              -- KG / PCS / LITRE / M2 / ...
  unit_price        NUMERIC(20,6),
  line_value        NUMERIC(20,6),
  value_currency    CHAR(3),
  fta_name          VARCHAR(64),              -- if claiming preferential rate
  duty_rate         NUMERIC(6,4),
  duty_amount       NUMERIC(20,6),
  vat_rate          NUMERIC(6,4),
  vat_amount        NUMERIC(20,6),
  excise_rate       NUMERIC(6,4),
  excise_amount     NUMERIC(20,6),
  restrictions      TEXT[],                   -- licence numbers, permit refs
  is_restricted     BOOLEAN       NOT NULL DEFAULT false,

  UNIQUE (customs_entry_id, line_number)
);
```

---

## 4. The Filing Workflow

```
Step 1 — DATA COLLECTION
  System pulls job data: parties, cargo, HS codes, values, incoterm
  Operator reviews and supplements missing data (HS code if not on cargo)

Step 2 — DUTY ESTIMATION
  System calculates estimated duty per line using duty_rate table
  Operator can override individual line calculations
  System flags restricted HS codes and checks licence requirements

Step 3 — DOCUMENT CHECK
  System verifies all required documents are received (commercial invoice, packing list, etc.)
  Missing documents block submission with specific list of what is needed

Step 4 — DECLARATION GENERATION
  System generates the declaration in the country's required format
  (XML for VNACCS, EDIFACT CUSDEC for others, REST JSON for modern systems)
  Operator reviews the generated declaration

Step 5 — SUBMISSION
  System submits to customs authority API
  Stores raw request and response in integration_message
  Marks customs_entry.status = SUBMITTED

Step 6 — ACKNOWLEDGEMENT
  Customs authority responds with acknowledgement + declaration number
  System updates customs_entry.declaration_number and status = ACKNOWLEDGED

Step 7 — ASSESSMENT (may be automatic or manual by customs)
  Customs system calculates official duty assessment
  If auto-assessed: status moves to RELEASED immediately
  If manual review: status = ASSESSMENT (waiting for customs officer)

Step 8A — RELEASED
  Customs releases the cargo
  System writes milestone: CUSTOMS_RELEASED
  Alerts operator and generates D/O

Step 8B — EXAMINATION (customs hold)
  Customs requests physical inspection
  System writes milestone: EXAMINATION_REQUESTED
  Sub-status = CUSTOMS_HOLD
  Alerts operator + supervisor (URGENT severity)

Step 8C — REJECTED
  Customs rejects the declaration (wrong HS code, missing document, valuation dispute)
  System alerts operator with rejection reason
  Operator corrects and resubmits (creates a new declaration, amendment)
```

---

## 5. Country-Specific Systems

### Vietnam — VNACCS/VCIS

```python
class VNACCSConnector:
    """
    Vietnam Automated Cargo Clearance System.
    Protocol: SOAP/XML via ViettelPost gateway or direct EDI.
    """
    def submit_import_declaration(self, entry: CustomsEntry) -> dict:
        xml = self.build_vnaccs_xml(entry)
        response = self.soap_client.call('submitDeclaration', xml)
        return {
            "declaration_number": response.get('tkhai_so'),
            "status":             "ACKNOWLEDGED" if response['result'] == '0' else "REJECTED",
            "message":            response.get('message')
        }

    def check_status(self, declaration_number: str) -> dict:
        response = self.soap_client.call('getDeclarationStatus',
                                         {'tkhai_so': declaration_number})
        return {
            "status":      self.map_status(response['trang_thai']),
            "duty_amount": response.get('so_thue'),
            "released_at": response.get('thoi_gian_thong_quan')
        }
```

### Singapore — TradeNet

TradeNet is REST-based and requires a TradeNet account and trading partner agreement. Declarations are submitted as JSON and responses are received via webhook.

### EU — CDS (Customs Declaration Service)

CDS is the UK/EU customs system. It uses the CHIEF/CDS XML format for declarations and provides a REST API for submission and status polling.

---

## 6. Advance Manifest Filing

Several jurisdictions require advance electronic filing before the vessel or aircraft departs.

```sql
CREATE TABLE advance_manifest_filing (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  consol_id         UUID          REFERENCES consolidation(id),
  filing_type       VARCHAR(16)   NOT NULL,   -- AMS / ENS / AFR / ISF / 24HR_RULE
  destination_country CHAR(2)     NOT NULL REFERENCES country(code),
  filing_ref        VARCHAR(64),              -- reference assigned by customs authority
  status            VARCHAR(16)   NOT NULL DEFAULT 'PENDING',
  deadline          TIMESTAMPTZ   NOT NULL,   -- must be submitted before this time
  submitted_at      TIMESTAMPTZ,
  response          JSONB,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

Advance manifest filing is triggered automatically when:
- A job is booked with a POD country that requires advance filing
- The ETD minus the filing deadline (24 hours for AMS/ENS) is within the next 48 hours

---

## 7. Customs Hold Management

When customs holds cargo for examination, specific workflows apply:

```python
def handle_customs_hold(customs_entry_id: str, hold_reason: str,
                         examination_date: date) -> None:
    entry = fetch_customs_entry(customs_entry_id)

    # Update entry status
    db.execute("""
        UPDATE customs_entry SET
          status = 'EXAMINATION',
          examination_requested_at = now(),
          updated_at = now()
        WHERE id = ?
    """, customs_entry_id)

    # Update job sub-status
    db.execute("""
        UPDATE shipment SET sub_status = 'CUSTOMS_HOLD'
        WHERE id = ?
    """, entry.job_id)

    # Write milestone
    write_milestone(entry.job_id, 'EXAMINATION_REQUESTED',
                    actual_date=datetime.now(), remarks=hold_reason)

    # URGENT alert to operator, supervisor, and consignee
    create_alert(
        job_id     = entry.job_id,
        alert_type = 'CUSTOMS_HOLD',
        severity   = 'URGENT',
        message    = f"Customs examination requested: {hold_reason}. "
                     f"Examination scheduled: {examination_date}"
    )

    # Create task for operator
    create_task(
        job_id          = entry.job_id,
        title           = f"Prepare for customs examination — {examination_date}",
        task_type       = 'CUSTOMS',
        is_mandatory    = True,
        due_date        = examination_date,
        milestone_gate  = 'CUSTOMS_RELEASED'
    )
```

---

## 8. Golden Rules

1. **Customs data is populated from the job — never re-entered.** The customs entry system reads from the job's cargo detail, party roles, HS codes, and financial values. Operators only supplement what is genuinely missing.
2. **Declaration numbers are assigned by customs — never generated internally.** Do not create your own declaration numbers. Wait for the customs authority's acknowledgement.
3. **Customs holds are URGENT alerts.** Every hour of customs hold costs money in demurrage and delays the consignee. Holds must alert immediately and trigger a workflow, not sit in a queue.
4. **Advance manifest deadlines are calculated and monitored automatically.** A missed AMS or ENS filing results in fines and cargo refusal. The system must alert with enough lead time to correct.
5. **Rejected declarations require a full re-submission, not an edit.** Most customs systems treat rejections as closed transactions. The operator corrects the data in the system and submits a new declaration — the old one is archived.
