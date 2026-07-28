<?php

namespace App\Module\Operations\Enum;

enum ConsolidationStatus: string
{
    case Open      = 'OPEN';
    case Closed    = 'CLOSED';
    case Departed  = 'DEPARTED';
    case Arrived   = 'ARRIVED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match($this) {
            self::Open      => 'Open',
            self::Closed    => 'Closed',
            self::Departed  => 'Departed',
            self::Arrived   => 'Arrived',
            self::Cancelled => 'Cancelled',
        };
    }
}
