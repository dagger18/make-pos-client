<?php
namespace App\Tests\Module\Loyalty;

use App\Module\Loyalty\Entity\LoyaltyCustomer;
use App\Module\Loyalty\Enum\TransactionType;
use App\Module\Loyalty\Service\LoyaltyService;
use PHPUnit\Framework\TestCase;

class LoyaltyServiceTest extends TestCase
{
    private LoyaltyService $svc;

    protected function setUp(): void
    {
        $this->svc = new LoyaltyService();
    }

    public function testEarnAddsPointsToCustomer(): void
    {
        $customer = new LoyaltyCustomer();
        $customer->setPoints(100);

        $this->svc->earn($customer, 50, null);

        $this->assertSame(150, $customer->getPoints());
    }

    public function testEarnReturnsTransactionWithPositivePoints(): void
    {
        $customer = new LoyaltyCustomer();

        $tx = $this->svc->earn($customer, 30, 'order-42');

        $this->assertSame(30, $tx->getPoints());
        $this->assertSame(TransactionType::Earn, $tx->getType());
        $this->assertSame('order-42', $tx->getReference());
    }

    public function testRedeemDeductsPointsFromCustomer(): void
    {
        $customer = new LoyaltyCustomer();
        $customer->setPoints(100);

        $this->svc->redeem($customer, 40, null);

        $this->assertSame(60, $customer->getPoints());
    }

    public function testRedeemReturnsTransactionWithNegativePoints(): void
    {
        $customer = new LoyaltyCustomer();
        $customer->setPoints(100);

        $tx = $this->svc->redeem($customer, 40, 'ref-1');

        $this->assertSame(-40, $tx->getPoints());
        $this->assertSame(TransactionType::Redeem, $tx->getType());
        $this->assertSame('ref-1', $tx->getReference());
    }

    public function testRedeemThrowsWhenInsufficientPoints(): void
    {
        $customer = new LoyaltyCustomer();
        $customer->setPoints(20);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient loyalty points.');

        $this->svc->redeem($customer, 50, null);
    }
}
