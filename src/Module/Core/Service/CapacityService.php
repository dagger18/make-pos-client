<?php

namespace App\Module\Core\Service;

use App\Misc\Enum\CapacityPeriod;
use App\Misc\Enum\CapacityType;
use App\Misc\Exception\CapacityExceededException;
use App\Module\Core\Repository\UserRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Quote\Repository\QuoteRepository;

class CapacityService
{
    public function __construct(
        private readonly ConfigService $configService,
        private readonly UserRepository $userRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly QuoteRepository $quoteRepository,
    ) {}

    /**
     * Throws CapacityExceededException if adding $amount would exceed the plan limit.
     * For count-based types (MaxUsers, MaxShipments, MaxQuotes) use $amount = 1.
     * For bandwidth, pass the size in MB.
     */
    public function assertAllowed(CapacityType $type, int $amount = 1): void
    {
        $rule = $this->getRule($type);
        if ($rule === null || !($rule['enabled'] ?? true)) {
            return;
        }

        $limit = isset($rule['limit']) ? (int) $rule['limit'] : null;
        if ($limit === null) {
            return;
        }

        $current = $this->getCurrentCount($type, $rule);
        if ($current + $amount > $limit) {
            throw new CapacityExceededException($type, $limit, $current);
        }
    }

    public function isAllowed(CapacityType $type, int $amount = 1): bool
    {
        try {
            $this->assertAllowed($type, $amount);
            return true;
        } catch (CapacityExceededException) {
            return false;
        }
    }

    /**
     * Records usage for per_day capacity types (does nothing for repository-backed totals).
     * For bandwidth, pass the size in MB; for all others pass 1 (or omit).
     */
    public function recordUsage(CapacityType $type, int $amount = 1): void
    {
        $rule = $this->getRule($type);
        if ($rule === null || !($rule['enabled'] ?? true)) {
            return;
        }

        $period = CapacityPeriod::tryFrom($rule['period'] ?? '') ?? CapacityPeriod::Total;
        if ($period !== CapacityPeriod::PerDay) {
            return; // Total types are backed by the DB — no separate recording needed
        }

        $key     = $this->getDailyKey($type);
        $current = (int) ($this->configService->getConfigValue($key) ?? '0');
        $this->configService->setConfig($key, (string) ($current + $amount));
    }

    /**
     * Returns all enabled capacity rules with their live current usage.
     * Rules with no limit or limit=0 are excluded.
     *
     * @return array<int, array{type: string, label: string, limit: int, current: int, period: string}>
     */
    public function getCapacityUsage(): array
    {
        $config = $this->configService->getConfigValue('capacity', true);
        if (!is_array($config)) {
            return [];
        }

        $result = [];
        foreach ($config as $rule) {
            if (!($rule['enabled'] ?? true)) {
                continue;
            }
            $type = CapacityType::tryFrom($rule['type'] ?? '');
            if ($type === null) {
                continue;
            }
            $limit = isset($rule['limit']) ? (int) $rule['limit'] : null;
            if ($limit === null || $limit === 0) {
                continue;
            }
            $result[] = [
                'type'         => $type->value,
                'label'        => preg_replace('/(?<!^)([A-Z])/', ' $1', $type->name),
                'limit'        => $limit,
                'current'      => $this->getCurrentCount($type, $rule),
                'period'       => $rule['period'] ?? CapacityPeriod::Total->value,
                'displayLimit' => isset($rule['displayLimit']) ? (float) $rule['displayLimit'] : null,
                'unit'         => $rule['unit'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * Returns the capacity rule for $type from the stored config, or null if unconfigured.
     * Rule shape: { type, limit, period, enabled, unit?, displayLimit? }
     */
    private function getRule(CapacityType $type): ?array
    {
        $config = $this->configService->getConfigValue('capacity', true);
        if (!is_array($config)) {
            return null;
        }
        foreach ($config as $rule) {
            if (($rule['type'] ?? null) === $type->value) {
                return $rule;
            }
        }
        return null;
    }

    private function getCurrentCount(CapacityType $type, array $rule): int
    {
        $period = CapacityPeriod::tryFrom($rule['period'] ?? '') ?? CapacityPeriod::Total;

        if ($period === CapacityPeriod::PerDay) {
            return (int) ($this->configService->getConfigValue($this->getDailyKey($type)) ?? '0');
        }

        return match ($type) {
            CapacityType::MaxUsers     => $this->userRepository->countActive(),
            CapacityType::MaxShipments => (int) $this->shipmentRepository->count([]),
            CapacityType::MaxQuotes    => (int) $this->quoteRepository->count([]),
            default                    => 0,
        };
    }

    /** Config key for today's per_day usage counter. */
    private function getDailyKey(CapacityType $type): string
    {
        return 'cap_' . $type->value . '_' . (new \DateTimeImmutable())->format('Y-m-d');
    }
}
