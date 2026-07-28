<?php
namespace App\Module\Sales\Enum;

enum PaymentMethod: string
{
    case Cash   = 'cash';
    case Card   = 'card';
    case QrCode = 'qr_code';
    case Other  = 'other';
}
