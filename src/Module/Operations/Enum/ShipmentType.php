<?php
namespace App\Module\Operations\Enum;
enum ShipmentType: string {
    case Export = 'EXP';
    case Import = 'IMP';
    case CrossTrade = 'XTD';
    case Domestic = 'DOM';
    case Transshipment = 'TSH';
    public function title(): string
    {
        return match ($this) {
            self::Export        => 'Export',
            self::Import        => 'Import',
            self::CrossTrade    => 'Cross Trade',
            self::Domestic      => 'Domestic',
            self::Transshipment => 'Transshipment',
        };
    }
}