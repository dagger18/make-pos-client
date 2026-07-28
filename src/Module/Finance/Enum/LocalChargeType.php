<?php
namespace App\Module\Finance\Enum;
enum LocalChargeType: string {
    case Origin = 'O';
    case Destination = 'D';
}