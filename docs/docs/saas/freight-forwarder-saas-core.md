# Freight Forwarder SaaS — Core System Design

## 1. Rate System Architecture

### Overview

A freight forwarder SaaS rate system is not four separate modules — it is four **tariff tables** that all reference a shared **Charge Master**, fed into a single rate aggregation engine that assembles quotes.

### Charge Master

Every charge across all categories shares a common schema:

| Field | Description |
|---|---|
| `charge_code` | Unique code (e.g. THC, BAF, DOC, DUTY) |
| `description` | Human-readable label |
| `calc_basis` | How it is calculated: flat, per container, per kg/CBM, per BL, % of value |
| `currency` | Default billing currency |
| `min_charge` | Minimum threshold |
| `max_charge` | Maximum cap (optional) |
| `category` | FREIGHT / LOCAL / CUSTOMS / SERVICE — classification tag for grouping |

The `category` field is mainly a UI filter and reporting tag. The underlying data structure is identical across all four categories.

---

### The Four Tariff Tables

#### 1. Freight Charges

Keyed by **trade lane + carrier + mode + container type**. Highly time-sensitive.

| Field | Description |
|---|---|
| `pol_code` | Port of loading (origin) |
| `pod_code` | Port of discharge (destination) |
| `carrier_id` | Carrier or NVOCC |
| `mode` | FCL / LCL / AIR / ROAD |
| `container_type` | 20GP / 40GP / 40HC / 40RF etc. |
| `base_rate` | Ocean/air base freight per unit |
| `effective_date` | Rate validity start |
| `expiry_date` | Rate validity end |
| `currency` | Rate currency |
| `customer_id` | Null = general tariff; set = contract rate for specific customer |

> **Key design rule:** Customer-specific rate cards take priority over general tariff when both match the same lane and validity window.

#### 2. Local Charges

Keyed by **port/terminal**, not by lane. A charge like THC at Ho Chi Minh City applies to every shipment departing or arriving at that port, regardless of destination. Stored once and reused across all rate cards touching that port.

| Field | Description |
|---|---|
| `port_code` | Port this charge applies at |
| `direction` | ORIGIN / DESTINATION / BOTH |
| `mode` | FCL / LCL / AIR |
| `charge_code` | e.g. THC, ISPS, DOC FEE, SEAL |
| `amount` | Per container, per BL, or per shipment |
| `effective_date` / `expiry_date` | Validity window |

**Common local charges:**

- **THC** — Terminal handling charge (origin and/or destination)
- **ISPS** — International Ship & Port Facility Security surcharge
- **DOC FEE** — Documentation fee (BL issuance)
- **SEAL** — Container seal fee
- **AMS / ENS / AFR** — Advance manifest filing fees (regulated per trade lane)
- **VGM** — Verified gross mass submission fee
- **ORC** — Origin receiving charge (origin CFS handling for LCL)
- **DDF** — Destination delivery fee (destination CFS handling for LCL)

#### 3. Customs Charges

Keyed by **country** (and sometimes HS code category). Regulatory in nature — driven by government tariff schedules, not carrier pricing.

| Field | Description |
|---|---|
| `country_code` | Destination or origin country |
| `hs_chapter` | Optional HS code chapter for duty rate lookup |
| `charge_type` | CUSTOMS_CLEARANCE / IMPORT_DUTY / VAT / EXCISE / INSPECTION |
| `calc_basis` | % of CIF value (duty), flat per entry (clearance), per kg (inspection) |
| `rate` | Duty rate as decimal (e.g. 0.12 = 12%) |

> Many platforms link to an **HS code reference table** or external customs API (e.g. national customs authority feeds) rather than maintaining duty rates manually.

#### 4. Service Charges

Keyed by **service type + location/vendor**. Covers ancillary services outside ocean/air freight.

| Field | Description |
|---|---|
| `service_type` | TRUCKING / WAREHOUSING / INSURANCE / FUMIGATION / LABELING / STUFFING |
| `vendor_id` | Sub-contractor providing the service |
| `location` | City or zone where the service is performed |
| `calc_basis` | Per km / % of cargo value / flat / per pallet / per hour |
| `rate` | Unit rate |

---

### Buy Rate vs. Sell Rate (The Margin Layer)

Every charge in every category has two parallel values:

| Value | Meaning |
|---|---|
| **Buy rate** (cost) | What you pay to the carrier, agent, or vendor |
| **Sell rate** | What you charge the customer |

The sell rate is either:
- **Cost + markup rule** — a percentage or fixed amount added on top of buy rate
- **Contract rate** — a customer-specific rate that overrides the general tariff entirely

This buy/sell split exists across all four categories. The **cost sheet** on every job shows both columns, making per-job margin immediately visible.

---

### Rate Aggregation Engine (Quote Engine)

When a quote is requested, the engine resolves each category independently:

1. Find matching `RATE_CARD` rows where `pol`, `pod`, `mode` match and `effective_date <= shipment_date <= expiry_date`
2. Prefer customer-specific rate cards over general tariff
3. Join to `RATE_CARD_LINE` for the requested container type
4. Pull active surcharges linked to the matched rate card
5. Pull local charges for `pol_code` (origin) and `pod_code` (destination)
6. Apply customs charges for destination country
7. Add selected service charges
8. Convert all currencies to quote currency using FX rates
9. Apply sell-rate markup rules
10. Assemble final quote line items grouped by category

---

### Rate Validity and Versioning

Rates are **never overwritten** — they are versioned by effective/expiry date.

- When a new rate is received, a new record is created with a new validity window
- The old rate is marked expired, not deleted
- Past quotes and bookings remain linked to the rate that was valid at the time
- Freight rates are often bulk-imported via Excel templates or carrier API feeds
- Local/customs/service rates are typically maintained manually (change less frequently)

---

## 2. Internal Organisation Structure

### Terminology Map

| Term in SaaS | Meaning |
|---|---|
| **Branch / Station** | A physical office location (e.g. Ho Chi Minh City, Hanoi). Station is the IATA-origin term with a station code (e.g. SGN). |
| **Department / Desk** | A team within a branch, typically split by direction (Import Desk, Export Desk) or by mode (Ocean Dept, Air Dept). |
| **Operator / Handler** | The internal staff member assigned to manage a specific job. Also called Job Owner or Job Handler. |
| **Overseas Agent** | An external partner company at the other end of the trade lane — a party role on the shipment, not an internal user. |
| **Division** | Used for mode or trade-lane separation at the company level (Ocean Division, Air Division). |

> **Critical distinction:** "Agent" in freight forwarding means two completely different things. In the system, the **overseas agent** is a party role (external organisation). The **internal agent** is an operator/user — always stored separately.

### Hierarchy

```
Company (HQ entity)
└── Branch / Station  (geographic office)
    ├── Import Department / Desk
    │   └── Operator  (staff member)
    │       └── Job / Shipment File  (direction: IMPORT)
    └── Export Department / Desk
        └── Operator  (staff member)
            └── Job / Shipment File  (direction: EXPORT)
```

Permissions, default tariffs, and reporting roll up this hierarchy. A branch-level rate card applies to all jobs in that branch unless overridden at the department or job level.

### Shipment Direction Values

| Value | Meaning |
|---|---|
| `IMPORT` | Cargo arriving into your country/office |
| `EXPORT` | Cargo departing from your country/office |
| `CROSS_TRADE` | Both origin and destination are foreign — your office is the coordinating intermediary |
| `DOMESTIC` | Origin and destination are within the same country |
| `TRANSSHIPMENT` | Cargo moves through your port/hub en route to final destination |

---

## 3. Shipment File Structure and Lifecycle

A shipment file is not a single record. It is a **hierarchy of objects** that grows as the shipment progresses through four phases.

### Phase 1 — Quote

The root object. Everything else hangs off this once the customer accepts.

**Quotation object:**

| Sub-object | Description |
|---|---|
| Rate lines | Freight + local + customs + service charges. Each line has charge_code, basis, buy_amount, sell_amount, currency, validity. |
| Parties snapshot | Shipper, consignee, notify party captured at quote time. Copied forward to the job on acceptance. |
| Route & mode | POL, POD, mode (FCL/LCL/Air), container type or weight/volume estimate, incoterms. |

Status lifecycle: `DRAFT → SENT → ACCEPTED / EXPIRED / DECLINED`

---

### Phase 2 — Booking (Export Leg)

Created at the origin branch when the customer confirms. Owns the export-side workflow.

**Shipment Job (Export) sub-objects:**

| Object | Key Fields | Description |
|---|---|---|
| **Booking order** | carrier_booking_ref, vessel, voyage, etd, eta, cutoff_si, cutoff_vgm | Sent to carrier or co-loader. Captures cut-off deadlines. |
| **House Bill (HBL)** | hbl_number, shipper, consignee, notify, description, gross_weight, volume | Your contract with the shipper. Issued by you. |
| **Master Bill (MBL)** | mbl_number, carrier, consol_id, freight_terms | Issued by the carrier to your company. One MBL can cover multiple HBLs. |
| **Containers / ULDs** | container_number, type, seal, vgm, vgm_method | FCL: one row per container. Air: ULD or loose cargo. |
| **Shipping Instruction (SI)** | — | Formal instruction to carrier on how to issue the MBL. Must be submitted before SI cut-off. |
| **Export customs entry** | declaration_number, hs_codes, declared_value, customs_status | Export declaration filed with customs authority. |

---

### Phase 3 — Operations (Import Leg)

Created at the destination branch, linked to the same master shipment. Triggered when the vessel or flight departs.

**Shipment Job (Import) sub-objects:**

| Object | Key Fields | Description |
|---|---|---|
| **Arrival notice** | — | Sent to consignee when ETA is confirmed. Contains vessel details, estimated charges, and document checklist. |
| **Import customs entry** | entry_number, hs_codes, customs_value, duty_amount, tax_amount, customs_status | Import declaration at destination. Duty and tax calculated against tariff schedule. |
| **Delivery Order (D/O)** | do_number, release_type (OBL/telex/seaway), issued_date | Authorises release of cargo from terminal after customs clearance and charge settlement. |
| **Inland delivery** | haulier, pickup_date, delivery_date, pod_signed_by | Trucking job from port/airport to consignee warehouse. Generates its own cost line. |

---

### Phase 4 — Accounting and Close

Generated throughout the job but reconciled and formally closed here.

| Object | Description |
|---|---|
| **AR Invoice** | Raised against shipper or consignee for sell-rate charges. A job can have multiple invoices (origin charges billed separately from destination charges). |
| **AP Bill** | Received from carriers, overseas agents, truckers, customs brokers. Matched against buy rates in the cost sheet. |
| **Cost Sheet** | Running P&L for the job. Buy vs. sell per charge line. Variance flag when AP bill differs from estimated buy rate. |
| **Credit Note** | Issued when a charge is disputed or reversed. Linked back to the original AR invoice. |

---

### Cross-Cutting Objects (Present Throughout All Phases)

| Object | Key Fields | Description |
|---|---|---|
| **Milestones / Events** | milestone_code, actual_date, planned_date, remarks, updated_by | Timestamped audit trail: cargo received → vessel departed → arrived at POD → customs cleared → delivered → job closed. Drives KPI dashboards and customer tracking portals. |
| **Document Store** | doc_type, filename, uploaded_by, date, required (bool) | Every document attached to the job — packing list, commercial invoice, certificate of origin, B/L copy, customs entry PDF. Tagged by document type so the system flags what is still missing. |
| **Party Roles** | party_type, organisation_id | Shipper, Consignee, Notify Party, Overseas Agent, Co-loader, Customs Broker, Trucker — each a role on the job, pointing to an organisation in the address book. |

---

### Consolidation Model (LCL and Air)

For LCL ocean and air consolidations, an additional layer sits above the individual jobs:

```
Consolidation (CONSOL)
├── Master Bill (MBL / MAWB)    — carrier's contract with you
├── Container / ULD              — the physical unit
└── House Bill 1 (HBL / HAWB)  → Job A (one customer's cargo)
    House Bill 2 (HBL / HAWB)  → Job B (another customer's cargo)
    House Bill 3 (HBL / HAWB)  → Job C (another customer's cargo)
```

The consol owns the port-level charges (THC, ORC, DDF). Each job carries its proportional share, calculated by weight or volume ratio.

---

### Job Status Lifecycle

```
QUOTE ACCEPTED
      ↓
BOOKED  (booking confirmed with carrier)
      ↓
CARGO RECEIVED  (at CFS or port)
      ↓
DEPARTED  (vessel/flight ETD)
      ↓
IN TRANSIT
      ↓
ARRIVED  (at POD)
      ↓
CUSTOMS CLEARANCE
      ↓
DELIVERED  (proof of delivery signed)
      ↓
INVOICED  (AR invoice sent)
      ↓
CLOSED  (all AP/AR settled, job locked)
```

Each transition writes a milestone record and can trigger automated customer notifications.
