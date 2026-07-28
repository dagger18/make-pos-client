<?php
namespace App\Module\Core\Enum;
enum PageType: string {
    case ReportShipment = 'report-shipment';
    case ReportStaff = 'report-staff';
    case ReportCharge = 'report-charge';
    case ManageShipment = 'manage-shipment';
    case ManageClient = 'manage-client';
    case ManageProvider = 'manage-provider';
    case ManageQuote = 'manage-quote';
    case ManageAccounting = 'manage-accounting';
    case ManageNotification = 'manage-notification';
}