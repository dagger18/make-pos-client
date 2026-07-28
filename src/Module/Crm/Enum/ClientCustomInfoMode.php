<?php
namespace App\Module\Crm\Enum;

use App\Module\Finance\Entity\InvoiceInfo;
enum ClientCustomInfoMode: string {
    case GeneralInfo = 'G';
    case InvoiceInfo = 'I';
    case CustomInfo = 'C';
}