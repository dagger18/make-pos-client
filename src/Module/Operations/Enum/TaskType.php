<?php

namespace App\Module\Operations\Enum;

use App\Module\Operations\Entity\Booking;

enum TaskType: string
{
    case Document  = 'DOCUMENT';
    case Booking   = 'BOOKING';
    case Customs   = 'CUSTOMS';
    case Invoice   = 'INVOICE';
    case FollowUp  = 'FOLLOW_UP';
    case Transport = 'TRANSPORT';
    case Other     = 'OTHER';

    public function label(): string
    {
        return match($this) {
            self::Document  => 'Document',
            self::Booking   => 'Booking',
            self::Customs   => 'Customs',
            self::Invoice   => 'Invoice',
            self::FollowUp  => 'Follow-Up',
            self::Transport => 'Transport',
            self::Other     => 'Other',
        };
    }
}
