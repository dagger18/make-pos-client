<?php
namespace App\Module\Finance\Enum;

enum VarianceStatus: string
{
    case Unmatched = 'UNMATCHED';
    case Matched   = 'MATCHED';
    case Variance  = 'VARIANCE';
    case Approved  = 'APPROVED';
    case Disputed  = 'DISPUTED';

    public function label(): string
    {
        return match($this) {
            self::Unmatched => 'Unmatched',
            self::Matched   => 'Matched',
            self::Variance  => 'Variance',
            self::Approved  => 'Approved',
            self::Disputed  => 'Disputed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Unmatched => 'default',
            self::Matched   => 'success',
            self::Variance  => 'warning',
            self::Approved  => 'info',
            self::Disputed  => 'error',
        };
    }
}
