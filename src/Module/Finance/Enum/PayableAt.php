<?php
namespace App\Module\Finance\Enum;
enum PayableAt: string {
    case Origin      = 'ORIGIN';
    case Destination = 'DESTINATION';
    case Both        = 'BOTH';
    public function title(): string
    {
        return match ($this) {
            self::Origin      => 'Origin',
            self::Destination => 'Destination',
            self::Both        => 'Both',
        };
    }
}
