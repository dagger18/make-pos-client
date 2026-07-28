<?php
namespace App\Module\Finance\Service;

use App\Module\Crm\Entity\Client;
use App\Module\Finance\Entity\CreditLimitHistory;
use App\Module\Core\Entity\User;
use App\Module\Finance\Enum\CreditStatus;
use App\Module\Finance\Repository\AgeingRepository;
use App\Module\Finance\Repository\CreditLimitHistoryRepository;

class CreditCheckService
{
    public function __construct(
        private readonly AgeingRepository            $ageingRepository,
        private readonly CreditLimitHistoryRepository $historyRepository,
    ) {}

    /**
     * Returns:
     * [
     *   decision:    PASS | WARN | REQUIRE_APPROVAL | HARD_BLOCK,
     *   reason:      string,
     *   exposure:    float|null,
     *   limit:       float|null,
     *   currency:    string|null,
     *   utilisation: float|null,
     *   available:   float|null,
     * ]
     */
    public function check(Client $client): array
    {
        if (in_array($client->getCreditStatus(), [CreditStatus::Blocked, CreditStatus::Blacklisted])) {
            return [
                'decision'    => 'HARD_BLOCK',
                'reason'      => 'Client credit status is ' . $client->getCreditStatus()->value,
                'exposure'    => null,
                'limit'       => null,
                'currency'    => null,
                'utilisation' => null,
                'available'   => null,
            ];
        }

        $limitMoney  = $client->getCreditLimit();
        $limitAmount = $limitMoney?->getAmount();
        $currency    = $limitMoney?->getCurrency();

        if ($limitAmount === null || $limitAmount <= 0 || $currency === null) {
            return [
                'decision'    => 'PASS',
                'reason'      => 'No credit limit configured',
                'exposure'    => null,
                'limit'       => null,
                'currency'    => null,
                'utilisation' => null,
                'available'   => null,
            ];
        }

        $exposure    = $this->ageingRepository->getClientExposure($client->getId(), $currency);
        $utilisation = ($exposure / $limitAmount) * 100;
        $available   = $limitAmount - $exposure;

        $decision = match(true) {
            $utilisation > 100 => 'REQUIRE_APPROVAL',
            $utilisation >= 80  => 'WARN',
            default             => 'PASS',
        };

        return [
            'decision'    => $decision,
            'reason'      => match($decision) {
                'REQUIRE_APPROVAL' => 'Outstanding exposure exceeds credit limit',
                'WARN'             => 'Outstanding exposure is above 80% of credit limit',
                default            => 'Within credit limit',
            },
            'exposure'    => $exposure,
            'limit'       => $limitAmount,
            'currency'    => $currency,
            'utilisation' => round($utilisation, 2),
            'available'   => $available,
        ];
    }

    public function recordHistory(
        Client        $client,
        ?User         $changedBy,
        string        $changeType,
        ?CreditStatus $oldStatus,
        ?CreditStatus $newStatus,
        ?float        $oldLimitAmount = null,
        ?float        $newLimitAmount = null,
        ?string       $currency = null,
        ?string       $reason = null,
    ): CreditLimitHistory {
        $history = new CreditLimitHistory();
        $history->setClient($client)
            ->setChangedBy($changedBy)
            ->setChangeType($changeType)
            ->setOldStatus($oldStatus)
            ->setNewStatus($newStatus)
            ->setOldLimitAmount($oldLimitAmount)
            ->setNewLimitAmount($newLimitAmount)
            ->setCurrency($currency)
            ->setReason($reason);
        return $this->historyRepository->save($history);
    }
}
