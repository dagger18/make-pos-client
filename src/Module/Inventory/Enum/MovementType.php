<?php
namespace App\Module\Inventory\Enum;

enum MovementType: string
{
    case Receive    = 'receive';
    case Adjustment = 'adjustment';
    case Return     = 'return';
    case WriteOff   = 'write_off';
}
