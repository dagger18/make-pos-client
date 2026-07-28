<?php
declare(strict_types=1);
$root = dirname(__DIR__) . '/src/Module';
$rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$fixed = 0;
foreach ($rit as $fi) {
    if ($fi->getExtension() !== 'php') {
        continue;
    }
    $lines   = file($fi->getPathname());
    $seen    = [];
    $out     = [];
    $changed = false;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, 'use ') && str_ends_with($trimmed, ';')) {
            if (isset($seen[$trimmed])) {
                $changed = true;
                continue; // drop duplicate
            }
            $seen[$trimmed] = true;
        }
        $out[] = $line;
    }
    if ($changed) {
        file_put_contents($fi->getPathname(), implode('', $out));
        echo 'DEDUP: ' . $fi->getPathname() . "\n";
        $fixed++;
    }
}
echo "Fixed $fixed files\n";
