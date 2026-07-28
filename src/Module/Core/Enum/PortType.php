<?php
namespace App\Module\Core\Enum;
enum PortType: string {
    case Sea = 'S';
    case Rail = 'L';
    case Road = 'D';
    case Air = 'A';
}