<?php
namespace App\Module\Finance\Enum;
enum EbitNoteType: string {
    case Debit = 'DN';
    case Credit = 'CN';
    case POBO = 'PO';
    case COBO = 'CO';
    case InvoiceDebit = 'ID';
    case InvoiceCredit = 'IC';
    case RecordReceipt = 'RPT';
    case RecordPayment = 'PMT';
    public function subName(): string
    {
        return match ($this) {
            self::InvoiceDebit     => 'Revenue',
            self::InvoiceCredit   => 'Cost'
        };
    }
}