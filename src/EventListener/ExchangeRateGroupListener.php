<?php

namespace App\EventListener;

use App\Module\Finance\Entity\ExchangeRateGroup;
use App\Module\Core\Service\ConfigService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, entity: ExchangeRateGroup::class)]
class ExchangeRateGroupListener
{
    public function __construct(private ConfigService $configService) {}

    public function postPersist(ExchangeRateGroup $entity): void
    {
        $config = $this->configService->getConfigValue('organization', isJson: true) ?? [];
        if (!empty($config['providerReady']) && empty($config['isInit'])) {
            $config['isInit'] = 1;
            $this->configService->setConfig('organization', json_encode($config));
        }
    }
}
