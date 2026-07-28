# make-pos Clone & Strip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clone make-cargo-client → make-pos-client and make-cargo-client-bo → make-pos-client-bo, strip all freight-forwarder features, and scaffold fresh POS modules.

**Architecture:** Two repos — Symfony PHP backend (`make-pos-client`) + Vue.js SPA back-office (`make-pos-client-bo`). All freight-specific modules are deleted outright. Generic SAAS infrastructure (Core, CRM, Finance, Tax, Notification, Reporting) is kept. Seven new POS modules are scaffolded as empty stubs for future implementation.

**Tech Stack:** PHP 8.2 / Symfony 6+, Doctrine ORM + Migrations, Vue 3 / Vite / Vuetify, pnpm

---

## Task 1: Copy backend files from make-cargo-client to make-pos-client

**Files:**
- Target: `d:/Projects/make-pos-client/` (current repo — already has only README.md and .git)

- [ ] **Step 1: Copy all source files (excluding .git, var, node_modules, vendor)**

Run from Git Bash or bash shell:
```bash
rsync -av --exclude='.git' --exclude='var/' --exclude='node_modules/' --exclude='vendor/' \
  /d/Projects/make-cargo-client/ /d/Projects/make-pos-client/
```
Expected: Files copied — `src/`, `config/`, `migrations/`, `templates/`, `translations/`, `assets/`, `assets-pdf/`, `bin/`, `public/`, `scripts/`, `tests/`, `composer.json`, `composer.lock`, `webpack.config.js`, `importmap.php`, `symfony.lock`, `phpunit.xml.dist`, `.env.example`

- [ ] **Step 2: Verify key directories exist**

```bash
ls /d/Projects/make-pos-client/src/Module/
```
Expected output includes: `Carrier  Compliance  Core  Crm  Emissions  Finance  Insurance  Integration  Lc  Notification  Operations  Quote  Reporting  Tax`

- [ ] **Step 3: Commit initial copy**

```bash
cd /d/Projects/make-pos-client
git add -A
git commit -m "chore: initial copy from make-cargo-client"
```

---

## Task 2: Copy frontend files from make-cargo-client-bo to make-pos-client-bo

**Files:**
- Target: `d:/Projects/make-pos-client-bo/` (already has only README.md and .git)

- [ ] **Step 1: Copy all source files (excluding .git, node_modules, dist)**

```bash
rsync -av --exclude='.git' --exclude='node_modules/' --exclude='dist/' \
  /d/Projects/make-cargo-client-bo/ /d/Projects/make-pos-client-bo/
```
Expected: All source files copied — `src/`, `public/`, `scripts/`, `e2e/`, `playwright/`, `.vscode/`, `fontCompressor/`, config files

- [ ] **Step 2: Verify key directories exist**

```bash
ls /d/Projects/make-pos-client-bo/src/pages/
```
Expected output includes: `accounting  carrier  client  consolidation  crm  dashboard.vue  quote  rate  shipment  warehouse  setting  ...`

- [ ] **Step 3: Commit initial copy**

```bash
cd /d/Projects/make-pos-client-bo
git add -A
git commit -m "chore: initial copy from make-cargo-client-bo"
```

---

## Task 3: Remove freight backend modules

**Files:**
- Delete: `src/Module/Carrier/`
- Delete: `src/Module/Compliance/`
- Delete: `src/Module/Emissions/`
- Delete: `src/Module/Insurance/`
- Delete: `src/Module/Integration/`
- Delete: `src/Module/Lc/`
- Delete: `src/Module/Operations/`
- Delete: `src/Module/Quote/`

Working directory: `d:/Projects/make-pos-client`

- [ ] **Step 1: Delete all freight modules**

```bash
cd /d/Projects/make-pos-client
rm -rf src/Module/Carrier
rm -rf src/Module/Compliance
rm -rf src/Module/Emissions
rm -rf src/Module/Insurance
rm -rf src/Module/Integration
rm -rf src/Module/Lc
rm -rf src/Module/Operations
rm -rf src/Module/Quote
```

- [ ] **Step 2: Verify only SAAS + Reporting modules remain**

```bash
ls src/Module/
```
Expected: `Core  Crm  Finance  Notification  Reporting  Tax`

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: remove freight backend modules (Carrier, Compliance, Emissions, Insurance, Integration, Lc, Operations, Quote)"
```

---

## Task 4: Clean freight artifacts from Core module

**Files:**
- Delete: `src/Module/Core/Entity/Port.php`
- Delete: `src/Module/Core/Entity/PackageType.php`
- Delete: `src/Module/Core/Controller/PortController.php`
- Delete: `src/Module/Core/Controller/PackageTypeController.php`
- Delete: `src/Module/Core/Repository/PortRepository.php`
- Delete: `src/Module/Core/Repository/PackageTypeRepository.php`
- Delete: `src/Module/Core/Service/PortService.php`
- Delete: `src/Module/Core/Service/PackageTypeService.php`
- Delete: `src/Module/Core/Enum/PortType.php`
- Modify: `src/Module/Core/Enum/Permission.php`

Working directory: `d:/Projects/make-pos-client`

- [ ] **Step 1: Delete Port and PackageType files**

```bash
cd /d/Projects/make-pos-client
rm src/Module/Core/Entity/Port.php
rm src/Module/Core/Entity/PackageType.php
rm src/Module/Core/Controller/PortController.php
rm src/Module/Core/Controller/PackageTypeController.php
rm src/Module/Core/Repository/PortRepository.php
rm src/Module/Core/Repository/PackageTypeRepository.php
rm src/Module/Core/Service/PortService.php
rm src/Module/Core/Service/PackageTypeService.php
rm src/Module/Core/Enum/PortType.php
```

- [ ] **Step 2: Remove freight cases from Permission enum**

Open `src/Module/Core/Enum/Permission.php`. Find and remove these cases (they reference freight operations):
```
case PackageType_MANAGE = '804';
case Rate_MANAGE_Import = '854';
```
Leave all other permission cases untouched.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: remove Port and PackageType entities from Core module"
```

---

## Task 5: Fix CapacityService and CapacityType enum

`CapacityService` currently depends on `ShipmentRepository` and `QuoteRepository` (both deleted). Replace those with POS-relevant capacity types.

**Files:**
- Modify: `src/Misc/Enum/CapacityType.php`
- Modify: `src/Module/Core/Service/CapacityService.php`

Working directory: `d:/Projects/make-pos-client`

- [ ] **Step 1: Update CapacityType enum**

Replace the contents of `src/Misc/Enum/CapacityType.php` with:
```php
<?php

namespace App\Misc\Enum;

enum CapacityType: string
{
    case NetworkBandwidth   = 'network_bandwidth';
    case EmailSend          = 'email_send';
    case ResetPassword      = 'reset_password';
    case FileStorage        = 'file_storage';
    case DocumentOperations = 'document_operations';
    case MaxUsers           = 'max_users';
    case MaxOrders          = 'max_orders';
    case MaxProducts        = 'max_products';
}
```

- [ ] **Step 2: Rewrite CapacityService to remove freight dependencies**

Read the current `src/Module/Core/Service/CapacityService.php`. Replace the constructor and any usage of `ShipmentRepository` / `QuoteRepository` with POS equivalents. Since the Sales and Catalog modules don't exist yet, stub the count methods to return 0 for now.

Replace the full file contents with:
```php
<?php

namespace App\Module\Core\Service;

use App\Misc\Enum\CapacityPeriod;
use App\Misc\Enum\CapacityType;
use App\Misc\Exception\CapacityExceededException;
use App\Module\Core\Repository\UserRepository;

class CapacityService
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly UserRepository $userRepository,
    ) {}

    public function assertAllowed(CapacityType $type, int $amount = 1): void
    {
        $rule = $this->getRule($type);
        if ($rule === null || !($rule['enabled'] ?? true)) {
            return;
        }

        $current = $this->getCurrent($type);
        $limit = $rule['limit'] ?? null;
        if ($limit !== null && ($current + $amount) > $limit) {
            throw new CapacityExceededException($type, $current, $limit);
        }
    }

    private function getRule(CapacityType $type): ?array
    {
        $plan = $this->configService->getConfigValue('plan', isJson: true) ?? [];
        return $plan[$type->value] ?? null;
    }

    private function getCurrent(CapacityType $type): int
    {
        return match ($type) {
            CapacityType::MaxUsers    => count($this->userRepository->getAll([], 'ENTITY')),
            CapacityType::MaxOrders   => 0, // TODO: inject OrderRepository when Sales module is built
            CapacityType::MaxProducts => 0, // TODO: inject ProductRepository when Catalog module is built
            default                   => 0,
        };
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Misc/Enum/CapacityType.php src/Module/Core/Service/CapacityService.php
git commit -m "chore: update CapacityService and CapacityType for POS (remove freight dependencies)"
```

---

## Task 6: Fix MasterSyncService

`MasterSyncService` has a `searchPorts()` method that calls the freight master API. Remove it.

**Files:**
- Modify: `src/Module/Core/Service/MasterSyncService.php`

Working directory: `d:/Projects/make-pos-client`

- [ ] **Step 1: Remove searchPorts method from MasterSyncService**

Open `src/Module/Core/Service/MasterSyncService.php`. Delete the entire `searchPorts()` method (the method that calls `/public/port/search`). Leave `searchCurrencies()` and `syncCurrentUsers()` untouched.

- [ ] **Step 2: Verify no remaining freight references in Core**

```bash
grep -r "Shipment\|Quote\|Carrier\|Vessel\|Port\|Lc\b\|Insurance\|Compliance\|Emission" \
  src/Module/Core/ src/Misc/ --include="*.php" -l
```
Expected: No output (or only false positives like "Report" — inspect manually if anything shows).

- [ ] **Step 3: Commit**

```bash
git add src/Module/Core/Service/MasterSyncService.php
git commit -m "chore: remove searchPorts from MasterSyncService"
```

---

## Task 7: Replace migrations with a fresh POS baseline

The existing baseline migration (`Version20250101000000.php`) creates all freight tables. Since we've removed those entities, we need to delete all migrations and generate a fresh one from the cleaned entity set.

**Files:**
- Delete: `migrations/mysql/*.php` (all files)
- Delete: `migrations/sqlite/*.php` (all files)
- Delete: `migrations/common/*.php` (if any)
- Generate: fresh migration via Doctrine

Working directory: `d:/Projects/make-pos-client`

- [ ] **Step 1: Delete all existing migration files**

```bash
cd /d/Projects/make-pos-client
rm -f migrations/mysql/*.php
rm -f migrations/sqlite/*.php
rm -f migrations/common/*.php 2>/dev/null || true
```

- [ ] **Step 2: Install PHP dependencies**

```bash
composer install --no-interaction
```
Expected: Vendor dependencies installed. If errors appear about missing classes from deleted modules, check for lingering references:
```bash
grep -r "Module\\\\Carrier\|Module\\\\Quote\|Module\\\\Operations\|Module\\\\Insurance\|Module\\\\Compliance\|Module\\\\Lc\b\|Module\\\\Emissions\|Module\\\\Integration" \
  src/ config/ --include="*.php" --include="*.yaml" -l
```
Fix any remaining references before proceeding.

- [ ] **Step 3: Verify container compiles**

```bash
php bin/console cache:clear
```
Expected: `Cache for the "dev" environment (debug=true) was successfully cleared.`
If errors reference deleted classes, fix them before continuing.

- [ ] **Step 4: Generate fresh migration**

```bash
php bin/console doctrine:migrations:diff --no-interaction
```
Expected: `Generated new migration class to "migrations/mysql/Version<timestamp>.php"`

- [ ] **Step 5: Review generated migration**

Open the generated migration file and verify:
- No tables for `carrier`, `shipment`, `booking`, `vessel`, `lc`, `insurance`, `quote`, `operations`, `compliance`, `emissions`
- Tables present for: `user`, `branch`, `department`, `config`, `media`, `page`, `user_group`, `crm_*`, `journal`, `invoice`, `tax_rule`, `notification_*`

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "chore: replace freight migrations with fresh POS baseline"
```

---

## Task 8: Remove freight frontend pages

Working directory: `d:/Projects/make-pos-client-bo`

**Files:**
- Delete: `src/pages/carrier/`
- Delete: `src/pages/portal/`
- Delete: `src/pages/provider/`
- Delete: `src/pages/shipment/`
- Delete: `src/pages/consolidation/`
- Delete: `src/pages/rate/`
- Delete: `src/pages/quote/`
- Delete: `src/pages/warehouse/`
- Delete: `src/pages/library/`
- Delete: `src/pages/setting/integration-connectors.vue`
- Delete: `src/pages/test.vue`, `src/pages/test2.vue`, `src/pages/test3.vue`

- [ ] **Step 1: Delete freight page directories**

```bash
cd /d/Projects/make-pos-client-bo
rm -rf src/pages/carrier
rm -rf src/pages/portal
rm -rf src/pages/provider
rm -rf src/pages/shipment
rm -rf src/pages/consolidation
rm -rf src/pages/rate
rm -rf src/pages/quote
rm -rf src/pages/warehouse
rm -rf src/pages/library
rm -f src/pages/setting/integration-connectors.vue
rm -f src/pages/test.vue src/pages/test2.vue src/pages/test3.vue
```

- [ ] **Step 2: Verify remaining pages**

```bash
ls src/pages/
```
Expected:
```
[...error].vue  accounting  client  crm  dashboard.vue  forgot-password.vue
login-with-token  login.vue  not-authorized.vue  profile.vue
register  report  report.vue  setting  user-settings.vue
```

- [ ] **Step 3: Verify setting pages**

```bash
ls src/pages/setting/
```
Expected: `branch.vue  chart-of-accounts.vue  company.vue  department.vue  global-setting.vue  groups.vue  pages.vue  users.vue`
(`integration-connectors.vue` must be absent)

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: remove freight frontend pages (carrier, portal, provider, shipment, consolidation, rate, quote, warehouse, library)"
```

---

## Task 9: Remove freight frontend stores and services

Working directory: `d:/Projects/make-pos-client-bo`

**Files:**
- Delete: `src/stores/portalAuthStore.js`
- Delete (freight services): listed below

- [ ] **Step 1: Delete portalAuthStore**

```bash
cd /d/Projects/make-pos-client-bo
rm -f src/stores/portalAuthStore.js
```

- [ ] **Step 2: Delete all freight-specific service files**

```bash
rm -f src/services/AgentProfileService.js
rm -f src/services/ArrivalNoticeService.js
rm -f src/services/BankAccountService.js
rm -f src/services/BookingService.js
rm -f src/services/CapacityService.js
rm -f src/services/CargoClaimService.js
rm -f src/services/CarrierPerformanceService.js
rm -f src/services/CarrierProfileService.js
rm -f src/services/ChargeItemService.js
rm -f src/services/ComplianceService.js
rm -f src/services/ComponentService.js
rm -f src/services/ConsolidationService.js
rm -f src/services/CustomsEntryService.js
rm -f src/services/DangerousGoodsService.js
rm -f src/services/DatasetService.js
rm -f src/services/DdService.js
rm -f src/services/DeliveryOrderService.js
rm -f src/services/EbitNoteService.js
rm -f src/services/EmissionsService.js
rm -f src/services/FlightScheduleService.js
rm -f src/services/InstructionService.js
rm -f src/services/InsuranceService.js
rm -f src/services/IntegrationService.js
rm -f src/services/InvoiceInfoService.js
rm -f src/services/LcService.js
rm -f src/services/ParcelService.js
rm -f src/services/PartnerTaxRegistrationService.js
rm -f src/services/PriceMarkupService.js
rm -f src/services/ProviderService.js
rm -f src/services/QuoteService.js
rm -f src/services/RailBookingService.js
rm -f src/services/RateBenchmarkService.js
rm -f src/services/RateImportService.js
rm -f src/services/RateService.js
rm -f src/services/ShipmentActivityService.js
rm -f src/services/ShipmentDocumentService.js
rm -f src/services/ShipmentLegService.js
rm -f src/services/ShipmentMilestoneService.js
rm -f src/services/ShipmentNoteService.js
rm -f src/services/ShipmentPartyService.js
rm -f src/services/ShipmentService.js
rm -f src/services/ShipmentTaskService.js
rm -f src/services/StrippingService.js
rm -f src/services/StuffingService.js
rm -f src/services/TrackingRequestService.js
rm -f src/services/TruckService.js
rm -f src/services/VesselRollService.js
rm -f src/services/VesselSailingService.js
rm -f src/services/WarehouseFacilityService.js
rm -f src/services/WarehouseReceiptService.js
```

- [ ] **Step 3: Verify remaining services are all generic/SAAS**

```bash
ls src/services/
```
Expected (only generic services remain):
```
AgeingService.js  BranchService.js  ClientService.js  CommonService.js
ConfigService.js  ContactService.js  CurrencyService.js  DepartmentService.js
ExchangeRateGroupService.js  JournalService.js  MediaService.js
MyProfileService.js  NotificationPreferenceService.js  NotificationService.js
OrganisationAddressService.js  PageService.js  PartnerService.js
PnlService.js  ReportAnalyticsService.js  SalesCrmService.js
TaxRuleService.js  UserGroupService.js  UserInviteService.js
UserService.js  VatReportService.js  library/  portal/
```

Note: `AgeingService.js` and `PnlService.js` are generic finance services — keep them.

- [ ] **Step 4: Check for any remaining imports of deleted services in kept pages**

```bash
grep -r "portalAuthStore\|ShipmentService\|CarrierService\|QuoteService\|BookingService\|ConsolidationService\|RateService\|WarehouseService\|LcService\|InsuranceService\|ComplianceService\|EmissionsService\|IntegrationService" \
  src/pages/ src/stores/ src/components/ --include="*.vue" --include="*.js" -l 2>/dev/null
```
Expected: No output. If any files appear, open them and remove the offending imports.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: remove freight frontend stores and services"
```

---

## Task 10: Rebrand backend

Working directory: `d:/Projects/make-pos-client`

**Files:**
- Modify: `composer.json`
- Modify: `.env.example`
- Modify: `README.md`

- [ ] **Step 1: Update composer.json name**

Open `composer.json`. Find the `"name"` field (if present) or add it. Set:
```json
"name": "make-pos/client",
```
Also update any `description` field if it mentions cargo or freight forwarding.

- [ ] **Step 2: Update .env.example**

Open `.env.example`. Add/update the app name comment at the top:
```
# make-pos-client — POS SaaS Backend
```
If there's a `DATABASE_URL` placeholder, update the database name from `make_cargo` to `make_pos`:
```
DATABASE_URL="mysql://root:@127.0.0.1:3306/make_pos?serverVersion=8.0&charset=utf8mb4"
```

- [ ] **Step 3: Update README.md**

Replace the contents of `README.md` with:
```markdown
# make-pos-client

Symfony PHP backend API for the make-pos SaaS POS platform.

## Setup

```bash
composer install
cp .env.example .env
# Edit .env with your database credentials
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:warmup
symfony serve
```
```

- [ ] **Step 4: Commit**

```bash
git add composer.json .env.example README.md
git commit -m "chore: rebrand backend to make-pos-client"
```

---

## Task 11: Rebrand frontend

Working directory: `d:/Projects/make-pos-client-bo`

**Files:**
- Modify: `package.json`
- Modify: `themeConfig.js`
- Modify: `index.html`
- Modify: `README.md`

- [ ] **Step 1: Update package.json name**

Open `package.json`. Set:
```json
"name": "make-pos-client-bo",
```

- [ ] **Step 2: Update themeConfig.js app title**

Open `themeConfig.js`. Find:
```js
title: 'vuexy',
```
Replace with:
```js
title: 'Make POS',
```

- [ ] **Step 3: Update index.html title**

Open `index.html`. Find the `<title>` tag and update it:
```html
<title>Make POS</title>
```

- [ ] **Step 4: Update README.md**

Replace the contents of `README.md` with:
```markdown
# make-pos-client-bo

Vue.js back-office SPA for the make-pos SaaS POS platform.

## Setup

```bash
pnpm install
cp .env.example .env
# Edit .env — set VITE_API_URL to your backend URL
pnpm dev
```
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: rebrand frontend to make-pos-client-bo"
```

---

## Task 12: Scaffold new POS backend modules

Create the empty folder structure for each new POS module. No code yet — just directories and a placeholder controller so Symfony's autoloader can see the namespace.

Working directory: `d:/Projects/make-pos-client`

**Files to create (all new):**
- `src/Module/{Catalog,Inventory,Sales,Table,Kitchen,Loyalty,Shift}/Controller/.gitkeep`
- `src/Module/{Catalog,Inventory,Sales,Table,Kitchen,Loyalty,Shift}/Entity/.gitkeep`
- `src/Module/{Catalog,Inventory,Sales,Table,Kitchen,Loyalty,Shift}/Enum/.gitkeep`
- `src/Module/{Catalog,Inventory,Sales,Table,Kitchen,Loyalty,Shift}/Repository/.gitkeep`
- `src/Module/{Catalog,Inventory,Sales,Table,Kitchen,Loyalty,Shift}/Service/.gitkeep`

- [ ] **Step 1: Create module scaffolds**

```bash
cd /d/Projects/make-pos-client
for module in Catalog Inventory Sales Table Kitchen Loyalty Shift; do
  for dir in Controller Entity Enum Repository Service; do
    mkdir -p "src/Module/$module/$dir"
    touch "src/Module/$module/$dir/.gitkeep"
  done
done
```

- [ ] **Step 2: Verify scaffold structure**

```bash
ls src/Module/
```
Expected: `Catalog  Core  Crm  Finance  Inventory  Kitchen  Loyalty  Notification  Reporting  Sales  Shift  Table  Tax`

```bash
ls src/Module/Catalog/
```
Expected: `Controller  Entity  Enum  Repository  Service`

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: scaffold new POS backend modules (Catalog, Inventory, Sales, Table, Kitchen, Loyalty, Shift)"
```

---

## Task 13: Scaffold new POS frontend pages and services

Working directory: `d:/Projects/make-pos-client-bo`

**Files to create:**
- `src/pages/pos/index.vue`
- `src/pages/product/index.vue`
- `src/pages/inventory/index.vue`
- `src/pages/order/index.vue`
- `src/pages/table/index.vue`
- `src/pages/kitchen/index.vue`
- `src/pages/loyalty/index.vue`
- `src/pages/shift/index.vue`
- `src/services/CatalogService.js`
- `src/services/InventoryService.js`
- `src/services/SalesService.js`
- `src/services/TableService.js`
- `src/services/KitchenService.js`
- `src/services/LoyaltyService.js`
- `src/services/ShiftService.js`

- [ ] **Step 1: Create placeholder page components**

Run this loop to generate each page stub:
```bash
cd /d/Projects/make-pos-client-bo
for page in pos product inventory order table kitchen loyalty shift; do
  mkdir -p "src/pages/$page"
  cat > "src/pages/$page/index.vue" <<EOF
<template>
  <div>
    <h1>{{ title }}</h1>
    <p>Coming soon.</p>
  </div>
</template>

<script setup>
const title = '$(echo $page | sed 's/.*/\u&/')';
</script>
EOF
done
```

- [ ] **Step 2: Create placeholder service files**

```bash
cd /d/Projects/make-pos-client-bo

for service in Catalog Inventory Sales Table Kitchen Loyalty Shift; do
  cat > "src/services/${service}Service.js" <<EOF
import CommonService from './CommonService'

const ${service}Service = {
  // TODO: implement ${service} API calls
}

export default ${service}Service
EOF
done
```

- [ ] **Step 3: Verify new pages exist**

```bash
ls src/pages/pos/ src/pages/product/ src/pages/kitchen/ src/pages/shift/
```
Expected: `index.vue` in each directory.

- [ ] **Step 4: Verify new services exist**

```bash
ls src/services/Catalog* src/services/Sales* src/services/Kitchen* src/services/Shift*
```
Expected: `CatalogService.js  SalesService.js  KitchenService.js  ShiftService.js`

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: scaffold new POS frontend pages and services"
```

---

## Task 14: Verify backend compiles clean

Working directory: `d:/Projects/make-pos-client`

- [ ] **Step 1: Clear cache and warm up**

```bash
cd /d/Projects/make-pos-client
php bin/console cache:clear
php bin/console cache:warmup
```
Expected: Both commands complete without errors.

- [ ] **Step 2: Check for any remaining freight class references**

```bash
grep -r "Module\\\\Carrier\|Module\\\\Quote\|Module\\\\Operations\|Module\\\\Insurance\|Module\\\\Compliance\|Module\\\\Lc\b\|Module\\\\Emissions\|Module\\\\Integration" \
  src/ config/ --include="*.php" --include="*.yaml" -l
```
Expected: No output. Fix any hits before proceeding.

- [ ] **Step 3: Validate Doctrine entity mapping**

```bash
php bin/console doctrine:schema:validate --skip-sync
```
Expected: `[OK] The mapping files are correct.`
Ignore "[WARNING] The database schema is not in sync" — that's expected since we haven't run migrations yet on a new DB.

- [ ] **Step 4: Commit any fixes**

If you made fixes in steps 2–3:
```bash
git add -A
git commit -m "fix: resolve remaining freight references in backend"
```

---

## Task 15: Verify frontend builds clean

Working directory: `d:/Projects/make-pos-client-bo`

- [ ] **Step 1: Install dependencies**

```bash
cd /d/Projects/make-pos-client-bo
pnpm install
```
Expected: Dependencies installed without errors.

- [ ] **Step 2: Run build**

```bash
pnpm build
```
Expected: Build completes successfully. Output goes to `dist/`.
If there are import errors referencing deleted services (e.g. `Cannot find module './ShipmentService'`), open the offending file and remove the import.

- [ ] **Step 3: Fix any broken imports**

For each error like `Cannot find module './XService'`:
```bash
grep -r "XService" src/ --include="*.vue" --include="*.js" -l
```
Open each file, remove the import line and any usage of that service.

Re-run `pnpm build` after each fix until build passes.

- [ ] **Step 4: Commit any fixes**

```bash
git add -A
git commit -m "fix: resolve remaining freight imports in frontend"
```

- [ ] **Step 5: Final verification — list both repos' module structure**

```bash
echo "=== Backend modules ===" && ls /d/Projects/make-pos-client/src/Module/
echo "=== Frontend pages ===" && ls /d/Projects/make-pos-client-bo/src/pages/
echo "=== Frontend services ===" && ls /d/Projects/make-pos-client-bo/src/services/ | grep -v "\.js$" -v 2>/dev/null; ls /d/Projects/make-pos-client-bo/src/services/*.js
```
Confirm no freight entries remain in either listing.
