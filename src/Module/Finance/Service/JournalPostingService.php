<?php
namespace App\Module\Finance\Service;

use App\Module\Finance\Entity\ChartOfAccount;

use App\Module\Finance\Entity\EbitNote;
use App\Module\Finance\Entity\JournalEntry;
use App\Module\Finance\Entity\JournalLine;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Finance\Repository\ChartOfAccountRepository;
use App\Module\Finance\Repository\JournalEntryRepository;

class JournalPostingService
{
    private const CHARGE_TYPE_MAP = [
        'freight' => ['revenue' => '4100', 'cogs' => '5100'],
        'FREIGHT' => ['revenue' => '4100', 'cogs' => '5100'],
        'local'   => ['revenue' => '4120', 'cogs' => '5120'],
        'LOCAL'   => ['revenue' => '4120', 'cogs' => '5120'],
        'customs' => ['revenue' => '4130', 'cogs' => '5130'],
        'CUSTOMS' => ['revenue' => '4130', 'cogs' => '5130'],
        'service' => ['revenue' => '4140', 'cogs' => '5140'],
        'SERVICE' => ['revenue' => '4140', 'cogs' => '5140'],
    ];

    public function __construct(
        private readonly JournalEntryRepository  $journalRepo,
        private readonly ChartOfAccountRepository $coaRepo,
    ) {}

    private function baseAmount(EbitNote $note): float
    {
        $money = $note->getAmount();
        if (!$money || !$money->getRate() || $money->getRate() == 0) return 0;
        return round($money->getAmount() / $money->getRate(), 4);
    }

    private function account(string $code): ?ChartOfAccount
    {
        return $this->coaRepo->findByCode($code);
    }

    private function line(JournalEntry $je, string $code, float $debit, float $credit, string $currency, float $rate, ?string $desc = null): void
    {
        $acc = $this->account($code);
        if (!$acc) return;
        $baseDebit  = $debit  > 0 ? round($debit  / $rate, 4) : 0;
        $baseCredit = $credit > 0 ? round($credit / $rate, 4) : 0;
        $l = (new JournalLine())
            ->setAccount($acc)
            ->setDebit($debit)->setCredit($credit)
            ->setCurrency($currency)
            ->setBaseDebit($baseDebit)->setBaseCredit($baseCredit)
            ->setFxRate($rate)
            ->setDescription($desc);
        $je->addLine($l);
    }

    private function entry(EbitNote $note, string $sourceType): JournalEntry
    {
        $je = new JournalEntry();
        $je->setEbitNote($note)
           ->setSourceType($sourceType)
           ->setEntryDate(new \DateTime())
           ->setIsPosted(true)
           ->setPostedAt(new \DateTime());
        $this->journalRepo->save($je);
        $je->setJournalNumber('JNL-' . (new \DateTime())->format('Ym') . '-' . str_pad((string)$je->getId(), 5, '0', STR_PAD_LEFT));
        $this->journalRepo->save($je);
        return $je;
    }

    public function postArInvoice(EbitNote $note): void
    {
        $currency = $note->getCurrency() ?? 'USD';
        $rate     = $note->getAmount()?->getRate() ?? 1;
        $je       = $this->entry($note, 'AR_INVOICE');
        $totalBase = 0;

        foreach ($note->getChargeItems() as $item) {
            $itemAmt  = $item->getAmount()?->getAmount() ?? 0;
            $itemRate = $item->getAmount()?->getRate() ?? $rate;
            $itemBase = $itemRate > 0 ? round($itemAmt / $itemRate, 4) : 0;
            $chargeType = $item->getChargeType() ?? 'FREIGHT';
            $revenueCode = self::CHARGE_TYPE_MAP[$chargeType]['revenue'] ?? '4100';
            $this->line($je, $revenueCode, 0, $itemBase, $currency, $itemRate, $item->getChargeName());
            $totalBase += $itemBase;
        }

        $this->line($je, '1100', $totalBase, 0, $currency, $rate, 'AR - ' . $note->getCode());
    }

    public function postApBill(EbitNote $note): void
    {
        $currency = $note->getCurrency() ?? 'USD';
        $rate     = $note->getAmount()?->getRate() ?? 1;
        $je       = $this->entry($note, 'AP_BILL');
        $totalBase = 0;

        foreach ($note->getChargeItems() as $item) {
            $itemAmt  = $item->getAmount()?->getAmount() ?? 0;
            $itemRate = $item->getAmount()?->getRate() ?? $rate;
            $itemBase = $itemRate > 0 ? round($itemAmt / $itemRate, 4) : 0;
            $chargeType = $item->getChargeType() ?? 'FREIGHT';
            $cogsCode = self::CHARGE_TYPE_MAP[$chargeType]['cogs'] ?? '5100';
            $this->line($je, $cogsCode, $itemBase, 0, $currency, $itemRate, $item->getChargeName());
            $totalBase += $itemBase;
        }

        $this->line($je, '2100', 0, $totalBase, $currency, $rate, 'AP - ' . $note->getCode());
    }

    public function postArPayment(EbitNote $receipt): void
    {
        $parent   = $receipt->getParentNote();
        $currency = $receipt->getCurrency() ?? 'USD';
        $rate     = $receipt->getAmount()?->getRate() ?? 1;
        $paidBase = $this->baseAmount($receipt);
        $invBase  = $parent ? $this->baseAmount($parent) : $paidBase;
        $fxGL     = round($paidBase - $invBase, 4);
        $je       = $this->entry($receipt, 'AR_PAYMENT');

        $this->line($je, '1200', $paidBase, 0, $currency, $rate, 'Receipt ' . $receipt->getCode());
        $this->line($je, '1100', 0, $invBase, $currency, $parent?->getAmount()?->getRate() ?? $rate);
        if (abs($fxGL) > 0.001) {
            if ($fxGL > 0) {
                $this->line($je, '6900', 0, $fxGL, $currency, 1, 'FX Gain on ' . $receipt->getCode());
            } else {
                $this->line($je, '6900', abs($fxGL), 0, $currency, 1, 'FX Loss on ' . $receipt->getCode());
            }
        }
    }

    public function postApPayment(EbitNote $payment): void
    {
        $parent   = $payment->getParentNote();
        $currency = $payment->getCurrency() ?? 'USD';
        $rate     = $payment->getAmount()?->getRate() ?? 1;
        $paidBase = $this->baseAmount($payment);
        $billBase = $parent ? $this->baseAmount($parent) : $paidBase;
        $fxGL     = round($billBase - $paidBase, 4);
        $je       = $this->entry($payment, 'AP_PAYMENT');

        $this->line($je, '2100', $billBase, 0, $currency, $parent?->getAmount()?->getRate() ?? $rate, 'AP ' . $payment->getCode());
        $this->line($je, '1200', 0, $paidBase, $currency, $rate);
        if (abs($fxGL) > 0.001) {
            if ($fxGL > 0) {
                $this->line($je, '6900', 0, $fxGL, $currency, 1, 'FX Gain on ' . $payment->getCode());
            } else {
                $this->line($je, '6900', abs($fxGL), 0, $currency, 1, 'FX Loss on ' . $payment->getCode());
            }
        }
    }

    public function postCreditNote(EbitNote $cn): void
    {
        $currency = $cn->getCurrency() ?? 'USD';
        $rate     = $cn->getAmount()?->getRate() ?? 1;
        $base     = $this->baseAmount($cn);
        $je       = $this->entry($cn, 'CREDIT_NOTE');

        $this->line($je, '4100', $base, 0, $currency, $rate, 'CN ' . $cn->getCode());
        $this->line($je, '1100', 0, $base, $currency, $rate);
    }
}
