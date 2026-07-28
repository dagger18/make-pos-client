<?php
namespace App\Module\Core\Enum;
enum ComponentType: string {
    case Table = 'Table';
    case ColumnLine = 'ColumnLine';
    case Pie = 'Pie';
    case AreaStacked = 'AreaStacked';
    case HeatMap = 'HeatMap';
}