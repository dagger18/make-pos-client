<?php
namespace App\Module\Operations\Enum;
enum ContainerType: string {
    case C20DC = "20DC";
    case C40DC = '40DC';
    case C20RF = '20RF';
    case C40RF = '40RF';
    case C20OT = '20OT';
    case C40OT = '40OT';
    case C20FR = '20FR';
    case C40FR = '40FR';
    case C40HC = '40HC';
    case C45HC = '45HC';
    case C20TK = '20TK';
    case C40TK = '40TK';
    case CFDC = 'FDC';
    case CFHC = 'FHC';
    public function title(): string
    {
        return match ($this) {
            self::C20DC   => "20'DC",
            self::C40DC   => "40'DC",
            self::C20RF   => "20'RF",
            self::C40RF   => "40'RF",
            self::C20OT   => "20'OT",
            self::C40OT   => "40'OT",
            self::C20FR   => "20'FR",
            self::C40FR   => "40'FR",
            self::C40HC   => "40'HC",
            self::C45HC   => "45'HC",
            self::C20TK   => "20'Tank",
            self::C40TK   => "40'Tank",
            self::CFDC    => "FOOCDC",
            self::CFHC    => "FOOCHC",
        };
    }

}