# Freight Forwarder SaaS — The Job Object In Depth

## 1. What a Job Is

The job (also called a shipment file, operational file, or dossier depending on the platform) is the central object in the entire freight forwarder system. It is not a single database record — it is a hierarchy of sub-objects that grows as the shipment progresses through its lifecycle.

Everything else in the system — quotes, invoices, documents, milestones, charges, parties — either creates the job or orbits around it.

### Complete object map

```
JOB / Shipment File
├── Core transport
│   ├── Booking
│   ├── Container / ULD          (FCL, FCL-RAIL)
│   ├── Cargo detail             (LCL, AIR, LTL, COU)
│   └── Route
│
├── Documents
│   ├── House BL / HAWB
│   ├── Master BL / MAWB
│   ├── Shipping Instruction
│   ├── Delivery Order
│   ├── Export Declaration
│   ├── Import Customs Entry
│   ├── Certificate of Origin
│   └── Document store          (all attached files)
│
├── Parties
│   ├── Shipper
│   ├── Consignee
│   ├── Notify Party
│   ├── Overseas Agent
│   ├── Carrier
│   ├── Customs Broker
│   ├── Haulier (origin + destination)
│   └── Warehouse / CFS
│
├── Financial
│   ├── Charge lines            (buy + sell per charge code)
│   ├── AR Invoice(s)
│   ├── AP Bill(s)
│   ├── Credit Note(s)
│   ├── Cost sheet              (derived P&L view)
│   └── Payment(s)
│
├── Operations
│   ├── Inland delivery
│   ├── Warehouse instruction
│   ├── Dangerous goods
│   ├── Special handling
│   ├── Arrival notice
│   └── Task list
│
└── Tracking and audit
    ├── Milestones              (planned vs actual event timeline)
    ├── Activity log            (immutable change history)
    └── Notes / Messages
```

---

## 2. The Job Header — The Root Record

The header is the parent record. All sub-objects reference it via `job_id`. It carries the classification, routing, ownership, status, and financial summary of the entire shipment.

```sql
CREATE TABLE shipment (
  -- Identity
  id                  UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  shipment_id         VARCHAR(64)   UNIQUE NOT NULL,      -- HCM-EXP-OCN-202604-00123
  quote_id            UUID          REFERENCES quote(id), -- originating quote if converted

  -- Classification
  transport_mode      VARCHAR(8)    NOT NULL,  -- OCN / AIR / RD / RAL / COU / MMD
  service_type        VARCHAR(16)   NOT NULL,  -- FCL / LCL / FTL / LTL / CONSOL / DIRECT
  direction           VARCHAR(16)   NOT NULL,  -- EXP / IMP / XTD / DOM / TSH
  incoterm            VARCHAR(8),              -- EXW / FOB / CIF / DDP etc.
  freight_terms       VARCHAR(16),             -- PREPAID / COLLECT / CC

  -- Routing
  pol_code            VARCHAR(10)   REFERENCES port(code),
  pod_code            VARCHAR(10)   REFERENCES port(code),
  place_of_receipt    VARCHAR(128),            -- inland origin (door pickup)
  place_of_delivery   VARCHAR(128),            -- inland destination (door delivery)

  -- Dates
  etd                 DATE,                    -- estimated time of departure
  eta                 DATE,                    -- estimated time of arrival
  cargo_ready_date    DATE,                    -- when cargo is available at origin
  cutoff_si           TIMESTAMPTZ,             -- shipping instruction cutoff
  cutoff_vgm          TIMESTAMPTZ,             -- VGM submission cutoff
  cutoff_cargo        TIMESTAMPTZ,             -- cargo receiving cutoff at port/CFS

  -- Ownership
  branch_id           UUID          NOT NULL REFERENCES branch(id),
  department_id       UUID          REFERENCES department(id),
  profit_center_id    UUID          NOT NULL REFERENCES profit_center(id),
  operator_id         UUID          REFERENCES app_user(id),
  sales_rep_id        UUID          REFERENCES app_user(id),
  overseas_agent_id   UUID          REFERENCES organisation(id),
  overseas_agent_ref  VARCHAR(64),             -- the agent's own job reference number

  -- Status
  status              VARCHAR(32)   NOT NULL DEFAULT 'DRAFT',
  sub_status          VARCHAR(32),             -- granular: AWAITING_SI / CUSTOMS_HOLD etc.
  is_on_hold          BOOLEAN       NOT NULL DEFAULT false,
  hold_reason         TEXT,

  -- Financial summary (maintained by trigger or computed column)
  base_currency       CHAR(3)       NOT NULL,
  total_buy           NUMERIC(20,6),
  total_sell          NUMERIC(20,6),
  margin              NUMERIC(20,6),

  -- Consolidation
  consol_id           UUID          REFERENCES consolidation(id), -- for LCL / air consol

  -- Multimodal parent
  parent_job_id       UUID          REFERENCES shipment(id),      -- for MMD sub-legs

  -- Configurable ID
  id_format_id        UUID          REFERENCES id_format_template(id),

  -- Audit
  created_at          TIMESTAMPTZ   NOT NULL DEFAULT now(),
  created_by          UUID          REFERENCES app_user(id),
  updated_at          TIMESTAMPTZ,
  updated_by          UUID          REFERENCES app_user(id),
  closed_at           TIMESTAMPTZ,
  closed_by           UUID          REFERENCES app_user(id)
);
```

---

## 3. Job Status Lifecycle — Full Detail

Every platform has a **main status** (broad phase) and a **sub-status** (granular operational state within that phase). This prevents the status enum from becoming unmanageable as the system grows.

```
DRAFT
  └── sub: PENDING_BOOKING

BOOKED
  └── sub: AWAITING_SI
  └── sub: SI_SUBMITTED
  └── sub: AWAITING_VGM
  └── sub: VGM_SUBMITTED
  └── sub: AWAITING_CARGO_RECEIPT
  └── sub: CARGO_RECEIVED

IN_TRANSIT
  └── sub: LOADED_ON_BOARD
  └── sub: VESSEL_DEPARTED
  └── sub: AT_TRANSSHIPMENT_PORT
  └── sub: VESSEL_ARRIVED

CUSTOMS_CLEARANCE
  └── sub: DOCS_PENDING
  └── sub: ENTRY_FILED
  └── sub: EXAMINATION_REQUESTED    ← customs hold
  └── sub: CUSTOMS_RELEASED

DELIVERY
  └── sub: AVAILABLE_FOR_PICKUP
  └── sub: OUT_FOR_DELIVERY
  └── sub: DELIVERED
  └── sub: POD_RECEIVED

INVOICING
  └── sub: AR_PENDING
  └── sub: AR_ISSUED
  └── sub: PARTIALLY_PAID
  └── sub: FULLY_PAID

CLOSED
  └── sub: NORMAL_CLOSE
  └── sub: CLOSED_WITH_VARIANCE     ← AP/AR mismatch noted

CANCELLED
  └── sub: CANCELLED_BY_CUSTOMER
  └── sub: CANCELLED_NO_SPACE
  └── sub: ROLLED_OVER              ← missed vessel, re-booked on next sailing
```

Each status transition writes a milestone record and can trigger automated actions: customer notifications, task creation, invoice generation, or an exception alert if a deadline was missed.

---

## 4. Parties — The Party Role Model

Parties are not stored as columns on the job header. They are stored as **party role rows** in a join table — one row per role per job. This keeps the header clean and allows roles to be added, changed, or removed without schema changes.

```sql
CREATE TABLE job_party (
  id               UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id           UUID        NOT NULL REFERENCES shipment(id),
  role             VARCHAR(32) NOT NULL,
  organisation_id  UUID        NOT NULL REFERENCES organisation(id),
  contact_id       UUID        REFERENCES contact(id),
  reference        VARCHAR(64),            -- the party's own reference number for this shipment
  is_also_notify   BOOLEAN     NOT NULL DEFAULT false,
  address_snapshot JSONB,                  -- frozen copy of address at time of job creation
  created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),

  UNIQUE (job_id, role)   -- one organisation per role per job
);
```

### Party role codes

| Role code | Description |
|---|---|
| `SHIPPER` | Seller / exporter — who sends the cargo |
| `CONSIGNEE` | Buyer / importer — who receives the cargo |
| `NOTIFY_1` | First notify party — alerted when vessel arrives at POD |
| `NOTIFY_2` | Second notify party (e.g. issuing bank for LC shipments) |
| `OVERSEAS_AGENT` | Partner forwarder at the other end of the trade lane |
| `CARRIER` | Vessel operator or airline |
| `CO_LOADER` | Consolidator used for LCL or air consolidation |
| `CUSTOMS_BROKER` | Import or export customs broker |
| `HAULIER_O` | Origin inland trucker |
| `HAULIER_D` | Destination inland trucker |
| `WAREHOUSE_O` | Origin CFS or warehouse |
| `WAREHOUSE_D` | Destination CFS or warehouse |
| `INSURANCE` | Cargo insurer |
| `SURVEYOR` | Pre-shipment inspection body |
| `BANK` | Issuing or negotiating bank (Letter of Credit transactions) |
| `FUMIGATION` | Fumigation service provider |

### The address snapshot rule

The `address_snapshot` JSONB column is critical for compliance. Addresses in the organisation master can change over time, but the address printed on a bill of lading must always reflect what it was at the time the BL was issued. The snapshot freezes the address at job creation — exactly the same pattern as `fx_rate_snapshot` in the currency system.

```python
def create_job_party(job_id, role, organisation_id):
    org = db.fetch_one("SELECT * FROM organisation WHERE id = ?", organisation_id)
    address_snapshot = {
        "name":    org.name,
        "address": org.address_line_1,
        "city":    org.city,
        "country": org.country,
        "tax_id":  org.tax_id,
    }
    db.insert("job_party", {
        "job_id":           job_id,
        "role":             role,
        "organisation_id":  organisation_id,
        "address_snapshot": json.dumps(address_snapshot),
    })
```

---

## 5. Milestones — The Event Timeline

Milestones are the operational heartbeat of the job. Every significant event is a timestamped row. The gap between `planned_date` and `actual_date` drives exception reports and SLA dashboards.

```sql
CREATE TABLE milestone (
  id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID        NOT NULL REFERENCES shipment(id),
  milestone_code  VARCHAR(32) NOT NULL REFERENCES milestone_master(code),
  planned_date    TIMESTAMPTZ,
  actual_date     TIMESTAMPTZ,
  is_exception    BOOLEAN     NOT NULL DEFAULT false,
  exception_hours NUMERIC(8,2),           -- hours late (positive) or early (negative)
  source          VARCHAR(16) NOT NULL,   -- MANUAL / SYSTEM / EDI / API / CARRIER_TRACKING
  remarks         TEXT,
  updated_by      UUID        REFERENCES app_user(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX idx_ms_job  ON milestone (job_id, actual_date DESC);
CREATE INDEX idx_ms_code ON milestone (milestone_code);
```

### Standard milestone codes for ocean FCL

| Code | Description | Automated trigger |
|---|---|---|
| `JOB_CREATED` | Job created in system | Notify operator |
| `CARGO_BOOKED` | Booking confirmed with carrier | Create SI and VGM tasks |
| `CARGO_READY` | Cargo ready at origin | Start monitoring cutoffs |
| `EMPTY_RELEASED` | Empty container released from depot | |
| `GATE_IN` | Container gated in at origin terminal | |
| `VGM_SUBMITTED` | VGM declared to carrier | |
| `SI_SUBMITTED` | Shipping instruction sent to carrier | |
| `ON_BOARD` | Cargo loaded on vessel — this is the BL date | Issue HBL, notify consignee |
| `VESSEL_DEPARTED` | Vessel departed POL | Start transit timer |
| `AT_TRANSSHIPMENT` | Arrived at transshipment port | Alert if connection at risk |
| `VESSEL_ARRIVED` | Vessel arrived at POD | Send arrival notice |
| `DISCHARGED` | Container discharged from vessel | |
| `AVAILABLE` | Container available for customs / pickup | |
| `ENTRY_FILED` | Customs entry submitted | |
| `CUSTOMS_RELEASED` | Customs cleared | Issue Delivery Order |
| `GATE_OUT` | Container gated out from terminal | |
| `DELIVERED` | Cargo delivered to consignee | |
| `POD_RECEIVED` | Signed proof of delivery received | Trigger AR invoice generation |
| `EMPTY_RETURNED` | Empty container returned to depot | Close detention calculation |
| `JOB_CLOSED` | All charges settled, job locked | Archive |

---

## 6. Tasks — The Operational Checklist

Every job has a task list. Tasks are the operational instructions to the operator — what must be done before the next milestone can be reached.

```sql
CREATE TABLE job_task (
  id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID         NOT NULL REFERENCES shipment(id),
  title           VARCHAR(128) NOT NULL,
  description     TEXT,
  task_type       VARCHAR(32),             -- DOCUMENT / BOOKING / CUSTOMS / INVOICE / FOLLOW_UP
  assigned_to     UUID         REFERENCES app_user(id),
  due_date        TIMESTAMPTZ,
  completed_at    TIMESTAMPTZ,
  completed_by    UUID         REFERENCES app_user(id),
  is_mandatory    BOOLEAN      NOT NULL DEFAULT false,
  milestone_gate  VARCHAR(32),             -- which milestone this task must complete before
  created_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);
```

`milestone_gate` is the key field. If a task has `milestone_gate = 'ON_BOARD'`, the system prevents the `ON_BOARD` milestone from being recorded until all mandatory tasks gated to it are completed. This enforces the operational checklist without hardcoding workflow rules in application logic.

### Auto-generated task list by mode

When a job is created, the system generates a standard task list based on `transport_mode` + `direction`. Example for OCN FCL EXP:

| Task | Type | Mandatory | Milestone gate |
|---|---|---|---|
| Confirm booking with carrier | BOOKING | Yes | CARGO_BOOKED |
| Collect packing list from shipper | DOCUMENT | Yes | SI_SUBMITTED |
| Collect commercial invoice from shipper | DOCUMENT | Yes | SI_SUBMITTED |
| Submit shipping instruction to carrier | BOOKING | Yes | SI_SUBMITTED |
| Obtain and submit VGM | BOOKING | Yes | VGM_SUBMITTED |
| Issue House Bill of Lading | DOCUMENT | Yes | ON_BOARD |
| File export customs declaration | CUSTOMS | Yes | ON_BOARD |
| Send arrival pre-alert to overseas agent | FOLLOW_UP | Yes | VESSEL_DEPARTED |
| Issue AR invoice to shipper | INVOICE | Yes | POD_RECEIVED |

---

## 7. The Document Store and Checklist

Each job has a required document set determined by mode and direction. The document store tracks both what is required and what has been received.

```sql
CREATE TABLE job_document (
  id              UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID         NOT NULL REFERENCES shipment(id),
  doc_type        VARCHAR(32)  NOT NULL,   -- PACKING_LIST / CI / HBL / MBL / COO / CUSTOMS_ENTRY ...
  doc_reference   VARCHAR(64),             -- the document's own reference number
  filename        VARCHAR(255),
  file_url        TEXT,
  is_required     BOOLEAN      NOT NULL DEFAULT true,
  is_received     BOOLEAN      NOT NULL DEFAULT false,
  received_at     TIMESTAMPTZ,
  received_from   UUID         REFERENCES organisation(id),
  issued_by       UUID         REFERENCES organisation(id),
  issue_date      DATE,
  expiry_date     DATE,                    -- for certificates, permits, licences
  remarks         TEXT,
  uploaded_by     UUID         REFERENCES app_user(id),
  created_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);
```

### Required documents by mode and direction

| Document | OCN EXP | OCN IMP | AIR EXP | AIR IMP | RD |
|---|---|---|---|---|---|
| Commercial Invoice | ✓ | ✓ | ✓ | ✓ | ✓ |
| Packing List | ✓ | ✓ | ✓ | ✓ | ✓ |
| House BL / HAWB | ✓ | ✓ | ✓ | ✓ | — |
| Master BL / MAWB | ✓ | ✓ | ✓ | ✓ | — |
| CMR / Waybill | — | — | — | — | ✓ |
| Export Declaration | ✓ | — | ✓ | — | ✓ (cross-border) |
| Import Customs Entry | — | ✓ | — | ✓ | ✓ (cross-border) |
| Certificate of Origin | if required | if required | if required | if required | — |
| Dangerous Goods Declaration | if DG | if DG | if DG | if DG | if DG |
| VGM Certificate | ✓ (FCL) | — | — | — | — |
| Phytosanitary Certificate | if required | if required | if required | if required | if required |

---

## 8. Dangerous Goods — The Special Sub-object

When a job carries dangerous goods (DG / hazmat), an additional sub-object is created regardless of mode. The IMO class drives applicable surcharges, carrier acceptance rules, and required additional documentation.

```sql
CREATE TABLE dangerous_goods (
  id                  UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id              UUID          NOT NULL REFERENCES shipment(id),
  cargo_detail_id     UUID          REFERENCES cargo_detail(id),
  imo_class           VARCHAR(8)    NOT NULL,   -- 1=explosives, 2=gases, 3=flammables, 4=solids, 5=oxidisers, 6=toxic, 7=radioactive, 8=corrosive, 9=misc
  un_number           VARCHAR(8)    NOT NULL,   -- e.g. UN1263 (paint), UN1950 (aerosols)
  packing_group       VARCHAR(4),               -- I (high danger) / II / III (low danger)
  proper_name         VARCHAR(255)  NOT NULL,   -- official chemical/product name per IMDG/IATA DGR
  technical_name      VARCHAR(255),
  flash_point         NUMERIC(6,2),             -- °C (for class 3 flammables)
  net_quantity        NUMERIC(12,3),
  gross_quantity      NUMERIC(12,3),
  uom                 VARCHAR(16),              -- KG / L / units
  emergency_contact   VARCHAR(128),
  msds_url            TEXT,                     -- Material Safety Data Sheet
  is_marine_pollutant BOOLEAN       NOT NULL DEFAULT false,
  is_limited_qty      BOOLEAN       NOT NULL DEFAULT false,
  is_excepted_qty     BOOLEAN       NOT NULL DEFAULT false
);
```

### IMO class reference

| Class | Description | Examples |
|---|---|---|
| 1 | Explosives | Fireworks, ammunition |
| 2 | Gases | LPG, oxygen cylinders, aerosols |
| 3 | Flammable liquids | Paints, solvents, fuel |
| 4 | Flammable solids | Matches, metal powders |
| 5 | Oxidising substances and organic peroxides | Bleach, hydrogen peroxide |
| 6 | Toxic and infectious substances | Pesticides, medical waste |
| 7 | Radioactive material | Medical isotopes |
| 8 | Corrosive substances | Acids, batteries |
| 9 | Miscellaneous dangerous goods | Dry ice, lithium batteries |

---

## 9. The Activity Log — Full Audit Trail

Every change to any field on any sub-object writes an activity log entry. This is the complete, immutable history of everything that happened to a job.

```sql
CREATE TABLE job_activity (
  id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID        NOT NULL REFERENCES shipment(id),
  object_type     VARCHAR(64) NOT NULL,  -- 'shipment' / 'charge_line' / 'milestone' / 'job_party' etc.
  object_id       UUID        NOT NULL,
  action          VARCHAR(32) NOT NULL,  -- CREATE / UPDATE / DELETE / STATUS_CHANGE / NOTE
  field_name      VARCHAR(64),           -- which field changed (for UPDATE actions)
  old_value       TEXT,
  new_value       TEXT,
  performed_by    UUID        REFERENCES app_user(id),
  performed_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
  source          VARCHAR(32) NOT NULL DEFAULT 'USER',  -- USER / SYSTEM / EDI / API
  ip_address      INET,
  remarks         TEXT
);

CREATE INDEX idx_ja_job ON job_activity (job_id, performed_at DESC);
CREATE INDEX idx_ja_obj ON job_activity (object_type, object_id);
```

This table is insert-only. Nothing is ever updated or deleted here. It answers:

- Who changed the ETD and when?
- When was this charge line added and by whom?
- What was the freight rate before the last revision?
- When did the status change from BOOKED to IN_TRANSIT?
- Who approved this invoice and when?

---

## 10. Notes and Messages

```sql
CREATE TABLE job_note (
  id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  job_id          UUID        NOT NULL REFERENCES shipment(id),
  note_type       VARCHAR(16) NOT NULL,   -- INTERNAL / CUSTOMER / AGENT / SYSTEM
  body            TEXT        NOT NULL,
  is_pinned       BOOLEAN     NOT NULL DEFAULT false,
  visible_to      VARCHAR(16) NOT NULL DEFAULT 'INTERNAL',  -- INTERNAL / CUSTOMER / ALL
  created_by      UUID        REFERENCES app_user(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

| note_type | Who can see it | Typical use |
|---|---|---|
| `INTERNAL` | Operators and managers only | Team communication, flags, internal reminders |
| `CUSTOMER` | Surfaced in customer tracking portal | Status updates sent to the shipper or consignee |
| `AGENT` | Shared with the overseas agent | Instructions or queries to the destination forwarder |
| `SYSTEM` | All — auto-generated | "Job status changed to IN_TRANSIT at 14:32 by system" |

---

## 11. The Consolidation Layer

For LCL and air consolidations, the job is a child of a consolidation record. The consol owns the MBL/MAWB, the vessel or flight, and the container or ULD. Individual jobs each own their HBL/HAWB and their portion of the charges.

```sql
CREATE TABLE consolidation (
  id              UUID        PRIMARY KEY DEFAULT gen_random_uuid(),
  consol_id       VARCHAR(64) UNIQUE NOT NULL,   -- HCM-CONSOL-OCN-202604-001
  transport_mode  VARCHAR(8)  NOT NULL,
  service_type    VARCHAR(16) NOT NULL,           -- LCL / CONSOL
  carrier_id      UUID        REFERENCES organisation(id),
  vessel          VARCHAR(64),
  voyage          VARCHAR(32),
  flight_number   VARCHAR(16),
  pol_code        VARCHAR(10) REFERENCES port(code),
  pod_code        VARCHAR(10) REFERENCES port(code),
  etd             DATE,
  eta             DATE,
  mbl_number      VARCHAR(32),
  mawb_number     VARCHAR(32),
  container_id    UUID        REFERENCES container(id),  -- the physical container for LCL
  uld_number      VARCHAR(32),                           -- the ULD for air
  status          VARCHAR(32) NOT NULL,
  branch_id       UUID        NOT NULL REFERENCES branch(id),
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### Consol vs. direct structure

```
Direct FCL shipment:
  shipment (service_type=FCL)
  └── container (one row per container)

LCL consolidation:
  consolidation
  ├── container (the shared box)
  ├── mbl_number
  ├── shipment A (service_type=LCL, consol_id=X) → HBL A → cargo_detail A
  ├── shipment B (service_type=LCL, consol_id=X) → HBL B → cargo_detail B
  └── shipment C (service_type=LCL, consol_id=X) → HBL C → cargo_detail C

Air consolidation:
  consolidation
  ├── mawb_number
  ├── uld_number
  ├── shipment A (service_type=CONSOL, consol_id=X) → HAWB A → cargo_detail A
  └── shipment B (service_type=CONSOL, consol_id=X) → HAWB B → cargo_detail B
```

Port-level charges (THC, ORC, DDF) sit on the consolidation. Each job's proportion is calculated by weight or volume ratio and written as charge lines on the individual job.

---

## 12. What Happens When a Job Is Created

When an operator converts a quote to a job (or creates a job from scratch), the system performs all of the following steps atomically in a single database transaction. If any step fails, the entire operation rolls back.

```
Step 1   Generate shipment_id from the active id_format_template

Step 2   Create the shipment header record
           direction / mode / service_type / incoterm / branch / profit_center

Step 3   Copy party roles from the quote (or prompt for manual entry)
           — snapshot each party's address into job_party.address_snapshot
           — this freeze is permanent: address changes in org master do not affect this job

Step 4   Copy rate lines from the quote as charge_lines
           — snap fx_rate_snapshot for each line's currency at this exact moment
           — assign profit_center_id per the direction + branch rules
           — set payable_at and visible_to per the direction and Incoterm

Step 5   Create the booking sub-object
           status: PENDING_CONFIRMATION until the carrier confirms

Step 6   Create cargo objects based on service_type
           FCL / FCL-RAIL  → container record(s)
           LCL / AIR / LTL → cargo_detail record
           FTL             → truck record
           COU             → parcel record

Step 7   Create the document checklist
           — determine required documents from mode + direction matrix
           — create job_document rows with is_required=true, is_received=false

Step 8   Create the mandatory task list
           — generate standard tasks from mode + direction template
           — assign to the operator
           — set due dates from ETD and known cutoffs

Step 9   Write the first milestone: JOB_CREATED
           source=SYSTEM, actual_date=now()

Step 10  Write the first activity log entry
           action=CREATE, object_type='shipment', performed_by=current_user

Step 11  Send notification to the assigned operator

Step 12  If consol_id is set — add this job to the consolidation's manifest
```

---

## 13. Job Close Checklist

Before a job can be moved to `CLOSED` status, the system validates:

| Check | Condition |
|---|---|
| All mandatory tasks completed | `job_task WHERE is_mandatory=true AND completed_at IS NULL` returns zero rows |
| All required documents received | `job_document WHERE is_required=true AND is_received=false` returns zero rows |
| POD received | `milestone WHERE milestone_code='POD_RECEIVED' AND actual_date IS NOT NULL` |
| All AR invoices issued | `invoice WHERE job_id=? AND type='AR' AND status='DRAFT'` returns zero rows |
| All AR invoices paid or written off | `invoice WHERE job_id=? AND type='AR' AND status NOT IN ('PAID','WRITTEN_OFF')` returns zero rows |
| All AP bills matched | `ap_bill WHERE job_id=? AND is_matched=false` returns zero rows |
| No open variance | `cost_sheet WHERE job_id=? AND variance != 0` — if variance exists, job closes as CLOSED_WITH_VARIANCE |
| Empty container returned (FCL) | `milestone WHERE milestone_code='EMPTY_RETURNED'` — or detention is still accruing |

A job that fails any mandatory check cannot be closed. The UI surfaces the specific blocking items so the operator knows what to resolve.

---

## 14. Summary: The Key Design Rules

1. **The job is a hierarchy, not a single record.** The header is the root; all sub-objects reference it via `job_id`. Never denormalise sub-object data into the header.

2. **Parties are stored as role rows, not columns.** One `job_party` row per role. The address is snapshotted at creation and never updated — changes in the org master do not affect issued documents.

3. **Milestones are planned vs. actual pairs.** The gap between them drives exception management and SLA reporting. Every status change writes a milestone — never update the job status without also writing a milestone row.

4. **Tasks gate milestones.** Mandatory tasks with a `milestone_gate` value prevent the milestone from being recorded until those tasks are done. This enforces the operational checklist at the data layer, not in application code.

5. **The activity log is insert-only.** Every field change on every sub-object writes a log entry. This table is never updated or deleted. It is the complete legal audit trail.

6. **Job creation is a single atomic transaction.** All 12 steps either complete together or roll back entirely. No partial jobs.

7. **Closing a job requires all checks to pass.** Outstanding tasks, missing documents, unpaid invoices, and unmatched AP bills all block closure. A job with a cost variance can close but is flagged `CLOSED_WITH_VARIANCE`.
