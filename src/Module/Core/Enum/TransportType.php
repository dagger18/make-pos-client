<?php
namespace App\Module\Core\Enum;
enum TransportType: string {
    case Ocean      = 'OCN';
    case Air        = 'AIR';
    case Road       = 'RD';
    case Rail       = 'RAL';
    case Courier    = 'COU';
    case Multimodal = 'MMD';

    public function title(): string
    {
        return match ($this) {
            self::Ocean      => 'Ocean',
            self::Air        => 'Air',
            self::Road       => 'Road',
            self::Rail       => 'Rail',
            self::Courier    => 'Courier',
            self::Multimodal => 'Multimodal',
        };
    }
}
