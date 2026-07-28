<?php
namespace App\Module\Inventory\Service;

use App\Module\Core\Entity\User;
use App\Module\Inventory\Entity\StockLevel;
use App\Module\Inventory\Entity\StockMovement;
use App\Module\Inventory\Enum\MovementType;

class InventoryService
{
    public function apply(
        StockLevel  $sl,
        int         $delta,
        MovementType $type,
        ?string     $notes = null,
        ?User       $user  = null,
    ): StockMovement {
        $sl->setQuantity($sl->getQuantity() + $delta);

        $movement = new StockMovement();
        $movement->setStockLevel($sl);
        $movement->setType($type);
        $movement->setQuantityDelta($delta);
        $movement->setNotes($notes);
        $movement->setCreatedBy($user);

        return $movement;
    }
}
