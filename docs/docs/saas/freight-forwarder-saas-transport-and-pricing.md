# Freight Forwarder SaaS — Transport Methods and Pricing Deep Dive

## 1. Transport Methods Overview

A freight forwarder SaaS must model each transport method differently because the **unit of measurement, the document set, the milestone chain, and the charge structure** all differ by mode.

| Mode | Key Unit | Primary Document | Typical Transit |
|---|---|---|---|
| Ocean FCL | Per container | Bill of Lading (MBL/HBL) | 10–40 days |
| Ocean LCL | Per CBM / ton (W/M) | Bill of Lading (MBL/HBL) | 15–45 days |
| Air (direct) | Per kg / chargeable weight | Air Waybill (MAWB/HAWB) | 1–5 days |
| Air (consolidation) | Per kg / chargeable weight | MAWB + HAWB | 2–7 days |
| Road FTL | Per truck / per load | CMR / Truck Waybill | Hours to days |
| Road LTL / Groupage | Per kg / CBM / pallet | CMR / Consignment Note | 1–5 days |
| Rail | Per container / wagon | Rail Waybill / CIM | 10–20 days |
| Sea-Air | Hybrid (CBM then kg) | Both B/L and AWB | 8–15 days |
| Courier / Express | Per kg / piece | Courier Waybill | 1–3 days |
| Multimodal | Multiple | Master document + sub-docs | Variable |

---

## 2. Ocean FCL (Full Container Load)

### What it is

The customer books an entire container — 20GP, 40GP, 40HC, reefer, or open-top. Your company acts as NVOCC (Non-Vessel Operating Common Carrier) or pure broker depending on the setup.

### Data model specifics

The container is a first-class object in the job:

| Field | Description |
|---|---|
| `container_number` | ISO format (e.g. MSCU1234567) |
| `container_type` | 20GP / 40GP / 40HC / 40NOR / 40RF / 45HC / 20OT / 40OT |
| `seal_number` | Applied at stuffing |
| `tare_weight` | Empty container weight (kg) |
| `cargo_weight` | Net cargo weight (kg) |
| `vgm` | Verified Gross Mass — legally required before loading |
| `vgm_method` | Method 1 (weighing) or Method 2 (calculated) |
| `vgm_submitted_at` | Timestamp of VGM declaration to carrier |
| `temperature_set` | For reefer containers (°C) |
| `humidity_set` | For reefer (%) |
| `ventilation` | For reefer (CBM/hr) |

### Key milestones unique to FCL

- Empty container released from depot (EMPTY RELEASE)
- Container stuffed / gated in at origin terminal (GATE IN)
- VGM submitted (VGM CONFIRMED)
- Shipping instruction submitted to carrier (SI SUBMITTED)
- Vessel loaded / on board (ON BOARD)
- Vessel arrived at POD (VESSEL ARRIVED)
- Container discharged (DISCHARGED)
- Container available for pickup at destination terminal (CONTAINER AVAILABLE)
- Container gated out to consignee (GATE OUT)
- Empty container returned to depot (EMPTY RETURNED)

### Charge basis

Most charges are **per container** (lump sum regardless of cargo weight). Surcharges like BAF are typically per TEU or per container.

---

## 3. Ocean LCL (Less than Container Load)

### What it is

Multiple shippers share one container. The forwarder or consolidator books the full container from the carrier (MBL level) and issues individual House Bills to each shipper (HBL level). Cargo is handled at a CFS (Container Freight Station) at both origin and destination.

### Data model specifics

LCL jobs do not have a container assigned directly. Instead the job has:

| Field | Description |
|---|---|
| `gross_weight_kg` | Actual weight of the shipment |
| `volume_cbm` | Volume in cubic metres |
| `chargeable_weight` | Max of actual weight (kg) and volumetric equivalent. Ocean W/M standard: 1 CBM = 1,000 kg |
| `pieces` | Number of cartons, pallets, or pieces |
| `marks_numbers` | Cargo marks — printed on cartons and referenced in HBL |
| `consol_id` | Foreign key to the consolidation that carries this shipment |

### The W/M (Weight or Measure) rule

Ocean LCL freight rates are quoted per **W/M** (revenue ton):

```
Chargeable weight = MAX( gross_weight_kg / 1000 ,  volume_cbm )
```

Example: 500 kg cargo, 2.5 CBM volume  
→ Weight ton = 500/1000 = 0.5  
→ Volume = 2.5 CBM  
→ Chargeable = 2.5 W/M (volume wins)

### Additional LCL-specific objects

| Object | Description |
|---|---|
| **Consolidation (CONSOL)** | Groups multiple HBLs under one MBL. Owns the container, the vessel booking, and port-level charges. |
| **CFS receipt** | Proof of cargo received at origin CFS. Generated per HBL. |
| **CFS delivery order** | Authorises release of individual cargo from destination CFS to consignee. |
| **Cargo split** | When a single shipment is split across multiple containers in the consol. |

### Charge basis

Charges are **per W/M** (revenue ton). Minimum charges apply — most carriers and CFS operators have a minimum of 1 W/M regardless of actual shipment size.

---

## 4. Air Freight

### Subtypes

| Subtype | Description |
|---|---|
| **Direct (airport-to-airport)** | Your customer's cargo fills the booking. You issue HAWB; carrier issues MAWB. |
| **Air consolidation** | You group multiple shippers into one MAWB booking. You issue HAWBs, you are the consolidator. |
| **Courier / express** | Single-piece or small shipments via integrators (FedEx, DHL, UPS). Separate waybill, no formal MAWB/HAWB split. |
| **Charter** | Full aircraft chartered for oversized or time-critical cargo. Rare. |

### Data model specifics

| Field | Description |
|---|---|
| `mawb_number` | Master Air Waybill — carrier issues this to your company |
| `hawb_number` | House Air Waybill — you issue this to the shipper |
| `flight_number` | Actual flight(s) used |
| `etd` | Estimated time of departure |
| `eta` | Estimated time of arrival |
| `pieces` | Number of pieces |
| `gross_weight_kg` | Actual weight |
| `volume_cbm` | Volume |
| `chargeable_weight_kg` | See volumetric formula below |
| `commodity_code` | IATA commodity code (drives rate class) |
| `uld_number` | Unit Load Device number if cargo is ULD-loaded |

### Chargeable weight formula (air)

IATA volumetric divisor is **6,000 cm³ per kg**:

```
Volumetric weight (kg) = (length_cm × width_cm × height_cm) / 6000

Chargeable weight = MAX( gross_weight_kg , volumetric_weight_kg )
```

Example: 1 carton, 80×60×50 cm, 15 kg actual  
→ Volumetric = (80×60×50)/6000 = 40 kg  
→ Chargeable = 40 kg (volumetric wins)

### IATA rate breaks (weight bands)

Air freight rates are tiered by total chargeable weight:

| Band | Typical label |
|---|---|
| Minimum charge | M |
| Under 45 kg | N (normal rate) |
| 45 kg and above | +45 |
| 100 kg and above | +100 |
| 300 kg and above | +300 |
| 500 kg and above | +500 |
| 1,000 kg and above | +1000 |

Higher weight bands have **lower per-kg rates**. The system must check whether "breaking up" the shipment into a higher band results in a lower total charge — a practice called **quantity surcharge break-even calculation**.

### Key milestones unique to air

- Cargo booked with airline (BOOKING CONFIRMED)
- Cargo received at origin freight station (CARGO RECEIVED)
- MAWB issued (AWB ISSUED)
- Flight departed (FLIGHT DEPARTED)
- Flight arrived at destination (FLIGHT ARRIVED)
- Cargo available for customs at destination (AVAILABLE FOR CUSTOMS)
- Customs cleared (CUSTOMS CLEARED)
- Cargo available for pickup / delivery (AVAILABLE FOR DELIVERY)
- Proof of delivery (POD SIGNED)

### Air-specific charges

| Charge | Basis |
|---|---|
| Airfreight | Per chargeable kg, per rate band |
| FSC (Fuel Surcharge) | Per kg — changes monthly |
| SSC (Security Surcharge) | Per kg or per shipment |
| AWB fee | Flat per waybill |
| Handling (origin) | Per kg or per shipment at origin freight terminal |
| Handling (destination) | Per kg or per shipment at destination freight terminal |
| X-ray / screening | Per shipment or per kg |
| COD charge | % of COD amount (cash on delivery) |
| Dangerous goods surcharge | Per shipment, requires DGR documentation |
| Perishable handling | Per kg or per shipment, requires cold chain documentation |
| Pharma / GDP handling | Per shipment, temperature-controlled facility required |

---

## 5. Road FTL (Full Truck Load)

### What it is

A single customer's cargo fills an entire truck. Common for domestic haulage, cross-border trucking (e.g. within ASEAN, EU), and first/last-mile as part of an international shipment. The truck may be a standalone service or a sub-leg of a multimodal shipment.

### Data model specifics

| Field | Description |
|---|---|
| `truck_type` | Box truck / curtainsider / flatbed / reefer / tanker / lowloader |
| `payload_capacity_kg` | Maximum payload for the truck type |
| `truck_plate` | Vehicle registration |
| `driver_name` / `driver_id` | Driver details |
| `haulier_id` | Sub-contracted trucking company |
| `pickup_address` | Origin address with contact and access notes |
| `delivery_address` | Destination address |
| `pickup_date` / `delivery_date` | Scheduled times |
| `actual_pickup` / `actual_delivery` | Actual timestamps (for milestone tracking) |
| `pod_signed_by` | Proof of delivery — name of receiver |
| `pod_image_url` | Photo of signed delivery note |

### Documents for road freight

| Document | Purpose |
|---|---|
| **CMR** (Convention Marchandises Routières) | International road consignment note — Europe and cross-border |
| **Waybill / Consignment note** | Domestic road freight |
| **Packing list** | Cargo detail |
| **Delivery note** | Signed by receiver on delivery |
| **Cross-border permit** | Required for some ASEAN and bilateral road agreements |
| **Phytosanitary / Health certificate** | For food, agricultural, or regulated goods |

### Charge basis

Road FTL is typically priced as a **flat rate per truck trip** (a lane rate), often with:

- Base lane rate (origin zone → destination zone)
- Fuel surcharge (% of base or per km)
- Toll charges (actual or estimated)
- Waiting time (per hour beyond free time)
- Additional stop charge (flat per extra collection/delivery point)
- Dangerous goods surcharge
- Tail-lift / loading equipment surcharge

---

## 6. Road LTL (Less than Truck Load) / Groupage

### What it is

Multiple customers' cargo is consolidated into one truck. The trucking company (or the forwarder acting as road consolidator) issues individual consignment notes to each shipper.

### Data model specifics

LTL adds the concept of a **road consolidation** — analogous to the LCL ocean consol:

```
Road Consolidation (truck trip)
├── Truck / vehicle
└── Consignment 1  → Customer A
    Consignment 2  → Customer B
    Consignment 3  → Customer C
```

Each consignment carries:

| Field | Description |
|---|---|
| `pallets` | Number of pallets |
| `ldm` | Loading metres (linear metres of truck floor space occupied) — European standard |
| `gross_weight_kg` | Actual weight |
| `volume_cbm` | Volume |
| `chargeable_weight` | Depends on carrier: may use LDM, CBM, or actual weight |

### Charge basis for LTL

LTL rates vary by carrier — common methods:

| Method | Description |
|---|---|
| **Per pallet** | Fixed rate per euro-pallet |
| **Per LDM** | Rate per loading metre (floor space used in truck) |
| **Per kg** | Rate per kg, with minimum charge |
| **Per CBM** | Rate per cubic metre |
| **Zone matrix** | Origin zone × destination zone = base rate, then scaled by weight/volume |

---

## 7. Rail Freight

### What it is

Increasingly significant for China–Europe trade lanes (Belt and Road routes) and domestic rail in large countries. Models similarly to ocean FCL but with rail-specific terminology.

### Data model specifics

| Field | Description |
|---|---|
| `wagon_number` | Rail wagon identifier |
| `train_number` | Train service number |
| `origin_terminal` | Inland rail terminal (ICD) |
| `destination_terminal` | Destination ICD |
| `etd` / `eta` | Estimated departure/arrival at terminals |
| `block_train_id` | For consolidated block trains — analogous to the ocean consol |

### Key document

**CIM (Convention Internationale concernant le transport des Marchandises par chemin de fer)** — the international rail consignment note, equivalent to the CMR for road.

### Charge basis

Similar to ocean FCL — typically per container or per wagon, with surcharges for:

- Terminal handling (at origin and destination ICD)
- Customs clearance (multiple border crossings for China–Europe)
- Last-mile trucking (from ICD to final address)
- Block train surcharge (if moving on a charter block train)

---

## 8. Sea-Air (Hybrid)

### What it is

Cargo moves by sea for the first leg (usually a long ocean haul to a hub) then transfers to air for the final leg. Used to balance cost and speed — cheaper than pure air, faster than pure ocean.

Common hub: Dubai (DXB) for China–Middle East–Europe sea-air.

### Data model specifics

The job contains two sub-legs:

```
Sea-Air Job
├── Ocean leg:   Origin port → Hub port (Bill of Lading)
└── Air leg:     Hub airport → Destination airport (Air Waybill)
```

Each leg has its own booking, waybill/BL, and milestone chain. The system must track the **transshipment event** at the hub: cargo discharged from vessel, cleared through hub customs, trucked to airport, and uplifted on a flight.

### Charge basis

Freight charges are split — ocean rate per CBM/W/M for the sea leg, air rate per chargeable kg for the air leg. Hub handling and transshipment charges are added as local charges at the hub.

---

## 9. Courier and Express

### What it is

Small shipments handled by integrators (FedEx, DHL Express, UPS, TNT). The forwarder either acts as a reseller of integrator services (using their API to book and track) or runs their own courier consolidation.

### Data model specifics

| Field | Description |
|---|---|
| `tracking_number` | Integrator tracking reference |
| `service_level` | Economy / Express / Priority Overnight |
| `pieces` | Number of packages |
| `gross_weight_kg` | Total actual weight |
| `declared_value` | For customs and insurance purposes |
| `incoterms` | DDP (integrator handles customs) is common for courier |

### Charge basis

Weight-based with surcharges. Key courier surcharges:

- Remote area surcharge (delivery to non-standard postcodes)
- Fuel surcharge (% — updates weekly with some integrators)
- Residential delivery surcharge
- Extended area surcharge
- Oversize / overweight piece surcharge
- Dangerous goods surcharge
- Saturday / out-of-hours delivery

---

## 10. Multimodal

### What it is

Any combination of two or more modes under a single contract and single document. The forwarder issues a **Multimodal Bill of Lading** or **Multimodal Transport Document (MTD)** covering the entire journey. This is the most complex mode to model because the system must manage sub-legs of different types.

### Data model

```
Multimodal Job (master)
├── Multimodal BL / MTD
├── Sub-leg 1: Truck FTL (factory → port)
├── Sub-leg 2: Ocean FCL (port → port)
├── Sub-leg 3: Rail (port inland terminal → city terminal)
└── Sub-leg 4: Truck LTL (city terminal → consignee)
```

Each sub-leg is a job of its own type (road / ocean / rail), linked to the master job. Milestones from each sub-leg feed into the master job's tracking timeline.

---

## 11. Quote Price Structure and Charges — Deep Dive

### The three-level price structure

Every quote assembles charges from three levels before presenting a total:

```
Level 1: FREIGHT CHARGES        (lane-specific, carrier-driven)
Level 2: LOCAL CHARGES          (port-specific, handling and documentation)
Level 3: ANCILLARY CHARGES      (customs, services, surcharges)
─────────────────────────────────────
         TOTAL SELL PRICE
```

### Complete charge taxonomy by transport mode

#### Ocean FCL — full charge list

| Charge Code | Full Name | Basis | Direction |
|---|---|---|---|
| **Base freight charges** | | | |
| OF | Ocean Freight | Per container | Both |
| **Surcharges** | | | |
| BAF | Bunker Adjustment Factor | Per container / per TEU | Both |
| CAF | Currency Adjustment Factor | % of ocean freight | Both |
| PSS | Peak Season Surcharge | Per container | Both |
| EBS | Emergency Bunker Surcharge | Per container | Both |
| LSS | Low Sulphur Surcharge | Per container | Both |
| WRS | War Risk Surcharge | Per container | Both |
| CRS | Congestion Surcharge | Per container (port-specific) | Both |
| GRI | General Rate Increase | Per container | Both |
| **Origin local charges** | | | |
| ORC | Origin Receiving Charge | Per container | Origin |
| THC-O | Terminal Handling Charge (origin) | Per container | Origin |
| VGM | Verified Gross Mass fee | Per container | Origin |
| SEAL | Container seal | Per container | Origin |
| ISPS | ISPS security surcharge | Per container / per BL | Origin |
| AMS | Advance Manifest Surcharge (US trades) | Per BL | Origin |
| ENS | Entry Summary Declaration (EU trades) | Per BL | Origin |
| AFR | Advance Filing Rules (Japan trades) | Per BL | Origin |
| SI FEE | Shipping Instruction fee | Per BL | Origin |
| BL FEE | Bill of Lading issuance fee | Per BL | Origin |
| DOC | Documentation fee | Per BL | Origin |
| STUFFING | CFS stuffing charge (if LCL-to-FCL) | Per CBM | Origin |
| **Destination local charges** | | | |
| THC-D | Terminal Handling Charge (destination) | Per container | Destination |
| DDC | Destination Delivery Charge | Per container | Destination |
| DDF | Destination Handling Fee | Per container | Destination |
| D/O FEE | Delivery Order issuance fee | Per BL | Destination |
| ISPS-D | ISPS surcharge (destination) | Per container | Destination |
| CGST | Cargo scanning / inspection | Per container | Destination |
| STRIPPING | CFS stripping charge (FCL-to-LCL) | Per CBM | Destination |
| DETENTION | Container free time overage | Per container per day | Destination |
| DEMURRAGE | Port storage after free time | Per container per day | Destination |

#### Ocean LCL — additional/different charges

| Charge Code | Full Name | Basis |
|---|---|---|
| OF-LCL | Ocean Freight LCL | Per W/M (revenue ton) |
| CFS-O | Origin CFS handling | Per W/M |
| CFS-D | Destination CFS handling | Per W/M |
| ORC-LCL | Origin receiving charge | Per W/M |
| PORT HANDLING | Port handling at CFS | Per W/M |
| CONSOL FEE | Consolidation service fee | Per BL or per W/M |
| DECONSOLIDATION | Destination deconsolidation | Per W/M |

#### Air freight — full charge list

| Charge Code | Full Name | Basis |
|---|---|---|
| **Base freight** | | |
| AF | Air Freight | Per chargeable kg |
| **Surcharges** | | |
| FSC | Fuel Surcharge | Per kg |
| SSC | Security Surcharge | Per kg or per AWB |
| WRS | War Risk Surcharge | Per kg |
| CSC | Congestion Surcharge | Per kg or per AWB |
| PCA | Pharma / Cold chain surcharge | Per shipment |
| DGS | Dangerous Goods Surcharge | Per shipment |
| OVS | Oversize surcharge | Per shipment |
| OHW | Overweight piece surcharge | Per piece |
| **Origin charges** | | |
| OCH | Origin cargo handling | Per kg or per AWB |
| AWB | Air Waybill fee | Per AWB |
| XRAY | X-ray / screening | Per shipment |
| BOOKING | Booking / consolidation fee | Per AWB |
| TRUCKING-O | Origin pickup trucking | Per shipment or per km |
| **Destination charges** | | |
| DCH | Destination cargo handling | Per kg or per AWB |
| CUSTOMS-AIR | Customs clearance | Per entry |
| TRUCKING-D | Destination delivery trucking | Per shipment or per km |
| STORAGE-AIR | Airline storage after free time | Per kg per day |
| COD | Cash on delivery charge | % of COD amount |

#### Road FTL / LTL — charge list

| Charge Code | Full Name | Basis |
|---|---|---|
| ROAD-BASE | Base trucking rate (lane rate) | Per truck / per trip |
| FUEL-ROAD | Fuel surcharge | % of base or per km |
| TOLL | Toll charges | Actual or estimated |
| WAIT | Waiting time | Per hour beyond free time |
| ADDSTOP | Additional collection/delivery stop | Flat per stop |
| TAIL-LIFT | Tail-lift / loading equipment | Per use |
| PALLET | LTL pallet rate | Per pallet |
| LDM | Loading metre rate (LTL) | Per LDM |
| DG-ROAD | Dangerous goods road surcharge | Per shipment |
| WEEKEND | Weekend/out-of-hours delivery | Flat |
| BORDER | Cross-border handling/permit | Per crossing |
| FUMIGATION | Fumigation (for some cross-border) | Per container / truck |

---

### Surcharge management — key design pattern

Surcharges (BAF, FSC, CAF, GRI, PSS, etc.) are **decoupled from the base rate card** and stored as separate records with their own validity dates. This is critical because:

- BAF and FSC update monthly (sometimes weekly)
- A base ocean rate contract may be valid for 3–6 months
- GRI and PSS are announced with short notice and override the base rate temporarily

```
RATE_CARD  (valid 3 months)
    │
    └── SURCHARGE: BAF  (updates monthly)
    └── SURCHARGE: PSS  (applied Oct–Jan only)
    └── SURCHARGE: GRI  (ad-hoc announcement)
```

When the quote engine assembles a price, it finds all surcharges where `effective_date <= shipment_date <= expiry_date` and linked to the matched rate card (or to the lane/carrier directly).

---

### Free time and detention/demurrage model

Ocean FCL jobs include a **free time** agreement — the number of days the container can sit at the terminal before storage charges begin. This is negotiated per carrier and per port.

| Object | Description |
|---|---|
| `free_time_origin_days` | Days at origin terminal before detention begins |
| `free_time_destination_days` | Days at destination terminal before demurrage begins |
| `detention_rate` | Per container per day beyond free time (detention = container at customer's premises) |
| `demurrage_rate` | Per container per day beyond free time (demurrage = container at port terminal) |
| `actual_return_date` | When empty was returned — used to calculate actual detention charge |

The system should flag jobs where the container has exceeded free time and automatically accrue the estimated additional charge on the cost sheet.

---

### Incoterms and their impact on charge scope

Incoterms determine which party (seller/shipper or buyer/consignee) is responsible for which charges. The SaaS must reflect this when building the quote — charges outside the forwarder's scope should be flagged as "for account of" the other party rather than included in the invoice.

| Incoterm | Freight paid by | Insurance paid by | Import customs/duty paid by |
|---|---|---|---|
| EXW | Buyer | Buyer | Buyer |
| FCA | Buyer | Buyer | Buyer |
| FOB | Buyer | Buyer | Buyer |
| CFR | Seller | Buyer | Buyer |
| CIF | Seller | Seller | Buyer |
| CPT | Seller | Buyer | Buyer |
| CIP | Seller | Seller | Buyer |
| DAP | Seller | Seller | Buyer (duty only) |
| DDP | Seller | Seller | Seller (all incl. duty) |

For DDP shipments, the forwarder must include import duty and customs brokerage in the sell price — this is the only incoterm where the origin party pays destination country duty.

---

### Quote line item structure

Each line in a quote is a structured record, not free text:

| Field | Description |
|---|---|
| `line_id` | Unique identifier |
| `charge_code` | References Charge Master |
| `description` | Display label (can be overridden per quote) |
| `category` | FREIGHT / LOCAL / CUSTOMS / SERVICE |
| `direction` | ORIGIN / DESTINATION / BOTH |
| `calc_basis` | PER_CONTAINER / PER_WM / PER_KG / PER_BL / FLAT / PCT_VALUE |
| `quantity` | Number of units (containers, W/M, kg) |
| `unit_buy_rate` | Buy rate per unit |
| `unit_sell_rate` | Sell rate per unit (customer-facing) |
| `currency` | Rate currency |
| `fx_rate` | Exchange rate applied to convert to quote currency |
| `buy_amount` | Total cost (quantity × unit_buy_rate × fx_rate) |
| `sell_amount` | Total charge to customer (quantity × unit_sell_rate × fx_rate) |
| `margin` | sell_amount − buy_amount |
| `margin_pct` | margin / buy_amount × 100 |
| `is_estimate` | True if the charge is estimated (e.g. customs duty, destination local charges) |
| `payable_at` | ORIGIN / DESTINATION — determines which office invoices the customer |
| `freight_terms` | PREPAID / COLLECT — determines whether shipper or consignee pays freight |

---

### Freight terms: Prepaid vs. Collect

| Term | Meaning | Impact on invoice |
|---|---|---|
| **Prepaid** | Shipper pays ocean/air freight at origin | Origin office invoices shipper for freight |
| **Collect** | Consignee pays ocean/air freight at destination | Destination office invoices consignee for freight |
| **CC (Charges Collect)** | All charges collected at destination | Destination invoices everything |

The freight terms field on the MBL/HBL drives which branch raises the AR invoice for freight charges — a critical workflow split in the accounting module.

---

### Margin rules and pricing strategy

Most SaaS platforms support layered margin rules that apply automatically when sell rates are generated:

| Rule type | Example |
|---|---|
| **Flat markup per charge code** | Add $50 to every THC charge |
| **% markup per charge category** | Add 8% to all freight charges |
| **Customer tier discount** | Gold-tier customers get 5% off sell rates |
| **Volume discount** | More than 10 TEUs per month: rate drops by $30/TEU |
| **Fixed sell rate** | Override — sell at exactly $1,200/40HC regardless of buy rate |
| **Minimum margin** | Never sell below $150/BL total margin |

These rules are evaluated in priority order. The highest-priority matching rule wins, or rules can be additive depending on platform configuration.

---

### Quote validity and acceptance workflow

| Status | Meaning |
|---|---|
| `DRAFT` | Being built — not yet sent to customer |
| `SENT` | Emailed or shared via customer portal — awaiting response |
| `ACCEPTED` | Customer confirmed — triggers job creation |
| `DECLINED` | Customer chose another forwarder |
| `EXPIRED` | Validity date passed without response |
| `REVISED` | A new version was issued (original is superseded) |
| `CONVERTED` | Deprecated term — same as ACCEPTED in older systems |

When a quote is accepted, the system:
1. Creates a shipment job record, copying all rate lines from the quote
2. Locks the sell rates on the job (changes require a rate amendment workflow)
3. Notifies the assigned operator
4. Optionally sends a booking confirmation to the shipper
