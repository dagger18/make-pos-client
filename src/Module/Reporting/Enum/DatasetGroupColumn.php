<?php
namespace App\Module\Reporting\Enum;

use App\Module\Carrier\Entity\Provider;
use App\Module\Crm\Entity\Client;
enum DatasetGroupColumn: string {
    case CompletedDate = 'completedDate';
    case ETD = 'etd';
    case CreatedDate = 'createdDate';
    case CreatedBy = 'createdBy';
    case AccountManager = 'accountManager';
    case Client = 'client';
    case Provider = 'provider';
    case Origin = 'origin';
    case Destination = 'destination';
    case Route = 'route';
    case ActivityCreatedDate = 'activity.createdDate';
    case ShipmentCompletedDate = 'shipment.completedDate';
}