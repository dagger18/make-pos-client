<?php
namespace App\Module\Kitchen\Service;

use App\Module\Kitchen\Entity\KitchenTicket;
use App\Module\Kitchen\Enum\KitchenStatus;

class KitchenService
{
    public function advance(KitchenTicket $ticket): void
    {
        $ticket->setStatus(match ($ticket->getStatus()) {
            KitchenStatus::Pending    => KitchenStatus::InProgress,
            KitchenStatus::InProgress => KitchenStatus::Ready,
            KitchenStatus::Ready      => KitchenStatus::Ready,
        });
    }
}
