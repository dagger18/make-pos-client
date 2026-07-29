<?php
namespace App\Module\Shift\Service;

use App\Module\Shift\Entity\Shift;
use App\Module\Shift\Enum\ShiftStatus;

class ShiftService
{
    public function close(Shift $shift, string $closingAmount, ?string $notes): void
    {
        $shift->setStatus(ShiftStatus::Closed);
        $shift->setClosedAt(new \DateTime());
        $shift->setClosingAmount($closingAmount);
        $shift->setNotes($notes);
    }
}
