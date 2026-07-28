<?php
namespace App\Module\Finance\Enum;
enum ChargeType: string {
    case LOCAL = 'LC';
    case SERVICE = 'SV';
    case CUSTOMS = 'CT';
    case FREIGHT = 'FR';
}