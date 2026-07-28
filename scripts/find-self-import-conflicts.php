<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$rit  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($rit as $fi) {
    if ($fi->getExtension() !== 'php') {
        continue;
    }
    $c = file_get_contents($fi->getPathname());
    if (!preg_match('/^namespace\s+([^;]+);/m', $c, $nsM)) {
        continue;
    }
    $ns = trim($nsM[1]);
    preg_match_all('/^use\s+([^;]+);/m', $c, $useM);
    foreach ($useM[1] as $fqcn) {
        $fqcn  = trim($fqcn);
        $parts = explode('\\', $fqcn);
        $shortName  = end($parts);
        $importedNS = implode('\\', array_slice($parts, 0, -1));
        if ($importedNS === $ns) {
            continue;
        }
        $pattern = '/^(?:class|interface|trait|enum)\s+' . preg_quote($shortName, '/') . '\b/m';
        if (preg_match($pattern, $c)) {
            echo str_replace($root . '/', '', $fi->getPathname()) . ': imports ' . $fqcn . ' but defines ' . $shortName . "\n";
        }
    }
}
