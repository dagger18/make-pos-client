<?php

declare(strict_types=1);

namespace App\Misc\Doctrine;

use function strcmp;
use Doctrine\Migrations\Version\Version;
use Doctrine\Migrations\Version\Comparator;

final class AlphabeticalComparator implements Comparator
{
    public function compare(Version $a, Version $b): int
    {
        return strcmp($this->stripNamespace($a), $this->stripNamespace($b));
    }

    private function stripNamespace(Version $version): string
    {
        $path = explode('\\', (string) $version);
        return end($path);
    }
}