<?php
namespace App\Module\Crm\Enum;

use App\Module\Crm\Entity\Client;
enum ClientType: string {
    case Agent = 'A';
    case Direct = 'D';
    case Forwarder = 'F';
    case Client = 'C';
    case Other = 'O';
}