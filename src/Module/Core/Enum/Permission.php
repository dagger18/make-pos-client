<?php
namespace App\Module\Core\Enum;
use App\Module\Finance\Enum\ChargeType;
enum Permission: string {
    case Admin = '99';
    case Provider_POST = '100';
    case Provider_PUT = '101';
    case Provider_DELETE = '102';
    case Provider_GET = '198';
    case Provider_SEEALL = '199';
    case Client_POST = '200';
    case Client_PUT = '201';
    case Client_DELETE = '202';
    case Client_SEEALL = '299';
    case Client_GET = '298';

    case Quote_POST = '300';
    case Quote_PUT = '301';
    case Quote_DELETE = '302';
    case Quote_SEEALL = '399';
    case Quote_GET = '398';

    case Shipment_POST = '400';
    case Shipment_DELETE = '401';
    case Shipment_PUT_Status = '402';
    case Shipment_PUT_Documents = '403';
    case Shipment_PUT_Manager = '404';
    case Shipment_SEEALL = '499';
    case Shipment_GET = '498';
    case Shipment_MANAGE_Booking = '405';
    case Shipment_MANAGE_Instruction = '406';
    case Shipment_MANAGE_Pricing = '408';
    case Shipment_MANAGE_Ebitnote = '407';
    case Shipment_PUT_AssignedUsers = '409';
    case Shipment_PUT_StatusRevert = '410';
    
    case EbitNote_MANAGE_ID = '500';
    case EbitNote_MANAGE_IC = '501';
    case EbitNote_MANAGE_DN = '502';
    case EbitNote_MANAGE_PO = '503';
    case EbitNote_MANAGE_CO = '504';
    case EbitNote_MANAGE_RPT = '505';
    case EbitNote_MANAGE_PMT = '506';
    case EbitNote_MANAGE_SOA = '507';

    case Charge_MANAGE_Customs  = '800';
    case Charge_MANAGE_Local = '801';
    case Charge_MANAGE_Service = '802';
    case Charge_MANAGE_Freight = '803';
    case Incoterm_MANAGE = '805';
    case TaxGroup_MANAGE = '806';
    case PaymentMethod_MANAGE = '807';
    case ShipmentMode_MANAGE = '808';
    case HsCode_MANAGE = '809';
    case DutyRate_MANAGE = '810';
    case HsRestriction_MANAGE = '811';
    case CarrierEventMapping_MANAGE = '812';

    case Rate_MANAGE_Customs = '850';
    case Rate_MANAGE_Local = '851';
    case Rate_MANAGE_Service = '852';
    case Rate_MANAGE_Freight = '853';

    case User_POST = '900';
    case User_PUT = '901';
    case User_DELETE = '902';
    case User_GET = '907';
    case Group_MANAGE = '903';
    case Config_MANAGE = '904';
    case PriceMarkup_MANAGE = '905';
    case Report_MANAGE = '906';
    case Branch_MANAGE = '908';
    case Department_MANAGE = '909';
    case Consolidation_GET = '910';
    case Warehouse_MANAGE = '911';
    case CRM_MANAGE = '912';
    case ChartOfAccounts_MANAGE = '913';

    public function getAbility(): array
    {
        return match ($this) {
            self::Admin   => ['action' => 'MANAGE', 'subject' => 'all'],
            default => $this->parseNameToAbility()
        };
    }
    private function parseNameToAbility(): array
    {
        [$module, $action] = explode('_', $this->name, 2);
        return ['action' => $action, 'subject' => $module];
    }

    public function getRateChargeType(): ?ChargeType
    {
        return match ($this) {
            self::Rate_MANAGE_Local => ChargeType::LOCAL,
            self::Rate_MANAGE_Service     => ChargeType::SERVICE,
            self::Rate_MANAGE_Customs      => ChargeType::CUSTOMS,
            self::Rate_MANAGE_Freight     => ChargeType::FREIGHT,
            default                       => null,
        };
    }

    public function getPermissionChargeType(): ?ChargeType
    {
        return match ($this) {
            self::Charge_MANAGE_Local => ChargeType::LOCAL,
            self::Charge_MANAGE_Service     => ChargeType::SERVICE,
            self::Charge_MANAGE_Customs      => ChargeType::CUSTOMS,
            self::Charge_MANAGE_Freight     => ChargeType::FREIGHT,
            default                       => null,
        };
    }
}