<?php
namespace App\Tests\Module\Sales;

use App\Module\Sales\Entity\Order;
use App\Module\Sales\Entity\OrderItem;
use App\Module\Sales\Entity\OrderPayment;
use App\Module\Sales\Service\SalesService;
use PHPUnit\Framework\TestCase;

class SalesServiceTest extends TestCase
{
    private SalesService $svc;

    protected function setUp(): void
    {
        $this->svc = new SalesService();
    }

    public function testCalculateTotalsWithOneItem(): void
    {
        $order = new Order();
        $item  = new OrderItem();
        $item->setItemTotal('20.000000');
        $order->addItem($item);

        $this->svc->calculateTotals($order);

        $this->assertEqualsWithDelta(20.0, (float) $order->getSubtotal(), 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $order->getTotal(), 0.001);
    }

    public function testCalculateTotalsAppliesDiscount(): void
    {
        $order = new Order();
        $order->setDiscountAmount('5.000000');
        $item  = new OrderItem();
        $item->setItemTotal('20.000000');
        $order->addItem($item);

        $this->svc->calculateTotals($order);

        $this->assertEqualsWithDelta(20.0, (float) $order->getSubtotal(), 0.001);
        $this->assertEqualsWithDelta(15.0, (float) $order->getTotal(), 0.001);
    }

    public function testCalculatePaidAmountSumsPayments(): void
    {
        $order = new Order();
        $p1 = new OrderPayment(); $p1->setAmount('10.000000'); $order->addPayment($p1);
        $p2 = new OrderPayment(); $p2->setAmount('15.000000'); $order->addPayment($p2);

        $this->svc->calculatePaidAmount($order);

        $this->assertEqualsWithDelta(25.0, (float) $order->getPaidAmount(), 0.001);
    }

    public function testIsFullyPaidReturnsTrueWhenExact(): void
    {
        $order = new Order();
        $order->setTotal('50.000000');
        $order->setPaidAmount('50.000000');

        $this->assertTrue($this->svc->isFullyPaid($order));
    }

    public function testIsFullyPaidReturnsFalseWhenUnderpaid(): void
    {
        $order = new Order();
        $order->setTotal('50.000000');
        $order->setPaidAmount('30.000000');

        $this->assertFalse($this->svc->isFullyPaid($order));
    }
}
