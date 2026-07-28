<?php
namespace App\Module\Core\Enum;
enum VisibleTo: string {
    case Origin      = 'ORIGIN';
    case Destination = 'DESTINATION';
    case All         = 'ALL';
    public function title(): string
    {
        return match ($this) {
            self::Origin      => 'Origin',
            self::Destination => 'Destination',
            self::All         => 'All',
        };
    }
}
