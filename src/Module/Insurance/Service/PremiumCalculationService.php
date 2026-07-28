<?php
namespace App\Module\Insurance\Service;

use App\Module\Insurance\Entity\InsurancePolicy;

class PremiumCalculationService
{
    private const INSURED_UPLIFT = 1.10;

    public function calculate(float $cargoValue, InsurancePolicy $policy): array
    {
        $insuredAmount = round($cargoValue * self::INSURED_UPLIFT, 6);

        $premium = match ($policy->getPremiumBasis()) {
            'PCT_VALUE' => $insuredAmount * $policy->getPremiumRate(),
            'FLAT_RATE' => $policy->getPremiumRate(),
            default     => 0.0,
        };

        if ($policy->getMinPremium() !== null) {
            $premium = max($premium, $policy->getMinPremium());
        }

        return [
            'cargoValue'    => $cargoValue,
            'insuredAmount' => $insuredAmount,
            'premiumRate'   => $policy->getPremiumRate(),
            'premiumAmount' => round($premium, 2),
            'currency'      => $policy->getCurrency(),
        ];
    }
}
