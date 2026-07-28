<?php
namespace App\Module\Quote\Enum;
enum QuoteStatus: string {
    case Draft = 'D';
    case Sent = 'S';
    case Booked = 'B';
    case Rejected = 'R';
}