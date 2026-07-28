<?php

namespace App\Module\Finance\Service;

use App\Module\Finance\Entity\EbitNote;
use App\Module\Finance\Enum\EbitNoteType;

class FxGainLossService
{
    private function baseAmount(EbitNote $note): ?float
    {
        $money = $note->getAmount();
        if (!$money || !$money->getRate() || $money->getRate() == 0) {
            return null;
        }
        return $money->getAmount() / $money->getRate();
    }

    public function compute(EbitNote $note): void
    {
        $parent = $note->getParentNote();
        if (!$parent) {
            return;
        }

        $parentBase = $this->baseAmount($parent);
        $noteBase   = $this->baseAmount($note);

        if ($parentBase === null || $noteBase === null) {
            return;
        }

        if ($note->getType() === EbitNoteType::RecordReceipt) {
            // AR receipt: gain = received_base - invoice_base
            $note->setFxGainLoss(round($noteBase - $parentBase, 4));
        } elseif ($note->getType() === EbitNoteType::RecordPayment) {
            // AP payment: gain = bill_base - paid_base (paid less = gain)
            $note->setFxGainLoss(round($parentBase - $noteBase, 4));
        }
    }
}
