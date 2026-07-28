<?php
namespace App\Module\Sales\Enum;

enum OrderStatus: string
{
    case Open      = 'open';
    case Paid      = 'paid';
    case Cancelled = 'cancelled';
}
