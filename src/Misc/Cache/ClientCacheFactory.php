<?php

namespace App\Misc\Cache;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ClientCacheFactory
{
    public function __construct(
        private readonly string $cacheDir,
        private readonly string $databaseUrl,
    ) {}

    public function createPool(): TagAwareCacheInterface
    {
        return new TagAwareAdapter(
            new FilesystemAdapter('', 0, $this->cacheDir . '/client_data/' . $this->resolveFolder())
        );
    }

    private function resolveFolder(): string
    {
        if (str_starts_with($this->databaseUrl, 'sqlite:')) {
            $path = parse_url($this->databaseUrl, PHP_URL_PATH) ?? '';
            $name = pathinfo($path, PATHINFO_FILENAME);
            return $name ?: 'default';
        }
        return 'default';
    }
}
