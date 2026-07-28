<?php
namespace App\Module\Carrier\Service;

class CarrierPerformanceScoreService
{
    private const WEIGHTS = [
        'onTimeDep'       => 0.25,
        'onTimeArr'       => 0.25,
        'bookingAccept'   => 0.20,
        'scheduleRel'     => 0.15,
        'rateConsistency' => 0.10,
        'claims'          => 0.05,
    ];

    /**
     * All pct arguments are 0–100 (percentage). claimsPer100 is claims per 100 shipments.
     * Any argument that is null is excluded from the composite and its weight is redistributed.
     * Returns null if there is no data at all.
     */
    public function computeComposite(
        ?float $onTimeDepPct,
        ?float $onTimeArrPct,
        ?float $bookingAcceptancePct,
        ?float $scheduleReliabilityPct,
        ?float $rateConsistencyPct,
        ?float $claimsPer100,
    ): ?float {
        // Convert claims to a 0-100 score: 0 claims per 100 = 100, 5+ claims per 100 = 0
        $claimsScore = $claimsPer100 !== null ? max(0.0, 100.0 - ($claimsPer100 * 20.0)) : null;

        $scores = [
            'onTimeDep'       => $onTimeDepPct,
            'onTimeArr'       => $onTimeArrPct,
            'bookingAccept'   => $bookingAcceptancePct,
            'scheduleRel'     => $scheduleReliabilityPct,
            'rateConsistency' => $rateConsistencyPct,
            'claims'          => $claimsScore,
        ];

        $totalWeight  = 0.0;
        $weightedSum  = 0.0;
        foreach ($scores as $key => $score) {
            if ($score !== null) {
                $totalWeight += self::WEIGHTS[$key];
                $weightedSum += $score * self::WEIGHTS[$key];
            }
        }

        if ($totalWeight < 0.001) {
            return null;
        }

        // Normalize weighted sum over available weight, then scale 0-100 → 0-5
        $normalizedScore = $weightedSum / $totalWeight;
        return round($normalizedScore / 20.0, 2);
    }

    public function scoreBand(float $composite): string
    {
        return match(true) {
            $composite >= 4.5 => 'A',
            $composite >= 3.5 => 'B',
            $composite >= 2.5 => 'C',
            $composite >= 1.5 => 'D',
            default           => 'F',
        };
    }
}
