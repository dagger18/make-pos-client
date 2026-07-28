<?php

namespace App\Module\Core\Service;

use App\Misc\Enum\CapacityType;
use App\Misc\Exception\CapacityExceededException;
use App\Module\Core\Repository\UserRepository;

class CapacityService
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly UserRepository $userRepository,
    ) {}

    public function assertAllowed(CapacityType $type, int $amount = 1): void
    {
        $rule = $this->getRule($type);
        if ($rule === null || !($rule['enabled'] ?? true)) {
            return;
        }

        $current = $this->getCurrent($type);
        $limit = $rule['limit'] ?? null;
        if ($limit !== null && ($current + $amount) > $limit) {
            throw new CapacityExceededException($type, $current, $limit);
        }
    }

    private function getRule(CapacityType $type): ?array
    {
        $plan = $this->configService->getConfigValue('plan', isJson: true) ?? [];
        return $plan[$type->value] ?? null;
    }

    private function getCurrent(CapacityType $type): int
    {
        return match ($type) {
            CapacityType::MaxUsers    => count($this->userRepository->getAll([], 'ENTITY')),
            CapacityType::MaxOrders   => 0, // TODO: inject OrderRepository when Sales module is built
            CapacityType::MaxProducts => 0, // TODO: inject ProductRepository when Catalog module is built
            default                   => 0,
        };
    }
}
