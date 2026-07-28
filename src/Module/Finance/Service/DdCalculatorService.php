<?php
namespace App\Module\Finance\Service;

use App\Module\Quote\Entity\Rate;

use App\Module\Carrier\Entity\ContainerDdTracking;

class DdCalculatorService
{
    /**
     * Calculate D&D charge from tiered rate table.
     * Rate tiers format: [{"from_day": 1, "to_day": 5, "rate_per_day": 50}, {"from_day": 6, "to_day": null, "rate_per_day": 100}]
     * to_day null means open-ended (applies to all remaining days).
     */
    public function calculateCharge(array $rateTiers, int $chargeableDays): float
    {
        if ($chargeableDays <= 0 || empty($rateTiers)) {
            return 0.0;
        }

        usort($rateTiers, fn($a, $b) => ($a['from_day'] ?? 1) <=> ($b['from_day'] ?? 1));

        $total    = 0.0;
        $daysLeft = $chargeableDays;

        foreach ($rateTiers as $tier) {
            if ($daysLeft <= 0) {
                break;
            }
            $from     = (int) ($tier['from_day'] ?? 1);
            $to       = isset($tier['to_day']) && $tier['to_day'] !== null ? (int) $tier['to_day'] : PHP_INT_MAX;
            $tierDays = min($daysLeft, $to - $from + 1);
            $total   += $tierDays * (float) ($tier['rate_per_day'] ?? 0);
            $daysLeft -= $tierDays;
        }

        return round($total, 4);
    }

    /**
     * Returns number of days beyond the free-end date as of $asOf.
     * Returns 0 if $asOf is on or before $freeEndDate.
     */
    public function computeChargeableDays(\DateTimeInterface $freeEndDate, \DateTimeInterface $asOf): int
    {
        $end = \DateTimeImmutable::createFromInterface($freeEndDate)->setTime(0, 0, 0);
        $as  = \DateTimeImmutable::createFromInterface($asOf)->setTime(0, 0, 0);

        if ($as <= $end) {
            return 0;
        }

        return (int) $end->diff($as)->days;
    }

    /**
     * Update accrual fields on a ContainerDdTracking record.
     * No-op if the record is already final or has no freeEndDate.
     */
    public function updateAccrual(ContainerDdTracking $record, \DateTimeInterface $asOf): void
    {
        if ($record->isFinal() || $record->getFreeEndDate() === null) {
            return;
        }

        $chargeable = $this->computeChargeableDays($record->getFreeEndDate(), $asOf);
        $amount     = $this->calculateCharge(
            $record->getFreeTimeAgreement()?->getRateTiers() ?? [],
            $chargeable
        );

        $record
            ->setChargeableDays($chargeable)
            ->setAccruedAmount((string) $amount)
            ->setLastAccrualDate($asOf);
    }

    /**
     * Finalise a record when the container has been returned.
     * Sets actualReturnDate, calculates final daysUsed and chargeableDays, marks isFinal=true.
     */
    public function finalise(ContainerDdTracking $record, \DateTimeInterface $returnDate): void
    {
        $record->setActualReturnDate($returnDate);

        if ($record->getFreeStartDate() !== null) {
            $start    = \DateTimeImmutable::createFromInterface($record->getFreeStartDate())->setTime(0, 0, 0);
            $returned = \DateTimeImmutable::createFromInterface($returnDate)->setTime(0, 0, 0);
            $daysUsed = (int) $start->diff($returned)->days;
            $record->setDaysUsed($daysUsed);
        }

        $this->updateAccrual($record, $returnDate);
        $record->setIsFinal(true);
    }
}
