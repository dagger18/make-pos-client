<?php
namespace App\Module\Quote\Enum;
enum FreightTerm: string {
    case Prepaid = 'P';
    case Collect = 'C';
    public function toTitle(): string {
        return match ($this) { self::Prepaid => 'PREPAID', self::Collect => 'COLLECT' };
    }
}