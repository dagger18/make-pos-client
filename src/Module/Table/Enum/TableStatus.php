<?php
namespace App\Module\Table\Enum;

enum TableStatus: string
{
    case Available = 'available';
    case Occupied  = 'occupied';
    case Reserved  = 'reserved';
    case Cleaning  = 'cleaning';
}
