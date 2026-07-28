<?php
namespace App\Module\Core\Enum;
// stands for magic number :}
class Magnum {
    public const ADMIN_GROUP_ID = 1;
    public const SYSTEM_ADMIN_ID = 1;
    public const COMPANY_ADMIN_ID = 2;
    public const COMPANY_PROVIDER_ID = 1;
    public const PRICE_MARKUP_10PERCENT_ID = 1;
    public const MIN_DAYS_ALERT = 14;
    public static function ENTITY_COLLECTION_UPDATABLE_MAP () {
        return [];
    }
}
