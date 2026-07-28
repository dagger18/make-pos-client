<?php
namespace App\Module\Core\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Entity\Config;
use App\Module\Core\Repository\ConfigRepository;

class ConfigService extends BaseService
{

    public function __construct(
        protected BaseService $baseService,
        public ConfigRepository $repository
    ) {
        $this->reflectFromParent($baseService);
    }
    public function getConfigValue(string $name, bool $isJson = false): string|array|null
    {
        $config = $this->repository->findOneBy(['name' => $name]);
        if (!$config) {
            return null;
        }
        $value = $config->getValue();
        return $isJson ? json_decode($value, true) : $value;
    }
    public function isConfigExists(string $name): bool
    {
        $config = $this->repository->findOneBy(['name' => $name]);
        return !!$config;
    }
    public function incrementBandwidth(int $bytes): void
    {
        $config = $this->repository->findOneBy(['name' => 'totalBandwidth']);
        if (!$config) {
            $config = new Config();
            $config->setName('totalBandwidth');
            $config->setType('system');
            $config->setValue((string) $bytes);
        } else {
            $config->setValue((string) ((int) $config->getValue() + $bytes));
        }
        $this->repository->save($config);
    }

    public function setConfig(string $name, string $value, ?string $type = 'system'): ?Config
    {
        /** @var Config $config */
        $config = $this->repository->findOneBy(['name' => $name]);
        if(is_null($config)){
            $config = new Config();
            $config->setName($name);
            $config->setType($type);
        }
        $config->setValue($value);
        $this->repository->save($config);
        return $config;
    }
}
