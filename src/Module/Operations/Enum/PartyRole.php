<?php

namespace App\Module\Operations\Enum;

enum PartyRole: string
{
    case Shipper       = 'SHIPPER';
    case Consignee     = 'CONSIGNEE';
    case Notify1       = 'NOTIFY_1';
    case Notify2       = 'NOTIFY_2';
    case OverseasAgent = 'OVERSEAS_AGENT';
    case Carrier       = 'CARRIER';
    case CoLoader      = 'CO_LOADER';
    case CustomsBroker = 'CUSTOMS_BROKER';
    case HaulierO      = 'HAULIER_O';
    case HaulierD      = 'HAULIER_D';
    case WarehouseO    = 'WAREHOUSE_O';
    case WarehouseD    = 'WAREHOUSE_D';
    case Insurance     = 'INSURANCE';
    case Surveyor      = 'SURVEYOR';
    case Bank          = 'BANK';
    case Fumigation    = 'FUMIGATION';

    public function label(): string
    {
        return match($this) {
            self::Shipper       => 'Shipper',
            self::Consignee     => 'Consignee',
            self::Notify1       => 'Notify Party 1',
            self::Notify2       => 'Notify Party 2',
            self::OverseasAgent => 'Overseas Agent',
            self::Carrier       => 'Carrier',
            self::CoLoader      => 'Co-Loader',
            self::CustomsBroker => 'Customs Broker',
            self::HaulierO      => 'Haulier (Origin)',
            self::HaulierD      => 'Haulier (Destination)',
            self::WarehouseO    => 'Warehouse (Origin)',
            self::WarehouseD    => 'Warehouse (Destination)',
            self::Insurance     => 'Insurer',
            self::Surveyor      => 'Surveyor',
            self::Bank          => 'Bank',
            self::Fumigation    => 'Fumigation',
        };
    }
}
