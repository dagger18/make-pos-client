<?php
namespace App\Module\Core\Enum;

enum AddressType: string
{
    case Registered = 'REGISTERED';
    case Billing    = 'BILLING';
    case Warehouse  = 'WAREHOUSE';
    case Pickup     = 'PICKUP';
    case Delivery   = 'DELIVERY';
}
