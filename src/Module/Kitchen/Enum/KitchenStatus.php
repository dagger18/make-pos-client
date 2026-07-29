<?php
namespace App\Module\Kitchen\Enum;

enum KitchenStatus: string
{
    case Pending    = 'pending';
    case InProgress = 'in_progress';
    case Ready      = 'ready';
}
