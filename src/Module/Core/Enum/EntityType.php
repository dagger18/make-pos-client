<?php
namespace App\Module\Core\Enum;

enum EntityType: string {
    case Client = 'client';
    case User = 'user';
    case PaymentRecord = 'payment-record';
    case Document = 'document';
    case IDMonthly = 'IDmonthlyzip';
    case ICmonthly = 'ICmonthlyzip';
}
