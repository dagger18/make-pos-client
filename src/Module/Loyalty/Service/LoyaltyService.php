<?php
namespace App\Module\Loyalty\Service;

use App\Module\Loyalty\Entity\LoyaltyCustomer;
use App\Module\Loyalty\Entity\LoyaltyTransaction;
use App\Module\Loyalty\Enum\TransactionType;

class LoyaltyService
{
    public function earn(LoyaltyCustomer $customer, int $points, ?string $reference): LoyaltyTransaction
    {
        $customer->setPoints($customer->getPoints() + $points);

        $tx = new LoyaltyTransaction();
        $tx->setCustomer($customer);
        $tx->setPoints($points);
        $tx->setType(TransactionType::Earn);
        $tx->setReference($reference);

        return $tx;
    }

    public function redeem(LoyaltyCustomer $customer, int $points, ?string $reference): LoyaltyTransaction
    {
        if ($customer->getPoints() < $points) {
            throw new \InvalidArgumentException('Insufficient loyalty points.');
        }

        $customer->setPoints($customer->getPoints() - $points);

        $tx = new LoyaltyTransaction();
        $tx->setCustomer($customer);
        $tx->setPoints(-$points);
        $tx->setType(TransactionType::Redeem);
        $tx->setReference($reference);

        return $tx;
    }
}
