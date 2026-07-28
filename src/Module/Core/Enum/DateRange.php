<?php
namespace App\Module\Core\Enum;
enum DateRange: string {
    case Today = 'Today';
    case ThisWeek = 'ThisWeek';
    case LastWeek = 'LastWeek';
    case ThisMonth = 'ThisMonth';
    case LastMonth = 'LastMonth';
    case ThreeMonths = 'ThreeMonths';
    case SixMonths = 'SixMonths';
    case ThisYear = 'ThisYear';
    case LastYear = 'LastYear';
    case ThreeYears = 'ThreeYears';

    public function range(): array
    {
        return self::{$this->value . 'Range'}();
    }
    public static function TodayRange() {
        return [
            date('Y-m-d 00:00:00'), 
            date('Y-m-d 23:59:59')
        ];
    }
    public static function ThisWeekRange() {
        return [
            date('Y-m-d 00:00:00', strtotime('this week')), 
            date('Y-m-d 23:59:59')
        ];
    }
    public static function LastWeekRange() {
        return [
            date('Y-m-d 00:00:00', strtotime('last week')), 
            date('Y-m-d 23:59:59', strtotime('last sunday'))
        ];
    }
    public static function ThisMonthRange() {
        return [
            date('Y-m-01 00:00:00'), 
            date('Y-m-d 23:59:59')
        ];
    }
    public static function LastMonthRange() {
        return [
            date('Y-m-01 00:00:00', strtotime('last month')), 
            date('Y-m-t 23:59:59', strtotime('last month'))
        ];
    }
    public static function ThreeMonthsRange() {
        return [
            date('Y-m-01 00:00:00', strtotime('-2 month')), 
            date('Y-m-t 23:59:59', strtotime('this month'))
        ];
    }
    public static function SixMonthsRange() {
        return [
            date('Y-m-01 00:00:00', strtotime('-5 month')), 
            date('Y-m-t 23:59:59')
        ];
    }
    public static function ThisYearRange() {
        return [
            date('Y-01-01 00:00:00', strtotime('this year')), 
            date('Y-m-d 23:59:59')
        ];
    }
    public static function LastYearRange() {
        return [
            date('Y-01-01 00:00:00', strtotime('last year')), 
            date('Y-12-t 23:59:59', strtotime('last year'))
        ];
    }
    public static function ThreeYearsRange() {
        return [
            date('Y-01-01 00:00:00', strtotime('-2 year')), 
            date('Y-m-d 23:59:59', strtotime('this year'))
        ];
    }

}