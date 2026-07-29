<?php
namespace App\Module\Shift\Enum;

enum ShiftStatus: string
{
    case Open   = 'open';
    case Closed = 'closed';
}
