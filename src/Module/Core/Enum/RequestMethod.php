<?php
namespace App\Module\Core\Enum;
enum RequestMethod: string {
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    public static function methodList() {
      return array_column(self::cases(), 'value');
    }
    public static function updateMethodList() {
      return [self::POST, self::PUT, self::PATCH];
    }
}