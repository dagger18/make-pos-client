<?php
namespace App\Module\Crm\Enum;

enum ClientTier: string
{
    case Platinum = 'PLATINUM';
    case Gold     = 'GOLD';
    case Silver   = 'SILVER';
    case Standard = 'STANDARD';
}
