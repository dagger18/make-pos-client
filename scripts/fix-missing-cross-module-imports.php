<?php
declare(strict_types=1);

/**
 * Adds missing `use` statements for cross-module class references.
 *
 * When services/repositories were in a single App\Service namespace, they
 * could reference each other without `use` statements. After the module split,
 * bare class names resolve to the current namespace which is wrong.
 *
 * This script scans all .php files in src/Module/ and for each file, checks
 * if bare class names used in the file are found in a DIFFERENT module namespace
 * without a matching `use` statement, then adds the missing import.
 */

$root = dirname(__DIR__);

// Full map of class short name => FQCN (only the moved classes)
$classMap = [
    // Core Services
    'BaseService'                  => 'App\\Module\\Core\\Service\\BaseService',
    'CommonService'                => 'App\\Module\\Core\\Service\\CommonService',
    'ConfigService'                => 'App\\Module\\Core\\Service\\ConfigService',
    'LogService'                   => 'App\\Module\\Core\\Service\\LogService',
    'MailService'                  => 'App\\Module\\Core\\Service\\MailService',
    'MasterService'                => 'App\\Module\\Core\\Service\\MasterService',
    'MasterSyncService'            => 'App\\Module\\Core\\Service\\MasterSyncService',
    'MediaService'                 => 'App\\Module\\Core\\Service\\MediaService',
    'PackageTypeService'           => 'App\\Module\\Core\\Service\\PackageTypeService',
    'PageService'                  => 'App\\Module\\Core\\Service\\PageService',
    'PortService'                  => 'App\\Module\\Core\\Service\\PortService',
    'RequestService'               => 'App\\Module\\Core\\Service\\RequestService',
    'UserAgentService'             => 'App\\Module\\Core\\Service\\UserAgentService',
    'UserGroupService'             => 'App\\Module\\Core\\Service\\UserGroupService',
    'UserService'                  => 'App\\Module\\Core\\Service\\UserService',
    'UserTokenService'             => 'App\\Module\\Core\\Service\\UserTokenService',
    'BranchService'                => 'App\\Module\\Core\\Service\\BranchService',
    'DepartmentService'            => 'App\\Module\\Core\\Service\\DepartmentService',
    'ComponentService'             => 'App\\Module\\Core\\Service\\ComponentService',
    'InterServiceTokenService'     => 'App\\Module\\Core\\Service\\InterServiceTokenService',
    // Core Repositories
    'BaseRepository'               => 'App\\Module\\Core\\Repository\\BaseRepository',
    'UserRepository'               => 'App\\Module\\Core\\Repository\\UserRepository',
    // Finance Services
    'BankAccountService'           => 'App\\Module\\Finance\\Service\\BankAccountService',
    'ChargeService'                => 'App\\Module\\Finance\\Service\\ChargeService',
    'ChargeItemService'            => 'App\\Module\\Finance\\Service\\ChargeItemService',
    'CurrencyService'              => 'App\\Module\\Finance\\Service\\CurrencyService',
    'EbitNoteService'              => 'App\\Module\\Finance\\Service\\EbitNoteService',
    'ExchangeRateGroupService'     => 'App\\Module\\Finance\\Service\\ExchangeRateGroupService',
    'FxGainLossService'            => 'App\\Module\\Finance\\Service\\FxGainLossService',
    'InvoiceInfoService'           => 'App\\Module\\Finance\\Service\\InvoiceInfoService',
    'JournalPostingService'        => 'App\\Module\\Finance\\Service\\JournalPostingService',
    'PaymentMethodService'         => 'App\\Module\\Finance\\Service\\PaymentMethodService',
    'TaxGroupService'              => 'App\\Module\\Finance\\Service\\TaxGroupService',
    'DdCalculatorService'          => 'App\\Module\\Finance\\Service\\DdCalculatorService',
    'CreditCheckService'           => 'App\\Module\\Finance\\Service\\CreditCheckService',
    // Finance Repositories
    'EbitNoteRepository'           => 'App\\Module\\Finance\\Repository\\EbitNoteRepository',
    'ChargeRepository'             => 'App\\Module\\Finance\\Repository\\ChargeRepository',
    'ChargeItemRepository'         => 'App\\Module\\Finance\\Repository\\ChargeItemRepository',
    // Quote Services
    'QuoteService'                 => 'App\\Module\\Quote\\Service\\QuoteService',
    'QuotePriceService'            => 'App\\Module\\Quote\\Service\\QuotePriceService',
    'RateService'                  => 'App\\Module\\Quote\\Service\\RateService',
    'PriceMarkupService'           => 'App\\Module\\Quote\\Service\\PriceMarkupService',
    'IncotermService'              => 'App\\Module\\Quote\\Service\\IncotermService',
    'CalculationTypeService'       => 'App\\Module\\Quote\\Service\\CalculationTypeService',
    'CustomChargeTypeService'      => 'App\\Module\\Quote\\Service\\CustomChargeTypeService',
    'QuoteCodeGeneratorService'    => 'App\\Module\\Quote\\Service\\QuoteCodeGeneratorService',
    // Operations Services
    'ShipmentService'              => 'App\\Module\\Operations\\Service\\ShipmentService',
    'ShipmentActivityService'      => 'App\\Module\\Operations\\Service\\ShipmentActivityService',
    'ShipmentDocumentService'      => 'App\\Module\\Operations\\Service\\ShipmentDocumentService',
    'ShipmentMilestoneService'     => 'App\\Module\\Operations\\Service\\ShipmentMilestoneService',
    'ShipmentModeService'          => 'App\\Module\\Operations\\Service\\ShipmentModeService',
    'ShipmentTaskService'          => 'App\\Module\\Operations\\Service\\ShipmentTaskService',
    'ShipmentIdGeneratorService'   => 'App\\Module\\Operations\\Service\\ShipmentIdGeneratorService',
    'BookingService'               => 'App\\Module\\Operations\\Service\\BookingService',
    'InstructionService'           => 'App\\Module\\Operations\\Service\\InstructionService',
    'ArrivalNoticeService'         => 'App\\Module\\Operations\\Service\\ArrivalNoticeService',
    'DeliveryOrderService'         => 'App\\Module\\Operations\\Service\\DeliveryOrderService',
    'ArchiveService'               => 'App\\Module\\Operations\\Service\\ArchiveService',
    // Operations Repositories
    'ShipmentRepository'           => 'App\\Module\\Operations\\Repository\\ShipmentRepository',
    // Carrier Services
    'ProviderService'              => 'App\\Module\\Carrier\\Service\\ProviderService',
    'CarrierEventMappingService'   => 'App\\Module\\Carrier\\Service\\CarrierEventMappingService',
    'CarrierPerformanceScoreService' => 'App\\Module\\Carrier\\Service\\CarrierPerformanceScoreService',
    'TrackingRequestService'       => 'App\\Module\\Carrier\\Service\\TrackingRequestService',
    'TrackingEventRawService'      => 'App\\Module\\Carrier\\Service\\TrackingEventRawService',
    'TrackingMilestoneWriterService' => 'App\\Module\\Carrier\\Service\\TrackingMilestoneWriterService',
    // CRM Services
    'ClientService'                => 'App\\Module\\Crm\\Service\\ClientService',
    'ContactService'               => 'App\\Module\\Crm\\Service\\ContactService',
    // Notification Services
    'NotificationGeneratorService' => 'App\\Module\\Notification\\Service\\NotificationGeneratorService',
    'NotificationTemplateRenderer' => 'App\\Module\\Notification\\Service\\NotificationTemplateRenderer',
    'InAppNotificationService'     => 'App\\Module\\Notification\\Service\\InAppNotificationService',
    // Reporting Services
    'DatasetService'               => 'App\\Module\\Reporting\\Service\\DatasetService',
    // Tax Services
    'HsCodeService'                => 'App\\Module\\Tax\\Service\\HsCodeService',
    'DutyRateService'              => 'App\\Module\\Tax\\Service\\DutyRateService',
    'HsRestrictionService'         => 'App\\Module\\Tax\\Service\\HsRestrictionService',
    'HsVersionMappingService'      => 'App\\Module\\Tax\\Service\\HsVersionMappingService',
    // Integration Services
    'PortalAuthService'            => 'App\\Module\\Integration\\Service\\PortalAuthService',
    'PortalDocumentService'        => 'App\\Module\\Integration\\Service\\PortalDocumentService',
    'PortalInvoiceService'         => 'App\\Module\\Integration\\Service\\PortalInvoiceService',
    'PortalQuoteRequestService'    => 'App\\Module\\Integration\\Service\\PortalQuoteRequestService',
    'PortalShipmentService'        => 'App\\Module\\Integration\\Service\\PortalShipmentService',

    // ---- Core Entities ----
    'Branch'                       => 'App\\Module\\Core\\Entity\\Branch',
    'Component'                    => 'App\\Module\\Core\\Entity\\Component',
    'ComponentSerie'               => 'App\\Module\\Core\\Entity\\ComponentSerie',
    'Config'                       => 'App\\Module\\Core\\Entity\\Config',
    'Department'                   => 'App\\Module\\Core\\Entity\\Department',
    'Log'                          => 'App\\Module\\Core\\Entity\\Log',
    'Media'                        => 'App\\Module\\Core\\Entity\\Media',
    'Money'                        => 'App\\Module\\Core\\Entity\\Money',
    'OrganisationAddress'          => 'App\\Module\\Core\\Entity\\OrganisationAddress',
    'PackageType'                  => 'App\\Module\\Core\\Entity\\PackageType',
    'Page'                         => 'App\\Module\\Core\\Entity\\Page',
    'Port'                         => 'App\\Module\\Core\\Entity\\Port',
    'SubEntity'                    => 'App\\Module\\Core\\Entity\\SubEntity',
    'User'                         => 'App\\Module\\Core\\Entity\\User',
    'UserAgent'                    => 'App\\Module\\Core\\Entity\\UserAgent',
    'UserGroup'                    => 'App\\Module\\Core\\Entity\\UserGroup',
    'UserNotificationPreference'   => 'App\\Module\\Core\\Entity\\UserNotificationPreference',
    'UserToken'                    => 'App\\Module\\Core\\Entity\\UserToken',
    // ---- Quote Entities ----
    'CalculationType'              => 'App\\Module\\Quote\\Entity\\CalculationType',
    'CustomChargeType'             => 'App\\Module\\Quote\\Entity\\CustomChargeType',
    'FreeTimeAgreement'            => 'App\\Module\\Quote\\Entity\\FreeTimeAgreement',
    'Incoterm'                     => 'App\\Module\\Quote\\Entity\\Incoterm',
    'PriceMarkup'                  => 'App\\Module\\Quote\\Entity\\PriceMarkup',
    'Quote'                        => 'App\\Module\\Quote\\Entity\\Quote',
    'QuotePrice'                   => 'App\\Module\\Quote\\Entity\\QuotePrice',
    'Rate'                         => 'App\\Module\\Quote\\Entity\\Rate',
    // ---- Finance Entities ----
    'BankAccount'                  => 'App\\Module\\Finance\\Entity\\BankAccount',
    'Charge'                       => 'App\\Module\\Finance\\Entity\\Charge',
    'ChargeItem'                   => 'App\\Module\\Finance\\Entity\\ChargeItem',
    'ChartOfAccount'               => 'App\\Module\\Finance\\Entity\\ChartOfAccount',
    'CreditLimitHistory'           => 'App\\Module\\Finance\\Entity\\CreditLimitHistory',
    'Currency'                     => 'App\\Module\\Finance\\Entity\\Currency',
    'EbitNote'                     => 'App\\Module\\Finance\\Entity\\EbitNote',
    'ExchangeRate'                 => 'App\\Module\\Finance\\Entity\\ExchangeRate',
    'ExchangeRateGroup'            => 'App\\Module\\Finance\\Entity\\ExchangeRateGroup',
    'InvoiceInfo'                  => 'App\\Module\\Finance\\Entity\\InvoiceInfo',
    'JournalEntry'                 => 'App\\Module\\Finance\\Entity\\JournalEntry',
    'JournalLine'                  => 'App\\Module\\Finance\\Entity\\JournalLine',
    'PaymentMethod'                => 'App\\Module\\Finance\\Entity\\PaymentMethod',
    'TaxGroup'                     => 'App\\Module\\Finance\\Entity\\TaxGroup',
    'TaxRule'                      => 'App\\Module\\Finance\\Entity\\TaxRule',
    // ---- Tax Entities ----
    'CustomerTaxExemption'         => 'App\\Module\\Tax\\Entity\\CustomerTaxExemption',
    'DutyRate'                     => 'App\\Module\\Tax\\Entity\\DutyRate',
    'HsCode'                       => 'App\\Module\\Tax\\Entity\\HsCode',
    'HsRestriction'                => 'App\\Module\\Tax\\Entity\\HsRestriction',
    'HsVersionMapping'             => 'App\\Module\\Tax\\Entity\\HsVersionMapping',
    'PartnerTaxRegistration'       => 'App\\Module\\Tax\\Entity\\PartnerTaxRegistration',
    // ---- Operations Entities ----
    'Archive'                      => 'App\\Module\\Operations\\Entity\\Archive',
    'ArrivalNotice'                => 'App\\Module\\Operations\\Entity\\ArrivalNotice',
    'Booking'                      => 'App\\Module\\Operations\\Entity\\Booking',
    'Consolidation'                => 'App\\Module\\Operations\\Entity\\Consolidation',
    'DangerousGoods'               => 'App\\Module\\Operations\\Entity\\DangerousGoods',
    'DeliveryOrder'                => 'App\\Module\\Operations\\Entity\\DeliveryOrder',
    'Instruction'                  => 'App\\Module\\Operations\\Entity\\Instruction',
    'InstructionContainer'         => 'App\\Module\\Operations\\Entity\\InstructionContainer',
    'Shipment'                     => 'App\\Module\\Operations\\Entity\\Shipment',
    'ShipmentActivity'             => 'App\\Module\\Operations\\Entity\\ShipmentActivity',
    'ShipmentDocument'             => 'App\\Module\\Operations\\Entity\\ShipmentDocument',
    'ShipmentMilestone'            => 'App\\Module\\Operations\\Entity\\ShipmentMilestone',
    'ShipmentMode'                 => 'App\\Module\\Operations\\Entity\\ShipmentMode',
    'ShipmentNote'                 => 'App\\Module\\Operations\\Entity\\ShipmentNote',
    'ShipmentParty'                => 'App\\Module\\Operations\\Entity\\ShipmentParty',
    'ShipmentTask'                 => 'App\\Module\\Operations\\Entity\\ShipmentTask',
    // ---- Carrier Entities ----
    'CargoClaim'                   => 'App\\Module\\Carrier\\Entity\\CargoClaim',
    'CarrierEventMapping'          => 'App\\Module\\Carrier\\Entity\\CarrierEventMapping',
    'CarrierPerformanceScore'      => 'App\\Module\\Carrier\\Entity\\CarrierPerformanceScore',
    'CarrierProfile'               => 'App\\Module\\Carrier\\Entity\\CarrierProfile',
    'ContainerDdTracking'          => 'App\\Module\\Carrier\\Entity\\ContainerDdTracking',
    'Provider'                     => 'App\\Module\\Carrier\\Entity\\Provider',
    'TrackingEventRaw'             => 'App\\Module\\Carrier\\Entity\\TrackingEventRaw',
    'TrackingRequest'              => 'App\\Module\\Carrier\\Entity\\TrackingRequest',
    'VesselRoll'                   => 'App\\Module\\Carrier\\Entity\\VesselRoll',
    // ---- CRM Entities ----
    'AgentProfile'                 => 'App\\Module\\Crm\\Entity\\AgentProfile',
    'Client'                       => 'App\\Module\\Crm\\Entity\\Client',
    'Contact'                      => 'App\\Module\\Crm\\Entity\\Contact',
    'Partner'                      => 'App\\Module\\Crm\\Entity\\Partner',
    // ---- Notification Entities ----
    'InAppNotification'            => 'App\\Module\\Notification\\Entity\\InAppNotification',
    'Mail'                         => 'App\\Module\\Notification\\Entity\\Mail',
    'NotificationQueue'            => 'App\\Module\\Notification\\Entity\\NotificationQueue',
    'NotificationRule'             => 'App\\Module\\Notification\\Entity\\NotificationRule',
    'NotificationTemplate'         => 'App\\Module\\Notification\\Entity\\NotificationTemplate',
    // ---- Reporting Entities ----
    'Dataset'                      => 'App\\Module\\Reporting\\Entity\\Dataset',
    'DatasetFilter'                => 'App\\Module\\Reporting\\Entity\\DatasetFilter',
    'DatasetProp'                  => 'App\\Module\\Reporting\\Entity\\DatasetProp',
    // ---- Integration Entities ----
    'PortalQuoteRequest'           => 'App\\Module\\Integration\\Entity\\PortalQuoteRequest',
    'PortalToken'                  => 'App\\Module\\Integration\\Entity\\PortalToken',
    'PortalUser'                   => 'App\\Module\\Integration\\Entity\\PortalUser',

    // ---- Core Enums ----
    'AddressType'                  => 'App\\Module\\Core\\Enum\\AddressType',
    'ComponentType'                => 'App\\Module\\Core\\Enum\\ComponentType',
    'Country'                      => 'App\\Module\\Core\\Enum\\Country',
    'DateRange'                    => 'App\\Module\\Core\\Enum\\DateRange',
    'DateSegment'                  => 'App\\Module\\Core\\Enum\\DateSegment',
    'EntityType'                   => 'App\\Module\\Core\\Enum\\EntityType',
    'MediaCategory'                => 'App\\Module\\Core\\Enum\\MediaCategory',
    'PageType'                     => 'App\\Module\\Core\\Enum\\PageType',
    'Permission'                   => 'App\\Module\\Core\\Enum\\Permission',
    'PortType'                     => 'App\\Module\\Core\\Enum\\PortType',
    'RequestMethod'                => 'App\\Module\\Core\\Enum\\RequestMethod',
    'ServiceType'                  => 'App\\Module\\Core\\Enum\\ServiceType',
    'TransportType'                => 'App\\Module\\Core\\Enum\\TransportType',
    'UserStatus'                   => 'App\\Module\\Core\\Enum\\UserStatus',
    'VisibleTo'                    => 'App\\Module\\Core\\Enum\\VisibleTo',
    'VolumeType'                   => 'App\\Module\\Core\\Enum\\VolumeType',
    'WeekDay'                      => 'App\\Module\\Core\\Enum\\WeekDay',
    'Magnum'                       => 'App\\Module\\Core\\Enum\\Magnum',
    // ---- Quote Enums ----
    'FreightTerm'                  => 'App\\Module\\Quote\\Enum\\FreightTerm',
    'QuoteStatus'                  => 'App\\Module\\Quote\\Enum\\QuoteStatus',
    // ---- Finance Enums ----
    'ChargeType'                   => 'App\\Module\\Finance\\Enum\\ChargeType',
    'CreditNoteReason'             => 'App\\Module\\Finance\\Enum\\CreditNoteReason',
    'CreditStatus'                 => 'App\\Module\\Finance\\Enum\\CreditStatus',
    'EbitNoteStatus'               => 'App\\Module\\Finance\\Enum\\EbitNoteStatus',
    'EbitNoteType'                 => 'App\\Module\\Finance\\Enum\\EbitNoteType',
    'LocalChargeType'              => 'App\\Module\\Finance\\Enum\\LocalChargeType',
    'PayableAt'                    => 'App\\Module\\Finance\\Enum\\PayableAt',
    'PaymentMethodType'            => 'App\\Module\\Finance\\Enum\\PaymentMethodType',
    'VarianceStatus'               => 'App\\Module\\Finance\\Enum\\VarianceStatus',
    // ---- Operations Enums ----
    'ConsolidationStatus'          => 'App\\Module\\Operations\\Enum\\ConsolidationStatus',
    'ContainerType'                => 'App\\Module\\Operations\\Enum\\ContainerType',
    'DocType'                      => 'App\\Module\\Operations\\Enum\\DocType',
    'NoteType'                     => 'App\\Module\\Operations\\Enum\\NoteType',
    'NoteVisibility'               => 'App\\Module\\Operations\\Enum\\NoteVisibility',
    'PartyRole'                    => 'App\\Module\\Operations\\Enum\\PartyRole',
    'ShipmentActivityType'         => 'App\\Module\\Operations\\Enum\\ShipmentActivityType',
    'ShipmentStatus'               => 'App\\Module\\Operations\\Enum\\ShipmentStatus',
    'ShipmentType'                 => 'App\\Module\\Operations\\Enum\\ShipmentType',
    'SubStatus'                    => 'App\\Module\\Operations\\Enum\\SubStatus',
    'TaskType'                     => 'App\\Module\\Operations\\Enum\\TaskType',
    // ---- Carrier Enums ----
    'CarrierType'                  => 'App\\Module\\Carrier\\Enum\\CarrierType',
    'MilestoneCode'                => 'App\\Module\\Carrier\\Enum\\MilestoneCode',
    'ProviderType'                 => 'App\\Module\\Carrier\\Enum\\ProviderType',
    // ---- CRM Enums ----
    'ClientCustomInfoMode'         => 'App\\Module\\Crm\\Enum\\ClientCustomInfoMode',
    'ClientResidenceType'          => 'App\\Module\\Crm\\Enum\\ClientResidenceType',
    'ClientTier'                   => 'App\\Module\\Crm\\Enum\\ClientTier',
    'ClientType'                   => 'App\\Module\\Crm\\Enum\\ClientType',
    // ---- Notification Enums ----
    'MailStatus'                   => 'App\\Module\\Notification\\Enum\\MailStatus',
    // ---- Reporting Enums ----
    'DatasetGroupColumn'           => 'App\\Module\\Reporting\\Enum\\DatasetGroupColumn',
    'DatasetRowType'               => 'App\\Module\\Reporting\\Enum\\DatasetRowType',
];

$rit   = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);
$fixed = 0;

foreach ($rit as $fi) {
    if ($fi->getExtension() !== 'php') {
        continue;
    }
    $path    = $fi->getPathname();
    $content = file_get_contents($path);

    // Extract current namespace of this file
    preg_match('/^namespace\s+([^;]+);/m', $content, $nsMatch);
    $currentNS = $nsMatch[1] ?? '';

    // Collect already-imported names (via `use` statements)
    preg_match_all('/^use\s+([^;]+);/m', $content, $useMatches);
    $importedFQCNs = array_map('trim', $useMatches[1]);
    $importedShortNames = array_map(function ($fqcn) {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }, $importedFQCNs);
    $importedFQCNsFlip       = array_flip($importedFQCNs);
    $importedShortNamesFlip  = array_flip($importedShortNames);

    // Collect class names defined in this file (to avoid self-import conflicts)
    preg_match_all('/^(?:class|interface|trait|enum)\s+(\w+)/m', $content, $defM);
    $definedNames = array_flip($defM[1]);

    $toAdd = [];

    foreach ($classMap as $shortName => $fqcn) {
        // Never import a class whose short name this file already defines
        if (isset($definedNames[$shortName])) {
            continue;
        }
        // Skip if already imported or defined in this namespace
        if (isset($importedFQCNsFlip[$fqcn])) {
            continue;
        }
        if (isset($importedShortNamesFlip[$shortName])) {
            continue; // imported as alias or different FQCN
        }

        $parts  = explode('\\', $fqcn);
        $ns     = implode('\\', array_slice($parts, 0, -1));

        // If this class is in the same namespace as the file, no import needed
        if ($ns === $currentNS) {
            continue;
        }

        // Check if the file uses this short name as a standalone word (not part of a longer name).
        // Covers: type hints (including ?Foo nullable), extends, implements, new Foo(, ::class
        $pattern = '/(?:^|\s|\||\(|\?)' . preg_quote($shortName, '/') . '(?:\s|::|\(|\||\$)/m';
        if (!preg_match($pattern, $content)) {
            continue;
        }

        $toAdd[$shortName] = $fqcn;
    }

    if (empty($toAdd)) {
        continue;
    }

    // Build the use statements to add
    $useLines = '';
    foreach ($toAdd as $sn => $fqcn) {
        $useLines .= "use {$fqcn};\n";
    }

    // Insert after the namespace declaration (and after any existing blank line)
    $content = preg_replace(
        '/^(namespace [^;]+;)/m',
        "$1\n\n" . rtrim($useLines),
        $content,
        1
    );

    file_put_contents($path, $content);
    echo 'FIXED [' . implode(', ', array_keys($toAdd)) . ']: ' . str_replace($root . '/', '', $path) . "\n";
    $fixed++;
}

echo "\nTotal files fixed: {$fixed}\n";
