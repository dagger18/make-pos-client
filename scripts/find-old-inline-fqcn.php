<?php
declare(strict_types=1);
$root = dirname(__DIR__) . '/src';
$rit  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rit as $fi) {
    if ($fi->getExtension() !== 'php') {
        continue;
    }
    $lines = file($fi->getPathname());
    foreach ($lines as $n => $line) {
        if (str_contains($line, 'use ')) {
            continue;
        }
        // Look for \App\Service\Foo, \App\Repository\Foo, \App\Controller\Foo patterns
        if (preg_match('/\\\\App\\\\(Service|Repository|Controller)\\\\/', $line)) {
            echo ($n + 1) . ': ' . trim($line) . '  [' . $fi->getPathname() . "]\n";
        }
    }
}
