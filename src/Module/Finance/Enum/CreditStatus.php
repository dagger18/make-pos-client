<?php
namespace App\Module\Finance\Enum;

enum CreditStatus: string
{
    case Active      = 'ACTIVE';
    case OnHold      = 'ON_HOLD';
    case Blocked     = 'BLOCKED';
    case Blacklisted = 'BLACKLISTED';
}
