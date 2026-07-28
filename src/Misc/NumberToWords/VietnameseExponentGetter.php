<?php

namespace App\Misc\NumberToWords;

use NumberToWords\Language\ExponentGetter;

class VietnameseExponentGetter implements ExponentGetter
{
    private static array $exponent = [
        '',
        'ngàn',
        'triệu',
        'tỷ',
        'nghìn tỷ',
        'triệu tỷ',
        'tỷ tỷ',
        'sextillion',
        'septillion',
        'octillion',
        'nonillion',
        'decillion',
        'undecillion',
        'duodecillion',
        'tredecillion',
        'quattuordecillion',
        'quindecillion',
        'sexdecillion',
        'septendecillion',
        'octodecillion',
        'novemdecillion',
        'vigintillion',
    ];

    public function getExponent(int $power): string
    {
        return self::$exponent[$power];
    }
}