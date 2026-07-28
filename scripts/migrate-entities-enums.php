<?php
declare(strict_types=1);
/**
 * Entity + Enum migration script.
 *
 * Moves src/Entity/ classes into src/Module/{Module}/Entity/
 * and src/Misc/Enum/ classes into src/Module/{Module}/Enum/,
 * updating namespace declarations and all `use` statements.
 *
 * Run from project root: php scripts/migrate-entities-enums.php
 */

$root = dirname(__DIR__);

// =========================================================
// ENTITY MAP — 'App\\Entity\\Foo' => 'Module'
// =========================================================
$entityMigrations = [
    // --- Core ---
    'App\\Entity\\Branch'                       => 'Core',
    'App\\Entity\\Component'                    => 'Core',
    'App\\Entity\\ComponentSerie'               => 'Core',
    'App\\Entity\\Config'                       => 'Core',
    'App\\Entity\\Department'                   => 'Core',
    'App\\Entity\\Log'                          => 'Core',
    'App\\Entity\\Media'                        => 'Core',
    'App\\Entity\\Money'                        => 'Core',
    'App\\Entity\\OrganisationAddress'          => 'Core',
    'App\\Entity\\PackageType'                  => 'Core',
    'App\\Entity\\Page'                         => 'Core',
    'App\\Entity\\Port'                         => 'Core',
    'App\\Entity\\SubEntity'                    => 'Core',
    'App\\Entity\\User'                         => 'Core',
    'App\\Entity\\UserAgent'                    => 'Core',
    'App\\Entity\\UserGroup'                    => 'Core',
    'App\\Entity\\UserNotificationPreference'   => 'Core',
    'App\\Entity\\UserToken'                    => 'Core',
    // --- Quote ---
    'App\\Entity\\CalculationType'              => 'Quote',
    'App\\Entity\\CustomChargeType'             => 'Quote',
    'App\\Entity\\FreeTimeAgreement'            => 'Quote',
    'App\\Entity\\Incoterm'                     => 'Quote',
    'App\\Entity\\PriceMarkup'                  => 'Quote',
    'App\\Entity\\Quote'                        => 'Quote',
    'App\\Entity\\QuotePrice'                   => 'Quote',
    'App\\Entity\\Rate'                         => 'Quote',
    // --- Finance ---
    'App\\Entity\\BankAccount'                  => 'Finance',
    'App\\Entity\\Charge'                       => 'Finance',
    'App\\Entity\\ChargeItem'                   => 'Finance',
    'App\\Entity\\ChartOfAccount'               => 'Finance',
    'App\\Entity\\CreditLimitHistory'           => 'Finance',
    'App\\Entity\\Currency'                     => 'Finance',
    'App\\Entity\\EbitNote'                     => 'Finance',
    'App\\Entity\\ExchangeRate'                 => 'Finance',
    'App\\Entity\\ExchangeRateGroup'            => 'Finance',
    'App\\Entity\\InvoiceInfo'                  => 'Finance',
    'App\\Entity\\JournalEntry'                 => 'Finance',
    'App\\Entity\\JournalLine'                  => 'Finance',
    'App\\Entity\\PaymentMethod'                => 'Finance',
    'App\\Entity\\TaxGroup'                     => 'Finance',
    'App\\Entity\\TaxRule'                      => 'Finance',
    // --- Tax ---
    'App\\Entity\\CustomerTaxExemption'         => 'Tax',
    'App\\Entity\\DutyRate'                     => 'Tax',
    'App\\Entity\\HsCode'                       => 'Tax',
    'App\\Entity\\HsRestriction'                => 'Tax',
    'App\\Entity\\HsVersionMapping'             => 'Tax',
    'App\\Entity\\PartnerTaxRegistration'       => 'Tax',
    // --- Operations ---
    'App\\Entity\\Archive'                      => 'Operations',
    'App\\Entity\\ArrivalNotice'                => 'Operations',
    'App\\Entity\\Booking'                      => 'Operations',
    'App\\Entity\\Consolidation'                => 'Operations',
    'App\\Entity\\DangerousGoods'               => 'Operations',
    'App\\Entity\\DeliveryOrder'                => 'Operations',
    'App\\Entity\\Instruction'                  => 'Operations',
    'App\\Entity\\InstructionContainer'         => 'Operations',
    'App\\Entity\\Shipment'                     => 'Operations',
    'App\\Entity\\ShipmentActivity'             => 'Operations',
    'App\\Entity\\ShipmentDocument'             => 'Operations',
    'App\\Entity\\ShipmentMilestone'            => 'Operations',
    'App\\Entity\\ShipmentMode'                 => 'Operations',
    'App\\Entity\\ShipmentNote'                 => 'Operations',
    'App\\Entity\\ShipmentParty'                => 'Operations',
    'App\\Entity\\ShipmentTask'                 => 'Operations',
    // --- Carrier ---
    'App\\Entity\\CargoClaim'                   => 'Carrier',
    'App\\Entity\\CarrierEventMapping'          => 'Carrier',
    'App\\Entity\\CarrierPerformanceScore'      => 'Carrier',
    'App\\Entity\\CarrierProfile'               => 'Carrier',
    'App\\Entity\\ContainerDdTracking'          => 'Carrier',
    'App\\Entity\\Provider'                     => 'Carrier',
    'App\\Entity\\TrackingEventRaw'             => 'Carrier',
    'App\\Entity\\TrackingRequest'              => 'Carrier',
    'App\\Entity\\VesselRoll'                   => 'Carrier',
    // --- CRM ---
    'App\\Entity\\AgentProfile'                 => 'Crm',
    'App\\Entity\\Client'                       => 'Crm',
    'App\\Entity\\Contact'                      => 'Crm',
    'App\\Entity\\Partner'                      => 'Crm',
    // --- Notification ---
    'App\\Entity\\InAppNotification'            => 'Notification',
    'App\\Entity\\Mail'                         => 'Notification',
    'App\\Entity\\NotificationQueue'            => 'Notification',
    'App\\Entity\\NotificationRule'             => 'Notification',
    'App\\Entity\\NotificationTemplate'         => 'Notification',
    // --- Reporting ---
    'App\\Entity\\Dataset'                      => 'Reporting',
    'App\\Entity\\DatasetFilter'                => 'Reporting',
    'App\\Entity\\DatasetProp'                  => 'Reporting',
    // --- Integration ---
    'App\\Entity\\PortalQuoteRequest'           => 'Integration',
    'App\\Entity\\PortalToken'                  => 'Integration',
    'App\\Entity\\PortalUser'                   => 'Integration',
];

// =========================================================
// ENUM MAP — 'App\\Misc\\Enum\\Foo' => 'Module'
// =========================================================
$enumMigrations = [
    // --- Core ---
    'App\\Misc\\Enum\\AddressType'              => 'Core',
    'App\\Misc\\Enum\\ComponentType'            => 'Core',
    'App\\Misc\\Enum\\Country'                  => 'Core',
    'App\\Misc\\Enum\\DateRange'                => 'Core',
    'App\\Misc\\Enum\\DateSegment'              => 'Core',
    'App\\Misc\\Enum\\EntityType'               => 'Core',
    'App\\Misc\\Enum\\MediaCategory'            => 'Core',
    'App\\Misc\\Enum\\PageType'                 => 'Core',
    'App\\Misc\\Enum\\Permission'               => 'Core',
    'App\\Misc\\Enum\\PortType'                 => 'Core',
    'App\\Misc\\Enum\\RequestMethod'            => 'Core',
    'App\\Misc\\Enum\\ServiceType'              => 'Core',
    'App\\Misc\\Enum\\TransportType'            => 'Core',
    'App\\Misc\\Enum\\UserStatus'               => 'Core',
    'App\\Misc\\Enum\\VisibleTo'                => 'Core',
    'App\\Misc\\Enum\\VolumeType'               => 'Core',
    'App\\Misc\\Enum\\WeekDay'                  => 'Core',
    'App\\Misc\\Enum\\Magnum'                   => 'Core',
    // --- Quote ---
    'App\\Misc\\Enum\\FreightTerm'              => 'Quote',
    'App\\Misc\\Enum\\QuoteStatus'              => 'Quote',
    // --- Finance ---
    'App\\Misc\\Enum\\ChargeType'               => 'Finance',
    'App\\Misc\\Enum\\CreditNoteReason'         => 'Finance',
    'App\\Misc\\Enum\\CreditStatus'             => 'Finance',
    'App\\Misc\\Enum\\EbitNoteStatus'           => 'Finance',
    'App\\Misc\\Enum\\EbitNoteType'             => 'Finance',
    'App\\Misc\\Enum\\LocalChargeType'          => 'Finance',
    'App\\Misc\\Enum\\PayableAt'                => 'Finance',
    'App\\Misc\\Enum\\PaymentMethodType'        => 'Finance',
    'App\\Misc\\Enum\\VarianceStatus'           => 'Finance',
    // --- Operations ---
    'App\\Misc\\Enum\\ConsolidationStatus'      => 'Operations',
    'App\\Misc\\Enum\\ContainerType'            => 'Operations',
    'App\\Misc\\Enum\\DocType'                  => 'Operations',
    'App\\Misc\\Enum\\NoteType'                 => 'Operations',
    'App\\Misc\\Enum\\NoteVisibility'           => 'Operations',
    'App\\Misc\\Enum\\PartyRole'                => 'Operations',
    'App\\Misc\\Enum\\ShipmentActivityType'     => 'Operations',
    'App\\Misc\\Enum\\ShipmentStatus'           => 'Operations',
    'App\\Misc\\Enum\\ShipmentType'             => 'Operations',
    'App\\Misc\\Enum\\SubStatus'                => 'Operations',
    'App\\Misc\\Enum\\TaskType'                 => 'Operations',
    // --- Carrier ---
    'App\\Misc\\Enum\\CarrierType'              => 'Carrier',
    'App\\Misc\\Enum\\ProviderType'             => 'Carrier',
    'App\\Misc\\Enum\\MilestoneCode'            => 'Carrier',
    // --- CRM ---
    'App\\Misc\\Enum\\ClientCustomInfoMode'     => 'Crm',
    'App\\Misc\\Enum\\ClientResidenceType'      => 'Crm',
    'App\\Misc\\Enum\\ClientTier'               => 'Crm',
    'App\\Misc\\Enum\\ClientType'               => 'Crm',
    // --- Notification ---
    'App\\Misc\\Enum\\MailStatus'               => 'Notification',
    // --- Reporting ---
    'App\\Misc\\Enum\\DatasetGroupColumn'       => 'Reporting',
    'App\\Misc\\Enum\\DatasetRowType'           => 'Reporting',
];

// =========================================================
// Build combined FQCN map: old => new
// =========================================================
$fqcnMap = [];

foreach ($entityMigrations as $oldFQCN => $module) {
    $parts     = explode('\\', $oldFQCN);
    $className = end($parts);
    $newFQCN   = "App\\Module\\{$module}\\Entity\\{$className}";
    $fqcnMap[$oldFQCN] = $newFQCN;
}

foreach ($enumMigrations as $oldFQCN => $module) {
    $parts     = explode('\\', $oldFQCN);
    $className = end($parts);
    $newFQCN   = "App\\Module\\{$module}\\Enum\\{$className}";
    $fqcnMap[$oldFQCN] = $newFQCN;
}

// =========================================================
// Phase 1: Move files + update namespace declarations
// =========================================================
echo "\n=== Phase 1: Moving files ===\n";
$moved   = 0;
$skipped = 0;

foreach ($fqcnMap as $oldFQCN => $newFQCN) {
    $parts     = explode('\\', $oldFQCN);
    $className = end($parts);

    // Derive old file path
    // App\Entity\Foo      => src/Entity/Foo.php
    // App\Misc\Enum\Foo   => src/Misc/Enum/Foo.php
    $relParts  = array_slice($parts, 1); // drop 'App'
    $oldPath   = $root . '/src/' . implode('/', $relParts) . '.php';

    $newParts  = explode('\\', $newFQCN);
    $newRelParts = array_slice($newParts, 1); // drop 'App'
    $newPath   = $root . '/src/' . implode('/', $newRelParts) . '.php';

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

    // Replace namespace declaration
    $content = file_get_contents($oldPath);
    $oldNSParts = $parts;
    array_pop($oldNSParts);
    $oldNS = implode('\\', $oldNSParts);

    $newNSParts = $newParts;
    array_pop($newNSParts);
    $newNS = implode('\\', $newNSParts);

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

echo "\nMoved: {$moved}  |  Skipped: {$skipped}\n";

// =========================================================
// Phase 2: Update all `use` statements across src/
// =========================================================
echo "\n=== Phase 2: Updating use statements ===\n";

$searches = [];
$replaces = [];
foreach ($fqcnMap as $old => $new) {
    $searches[] = 'use ' . $old . ';';
    $replaces[] = 'use ' . $new . ';';
    $searches[] = 'use ' . $old . ' as ';
    $replaces[] = 'use ' . $new . ' as ';
}

$rit     = new RecursiveIteratorIterator(
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
        echo '  UPDATED: ' . str_replace($root . DIRECTORY_SEPARATOR, '', $filePath) . "\n";
        $updated++;
    }
}
echo "\nFiles updated: {$updated}\n";

// =========================================================
// Phase 3: Update serializer group YAML files
// =========================================================
echo "\n=== Phase 3: Updating serializer group YAMLs ===\n";

$yamlDir = $root . '/config/serializer_groups';
$yamlUpdated = 0;
if (is_dir($yamlDir)) {
    foreach (scandir($yamlDir) as $f) {
        if (!str_ends_with($f, '.yaml')) {
            continue;
        }
        $path    = $yamlDir . '/' . $f;
        $content = file_get_contents($path);
        $new     = str_replace(
            array_keys($fqcnMap),
            array_values($fqcnMap),
            $content
        );
        if ($new !== $content) {
            file_put_contents($path, $new);
            echo "  UPDATED: config/serializer_groups/{$f}\n";
            $yamlUpdated++;
        }
    }
}
echo "\nYAML files updated: {$yamlUpdated}\n";

// =========================================================
// Phase 4: Remove empty old directories
// =========================================================
echo "\n=== Phase 4: Removing empty old directories ===\n";
$dirsToCheck = [
    $root . '/src/Entity',
    $root . '/src/Misc/Enum',
];
foreach ($dirsToCheck as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    // Remove recursively if only empty subdirs remain
    $files = array_diff(scandir($dir), ['.', '..']);
    if (empty($files)) {
        rmdir($dir);
        echo "  REMOVED: {$dir}\n";
    } else {
        echo "  KEPT (not empty): {$dir} (" . count($files) . " items)\n";
        foreach ($files as $item) {
            echo "    - {$item}\n";
        }
    }
}

echo "\n=== Migration complete ===\n";
echo "Next steps:\n";
echo "  1. Update config/packages/doctrine.yaml\n";
echo "  2. Update config/services.yaml exclude list\n";
echo "  3. Run: php bin/console cache:clear\n";
