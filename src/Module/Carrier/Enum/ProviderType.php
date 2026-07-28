<?php
namespace App\Module\Carrier\Enum;
enum ProviderType: string {
    case Carrier = 'CA';
    case CoLoader = 'CL';
    case Airline = 'AL';
    case Agent = 'AG';
    case Trucking = 'TK';
    case Other = 'OT';
}