<?php
namespace App\Module\Reporting\Enum;
enum DatasetRowType: string {
    case Entity = 'Entity';
    case Group = 'Group';
    case GroupColumnEntity = 'GroupColumnEntity';
}