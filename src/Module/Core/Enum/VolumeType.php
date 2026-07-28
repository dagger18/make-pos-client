<?php
namespace App\Module\Core\Enum;
enum VolumeType: string {
    case Container = 'C';
    case Unit = 'U';
    case TotalShip = 'S';
    case TotalWeight = 'W';
    case Other = 'O';
}