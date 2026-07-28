<?php
namespace App\Module\Operations\Enum;
enum ShipmentActivityType: string {
    case Create = 'CE';
    case Replicate = 'RE';
    case Update = 'UE';
    case UpdateStatus = 'US';
    case Delete = 'DE';
    case SubUpdate = 'SU';
    case SubCreate = 'SC';
    case SubDelete = 'SD';
    case UpdateSubStatus = 'USS';
    case UpdateHold = 'UH';
}