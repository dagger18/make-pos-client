# make-pos — POS SaaS Platform Design

**Date:** 2026-07-28
**Verticals:** Retail + F&B (Restaurant)
**Approach:** Clone make-cargo-client + make-cargo-client-bo, strip freight features, scaffold POS modules fresh.

---

## 1. Architecture Overview

Two repositories form the product:

| Repo | Stack | Purpose |
|------|-------|---------|
| `make-pos-client` | Symfony PHP | REST API backend |
| `make-pos-client-bo` | Vue.js + Vite | Back-office / admin SPA |

### Tenant Hierarchy

```
Organisation (SaaS tenant)
└── Branch (location / outlet)
    ├── Staff → Shifts
    ├── Tables → Sections   (F&B only)
    ├── Products → Inventory
    └── Orders → Payments
```

One organisation can have many branches. All POS transactions, tables, inventory, and shifts are scoped to a branch.

### Vertical Handling

Both retail and F&B are served by a single codebase. Each `Branch` carries a `mode` flag (`retail` | `food`) that enables/disables F&B-specific features (table management, kitchen display, product modifiers) at runtime. Products also carry a `type` flag to distinguish food items from retail goods within mixed-mode branches.

---

## 2. Backend Module Structure (`make-pos-client`)

### Keep from make-cargo-client

| Module | Purpose | Notes |
|--------|---------|-------|
| `Core` | Auth, Users, Branches, Departments, Config, Media, UserGroups, Pages | Remove `Port`, `PackageType` entities (freight-only) |
| `Crm` | Leads, Opportunities, Contacts, Activities | Keep as-is |
| `Finance` | Chart of Accounts, Journals, Invoices, Credit Notes, Payments, P&L | Keep as-is |
| `Tax` | Tax rules, VAT reporting | Keep as-is |
| `Notification` | Notification system, user preferences | Keep as-is |
| `Reporting` | Analytics engine, report runner | Keep as-is |

### Remove from make-cargo-client (freight-specific)

- `Carrier`
- `Compliance`
- `Emissions`
- `Insurance`
- `Integration`
- `Lc` (Letter of Credit)
- `Operations`
- `Quote`

### New POS Modules (fresh scaffolds)

| Module | Responsibility |
|--------|----------------|
| `Catalog` | Products, categories, variants, modifiers, pricing |
| `Inventory` | Stock levels per branch, adjustments, purchase orders |
| `Sales` | POS orders, line items, receipts, split bills, payments |
| `Table` | F&B table layouts, sections, live occupancy status |
| `Kitchen` | KDS tickets, kitchen stations, ticket lifecycle |
| `Loyalty` | Points program, redemption rules, customer accounts |
| `Shift` | Staff shifts, cash drawer open/close, daily summary |

Each new module follows the existing pattern: `Controller/`, `Entity/`, `Enum/`, `Repository/`, `Service/`.

---

## 3. Frontend Page Structure (`make-pos-client-bo`)

### Keep from make-cargo-client-bo

| Area | Pages |
|------|-------|
| Auth | `login.vue`, `register/`, `forgot-password.vue`, `login-with-token/` |
| Settings | `branch.vue`, `company.vue`, `users.vue`, `departments.vue`, `global-setting.vue`, `groups.vue`, `pages.vue` |
| CRM | `leads.vue`, `opportunities.vue`, `activities.vue` |
| Accounting | `CO.vue`, `IC.vue`, `ID.vue`, `PMT.vue`, `PO.vue`, `RPT.vue`, `journal.vue`, `pnl-period.vue`, `ageing-ar.vue`, `ageing-ap.vue` |
| Core | `dashboard.vue`, `report.vue`, `profile.vue`, `user-settings.vue` |
| Stores | `appStore.js`, `authStore.js`, `sessionExpiryStore.js` |
| Services | `CommonService.js`, `BranchService.js`, `ConfigService.js`, `UserService.js`, `ChartOfAccountService.js`, `JournalService.js`, `ClientService.js`, `ContactService.js`, `SalesCrmService.js`, `TaxRuleService.js`, `NotificationService.js`, `ReportAnalyticsService.js`, `MediaService.js`, `CurrencyService.js`, `DepartmentService.js`, `ExchangeRateGroupService.js`, `MyProfileService.js`, `NotificationPreferenceService.js`, `OrganisationAddressService.js`, `PageService.js`, `PartnerService.js`, `UserGroupService.js`, `UserInviteService.js`, `VatReportService.js`, `AgeingService.js`, `PnlService.js` |

### Remove from make-cargo-client-bo (freight-specific)

**Pages:** `carrier/`, `portal/`, `provider/`, `shipment/`, `consolidation/`, `rate/`, `quote/`, `warehouse/`, `library/`, `setting/integration-connectors.vue`

**Stores:** `portalAuthStore.js`

**Services (freight-specific, ~50 files):** `ArrivalNoticeService.js`, `AgentProfileService.js`, `BankAccountService.js`, `BookingService.js`, `CapacityService.js`, `CargoClaimService.js`, `CarrierPerformanceService.js`, `CarrierProfileService.js`, `ChargeItemService.js`, `ComplianceService.js`, `ComponentService.js`, `ConsolidationService.js`, `CustomsEntryService.js`, `DangerousGoodsService.js`, `DatasetService.js`, `DdService.js`, `DeliveryOrderService.js`, `EbitNoteService.js`, `EmissionsService.js`, `FlightScheduleService.js`, `InstructionService.js`, `InsuranceService.js`, `IntegrationService.js`, `InvoiceInfoService.js`, `LcService.js`, `ParcelService.js`, `PartnerTaxRegistrationService.js`, `PriceMarkupService.js`, `ProviderService.js`, `QuoteService.js`, `RailBookingService.js`, `RateBenchmarkService.js`, `RateImportService.js`, `RateService.js`, `ShipmentActivityService.js`, `ShipmentDocumentService.js`, `ShipmentLegService.js`, `ShipmentMilestoneService.js`, `ShipmentNoteService.js`, `ShipmentPartyService.js`, `ShipmentService.js`, `ShipmentTaskService.js`, `StrippingService.js`, `StuffingService.js`, `TrackingRequestService.js`, `TruckService.js`, `VesselRollService.js`, `VesselSailingService.js`, `WarehouseFacilityService.js`, `WarehouseReceiptService.js`

### New POS Pages (fresh scaffolds)

| Page | Purpose |
|------|---------|
| `pos/` | Live POS terminal — product grid, cart, checkout |
| `product/` | Product catalog, categories, variants, modifiers |
| `inventory/` | Stock management, adjustments per branch |
| `order/` | Order history, order detail, receipt printing |
| `table/` | F&B table layout editor + live floor view |
| `kitchen/` | KDS display (kitchen-facing screen) |
| `loyalty/` | Loyalty program management, customer points |
| `shift/` | Shift open/close, cash drawer, daily summary |

Each new page gets a corresponding service file in `src/services/`.

---

## 4. Core Data Model (New POS Modules)

Existing Finance/CRM/Core entities are inherited unchanged.

### Catalog

```
Product
  sku, name, description, price, cost
  type: retail | food
  category: ProductCategory
  branch_availability: Branch[]

ProductCategory
  name, parent (self-ref tree), icon

ProductVariant
  product, name, options: {key: value}[], price_override

ModifierGroup
  name, required: bool, min, max

ProductModifier
  group, name, price_delta
```

### Inventory

```
StockLevel
  product, branch, quantity_on_hand

StockAdjustment
  stock_level, reason, quantity_delta, staff, created_at

PurchaseOrder
  branch, supplier (CRM contact), status, line_items[]
```

### Sales

```
Order
  branch, cashier (User), table (nullable)
  type: retail | food
  status: open | paid | voided | refunded
  subtotal, tax_amount, discount_amount, total

OrderItem
  order, product, variant (nullable), modifiers[]
  quantity, unit_price, subtotal

Payment
  order, method: cash | card | qr | loyalty
  amount, change_given, reference
```

### Table (F&B)

```
TableSection
  branch, name (e.g. "Indoor", "Terrace")

Table
  section, name, capacity
  status: available | occupied | reserved
```

### Kitchen

```
KitchenStation
  branch, name (e.g. "Grill", "Bar", "Drinks")

KitchenTicket
  order, station, items[]
  status: pending | in_progress | done
  created_at, started_at, completed_at
```

### Loyalty

```
LoyaltyAccount
  customer (CRM contact), points_balance, tier

LoyaltyTransaction
  account, order, points_delta, type: earned | redeemed
```

### Shift

```
Shift
  branch, cashier (User)
  opened_at, closed_at
  opening_float, closing_count, variance
  status: open | closed
```

---

## 5. Clone & Migration Plan

### Phase 1 — Copy files

- Copy `make-cargo-client/` → `make-pos-client/` (exclude `.git/`, `var/`, `node_modules/`, `vendor/`)
- Copy `make-cargo-client-bo/` → `make-pos-client-bo/` (exclude `.git/`, `node_modules/`, `dist/`)

### Phase 2 — Strip backend (`make-pos-client`)

- Delete `src/Module/{Carrier,Compliance,Emissions,Insurance,Integration,Lc,Operations,Quote}/`
- Delete `src/Module/Core/Entity/Port.php`, `PackageType.php` and their matching Controller/Repository/Service files
- Remove freight module route configs from `config/routes/`
- Remove freight-related Doctrine migration files
- Update `composer.json`: name `make-cargo/client` → `make-pos/client`
- Update `.env.example`: database name, app name

### Phase 3 — Strip frontend (`make-pos-client-bo`)

- Delete freight pages: `src/pages/{carrier,portal,provider,shipment,consolidation,rate,quote,warehouse,library}/`
- Delete `src/pages/setting/integration-connectors.vue`
- Delete `src/stores/portalAuthStore.js`
- Delete ~50 freight-specific service files from `src/services/`
- Update `package.json` name + app title in `index.html` and `themeConfig.js`

### Phase 4 — Rename & rebrand

- Replace all `make-cargo` / `MakeCargo` string references with `make-pos` / `MakePos` in configs, env files, README, and translation files
- Update database name in `.env.example`

### Phase 5 — Scaffold new POS modules

**Backend** — create folder structure for each new module:
```
src/Module/{Catalog,Inventory,Sales,Table,Kitchen,Loyalty,Shift}/
  Controller/
  Entity/
  Enum/
  Repository/
  Service/
```

**Frontend** — create placeholder for each new page + service:
```
src/pages/{pos,product,inventory,order,table,kitchen,loyalty,shift}/index.vue
src/services/{Catalog,Inventory,Sales,Table,Kitchen,Loyalty,Shift}Service.js
```
