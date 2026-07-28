<?php
declare(strict_types=1);
/**
 * Replaces inline \App\Misc\Enum\X and \App\Entity\X FQCNs in PHP files
 * with the new App\Module\* equivalents, adding `use` statements where needed.
 */

$root = dirname(__DIR__);

// Old inline FQCN => New FQCN (same map as migrate-entities-enums.php)
$fqcnMap = [
    // Entities
    'App\\Entity\\Branch'                       => 'App\\Module\\Core\\Entity\\Branch',
    'App\\Entity\\Component'                    => 'App\\Module\\Core\\Entity\\Component',
    'App\\Entity\\ComponentSerie'               => 'App\\Module\\Core\\Entity\\ComponentSerie',
    'App\\Entity\\Config'                       => 'App\\Module\\Core\\Entity\\Config',
    'App\\Entity\\Department'                   => 'App\\Module\\Core\\Entity\\Department',
    'App\\Entity\\Log'                          => 'App\\Module\\Core\\Entity\\Log',
    'App\\Entity\\Media'                        => 'App\\Module\\Core\\Entity\\Media',
    'App\\Entity\\Money'                        => 'App\\Module\\Core\\Entity\\Money',
    'App\\Entity\\OrganisationAddress'          => 'App\\Module\\Core\\Entity\\OrganisationAddress',
    'App\\Entity\\PackageType'                  => 'App\\Module\\Core\\Entity\\PackageType',
    'App\\Entity\\Page'                         => 'App\\Module\\Core\\Entity\\Page',
    'App\\Entity\\Port'                         => 'App\\Module\\Core\\Entity\\Port',
    'App\\Entity\\SubEntity'                    => 'App\\Module\\Core\\Entity\\SubEntity',
    'App\\Entity\\User'                         => 'App\\Module\\Core\\Entity\\User',
    'App\\Entity\\UserAgent'                    => 'App\\Module\\Core\\Entity\\UserAgent',
    'App\\Entity\\UserGroup'                    => 'App\\Module\\Core\\Entity\\UserGroup',
    'App\\Entity\\UserNotificationPreference'   => 'App\\Module\\Core\\Entity\\UserNotificationPreference',
    'App\\Entity\\UserToken'                    => 'App\\Module\\Core\\Entity\\UserToken',
    'App\\Entity\\CalculationType'              => 'App\\Module\\Quote\\Entity\\CalculationType',
    'App\\Entity\\CustomChargeType'             => 'App\\Module\\Quote\\Entity\\CustomChargeType',
    'App\\Entity\\FreeTimeAgreement'            => 'App\\Module\\Quote\\Entity\\FreeTimeAgreement',
    'App\\Entity\\Incoterm'                     => 'App\\Module\\Quote\\Entity\\Incoterm',
    'App\\Entity\\PriceMarkup'                  => 'App\\Module\\Quote\\Entity\\PriceMarkup',
    'App\\Entity\\Quote'                        => 'App\\Module\\Quote\\Entity\\Quote',
    'App\\Entity\\QuotePrice'                   => 'App\\Module\\Quote\\Entity\\QuotePrice',
    'App\\Entity\\Rate'                         => 'App\\Module\\Quote\\Entity\\Rate',
    'App\\Entity\\BankAccount'                  => 'App\\Module\\Finance\\Entity\\BankAccount',
    'App\\Entity\\Charge'                       => 'App\\Module\\Finance\\Entity\\Charge',
    'App\\Entity\\ChargeItem'                   => 'App\\Module\\Finance\\Entity\\ChargeItem',
    'App\\Entity\\ChartOfAccount'               => 'App\\Module\\Finance\\Entity\\ChartOfAccount',
    'App\\Entity\\CreditLimitHistory'           => 'App\\Module\\Finance\\Entity\\CreditLimitHistory',
    'App\\Entity\\Currency'                     => 'App\\Module\\Finance\\Entity\\Currency',
    'App\\Entity\\EbitNote'                     => 'App\\Module\\Finance\\Entity\\EbitNote',
    'App\\Entity\\ExchangeRate'                 => 'App\\Module\\Finance\\Entity\\ExchangeRate',
    'App\\Entity\\ExchangeRateGroup'            => 'App\\Module\\Finance\\Entity\\ExchangeRateGroup',
    'App\\Entity\\InvoiceInfo'                  => 'App\\Module\\Finance\\Entity\\InvoiceInfo',
    'App\\Entity\\JournalEntry'                 => 'App\\Module\\Finance\\Entity\\JournalEntry',
    'App\\Entity\\JournalLine'                  => 'App\\Module\\Finance\\Entity\\JournalLine',
    'App\\Entity\\PaymentMethod'                => 'App\\Module\\Finance\\Entity\\PaymentMethod',
    'App\\Entity\\TaxGroup'                     => 'App\\Module\\Finance\\Entity\\TaxGroup',
    'App\\Entity\\TaxRule'                      => 'App\\Module\\Finance\\Entity\\TaxRule',
    'App\\Entity\\CustomerTaxExemption'         => 'App\\Module\\Tax\\Entity\\CustomerTaxExemption',
    'App\\Entity\\DutyRate'                     => 'App\\Module\\Tax\\Entity\\DutyRate',
    'App\\Entity\\HsCode'                       => 'App\\Module\\Tax\\Entity\\HsCode',
    'App\\Entity\\HsRestriction'                => 'App\\Module\\Tax\\Entity\\HsRestriction',
    'App\\Entity\\HsVersionMapping'             => 'App\\Module\\Tax\\Entity\\HsVersionMapping',
    'App\\Entity\\PartnerTaxRegistration'       => 'App\\Module\\Tax\\Entity\\PartnerTaxRegistration',
    'App\\Entity\\Archive'                      => 'App\\Module\\Operations\\Entity\\Archive',
    'App\\Entity\\ArrivalNotice'                => 'App\\Module\\Operations\\Entity\\ArrivalNotice',
    'App\\Entity\\Booking'                      => 'App\\Module\\Operations\\Entity\\Booking',
    'App\\Entity\\Consolidation'                => 'App\\Module\\Operations\\Entity\\Consolidation',
    'App\\Entity\\DangerousGoods'               => 'App\\Module\\Operations\\Entity\\DangerousGoods',
    'App\\Entity\\DeliveryOrder'                => 'App\\Module\\Operations\\Entity\\DeliveryOrder',
    'App\\Entity\\Instruction'                  => 'App\\Module\\Operations\\Entity\\Instruction',
    'App\\Entity\\InstructionContainer'         => 'App\\Module\\Operations\\Entity\\InstructionContainer',
    'App\\Entity\\Shipment'                     => 'App\\Module\\Operations\\Entity\\Shipment',
    'App\\Entity\\ShipmentActivity'             => 'App\\Module\\Operations\\Entity\\ShipmentActivity',
    'App\\Entity\\ShipmentDocument'             => 'App\\Module\\Operations\\Entity\\ShipmentDocument',
    'App\\Entity\\ShipmentMilestone'            => 'App\\Module\\Operations\\Entity\\ShipmentMilestone',
    'App\\Entity\\ShipmentMode'                 => 'App\\Module\\Operations\\Entity\\ShipmentMode',
    'App\\Entity\\ShipmentNote'                 => 'App\\Module\\Operations\\Entity\\ShipmentNote',
    'App\\Entity\\ShipmentParty'                => 'App\\Module\\Operations\\Entity\\ShipmentParty',
    'App\\Entity\\ShipmentTask'                 => 'App\\Module\\Operations\\Entity\\ShipmentTask',
    'App\\Entity\\CargoClaim'                   => 'App\\Module\\Carrier\\Entity\\CargoClaim',
    'App\\Entity\\CarrierEventMapping'          => 'App\\Module\\Carrier\\Entity\\CarrierEventMapping',
    'App\\Entity\\CarrierPerformanceScore'      => 'App\\Module\\Carrier\\Entity\\CarrierPerformanceScore',
    'App\\Entity\\CarrierProfile'               => 'App\\Module\\Carrier\\Entity\\CarrierProfile',
    'App\\Entity\\ContainerDdTracking'          => 'App\\Module\\Carrier\\Entity\\ContainerDdTracking',
    'App\\Entity\\Provider'                     => 'App\\Module\\Carrier\\Entity\\Provider',
    'App\\Entity\\TrackingEventRaw'             => 'App\\Module\\Carrier\\Entity\\TrackingEventRaw',
    'App\\Entity\\TrackingRequest'              => 'App\\Module\\Carrier\\Entity\\TrackingRequest',
    'App\\Entity\\VesselRoll'                   => 'App\\Module\\Carrier\\Entity\\VesselRoll',
    'App\\Entity\\AgentProfile'                 => 'App\\Module\\Crm\\Entity\\AgentProfile',
    'App\\Entity\\Client'                       => 'App\\Module\\Crm\\Entity\\Client',
    'App\\Entity\\Contact'                      => 'App\\Module\\Crm\\Entity\\Contact',
    'App\\Entity\\Partner'                      => 'App\\Module\\Crm\\Entity\\Partner',
    'App\\Entity\\InAppNotification'            => 'App\\Module\\Notification\\Entity\\InAppNotification',
    'App\\Entity\\Mail'                         => 'App\\Module\\Notification\\Entity\\Mail',
    'App\\Entity\\NotificationQueue'            => 'App\\Module\\Notification\\Entity\\NotificationQueue',
    'App\\Entity\\NotificationRule'             => 'App\\Module\\Notification\\Entity\\NotificationRule',
    'App\\Entity\\NotificationTemplate'         => 'App\\Module\\Notification\\Entity\\NotificationTemplate',
    'App\\Entity\\Dataset'                      => 'App\\Module\\Reporting\\Entity\\Dataset',
    'App\\Entity\\DatasetFilter'                => 'App\\Module\\Reporting\\Entity\\DatasetFilter',
    'App\\Entity\\DatasetProp'                  => 'App\\Module\\Reporting\\Entity\\DatasetProp',
    'App\\Entity\\PortalQuoteRequest'           => 'App\\Module\\Integration\\Entity\\PortalQuoteRequest',
    'App\\Entity\\PortalToken'                  => 'App\\Module\\Integration\\Entity\\PortalToken',
    'App\\Entity\\PortalUser'                   => 'App\\Module\\Integration\\Entity\\PortalUser',
    // Enums
    'App\\Misc\\Enum\\AddressType'              => 'App\\Module\\Core\\Enum\\AddressType',
    'App\\Misc\\Enum\\ComponentType'            => 'App\\Module\\Core\\Enum\\ComponentType',
    'App\\Misc\\Enum\\Country'                  => 'App\\Module\\Core\\Enum\\Country',
    'App\\Misc\\Enum\\DateRange'                => 'App\\Module\\Core\\Enum\\DateRange',
    'App\\Misc\\Enum\\DateSegment'              => 'App\\Module\\Core\\Enum\\DateSegment',
    'App\\Misc\\Enum\\EntityType'               => 'App\\Module\\Core\\Enum\\EntityType',
    'App\\Misc\\Enum\\MediaCategory'            => 'App\\Module\\Core\\Enum\\MediaCategory',
    'App\\Misc\\Enum\\PageType'                 => 'App\\Module\\Core\\Enum\\PageType',
    'App\\Misc\\Enum\\Permission'               => 'App\\Module\\Core\\Enum\\Permission',
    'App\\Misc\\Enum\\PortType'                 => 'App\\Module\\Core\\Enum\\PortType',
    'App\\Misc\\Enum\\RequestMethod'            => 'App\\Module\\Core\\Enum\\RequestMethod',
    'App\\Misc\\Enum\\ServiceType'              => 'App\\Module\\Core\\Enum\\ServiceType',
    'App\\Misc\\Enum\\TransportType'            => 'App\\Module\\Core\\Enum\\TransportType',
    'App\\Misc\\Enum\\UserStatus'               => 'App\\Module\\Core\\Enum\\UserStatus',
    'App\\Misc\\Enum\\VisibleTo'                => 'App\\Module\\Core\\Enum\\VisibleTo',
    'App\\Misc\\Enum\\VolumeType'               => 'App\\Module\\Core\\Enum\\VolumeType',
    'App\\Misc\\Enum\\WeekDay'                  => 'App\\Module\\Core\\Enum\\WeekDay',
    'App\\Misc\\Enum\\Magnum'                   => 'App\\Module\\Core\\Enum\\Magnum',
    'App\\Misc\\Enum\\FreightTerm'              => 'App\\Module\\Quote\\Enum\\FreightTerm',
    'App\\Misc\\Enum\\QuoteStatus'              => 'App\\Module\\Quote\\Enum\\QuoteStatus',
    'App\\Misc\\Enum\\ChargeType'               => 'App\\Module\\Finance\\Enum\\ChargeType',
    'App\\Misc\\Enum\\CreditNoteReason'         => 'App\\Module\\Finance\\Enum\\CreditNoteReason',
    'App\\Misc\\Enum\\CreditStatus'             => 'App\\Module\\Finance\\Enum\\CreditStatus',
    'App\\Misc\\Enum\\EbitNoteStatus'           => 'App\\Module\\Finance\\Enum\\EbitNoteStatus',
    'App\\Misc\\Enum\\EbitNoteType'             => 'App\\Module\\Finance\\Enum\\EbitNoteType',
    'App\\Misc\\Enum\\LocalChargeType'          => 'App\\Module\\Finance\\Enum\\LocalChargeType',
    'App\\Misc\\Enum\\PayableAt'                => 'App\\Module\\Finance\\Enum\\PayableAt',
    'App\\Misc\\Enum\\PaymentMethodType'        => 'App\\Module\\Finance\\Enum\\PaymentMethodType',
    'App\\Misc\\Enum\\VarianceStatus'           => 'App\\Module\\Finance\\Enum\\VarianceStatus',
    'App\\Misc\\Enum\\ConsolidationStatus'      => 'App\\Module\\Operations\\Enum\\ConsolidationStatus',
    'App\\Misc\\Enum\\ContainerType'            => 'App\\Module\\Operations\\Enum\\ContainerType',
    'App\\Misc\\Enum\\DocType'                  => 'App\\Module\\Operations\\Enum\\DocType',
    'App\\Misc\\Enum\\NoteType'                 => 'App\\Module\\Operations\\Enum\\NoteType',
    'App\\Misc\\Enum\\NoteVisibility'           => 'App\\Module\\Operations\\Enum\\NoteVisibility',
    'App\\Misc\\Enum\\PartyRole'                => 'App\\Module\\Operations\\Enum\\PartyRole',
    'App\\Misc\\Enum\\ShipmentActivityType'     => 'App\\Module\\Operations\\Enum\\ShipmentActivityType',
    'App\\Misc\\Enum\\ShipmentStatus'           => 'App\\Module\\Operations\\Enum\\ShipmentStatus',
    'App\\Misc\\Enum\\ShipmentType'             => 'App\\Module\\Operations\\Enum\\ShipmentType',
    'App\\Misc\\Enum\\SubStatus'                => 'App\\Module\\Operations\\Enum\\SubStatus',
    'App\\Misc\\Enum\\TaskType'                 => 'App\\Module\\Operations\\Enum\\TaskType',
    'App\\Misc\\Enum\\CarrierType'              => 'App\\Module\\Carrier\\Enum\\CarrierType',
    'App\\Misc\\Enum\\MilestoneCode'            => 'App\\Module\\Carrier\\Enum\\MilestoneCode',
    'App\\Misc\\Enum\\ProviderType'             => 'App\\Module\\Carrier\\Enum\\ProviderType',
    'App\\Misc\\Enum\\ClientCustomInfoMode'     => 'App\\Module\\Crm\\Enum\\ClientCustomInfoMode',
    'App\\Misc\\Enum\\ClientResidenceType'      => 'App\\Module\\Crm\\Enum\\ClientResidenceType',
    'App\\Misc\\Enum\\ClientTier'               => 'App\\Module\\Crm\\Enum\\ClientTier',
    'App\\Misc\\Enum\\ClientType'               => 'App\\Module\\Crm\\Enum\\ClientType',
    'App\\Misc\\Enum\\MailStatus'               => 'App\\Module\\Notification\\Enum\\MailStatus',
    'App\\Misc\\Enum\\DatasetGroupColumn'       => 'App\\Module\\Reporting\\Enum\\DatasetGroupColumn',
    'App\\Misc\\Enum\\DatasetRowType'           => 'App\\Module\\Reporting\\Enum\\DatasetRowType',
];

// Build search/replace for inline \Old\FQCN patterns in code
// These appear as: \App\Misc\Enum\Foo or \App\Entity\Foo (with backslash prefix)
// We replace them with their short name and ensure a `use` statement exists

$rit     = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);
$updated = 0;

foreach ($rit as $fi) {
    if ($fi->getExtension() !== 'php') {
        continue;
    }
    $path    = $fi->getPathname();
    $content = file_get_contents($path);
    $changed = false;
    $toImport = []; // shortName => newFQCN

    foreach ($fqcnMap as $oldFQCN => $newFQCN) {
        // Look for \Old\FQCN pattern (with leading backslash)
        $inlinePattern = '\\' . $oldFQCN; // e.g. \App\Misc\Enum\VarianceStatus
        if (!str_contains($content, $inlinePattern)) {
            continue;
        }

        $parts     = explode('\\', $newFQCN);
        $shortName = end($parts);

        // Replace \OldFQCN with just ShortName
        $content = str_replace('\\' . $oldFQCN, $shortName, $content);
        $changed = true;

        // Queue the use statement
        if (!str_contains($content, 'use ' . $newFQCN . ';')) {
            $toImport[$shortName] = $newFQCN;
        }
    }

    if ($changed) {
        // Add any missing use statements after the namespace declaration
        if (!empty($toImport)) {
            $useLines = implode("\n", array_map(fn($fqcn) => "use {$fqcn};", $toImport));
            $content  = preg_replace(
                '/^(namespace [^;]+;)/m',
                "$1\n\n" . $useLines,
                $content,
                1
            );
        }
        file_put_contents($path, $content);
        echo 'FIXED: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $path) . "\n";
        $updated++;
    }
}

echo "\nTotal files updated: {$updated}\n";
