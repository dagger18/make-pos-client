<?php
namespace App\Module\Core\Enum;
enum ServiceType: string {
    // OCN
    case FCL       = 'FCL';
    case LCL       = 'LCL';
    case Bulk      = 'BULK';
    case RoRo      = 'RORO';
    case OOG       = 'OOG';
    // AIR
    case Direct    = 'DIRECT';
    case Consol    = 'CONSOL';
    case Charter   = 'CHARTER';
    case AirExpress = 'EXPRESS';
    // RD
    case FTL       = 'FTL';
    case LTL       = 'LTL';
    case Groupage  = 'GROUPAGE';
    case CourierRD = 'COURIER-RD';
    // RAL
    case FCL_Rail  = 'FCL-RAIL';
    case LCL_Rail  = 'LCL-RAIL';
    case Block     = 'BLOCK';
    case Wagon     = 'WAGON';
    // COU
    case Economy   = 'ECONOMY';
    case ExpressCOU = 'EXPRESS-COU';
    case Overnight = 'OVERNIGHT';
    case SameDay   = 'SAME-DAY';
    // MMD
    case SeaAir      = 'SEA-AIR';
    case SeaRoad     = 'SEA-ROAD';
    case AirRoad     = 'AIR-ROAD';
    case RailRoad    = 'RAIL-ROAD';
    case SeaRailRoad = 'SEA-RAIL-ROAD';

    public function transportMode(): TransportType
    {
        return match ($this) {
            self::FCL, self::LCL, self::Bulk, self::RoRo, self::OOG
                => TransportType::Ocean,
            self::Direct, self::Consol, self::Charter, self::AirExpress
                => TransportType::Air,
            self::FTL, self::LTL, self::Groupage, self::CourierRD
                => TransportType::Road,
            self::FCL_Rail, self::LCL_Rail, self::Block, self::Wagon
                => TransportType::Rail,
            self::Economy, self::ExpressCOU, self::Overnight, self::SameDay
                => TransportType::Courier,
            self::SeaAir, self::SeaRoad, self::AirRoad, self::RailRoad, self::SeaRailRoad
                => TransportType::Multimodal,
        };
    }

    public function shortCode(): string
    {
        return match ($this) {
            self::FCL        => 'FCL',
            self::LCL        => 'LCL',
            self::Bulk       => 'BLK',
            self::RoRo       => 'RRO',
            self::OOG        => 'OOG',
            self::Direct     => 'DRT',
            self::Consol     => 'CSL',
            self::Charter    => 'CHT',
            self::AirExpress => 'AEX',
            self::FTL        => 'FTL',
            self::LTL        => 'LTL',
            self::Groupage   => 'GRP',
            self::CourierRD  => 'CRD',
            self::FCL_Rail   => 'RCL',
            self::LCL_Rail   => 'RLC',
            self::Block      => 'BLO',
            self::Wagon      => 'WGN',
            self::Economy    => 'ECO',
            self::ExpressCOU => 'EXC',
            self::Overnight  => 'OVN',
            self::SameDay    => 'SDY',
            self::SeaAir     => 'SAR',
            self::SeaRoad    => 'SRD',
            self::AirRoad    => 'ARD',
            self::RailRoad   => 'RAD',
            self::SeaRailRoad => 'SRR',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::FCL        => 'Full Container Load',
            self::LCL        => 'Less than Container Load',
            self::Bulk       => 'Bulk / Break-bulk',
            self::RoRo       => 'Roll-on Roll-off',
            self::OOG        => 'Out of Gauge',
            self::Direct     => 'Direct',
            self::Consol     => 'Consolidation',
            self::Charter    => 'Charter',
            self::AirExpress => 'Express',
            self::FTL        => 'Full Truck Load',
            self::LTL        => 'Less than Truck Load',
            self::Groupage   => 'Groupage',
            self::CourierRD  => 'Road Courier',
            self::FCL_Rail   => 'Full Container on Rail',
            self::LCL_Rail   => 'Shared Rail Container',
            self::Block      => 'Block Train',
            self::Wagon      => 'Full Wagon Load',
            self::Economy    => 'Economy',
            self::ExpressCOU => 'Courier Express',
            self::Overnight  => 'Overnight',
            self::SameDay    => 'Same Day',
            self::SeaAir     => 'Sea-Air',
            self::SeaRoad    => 'Sea-Road',
            self::AirRoad    => 'Air-Road',
            self::RailRoad   => 'Rail-Road',
            self::SeaRailRoad => 'Sea-Rail-Road',
        };
    }
}
