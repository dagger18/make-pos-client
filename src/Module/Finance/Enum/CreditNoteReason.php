<?php
namespace App\Module\Finance\Enum;

enum CreditNoteReason: string
{
    case RateError        = 'RATE_ERROR';
    case Duplicate        = 'DUPLICATE';
    case WeightAdjustment = 'WEIGHT_ADJUSTMENT';
    case Dispute          = 'DISPUTE';
    case Rebate           = 'REBATE';
    case CarrierCredit    = 'CARRIER_CREDIT';
    case Shortfall        = 'SHORTFALL';
    case Overbilling      = 'OVERBILLING';

    public function label(): string
    {
        return match($this) {
            self::RateError        => 'Rate Error',
            self::Duplicate        => 'Duplicate',
            self::WeightAdjustment => 'Weight Adjustment',
            self::Dispute          => 'Dispute',
            self::Rebate           => 'Rebate',
            self::CarrierCredit    => 'Carrier Credit',
            self::Shortfall        => 'Shortfall',
            self::Overbilling      => 'Overbilling',
        };
    }
}
