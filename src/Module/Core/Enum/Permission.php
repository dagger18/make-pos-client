<?php
namespace App\Module\Core\Enum;

enum Permission: string {
    case Admin = '99';

    case Client_POST = '200';
    case Client_PUT = '201';
    case Client_DELETE = '202';
    case Client_SEEALL = '299';
    case Client_GET = '298';

    case Charge_MANAGE_Customs  = '800';
    case Charge_MANAGE_Local = '801';
    case Charge_MANAGE_Service = '802';
    case Charge_MANAGE_Freight = '803';
    case TaxGroup_MANAGE = '806';
    case PaymentMethod_MANAGE = '807';

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
}
