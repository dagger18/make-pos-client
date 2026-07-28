<?php
namespace App\Module\Carrier\Enum;

enum CarrierType: string
{
    case Ocean   = 'OCEAN';
    case Air     = 'AIR';
    case Road    = 'ROAD';
    case Rail    = 'RAIL';
    case Courier = 'COURIER';
    case Nvocc   = 'NVOCC';
}
