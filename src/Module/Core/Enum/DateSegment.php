<?php
namespace App\Module\Core\Enum;

use App\Module\Reporting\Enum\DatasetGroupColumn;

enum DateSegment: string {
    case Daily = 'Daily';
    case Monthly = 'Monthly';
    case Yearly = 'Yearly';
    public function computeQuery($alias, DatasetGroupColumn $groupColumn): string
    {
        return self::{$this->value . 'Query'}($alias, $groupColumn);
    }
    public static function DailyQuery($alias, DatasetGroupColumn $groupColumn) {
        return "DATE(DATE_ADD($alias.{$groupColumn->value},7, 'HOUR')) as a{$groupColumn->value}";
    }
    public static function MonthlyQuery($alias, DatasetGroupColumn $groupColumn) {
        return "DATE_FORMAT(DATE_ADD($alias.{$groupColumn->value},7, 'HOUR'), '%Y-%m') as a{$groupColumn->value}";
    }
    public static function YearlyQuery($alias, DatasetGroupColumn $groupColumn) {
        return "YEAR(DATE_ADD($alias.{$groupColumn->value},7, 'HOUR')) as a{$groupColumn->value}";
    }
}