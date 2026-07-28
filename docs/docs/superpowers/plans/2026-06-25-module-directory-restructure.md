# Module Directory Restructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move all controllers, repositories, and services from flat `src/Controller/`, `src/Repository/`, `src/Service/` directories into `src/Module/{Module}/{Type}/` subdirectories, updating all namespaces and cross-references.

**Architecture:** A PHP migration script holds the complete old→new class mapping. It moves every file, rewrites its `namespace` declaration, then does a global search-replace across all `.php` files to update every `use` statement that references the moved classes. Config files (`routes.yaml`, `services.yaml`) are updated separately after the PHP migration is complete. Entities stay in `src/Entity/` — no Doctrine config changes needed.

**Tech Stack:** PHP 8.2, Symfony 7, Git. Migration done with a pure-PHP script (no shell tools needed beyond `php` and `git`).

**Design spec:** `docs/superpowers/specs/2026-06-25-module-architecture-design.md`

---

## New namespace conventions

| Old path / namespace | New path / namespace |
|---|---|
| `src/Controller/Api/QuoteController.php` / `App\Controller\Api` | `src/Module/Quote/Controller/QuoteController.php` / `App\Module\Quote\Controller` |
| `src/Repository/QuoteRepository.php` / `App\Repository` | `src/Module/Quote/Repository/QuoteRepository.php` / `App\Module\Quote\Repository` |
| `src/Service/QuoteService.php` / `App\Service` | `src/Module/Quote/Service/QuoteService.php` / `App\Module\Quote\Service` |

---

### Task 1: Write the migration script

**Files:**
- Create: `scripts/migrate-to-modules.php`

- [ ] **Step 1: Create `scripts/` directory and write the script**

Create the file `scripts/migrate-to-modules.php` with the following exact content. This script has the complete mapping for every controller, repository, and service. Run it from the project root.

```php
<?php
declare(strict_types=1);
/**
 * Module directory restructure migration script.
 *
 * Moves src/Controller/, src/Repository/, src/Service/ classes into
 * src/Module/{Module}/{Type}/ subdirectories, updating namespace
 * declarations and all `use` statements across the codebase.
 *
 * Run from project root: php scripts/migrate-to-modules.php
 * Safe to re-run — skips files that have already been moved.
 */

$root = dirname(__DIR__);

// Map: 'Old\\FQCN' => ['ModuleName', 'Type']
// ModuleName: Core|Quote|Finance|Tax|Operations|Carrier|Crm|Notification|Reporting|Integration
// Type: Controller|Repository|Service
$migrations = [
    // =========================================================
    // CONTROLLERS — API
    // =========================================================
    'App\\Controller\\Api\\AccountingCloseController'       => ['Finance',      'Controller'],
    'App\\Controller\\Api\\AgeingController'                => ['Finance',      'Controller'],
    'App\\Controller\\Api\\AgentProfileController'          => ['Crm',          'Controller'],
    'App\\Controller\\Api\\ArrivalNoticeController'         => ['Operations',   'Controller'],
    'App\\Controller\\Api\\BankAccountController'           => ['Finance',      'Controller'],
    'App\\Controller\\Api\\BookingController'               => ['Operations',   'Controller'],
    'App\\Controller\\Api\\BranchController'                => ['Core',         'Controller'],
    'App\\Controller\\Api\\CalculationTypeController'       => ['Quote',        'Controller'],
    'App\\Controller\\Api\\CargoClaimController'            => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\CarrierEventMappingController'   => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\CarrierPerformanceController'    => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\CarrierProfileController'        => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\ChargeController'                => ['Finance',      'Controller'],
    'App\\Controller\\Api\\ChargeItemController'            => ['Finance',      'Controller'],
    'App\\Controller\\Api\\ChartOfAccountController'        => ['Finance',      'Controller'],
    'App\\Controller\\Api\\ClientController'                => ['Crm',          'Controller'],
    'App\\Controller\\Api\\ComponentController'             => ['Core',         'Controller'],
    'App\\Controller\\Api\\ConfigController'                => ['Core',         'Controller'],
    'App\\Controller\\Api\\ConsolidationController'         => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ContactController'               => ['Crm',          'Controller'],
    'App\\Controller\\Api\\ContainerDdController'           => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\CrudController'                  => ['Core',         'Controller'],
    'App\\Controller\\Api\\CurrencyController'              => ['Finance',      'Controller'],
    'App\\Controller\\Api\\CustomChargeTypeController'      => ['Quote',        'Controller'],
    'App\\Controller\\Api\\CustomerTaxExemptionController'  => ['Tax',          'Controller'],
    'App\\Controller\\Api\\DangerousGoodsController'        => ['Operations',   'Controller'],
    'App\\Controller\\Api\\DatasetController'               => ['Reporting',    'Controller'],
    'App\\Controller\\Api\\DeliveryOrderController'         => ['Operations',   'Controller'],
    'App\\Controller\\Api\\DepartmentController'            => ['Core',         'Controller'],
    'App\\Controller\\Api\\DutyRateController'              => ['Tax',          'Controller'],
    'App\\Controller\\Api\\EbitNoteController'              => ['Finance',      'Controller'],
    'App\\Controller\\Api\\ExchangeRateGroupController'     => ['Finance',      'Controller'],
    'App\\Controller\\Api\\FlightScheduleController'        => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\FreeTimeAgreementController'     => ['Quote',        'Controller'],
    'App\\Controller\\Api\\HsCodeController'                => ['Tax',          'Controller'],
    'App\\Controller\\Api\\HsRestrictionController'         => ['Tax',          'Controller'],
    'App\\Controller\\Api\\HsVersionMappingController'      => ['Tax',          'Controller'],
    'App\\Controller\\Api\\IncotermController'              => ['Quote',        'Controller'],
    'App\\Controller\\Api\\InstructionController'           => ['Operations',   'Controller'],
    'App\\Controller\\Api\\InvoiceInfoController'           => ['Finance',      'Controller'],
    'App\\Controller\\Api\\JournalEntryController'          => ['Finance',      'Controller'],
    'App\\Controller\\Api\\KpiController'                   => ['Reporting',    'Controller'],
    'App\\Controller\\Api\\MediaController'                 => ['Core',         'Controller'],
    'App\\Controller\\Api\\MyProfileController'             => ['Core',         'Controller'],
    'App\\Controller\\Api\\OrganisationAddressController'   => ['Core',         'Controller'],
    'App\\Controller\\Api\\PackageTypeController'           => ['Core',         'Controller'],
    'App\\Controller\\Api\\PageController'                  => ['Core',         'Controller'],
    'App\\Controller\\Api\\PartnerTaxRegistrationController'=> ['Tax',          'Controller'],
    'App\\Controller\\Api\\PaymentMethodController'         => ['Finance',      'Controller'],
    'App\\Controller\\Api\\PnlController'                   => ['Finance',      'Controller'],
    'App\\Controller\\Api\\PortController'                  => ['Core',         'Controller'],
    'App\\Controller\\Api\\PriceMarkupController'           => ['Quote',        'Controller'],
    'App\\Controller\\Api\\ProviderController'              => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\QuoteController'                 => ['Quote',        'Controller'],
    'App\\Controller\\Api\\RateController'                  => ['Quote',        'Controller'],
    'App\\Controller\\Api\\ReportAnalyticsController'       => ['Reporting',    'Controller'],
    'App\\Controller\\Api\\ShipmentActivityController'      => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ShipmentController'              => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ShipmentDocumentController'      => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ShipmentMilestoneController'     => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ShipmentModeController'          => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ShipmentNoteController'          => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ShipmentPartyController'         => ['Operations',   'Controller'],
    'App\\Controller\\Api\\ShipmentTaskController'          => ['Operations',   'Controller'],
    'App\\Controller\\Api\\TaxGroupController'              => ['Finance',      'Controller'],
    'App\\Controller\\Api\\TaxRuleController'               => ['Finance',      'Controller'],
    'App\\Controller\\Api\\TestController'                  => ['Core',         'Controller'],
    'App\\Controller\\Api\\TrackingRequestController'       => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\TrackingWebhookController'       => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\UserController'                  => ['Core',         'Controller'],
    'App\\Controller\\Api\\UserGroupController'             => ['Core',         'Controller'],
    'App\\Controller\\Api\\VatReportController'             => ['Tax',          'Controller'],
    'App\\Controller\\Api\\VesselRollController'            => ['Carrier',      'Controller'],
    'App\\Controller\\Api\\VesselSailingController'         => ['Carrier',      'Controller'],
    // CONTROLLERS — Http
    'App\\Controller\\Http\\IndexController'                => ['Core',         'Controller'],
    // CONTROLLERS — Portal
    'App\\Controller\\Portal\\PortalAuthController'         => ['Integration',  'Controller'],
    'App\\Controller\\Portal\\PortalDocumentController'     => ['Integration',  'Controller'],
    'App\\Controller\\Portal\\PortalInvoiceController'      => ['Integration',  'Controller'],
    'App\\Controller\\Portal\\PortalQuoteRequestController' => ['Integration',  'Controller'],
    'App\\Controller\\Portal\\PortalShipmentController'     => ['Integration',  'Controller'],

    // =========================================================
    // REPOSITORIES
    // =========================================================
    'App\\Repository\\AgeingRepository'                     => ['Finance',      'Repository'],
    'App\\Repository\\AgentProfileRepository'               => ['Crm',          'Repository'],
    'App\\Repository\\ArchiveRepository'                    => ['Operations',   'Repository'],
    'App\\Repository\\ArrivalNoticeRepository'              => ['Operations',   'Repository'],
    'App\\Repository\\BankAccountRepository'                => ['Finance',      'Repository'],
    'App\\Repository\\BaseRepository'                       => ['Core',         'Repository'],
    'App\\Repository\\BookingRepository'                    => ['Operations',   'Repository'],
    'App\\Repository\\BranchRepository'                     => ['Core',         'Repository'],
    'App\\Repository\\CalculationTypeRepository'            => ['Quote',        'Repository'],
    'App\\Repository\\CargoClaimRepository'                 => ['Carrier',      'Repository'],
    'App\\Repository\\CarrierEventMappingRepository'        => ['Carrier',      'Repository'],
    'App\\Repository\\CarrierPerformanceScoreRepository'    => ['Carrier',      'Repository'],
    'App\\Repository\\CarrierProfileRepository'             => ['Carrier',      'Repository'],
    'App\\Repository\\ChargeItemRepository'                 => ['Finance',      'Repository'],
    'App\\Repository\\ChargeRepository'                     => ['Finance',      'Repository'],
    'App\\Repository\\ChargeTypeRepository'                 => ['Finance',      'Repository'],
    'App\\Repository\\ChartOfAccountRepository'             => ['Finance',      'Repository'],
    'App\\Repository\\ClientRepository'                     => ['Crm',          'Repository'],
    'App\\Repository\\ComponentRepository'                  => ['Core',         'Repository'],
    'App\\Repository\\ConfigRepository'                     => ['Core',         'Repository'],
    'App\\Repository\\ConsolidationRepository'              => ['Operations',   'Repository'],
    'App\\Repository\\ContactRepository'                    => ['Crm',          'Repository'],
    'App\\Repository\\ContainerDdTrackingRepository'        => ['Carrier',      'Repository'],
    'App\\Repository\\CreditLimitHistoryRepository'         => ['Finance',      'Repository'],
    'App\\Repository\\CurrencyRepository'                   => ['Finance',      'Repository'],
    'App\\Repository\\CustomChargeTypeRepository'           => ['Quote',        'Repository'],
    'App\\Repository\\CustomerTaxExemptionRepository'       => ['Tax',          'Repository'],
    'App\\Repository\\DangerousGoodsRepository'             => ['Operations',   'Repository'],
    'App\\Repository\\DatasetRepository'                    => ['Reporting',    'Repository'],
    'App\\Repository\\DeliveryOrderRepository'              => ['Operations',   'Repository'],
    'App\\Repository\\DepartmentRepository'                 => ['Core',         'Repository'],
    'App\\Repository\\DutyRateRepository'                   => ['Tax',          'Repository'],
    'App\\Repository\\EbitNoteRepository'                   => ['Finance',      'Repository'],
    'App\\Repository\\ExchangeRateGroupRepository'          => ['Finance',      'Repository'],
    'App\\Repository\\ExchangeRateRepository'               => ['Finance',      'Repository'],
    'App\\Repository\\FreeTimeAgreementRepository'          => ['Quote',        'Repository'],
    'App\\Repository\\HsCodeRepository'                     => ['Tax',          'Repository'],
    'App\\Repository\\HsRestrictionRepository'              => ['Tax',          'Repository'],
    'App\\Repository\\HsVersionMappingRepository'           => ['Tax',          'Repository'],
    'App\\Repository\\InAppNotificationRepository'          => ['Notification', 'Repository'],
    'App\\Repository\\IncotermRepository'                   => ['Quote',        'Repository'],
    'App\\Repository\\InstructionRepository'                => ['Operations',   'Repository'],
    'App\\Repository\\InvoiceInfoRepository'                => ['Finance',      'Repository'],
    'App\\Repository\\JournalEntryRepository'               => ['Finance',      'Repository'],
    'App\\Repository\\JournalLineRepository'                => ['Finance',      'Repository'],
    'App\\Repository\\KpiRepository'                        => ['Reporting',    'Repository'],
    'App\\Repository\\LogRepository'                        => ['Core',         'Repository'],
    'App\\Repository\\MailRepository'                       => ['Notification', 'Repository'],
    'App\\Repository\\MediaRepository'                      => ['Core',         'Repository'],
    'App\\Repository\\NotificationQueueRepository'          => ['Notification', 'Repository'],
    'App\\Repository\\NotificationRuleRepository'           => ['Notification', 'Repository'],
    'App\\Repository\\NotificationTemplateRepository'       => ['Notification', 'Repository'],
    'App\\Repository\\OrganisationAddressRepository'        => ['Core',         'Repository'],
    'App\\Repository\\PackageTypeRepository'                => ['Core',         'Repository'],
    'App\\Repository\\PageRepository'                       => ['Core',         'Repository'],
    'App\\Repository\\PartnerRepository'                    => ['Crm',          'Repository'],
    'App\\Repository\\PartnerTaxRegistrationRepository'     => ['Tax',          'Repository'],
    'App\\Repository\\PaymentMethodRepository'              => ['Finance',      'Repository'],
    'App\\Repository\\PnlRepository'                        => ['Finance',      'Repository'],
    'App\\Repository\\PortRepository'                       => ['Core',         'Repository'],
    'App\\Repository\\PortalQuoteRequestRepository'         => ['Integration',  'Repository'],
    'App\\Repository\\PortalTokenRepository'                => ['Integration',  'Repository'],
    'App\\Repository\\PortalUserRepository'                 => ['Integration',  'Repository'],
    'App\\Repository\\PriceMarkupRepository'                => ['Quote',        'Repository'],
    'App\\Repository\\PricingLevelRepository'               => ['Quote',        'Repository'],
    'App\\Repository\\ProviderRepository'                   => ['Carrier',      'Repository'],
    'App\\Repository\\QuotePriceRepository'                 => ['Quote',        'Repository'],
    'App\\Repository\\QuoteRepository'                      => ['Quote',        'Repository'],
    'App\\Repository\\RateRepository'                       => ['Quote',        'Repository'],
    'App\\Repository\\ReportRepository'                     => ['Reporting',    'Repository'],
    'App\\Repository\\ShipmentActivityRepository'           => ['Operations',   'Repository'],
    'App\\Repository\\ShipmentDocumentRepository'           => ['Operations',   'Repository'],
    'App\\Repository\\ShipmentMilestoneRepository'          => ['Operations',   'Repository'],
    'App\\Repository\\ShipmentModeRepository'               => ['Operations',   'Repository'],
    'App\\Repository\\ShipmentNoteRepository'               => ['Operations',   'Repository'],
    'App\\Repository\\ShipmentPartyRepository'              => ['Operations',   'Repository'],
    'App\\Repository\\ShipmentRepository'                   => ['Operations',   'Repository'],
    'App\\Repository\\ShipmentTaskRepository'               => ['Operations',   'Repository'],
    'App\\Repository\\TaxGroupRepository'                   => ['Finance',      'Repository'],
    'App\\Repository\\TaxRuleRepository'                    => ['Finance',      'Repository'],
    'App\\Repository\\TrackingEventRawRepository'           => ['Carrier',      'Repository'],
    'App\\Repository\\TrackingRequestRepository'            => ['Carrier',      'Repository'],
    'App\\Repository\\UserAgentRepository'                  => ['Core',         'Repository'],
    'App\\Repository\\UserGroupRepository'                  => ['Core',         'Repository'],
    'App\\Repository\\UserNotificationPreferenceRepository' => ['Notification', 'Repository'],
    'App\\Repository\\UserRepository'                       => ['Core',         'Repository'],
    'App\\Repository\\UserTokenRepository'                  => ['Core',         'Repository'],
    'App\\Repository\\VatReportRepository'                  => ['Tax',          'Repository'],
    'App\\Repository\\VesselRollRepository'                 => ['Carrier',      'Repository'],

    // =========================================================
    // SERVICES
    // =========================================================
    'App\\Service\\ArchiveService'                          => ['Operations',   'Service'],
    'App\\Service\\ArrivalNoticeService'                    => ['Operations',   'Service'],
    'App\\Service\\BankAccountService'                      => ['Finance',      'Service'],
    'App\\Service\\BaseService'                             => ['Core',         'Service'],
    'App\\Service\\BookingService'                          => ['Operations',   'Service'],
    'App\\Service\\BranchService'                           => ['Core',         'Service'],
    'App\\Service\\CalculationTypeService'                  => ['Quote',        'Service'],
    'App\\Service\\CarrierEventMappingService'              => ['Carrier',      'Service'],
    'App\\Service\\CarrierPerformanceScoreService'          => ['Carrier',      'Service'],
    'App\\Service\\ChargeItemService'                       => ['Finance',      'Service'],
    'App\\Service\\ChargeService'                           => ['Finance',      'Service'],
    'App\\Service\\ClientService'                           => ['Crm',          'Service'],
    'App\\Service\\CommonService'                           => ['Core',         'Service'],
    'App\\Service\\ComponentService'                        => ['Core',         'Service'],
    'App\\Service\\ConfigService'                           => ['Core',         'Service'],
    'App\\Service\\ContactService'                          => ['Crm',          'Service'],
    'App\\Service\\CreditCheckService'                      => ['Finance',      'Service'],
    'App\\Service\\CurrencyService'                         => ['Finance',      'Service'],
    'App\\Service\\CustomChargeTypeService'                 => ['Quote',        'Service'],
    'App\\Service\\DatasetService'                          => ['Reporting',    'Service'],
    'App\\Service\\DdCalculatorService'                     => ['Finance',      'Service'],
    'App\\Service\\DeliveryOrderService'                    => ['Operations',   'Service'],
    'App\\Service\\DepartmentService'                       => ['Core',         'Service'],
    'App\\Service\\DutyRateService'                         => ['Tax',          'Service'],
    'App\\Service\\EbitNoteService'                         => ['Finance',      'Service'],
    'App\\Service\\ExchangeRateGroupService'                => ['Finance',      'Service'],
    'App\\Service\\FxGainLossService'                       => ['Finance',      'Service'],
    'App\\Service\\HsCodeService'                           => ['Tax',          'Service'],
    'App\\Service\\HsRestrictionService'                    => ['Tax',          'Service'],
    'App\\Service\\HsVersionMappingService'                 => ['Tax',          'Service'],
    'App\\Service\\InAppNotificationService'                => ['Notification', 'Service'],
    'App\\Service\\IncotermService'                         => ['Quote',        'Service'],
    'App\\Service\\InstructionService'                      => ['Operations',   'Service'],
    'App\\Service\\InterServiceTokenService'                => ['Core',         'Service'],
    'App\\Service\\InvoiceInfoService'                      => ['Finance',      'Service'],
    'App\\Service\\JournalPostingService'                   => ['Finance',      'Service'],
    'App\\Service\\LogService'                              => ['Core',         'Service'],
    'App\\Service\\MailService'                             => ['Core',         'Service'],
    'App\\Service\\MasterService'                           => ['Core',         'Service'],
    'App\\Service\\MasterSyncService'                       => ['Core',         'Service'],
    'App\\Service\\MediaService'                            => ['Core',         'Service'],
    'App\\Service\\NotificationGeneratorService'            => ['Notification', 'Service'],
    'App\\Service\\NotificationTemplateRenderer'            => ['Notification', 'Service'],
    'App\\Service\\PackageTypeService'                      => ['Core',         'Service'],
    'App\\Service\\PageService'                             => ['Core',         'Service'],
    'App\\Service\\PaymentMethodService'                    => ['Finance',      'Service'],
    'App\\Service\\PortService'                             => ['Core',         'Service'],
    'App\\Service\\PortalAuthService'                       => ['Integration',  'Service'],
    'App\\Service\\PortalDocumentService'                   => ['Integration',  'Service'],
    'App\\Service\\PortalInvoiceService'                    => ['Integration',  'Service'],
    'App\\Service\\PortalQuoteRequestService'               => ['Integration',  'Service'],
    'App\\Service\\PortalShipmentService'                   => ['Integration',  'Service'],
    'App\\Service\\PriceMarkupService'                      => ['Quote',        'Service'],
    'App\\Service\\ProviderService'                         => ['Carrier',      'Service'],
    'App\\Service\\QuoteCodeGeneratorService'               => ['Quote',        'Service'],
    'App\\Service\\QuotePriceService'                       => ['Quote',        'Service'],
    'App\\Service\\QuoteService'                            => ['Quote',        'Service'],
    'App\\Service\\RateService'                             => ['Quote',        'Service'],
    'App\\Service\\RequestService'                          => ['Core',         'Service'],
    'App\\Service\\ShipmentActivityService'                 => ['Operations',   'Service'],
    'App\\Service\\ShipmentDocumentService'                 => ['Operations',   'Service'],
    'App\\Service\\ShipmentIdGeneratorService'              => ['Operations',   'Service'],
    'App\\Service\\ShipmentMilestoneService'                => ['Operations',   'Service'],
    'App\\Service\\ShipmentModeService'                     => ['Operations',   'Service'],
    'App\\Service\\ShipmentService'                         => ['Operations',   'Service'],
    'App\\Service\\ShipmentTaskService'                     => ['Operations',   'Service'],
    'App\\Service\\TaxGroupService'                         => ['Finance',      'Service'],
    'App\\Service\\TrackingEventRawService'                 => ['Carrier',      'Service'],
    'App\\Service\\TrackingMilestoneWriterService'          => ['Carrier',      'Service'],
    'App\\Service\\TrackingRequestService'                  => ['Carrier',      'Service'],
    'App\\Service\\UserAgentService'                        => ['Core',         'Service'],
    'App\\Service\\UserGroupService'                        => ['Core',         'Service'],
    'App\\Service\\UserService'                             => ['Core',         'Service'],
    'App\\Service\\UserTokenService'                        => ['Core',         'Service'],
];

// ---------------------------------------------------------------
// Build replacement index: old FQCN => new FQCN
// ---------------------------------------------------------------
$fqcnMap = []; // oldFQCN => newFQCN

// Derive old file path from FQCN:
// App\Controller\Api\Foo  => src/Controller/Api/Foo.php
// App\Controller\Http\Foo => src/Controller/Http/Foo.php
// App\Controller\Portal\Foo => src/Controller/Portal/Foo.php
// App\Repository\Foo      => src/Repository/Foo.php
// App\Service\Foo         => src/Service/Foo.php
function oldFqcnToPath(string $fqcn, string $root): string
{
    $rel = str_replace(['App\\', '\\'], ['src/', '/'], $fqcn);
    return $root . '/' . $rel . '.php';
}

// ---------------------------------------------------------------
// Phase 1: Move files + update namespace declarations
// ---------------------------------------------------------------
echo "\n=== Phase 1: Moving files ===\n";
$moved   = 0;
$skipped = 0;

foreach ($migrations as $oldFQCN => [$module, $type]) {
    $parts     = explode('\\', $oldFQCN);
    $className = end($parts);
    $oldPath   = oldFqcnToPath($oldFQCN, $root);

    $newNS   = "App\\Module\\{$module}\\{$type}";
    $newFQCN = "{$newNS}\\{$className}";
    $newPath = "{$root}/src/Module/{$module}/{$type}/{$className}.php";

    $fqcnMap[$oldFQCN] = $newFQCN;

    if (!file_exists($oldPath)) {
        echo "  SKIP (not found): {$oldPath}\n";
        $skipped++;
        continue;
    }

    // Create target directory
    $newDir = dirname($newPath);
    if (!is_dir($newDir)) {
        mkdir($newDir, 0755, true);
    }

    // Read file, replace namespace declaration, write to new location
    $content = file_get_contents($oldPath);
    $oldNSParts = $parts;
    array_pop($oldNSParts);           // remove class name
    $oldNS = implode('\\', $oldNSParts);
    $content = preg_replace(
        '/^namespace\s+' . preg_quote($oldNS, '/') . '\s*;/m',
        "namespace {$newNS};",
        $content
    );

    file_put_contents($newPath, $content);
    unlink($oldPath);
    echo "  MOVED: {$oldFQCN}\n        => {$newFQCN}\n";
    $moved++;
}

echo "\nMoved: {$moved}  |  Skipped (not found): {$skipped}\n";

// ---------------------------------------------------------------
// Phase 2: Update all `use` statements across every .php file in src/
// ---------------------------------------------------------------
echo "\n=== Phase 2: Updating use statements ===\n";

// Build search/replace arrays for use statements
// We replace `use Old\FQCN;` and `use Old\FQCN as Alias;`
$searches  = [];
$replaces  = [];
foreach ($fqcnMap as $old => $new) {
    // `use App\Service\QuoteService;`
    $searches[] = 'use ' . $old . ';';
    $replaces[] = 'use ' . $new . ';';
    // `use App\Service\QuoteService as QS;`
    $searches[] = 'use ' . $old . ' as ';
    $replaces[] = 'use ' . $new . ' as ';
}

$rit = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);

$updated = 0;
foreach ($rit as $fileInfo) {
    if ($fileInfo->getExtension() !== 'php') {
        continue;
    }
    $filePath = $fileInfo->getPathname();
    $content  = file_get_contents($filePath);
    $new      = str_replace($searches, $replaces, $content);
    if ($new !== $content) {
        file_put_contents($filePath, $new);
        echo '  UPDATED: ' . str_replace($root . '/', '', $filePath) . "\n";
        $updated++;
    }
}

echo "\nFiles updated: {$updated}\n";

// ---------------------------------------------------------------
// Phase 3: Remove now-empty old directories
// ---------------------------------------------------------------
echo "\n=== Phase 3: Removing empty old directories ===\n";
$dirsToCheck = [
    $root . '/src/Controller/Api',
    $root . '/src/Controller/Http',
    $root . '/src/Controller/Portal',
    $root . '/src/Controller',
    $root . '/src/Repository',
    $root . '/src/Service',
];
foreach ($dirsToCheck as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    $files = array_diff(scandir($dir), ['.', '..']);
    if (empty($files)) {
        rmdir($dir);
        echo "  REMOVED: {$dir}\n";
    } else {
        echo "  KEPT (not empty): {$dir} (" . count($files) . " items remain)\n";
    }
}

echo "\n=== Migration complete ===\n";
echo "Next steps:\n";
echo "  1. Update config/routes.yaml (see plan Task 3)\n";
echo "  2. Update config/services.yaml (see plan Task 4)\n";
echo "  3. Run: php bin/console cache:clear\n";
```

- [ ] **Step 2: Verify the script file exists and has no syntax errors**

```bash
php -l scripts/migrate-to-modules.php
```

Expected: `No syntax errors detected in scripts/migrate-to-modules.php`

- [ ] **Step 3: Commit the script**

```bash
git add scripts/migrate-to-modules.php
git commit -m "chore: add module migration script"
```

---

### Task 2: Run the migration script

**Files:**
- Modify: ~237 controller/repository/service files (moved to new locations)
- Create: `src/Module/*/Controller/`, `src/Module/*/Repository/`, `src/Module/*/Service/` dirs

- [ ] **Step 1: Run the migration script**

```bash
php scripts/migrate-to-modules.php
```

Expected output: lines of `MOVED:` and `UPDATED:` entries, then `Migration complete`.

If any lines say `SKIP (not found)`, note the class name — it may have a different filename than expected. Check with `find src/ -name "*.php" | xargs grep -l "class ClassName"` and add the correct mapping to the script if needed.

- [ ] **Step 2: Verify no PHP files remain in the old flat directories**

```bash
find src/Controller src/Repository src/Service -name "*.php" 2>/dev/null
```

Expected: empty output (all moved).

- [ ] **Step 3: Verify new module directories exist**

```bash
find src/Module -type d | sort
```

Expected: directories like `src/Module/Core/Controller`, `src/Module/Quote/Repository`, `src/Module/Finance/Service`, etc. for all 10 modules.

- [ ] **Step 4: Check for any old-namespace `use` statements that weren't updated**

```bash
grep -r "use App\\\\Controller\\\\Api\\\\" src/ 2>/dev/null
grep -r "use App\\\\Repository\\\\" src/ 2>/dev/null
grep -r "use App\\\\Service\\\\" src/ 2>/dev/null
```

Expected: empty output for all three commands (all references updated).

- [ ] **Step 5: Quick PHP syntax check on all moved files**

```bash
find src/Module -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors"
```

Expected: empty output (no syntax errors).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "refactor(modules): move controllers/repositories/services into src/Module/ subdirs"
```

---

### Task 3: Update `config/routes.yaml`

**Files:**
- Modify: `config/routes.yaml`

The old resource paths point to the old flat directories. Replace the entire file with:

- [ ] **Step 1: Read the current `config/routes.yaml` to understand what's there**

Current content:
```yaml
http:
  resource: ../src/Controller/*
  type: attribute
api:
  prefix: /api
  name_prefix: 'api_'
  resource: ../src/Controller/Api/*
  type: attribute
  exclude: 
    - ../src/Controller/Api/CrudController.php
```

- [ ] **Step 2: Replace the entire `config/routes.yaml` with**

```yaml
http:
  resource: '../src/Module/'
  type: attribute

api:
  prefix: /api
  name_prefix: 'api_'
  resource: '../src/Module/'
  type: attribute
  exclude:
    - '../src/Module/Core/Controller/CrudController.php'
    - '../src/Module/Integration/Controller/'
```

**Why this works:**
- `resource: '../src/Module/'` makes Symfony's attribute route loader scan all PHP files under `src/Module/` recursively. It only registers routes for classes that have `#[Route]` attributes, so Repository and Service files are silently skipped.
- The `http:` entry loads all module routes without a URL prefix (preserving the existing behavior).
- The `api:` entry loads all module routes with the `/api` prefix, excluding `CrudController` (which has no routes of its own — it's a base class) and the `Integration/Controller/` directory (Portal controllers don't need `/api` prefix).

- [ ] **Step 3: Verify the YAML is valid**

```bash
php bin/console debug:router 2>&1 | tail -5
```

Expected: route list without errors. If you see `ParseException`, check indentation — YAML requires 2-space indentation.

- [ ] **Step 4: Commit**

```bash
git add config/routes.yaml
git commit -m "refactor(modules): update routes.yaml to scan src/Module/ dirs"
```

---

### Task 4: Update `config/services.yaml`

**Files:**
- Modify: `config/services.yaml`

Three sets of changes:
1. `app.auto_service_locator` argument entries — all `App\Service\X` keys/values → `App\Module\{M}\Service\X`
2. `App\Repository\VatReportRepository` entry in the locator → `App\Module\Tax\Repository\VatReportRepository`
3. Explicit service definitions with old namespaces → new namespaces

- [ ] **Step 1: Read the full current `config/services.yaml`** to see all entries (lines 65–145 contain the auto_service_locator block and explicit definitions)

- [ ] **Step 2: Replace the `app.auto_service_locator` arguments block**

The current block (lines 66–141 approximately) maps service class names to themselves. Replace the entire `app.auto_service_locator:` definition with:

```yaml
    app.auto_service_locator:
        class: Symfony\Component\DependencyInjection\ServiceLocator
        arguments:
            -
                App\Module\Core\Service\BaseService: '@App\Module\Core\Service\BaseService'
                App\Module\Core\Service\CommonService: '@App\Module\Core\Service\CommonService'
                App\Module\Core\Service\UserAgentService: '@App\Module\Core\Service\UserAgentService'
                App\Module\Core\Service\LogService: '@App\Module\Core\Service\LogService'
                App\Module\Core\Service\UserService: '@App\Module\Core\Service\UserService'
                App\Module\Core\Service\UserGroupService: '@App\Module\Core\Service\UserGroupService'
                App\Module\Core\Service\MediaService: '@App\Module\Core\Service\MediaService'
                App\Module\Core\Service\RequestService: '@App\Module\Core\Service\RequestService'
                App\Module\Core\Service\UserTokenService: '@App\Module\Core\Service\UserTokenService'
                ObjectNormalizer: '@serializer.normalizer.object'

                App\Module\Carrier\Service\ProviderService: '@App\Module\Carrier\Service\ProviderService'
                App\Module\Crm\Service\ClientService: '@App\Module\Crm\Service\ClientService'
                App\Module\Quote\Service\PriceMarkupService: '@App\Module\Quote\Service\PriceMarkupService'
                App\Module\Core\Service\MailService: '@App\Module\Core\Service\MailService'

                App\Module\Finance\Service\BankAccountService: '@App\Module\Finance\Service\BankAccountService'
                App\Module\Operations\Service\BookingService: '@App\Module\Operations\Service\BookingService'
                App\Module\Quote\Service\CalculationTypeService: '@App\Module\Quote\Service\CalculationTypeService'
                App\Module\Finance\Service\ChargeService: '@App\Module\Finance\Service\ChargeService'
                App\Module\Finance\Service\ChargeItemService: '@App\Module\Finance\Service\ChargeItemService'
                App\Module\Core\Service\ComponentService: '@App\Module\Core\Service\ComponentService'
                App\Module\Crm\Service\ContactService: '@App\Module\Crm\Service\ContactService'
                App\Module\Finance\Service\CurrencyService: '@App\Module\Finance\Service\CurrencyService'
                App\Module\Quote\Service\CustomChargeTypeService: '@App\Module\Quote\Service\CustomChargeTypeService'
                App\Module\Reporting\Service\DatasetService: '@App\Module\Reporting\Service\DatasetService'
                App\Module\Finance\Service\EbitNoteService: '@App\Module\Finance\Service\EbitNoteService'
                App\Module\Finance\Service\ExchangeRateGroupService: '@App\Module\Finance\Service\ExchangeRateGroupService'
                App\Module\Finance\Service\FxGainLossService: '@App\Module\Finance\Service\FxGainLossService'
                App\Module\Quote\Service\IncotermService: '@App\Module\Quote\Service\IncotermService'
                App\Module\Operations\Service\InstructionService: '@App\Module\Operations\Service\InstructionService'
                App\Module\Finance\Service\InvoiceInfoService: '@App\Module\Finance\Service\InvoiceInfoService'
                App\Module\Core\Service\PackageTypeService: '@App\Module\Core\Service\PackageTypeService'
                App\Module\Core\Service\PageService: '@App\Module\Core\Service\PageService'
                App\Module\Finance\Service\PaymentMethodService: '@App\Module\Finance\Service\PaymentMethodService'
                App\Module\Core\Service\PortService: '@App\Module\Core\Service\PortService'
                App\Module\Quote\Service\QuoteService: '@App\Module\Quote\Service\QuoteService'
                App\Module\Quote\Service\RateService: '@App\Module\Quote\Service\RateService'
                App\Module\Operations\Service\ShipmentActivityService: '@App\Module\Operations\Service\ShipmentActivityService'
                App\Module\Operations\Service\ShipmentModeService: '@App\Module\Operations\Service\ShipmentModeService'
                App\Module\Operations\Service\ShipmentService: '@App\Module\Operations\Service\ShipmentService'
                App\Module\Finance\Service\TaxGroupService: '@App\Module\Finance\Service\TaxGroupService'
                App\Module\Core\Service\MasterSyncService: '@App\Module\Core\Service\MasterSyncService'
                App\Module\Quote\Service\QuotePriceService: '@App\Module\Quote\Service\QuotePriceService'
                App\Module\Core\Service\BranchService: '@App\Module\Core\Service\BranchService'
                App\Module\Core\Service\DepartmentService: '@App\Module\Core\Service\DepartmentService'
                App\Module\Core\Service\ConfigService: '@App\Module\Core\Service\ConfigService'
                App\Module\Finance\Service\JournalPostingService: '@App\Module\Finance\Service\JournalPostingService'
                App\Module\Operations\Service\ArrivalNoticeService: '@App\Module\Operations\Service\ArrivalNoticeService'
                App\Module\Operations\Service\DeliveryOrderService: '@App\Module\Operations\Service\DeliveryOrderService'
                App\Module\Tax\Service\HsCodeService: '@App\Module\Tax\Service\HsCodeService'
                App\Module\Tax\Service\DutyRateService: '@App\Module\Tax\Service\DutyRateService'
                App\Module\Tax\Service\HsRestrictionService: '@App\Module\Tax\Service\HsRestrictionService'
                App\Module\Tax\Service\HsVersionMappingService: '@App\Module\Tax\Service\HsVersionMappingService'
                App\Module\Carrier\Service\TrackingRequestService: '@App\Module\Carrier\Service\TrackingRequestService'
                App\Module\Carrier\Service\TrackingEventRawService: '@App\Module\Carrier\Service\TrackingEventRawService'
                App\Module\Carrier\Service\CarrierEventMappingService: '@App\Module\Carrier\Service\CarrierEventMappingService'
                App\Module\Finance\Service\DdCalculatorService: '@App\Module\Finance\Service\DdCalculatorService'

                App\Module\Integration\Service\PortalAuthService: '@App\Module\Integration\Service\PortalAuthService'
                App\Module\Integration\Service\PortalShipmentService: '@App\Module\Integration\Service\PortalShipmentService'
                App\Module\Integration\Service\PortalDocumentService: '@App\Module\Integration\Service\PortalDocumentService'
                App\Module\Integration\Service\PortalInvoiceService: '@App\Module\Integration\Service\PortalInvoiceService'
                App\Module\Integration\Service\PortalQuoteRequestService: '@App\Module\Integration\Service\PortalQuoteRequestService'
                App\Module\Notification\Service\InAppNotificationService: '@App\Module\Notification\Service\InAppNotificationService'
                App\Module\Finance\Service\CreditCheckService: '@App\Module\Finance\Service\CreditCheckService'
                App\Module\Notification\Service\NotificationGeneratorService: '@App\Module\Notification\Service\NotificationGeneratorService'
                App\Module\Notification\Service\NotificationTemplateRenderer: '@App\Module\Notification\Service\NotificationTemplateRenderer'

                App\Module\Tax\Repository\VatReportRepository: '@App\Module\Tax\Repository\VatReportRepository'
                App\Module\Carrier\Service\CarrierPerformanceScoreService: '@App\Module\Carrier\Service\CarrierPerformanceScoreService'
```

- [ ] **Step 3: Update the explicit `App\Service\MailService` definition**

Find:
```yaml
    App\Service\MailService:
        arguments:
            $defaultFromAddress: no-reply@makecargo.com
```

Replace with:
```yaml
    App\Module\Core\Service\MailService:
        arguments:
            $defaultFromAddress: no-reply@makecargo.com
```

- [ ] **Step 4: Update the `App\Module\ModuleRegistry` definition** (already correct from the module activation task — verify it still says `App\Module\ModuleRegistry` not an old path)

- [ ] **Step 5: Verify services.yaml syntax by checking the container builds**

```bash
php bin/console cache:clear 2>&1
```

Expected: `Cache for the "dev" environment (debug=true) was successfully cleared.`

If you see YAML parse errors, check indentation. YAML requires 2-space indentation, and the `app.auto_service_locator` block uses 4 spaces for the nested mapping keys.

- [ ] **Step 6: Commit**

```bash
git add config/services.yaml
git commit -m "refactor(modules): update services.yaml for new module namespaces"
```

---

### Task 5: Verify — container, routes, and no old namespace references

**Files:** none created/modified — verification only

- [ ] **Step 1: Clear cache and verify it builds cleanly**

```bash
php bin/console cache:clear
```

Expected: success message, no errors.

- [ ] **Step 2: Verify the container has key services under new namespaces**

```bash
php bin/console debug:container "App\Module\Quote\Service\QuoteService" 2>&1 | head -5
php bin/console debug:container "App\Module\Finance\Repository\EbitNoteRepository" 2>&1 | head -5
php bin/console debug:container "App\Module\Operations\Controller\ShipmentController" 2>&1 | head -5
```

Expected: each command shows the service definition info (class, autowired, etc.).

- [ ] **Step 3: Verify routes are registered**

```bash
php bin/console debug:router | grep "api_shipment" | head -5
php bin/console debug:router | grep "api_quote" | head -5
```

Expected: route entries appear with `/api` prefix.

- [ ] **Step 4: Scan for any remaining old-namespace references**

```bash
grep -r "use App\\\\Controller\\\\Api\\\\" src/ 2>/dev/null && echo "FOUND OLD REFS" || echo "Clean"
grep -r "use App\\\\Repository\\\\" src/ 2>/dev/null && echo "FOUND OLD REFS" || echo "Clean"
grep -r "use App\\\\Service\\\\" src/ 2>/dev/null && echo "FOUND OLD REFS" || echo "Clean"
```

Expected: all print `Clean`. If any print `FOUND OLD REFS`, run `grep -r "use App\\Controller\\Api\\" src/` (without extra escaping) to see which files still have old references, then update them manually.

- [ ] **Step 5: Verify the module compiler pass still works (spot-check)**

```bash
# Temporarily set a limited module list
php -r "putenv('ENABLED_MODULES=core,quote'); require 'vendor/autoload.php'; \$k = new App\Kernel('dev', true); \$k->boot(); echo 'OK';" 2>&1 | tail -3
```

Expected: `OK` (no fatal errors).

Restore full modules:
```bash
php bin/console cache:clear
```

- [ ] **Step 6: Final commit (if any manual fixes were needed in Step 4)**

```bash
git add -A
git commit -m "fix(modules): clean up any remaining old-namespace references after migration"
```

If no fixes were needed, skip this step.
