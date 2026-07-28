<?php
namespace App\Module\Finance\Enum;
enum PaymentMethodType: string {
    case Cash = 'C';
    case Bank = 'B';
    case Other = 'O';
}