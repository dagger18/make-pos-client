<?php
namespace App\Module\Operations\Service;

use App\Module\Core\Service\ConfigService;

use App\Module\Operations\Entity\Shipment;

class ShipmentIdGeneratorService
{
    const DEFAULT_FORMAT = '$TransType_$OrgCode_$DestCode_$GlobalCounter';
    const CONFIG_FORMAT_KEY = 'shipmentIdFormat';
    const CONFIG_GLOBAL_COUNTER_KEY = 'shipmentIdGlobalCounter';
    const CONFIG_MONTHLY_COUNTER_KEY = 'shipmentIdMonthlyCounter';

    public function __construct(
        protected ConfigService $configService,
    ) {}

    public function generate(Shipment $shipment): string
    {
        $format = $this->configService->getConfigValue(self::CONFIG_FORMAT_KEY)
                  ?? self::DEFAULT_FORMAT;

        $globalCounter  = $this->incrementGlobalCounter();
        $monthlyCounter = $this->incrementMonthlyCounter();

        $quote      = $shipment->getQuote();
        $originCode = $quote->getOriginPort()?->getCode() ?? '';
        $destCode   = $quote->getDestinationPort()?->getCode() ?? '';
        $transType  = $quote->getTransportType()?->value ?? '';
        $serviceType = $quote->getServiceType()?->shortCode() ?? '';
        $shipType   = $quote->getShipmentType()?->value ?? '';
        $yearMonth  = (new \DateTime())->format('Ym');
        $branchCode = $shipment->getCreatedBy()?->getBranch()?->getCode() ?? '';

        return str_replace(
            ['$GlobalCounter', '$MonthlyCounter', '$Branch', '$ShipType', '$TransType', '$ServiceType', '$YearMonth', '$OrgCode', '$DestCode'],
            [
                str_pad((string) $globalCounter,  8, '0', STR_PAD_LEFT),
                str_pad((string) $monthlyCounter, 5, '0', STR_PAD_LEFT),
                substr($branchCode, 0, 3),
                $shipType,
                $transType,
                $serviceType,
                $yearMonth,
                $originCode,
                $destCode,
            ],
            $format
        );
    }

    private function incrementGlobalCounter(): int
    {
        $current = (int) ($this->configService->getConfigValue(self::CONFIG_GLOBAL_COUNTER_KEY) ?? 0);
        $next = $current + 1;
        $this->configService->setConfig(self::CONFIG_GLOBAL_COUNTER_KEY, (string) $next, 'system');
        return $next;
    }

    private function incrementMonthlyCounter(): int
    {
        $currentYearMonth = (new \DateTime())->format('Ym');
        $stored = $this->configService->getConfigValue(self::CONFIG_MONTHLY_COUNTER_KEY);

        if ($stored && str_contains($stored, ':')) {
            [$storedYearMonth, $storedCount] = explode(':', $stored, 2);
            $next = ($storedYearMonth === $currentYearMonth) ? (int) $storedCount + 1 : 1;
        } else {
            $next = 1;
        }

        $this->configService->setConfig(
            self::CONFIG_MONTHLY_COUNTER_KEY,
            $currentYearMonth . ':' . $next,
            'system'
        );
        return $next;
    }
}
