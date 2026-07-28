<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\BaseService;

use NumberFormatter;
use App\Module\Core\Entity\Money;
use App\Module\Finance\Repository\ExchangeRateGroupRepository;

class ExchangeRateGroupService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ExchangeRateGroupRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function getCurrentExchangeRateAsMap(): array
    {
        $currentGroup = $this->repository->getCurrentGroup();
        $map = [];
        foreach ($currentGroup->getExchangeRates() as $exchangeRate) {
            $map[$exchangeRate->getFromCurrency()->getCode()] = $exchangeRate->getFromRate();
            $map[$exchangeRate->getToCurrency()->getCode()] = $exchangeRate->getToRate();
        }
        return $map;
    }

    public function convertMoney(Money $money, string $toCurrency, array $exchangeRates, ?float $rate = null): ?float
    {
        if (is_null($rate)) {
            $rate = $this->getRateOfCurrency($toCurrency, $exchangeRates);
        }
        return $this->commonService->convertMoney($money, $toCurrency, $rate);
    }

    public function formatMoney(Money $money, string $toCurrency, array $exchangeRates): string
    {
        $amount = $this->convertMoney($money, $toCurrency, $exchangeRates);
        if ($amount === null) {
            return '';
        }
        $fmt = new NumberFormatter('en_US', NumberFormatter::CURRENCY);
        return preg_replace('/[^0-9,\.-]/', '', $fmt->formatCurrency($amount, $toCurrency));
    }

    public function getRateOfCurrency(string $currency, array $exchangeRates): float
    {
        if (empty($currency)) return 0;
        foreach ($exchangeRates as $curr => $rate) {
            if ($curr === $currency) {
                return $rate;
            }
        }
        if ($currency === 'USD') {
            return 1;
        }
        $map = $this->getCurrentExchangeRateAsMap();
        if (isset($map[$currency])) {
            return $map[$currency];
        }
        throw new \RuntimeException('Rate not found for ' . $currency);
    }
}
