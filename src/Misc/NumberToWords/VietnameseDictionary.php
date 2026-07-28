<?php

namespace App\Misc\NumberToWords;

use NumberToWords\Language\Dictionary;

class VietnameseDictionary implements Dictionary
{
    public const LOCALE = 'vi_VN';
    public const LANGUAGE_NAME = 'Vietnamese';
    public const LANGUAGE_NAME_NATIVE = 'Việt Nam';

    private static array $units = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    private static array $unitsTen = ['', 'mốt', 'hai', 'ba', 'bốn', 'lăm', 'sáu', 'bảy', 'tám', 'chín'];

    private static array $teens = [
        'mười',
        'mười một',
        'mười hai',
        'mười ba',
        'mười bốn',
        'mười lăm',
        'mười sáu',
        'mười bảy',
        'mười tám',
        'mười chín'
    ];

    private static array $tens = [
        '',
        'mười',
        'hai mươi',
        'ba mươi',
        'bốn mươi',
        'năm mươi',
        'sáu mươi',
        'bảy mươi',
        'tám mươi',
        'chín mươi'
    ];

    private static string $hundred = 'trăm';

    public static array $currencyNames = [
        'VND' => [['đồng'], ['hào']],
    ];

    public function getZero(): string
    {
        return 'không';
    }

    public function getMinus(): string
    {
        return 'âm';
    }

    public function getCorrespondingUnit(int $unit): string
    {
        return self::$units[$unit];
    }

    public function getCorrespondingUnitTen(int $unit): string
    {
        return self::$unitsTen[$unit];
    }

    public function getCorrespondingTen(int $ten): string
    {
        return self::$tens[$ten];
    }

    public function getCorrespondingTeen(int $teen): string
    {
        return self::$teens[$teen];
    }

    public function getCorrespondingHundred(int $hundred): string
    {
        return self::$units[$hundred] . ' ' . self::$hundred;
    }
}