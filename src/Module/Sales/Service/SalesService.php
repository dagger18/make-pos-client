<?php
namespace App\Module\Sales\Service;

use App\Module\Sales\Entity\Order;

class SalesService
{
    public function calculateTotals(Order $order): void
    {
        $subtotal = 0.0;
        foreach ($order->getItems() as $item) {
            $subtotal += (float) $item->getItemTotal();
        }
        $order->setSubtotal(round($subtotal, 6));
        $total = $subtotal - (float) $order->getDiscountAmount() + (float) $order->getTaxAmount();
        $order->setTotal(round(max(0.0, $total), 6));
    }

    public function calculatePaidAmount(Order $order): void
    {
        $paid = 0.0;
        foreach ($order->getPayments() as $payment) {
            $paid += (float) $payment->getAmount();
        }
        $order->setPaidAmount(round($paid, 6));
    }

    public function isFullyPaid(Order $order): bool
    {
        return (float) $order->getPaidAmount() >= (float) $order->getTotal() - 0.001;
    }
}
