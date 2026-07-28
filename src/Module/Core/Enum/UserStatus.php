<?php
namespace App\Module\Core\Enum;
enum UserStatus: string {
    case Active = 'A';
    case InActive = 'I';
    case Pending = 'P';
}