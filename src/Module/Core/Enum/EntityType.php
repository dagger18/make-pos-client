<?php
namespace App\Module\Core\Enum;

use App\Module\Core\Entity\User;
use App\Module\Quote\Entity\Quote;
use App\Module\Finance\Entity\EbitNote;
use App\Module\Operations\Entity\Booking;
use App\Module\Operations\Entity\Instruction;
use App\Module\Operations\Entity\Shipment;
use App\Module\Carrier\Entity\Provider;
use App\Module\Crm\Entity\Client;
enum EntityType: string {
    case Provider = 'provider';
    case Client = 'client';
    case User = 'user';
    case Quote = 'quote';
    case Booking = 'booking';
    case Instruction = 'instruction';
    case Shipment = 'shipment';
    case EbitNote = 'ebitnote';
    case PaymentRecord = 'payment-record';
    case Document = 'document';
    case IDMonthly = 'IDmonthlyzip';
    case ICmonthly = 'ICmonthlyzip';
}