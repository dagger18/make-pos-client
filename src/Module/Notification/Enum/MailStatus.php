<?php
namespace App\Module\Notification\Enum;
enum MailStatus: string {
    case Pending = 'P';
    case Sent = 'S';
    case Cancelled = 'C';
    case Failed = 'F';
}