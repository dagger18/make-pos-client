<?php

namespace App\Module\Operations\Enum;

use App\Module\Operations\Entity\ArrivalNotice;
use App\Module\Operations\Entity\DeliveryOrder;

enum DocType: string
{
    case CommercialInvoice  = 'COMMERCIAL_INVOICE';
    case PackingList        = 'PACKING_LIST';
    case HBL                = 'HBL';
    case MBL                = 'MBL';
    case HAWB               = 'HAWB';
    case MAWB               = 'MAWB';
    case CMR                = 'CMR';
    case ExportDeclaration  = 'EXPORT_DECLARATION';
    case ImportEntry        = 'IMPORT_ENTRY';
    case CertificateOfOrigin= 'CERTIFICATE_OF_ORIGIN';
    case DGDeclaration      = 'DG_DECLARATION';
    case VGMCertificate     = 'VGM_CERTIFICATE';
    case Phytosanitary      = 'PHYTOSANITARY';
    case InsuranceCert      = 'INSURANCE_CERT';
    case ArrivalNotice      = 'ARRIVAL_NOTICE';
    case DeliveryOrder      = 'DELIVERY_ORDER';
    case ShippingInstruction= 'SHIPPING_INSTRUCTION';
    case LetterOfCredit     = 'LETTER_OF_CREDIT';
    case Other              = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CommercialInvoice   => 'Commercial Invoice',
            self::PackingList         => 'Packing List',
            self::HBL                 => 'House Bill of Lading',
            self::MBL                 => 'Master Bill of Lading',
            self::HAWB                => 'House Air Waybill',
            self::MAWB                => 'Master Air Waybill',
            self::CMR                 => 'CMR Waybill',
            self::ExportDeclaration   => 'Export Declaration',
            self::ImportEntry         => 'Import Customs Entry',
            self::CertificateOfOrigin => 'Certificate of Origin',
            self::DGDeclaration       => 'Dangerous Goods Declaration',
            self::VGMCertificate      => 'VGM Certificate',
            self::Phytosanitary       => 'Phytosanitary Certificate',
            self::InsuranceCert       => 'Insurance Certificate',
            self::ArrivalNotice       => 'Arrival Notice',
            self::DeliveryOrder       => 'Delivery Order',
            self::ShippingInstruction => 'Shipping Instruction',
            self::LetterOfCredit      => 'Letter of Credit',
            self::Other               => 'Other',
        };
    }
}
