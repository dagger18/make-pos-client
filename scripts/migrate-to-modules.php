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
