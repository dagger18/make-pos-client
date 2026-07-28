# Module Architecture Design

**Goal:** Split the application into 10 named modules that can be independently enabled or disabled per deployment tier (Demo, Pro, Business) via a single `ENABLED_MODULES` environment variable.

**Approach:** Option A — one codebase, one deployment. A PHP attribute `#[AppModule]` tags every API controller. A Kernel compiler pass removes disabled modules' services from the DI container at build time. A request listener enforces the gate at runtime. All entities remain in Doctrine (same schema across all tiers — simpler migrations).

---

## Modules

### `core` — always enabled, not toggleable
Everything every tier needs to function.

**Entities:** User, UserGroup, UserAgent, UserToken, UserNotificationPreference, Branch, Department, Config, OrganisationAddress, Media, Log, SubEntity, Money, Page

**Controllers:** UserController, UserGroupController, MyProfileController, BranchController, DepartmentController, ConfigController, MediaController, PageController

**Services:** UserService, UserGroupService, UserAgentService, UserTokenService, LogService, MediaService, ConfigService, CommonService, BaseService, RequestService, InterServiceTokenService, MasterService, MasterSyncService, MailService

---

### `quote` — quoting & rate engine
**Entities:** Quote, QuotePrice, Rate, PriceMarkup, CalculationType, CustomChargeType, Incoterm, FreeTimeAgreement

**Controllers:** QuoteController, RateController, PriceMarkupController, CalculationTypeController, CustomChargeTypeController, IncotermController, FreeTimeAgreementController

**Services:** QuoteService, QuotePriceService, RateService, PriceMarkupService, CalculationTypeService, CustomChargeTypeService, IncotermService, QuoteCodeGeneratorService

**Dependencies:** `core`

---

### `finance` — accounting & money
**Entities:** EbitNote, ChargeItem, Charge, JournalEntry, JournalLine, ChartOfAccount, BankAccount, InvoiceInfo, ExchangeRate, ExchangeRateGroup, Currency, PaymentMethod, CreditLimitHistory, TaxGroup, TaxRule

**Controllers:** EbitNoteController, ChargeController, ChargeItemController, JournalEntryController, ChartOfAccountController, BankAccountController, InvoiceInfoController, ExchangeRateGroupController, CurrencyController, PaymentMethodController, AgeingController, PnlController, AccountingCloseController

**Services:** EbitNoteService, ChargeService, ChargeItemService, JournalPostingService, BankAccountService, CurrencyService, ExchangeRateGroupService, FxGainLossService, InvoiceInfoService, TaxGroupService, CreditCheckService, DdCalculatorService

**Dependencies:** `core`, `operations`, `quote`

---

### `tax` — VAT, HS code & customs
**Entities:** CustomerTaxExemption, PartnerTaxRegistration, HsCode, HsRestriction, HsVersionMapping, DutyRate

**Controllers:** CustomerTaxExemptionController, PartnerTaxRegistrationController, HsCodeController, HsRestrictionController, HsVersionMappingController, DutyRateController, VatReportController

**Services:** HsCodeService, HsRestrictionService, HsVersionMappingService, DutyRateService

**Dependencies:** `core`, `finance`

---

### `operations` — job execution & documents
**Entities:** Shipment, ShipmentActivity, ShipmentDocument, ShipmentMilestone, ShipmentMode, ShipmentNote, ShipmentParty, ShipmentTask, Booking, Instruction, InstructionContainer, Consolidation, DeliveryOrder, ArrivalNotice, DangerousGoods, Archive

**Controllers:** ShipmentController, ShipmentActivityController, ShipmentDocumentController, ShipmentMilestoneController, ShipmentModeController, ShipmentNoteController, ShipmentPartyController, ShipmentTaskController, BookingController, InstructionController, ConsolidationController, DeliveryOrderController, ArrivalNoticeController, DangerousGoodsController

**Services:** ShipmentService, ShipmentActivityService, ShipmentDocumentService, ShipmentMilestoneService, ShipmentModeService, ShipmentTaskService, BookingService, InstructionService, ArrivalNoticeService, DeliveryOrderService, ArchiveService, ShipmentIdGeneratorService

**Dependencies:** `core`, `quote`

---

### `carrier` — carrier intelligence & tracking
**Entities:** Provider, CarrierProfile, CarrierEventMapping, CarrierPerformanceScore, CargoClaim, VesselRoll, TrackingRequest, TrackingEventRaw, ContainerDdTracking

**Controllers:** ProviderController, CarrierProfileController, CarrierEventMappingController, CarrierPerformanceController, CargoClaimController, VesselRollController, VesselSailingController, TrackingRequestController, TrackingWebhookController, ContainerDdController, FlightScheduleController

**Services:** ProviderService, CarrierEventMappingService, CarrierPerformanceScoreService, TrackingRequestService, TrackingEventRawService, TrackingMilestoneWriterService

**Dependencies:** `core`, `operations`

---

### `crm` — parties & relationships
**Entities:** Client, Partner, Contact, AgentProfile

**Controllers:** ClientController, ContactController, AgentProfileController

**Services:** ClientService, ContactService

**Dependencies:** `core`

---

### `notification` — alerts & messaging
**Entities:** NotificationQueue, NotificationTemplate, NotificationRule, InAppNotification, Mail

**Controllers:** *(none — internal only)*

**Services:** NotificationGeneratorService, NotificationTemplateRenderer, InAppNotificationService

**Dependencies:** `core`

---

### `reporting` — KPI & dashboards
**Entities:** Dataset, DatasetFilter, DatasetProp

**Controllers:** DatasetController, KpiController, ReportAnalyticsController

**Services:** DatasetService

**Dependencies:** `core`, `finance`, `operations`

---

### `integration` — customer portal & EDI
**Entities:** PortalUser, PortalToken, PortalQuoteRequest

**Controllers:** *(Portal controllers live under `src/Controller/Portal/`)*

**Services:** PortalAuthService, PortalDocumentService, PortalInvoiceService, PortalQuoteRequestService, PortalShipmentService

**Dependencies:** `core`, `operations`, `finance`

---

## Dependency Graph

```
core
├── quote
│   └── operations
│       ├── finance
│       │   └── tax
│       ├── carrier
│       └── reporting (also needs finance)
├── crm
├── notification
└── integration (also needs operations + finance)
```

---

## Tier Map

| Module         | DEMO | PRO | BUSINESS |
|----------------|:----:|:---:|:--------:|
| `core`         |  ✓   |  ✓  |    ✓     |
| `quote`        |  ✓   |  ✓  |    ✓     |
| `operations`   |  ✓   |  ✓  |    ✓     |
| `finance`      |  ✓¹  |  ✓  |    ✓     |
| `tax`          |  —   |  ✓² |    ✓     |
| `carrier`      |  —   |  ✓  |    ✓     |
| `crm`          |  —   |  ✓  |    ✓     |
| `notification` |  —   |  ✓  |    ✓     |
| `reporting`    |  —   |  ✓  |    ✓     |
| `integration`  |  —   |  —  |    ✓     |

> ¹ DEMO finance: invoicing endpoint active, but `AccountingCloseController`, `JournalEntryController`, `AgeingController`, `PnlController` disabled via sub-module flags (future phase).
> ² PRO tax: VAT/HS code enabled; customs filing integration disabled (BUSINESS only).

**ENABLED_MODULES values per tier:**
```bash
# Demo
ENABLED_MODULES=core,quote,operations,finance

# Pro
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting

# Business
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting,integration
```

---

## Activation Mechanism (Option A)

All code lives in one deployment. The env var controls which modules are active.

**Build time (Compiler Pass in `Kernel::process()`):**
- Reads `ENABLED_MODULES` env var
- Iterates all services in the container
- Removes any service whose class has `#[AppModule('x')]` where `x` is disabled
- Disabled controllers never enter the DI container → never dispatched → no route resolution needed

**Request time (fallback `ModuleGuardListener`):**
- Runs on `KernelEvents::CONTROLLER` event
- Reads `#[AppModule]` attribute from the resolved controller class via reflection
- Returns HTTP 403 if the module is disabled
- This is a safety net for cached containers during the warm-up transition window

**Entity scanning:** All entities remain in Doctrine for all tiers — same DB schema everywhere. This keeps migrations simple and lets you upgrade a client's tier without a schema migration.

---

## Future Phase — Directory Restructuring (Plan B)

Once the activation system is live, the optional next step is reorganising `src/` into module subdirectories:

```
src/
  Module/
    Quote/
      Entity/Quote.php
      Controller/QuoteController.php
      Repository/QuoteRepository.php
      Service/QuoteService.php
    Finance/
      ...
  Shared/           ← core cross-cutting (Money, Media, Log, ...)
  Misc/             ← unchanged framework utilities
```

This is ~350 file moves + namespace updates across the whole codebase. It is purely a developer-experience improvement — it does not change runtime behaviour. Tackle it as a dedicated branch when there is no in-flight feature work.
