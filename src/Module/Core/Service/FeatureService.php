<?php
namespace App\Module\Core\Service;

use App\Misc\Enum\Feature;
use App\Module\Core\Repository\ConfigRepository;

class FeatureService
{
    /** @var array<int> */
    private array $envEnabled;

    /** @var array<int>|null */
    private ?array $merged = null;

    public function __construct(
        ?string $featuresEnv,
        private readonly ConfigRepository $configRepository,
    ) {
        if ($featuresEnv === null || trim($featuresEnv) === '') {
            $this->envEnabled = [];
            return;
        }
        $this->envEnabled = array_map('intval', array_filter(
            array_map('trim', explode(',', $featuresEnv))
        ));
    }

    private function getEnabled(): array
    {
        if ($this->merged !== null) {
            return $this->merged;
        }
        $config = $this->configRepository->findOneBy(['name' => 'features', 'type' => 'system']);
        $configIds = [];
        if ($config) {
            $decoded = json_decode($config->getValue() ?? '', true);
            if (is_array($decoded)) {
                $configIds = array_map('intval', $decoded);
            }
        }
        $this->merged = array_values(array_unique(array_merge($this->envEnabled, $configIds)));
        return $this->merged;
    }

    public function isEnabled(Feature $feature): bool
    {
        return in_array($feature->value, $this->getEnabled(), true);
    }

    /** @return int[] */
    public function enabledIds(): array
    {
        return $this->getEnabled();
    }
}
