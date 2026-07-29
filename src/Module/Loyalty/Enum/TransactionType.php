<?php
namespace App\Module\Loyalty\Enum;

enum TransactionType: string
{
    case Earn       = 'earn';
    case Redeem     = 'redeem';
    case Adjustment = 'adjustment';
}
