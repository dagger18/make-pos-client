<?php
namespace App\Module\Finance\Service;

use App\Module\Core\Service\MediaService;
use App\Module\Operations\Service\ShipmentService;

use App\Module\Core\Service\BaseService;

use App\Module\Finance\Entity\ChargeItem;
use App\Module\Finance\Entity\EbitNote;
use App\Module\Core\Entity\Money;
use App\Module\Quote\Entity\Quote;
use App\Module\Finance\Enum\EbitNoteStatus;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Core\Enum\EntityType;
use App\Module\Core\Enum\Magnum;
use App\Misc\NumberToWords\NumberToWords;
use App\Module\Finance\Repository\ChargeItemRepository;
use App\Module\Finance\Repository\EbitNoteRepository;
use App\Module\Crm\Repository\PartnerRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use ZipArchive;

class EbitNoteService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public EbitNoteRepository $repository,
        public PartnerRepository $partnerRepository,
        protected MediaService $mediaService,
        protected ExchangeRateGroupService $exchangeRateGroupService,
        protected Environment $twig,
        public ChargeItemRepository $chargeItemRepository,
        protected RequestStack $requestStack,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function setCode(EbitNote $ebitNote, ?int $codeIndex = null): void
    {
        $month = (new \DateTime())->format('ym');
        $number = $this->repository->getNextActiveCode($ebitNote->getType(), $codeIndex);
        if (
            $ebitNote->getType() === EbitNoteType::InvoiceCredit
            || $ebitNote->getType() === EbitNoteType::InvoiceDebit
            || $ebitNote->getType() === EbitNoteType::POBO
            || $ebitNote->getType() === EbitNoteType::COBO
        ) {
            $ebitNote->setCode($ebitNote->getType()->value . $month . $number);
        } else {
            $ebitNote->setCode($ebitNote->getType()->value . '_' . $month . '_' . $number);
        }
    }

    public function replicate(
        EbitNote $fromEbitNote,
        EbitNoteType $toType,
        array $useChargeItems = [],
        bool $replicateChargeItems = true
    ): EbitNote {
        $ebitNote = clone $fromEbitNote;
        $ebitNote->setCode(null);
        $ebitNote->setType($toType);
        $ebitNote->setCreatedBy($this->getUser());
        $ebitNote->setParentNote($fromEbitNote);
        $chargeItems = count($useChargeItems) === 0 ? $fromEbitNote->getChargeItems() : $useChargeItems;
        $amountNoTax = 0;
        $tax = 0;
        $amount = 0;
        $currency = $ebitNote->getAmountNoTax()->getCurrency();
        $rate = $ebitNote->getAmountNoTax()->getRate();
        foreach ($chargeItems as $fromChargeItem) {
            $chargeItem = clone $fromChargeItem;
            if ($replicateChargeItems) {
                $ebitNote->addChargeItem($chargeItem);
            }
            $amountNoTax += $chargeItem->getAmount()->getAmount();
            $tax += $chargeItem->getTax()->getAmount();
            $amount += $chargeItem->getAmount()->getAmount() + $chargeItem->getTax()->getAmount();
        }
        $ebitNote->setAmountNoTax(new Money($amountNoTax, $currency, $rate));
        $ebitNote->setTax(new Money($tax, $currency, $rate));
        $ebitNote->setAmount(new Money($amount, $currency, $rate));
        return $ebitNote;
    }

    public function makeInvoice(EbitNote $ebitNote, Request $request): void
    {
        $invoices = [];
        $split = $request->request->has('splitByTaxGroup')
            ? $request->request->get('splitByTaxGroup') === 'true'
            : false;
        if ($split) {
            foreach ($ebitNote->getChargeItems() as $chargeItem) {
                $taxGroupId = $chargeItem->getTaxGroup()->getId();
                if (!isset($invoices[$taxGroupId])) {
                    $invoices[$taxGroupId] = [];
                }
                $invoices[$taxGroupId][] = $chargeItem;
            }
        } else {
            $invoices['all'] = $ebitNote->getChargeItems();
        }
        $ebitNote->setStatus(EbitNoteStatus::Active);
        $newInvoices = [];
        foreach ($invoices as $invoiceChargeItems) {
            $toType = $ebitNote->getType() === EbitNoteType::Debit
                ? EbitNoteType::InvoiceDebit
                : ($ebitNote->getType() === EbitNoteType::Credit
                    ? EbitNoteType::InvoiceCredit
                    : null
                );
            $newInvoices[] = $this->replicate($ebitNote, $toType, $invoiceChargeItems);
        }

        $callback = function () use ($ebitNote, $request, $newInvoices) {
            foreach ($newInvoices as $index => $invoice) {
                $this->setCode($invoice, $index);
            }
            foreach ($newInvoices as $invoice) {
                $invoice->setCreatedDate(new \DateTime());
                $this->repository->save($invoice, $request);
            }
            $this->repository->save($ebitNote, $request);
            /** @var ShipmentService $shipmentService */
            $shipmentService = $this->serviceLocator->get(ShipmentService::class);
            $shipmentService->calculateProfitLoss($ebitNote->getShipment());
            $this->repository->save($ebitNote->getShipment());
        };
        $this->repository->getEntityManager()->wrapInTransaction($callback);
    }

    public function cancelAllInvoices(EbitNote $ebitNote, Request $request): void
    {
        $invoices = $this->repository->getAllInvoices($ebitNote);
        $callback = function () use ($ebitNote, $request, $invoices) {
            foreach ($invoices as $invoice) {
                $this->repository->delete($invoice);
            }
            $ebitNote->setStatus(EbitNoteStatus::Pending);
            $this->repository->save($ebitNote, $request);
            /** @var ShipmentService $shipmentService */
            $shipmentService = $this->serviceLocator->get(ShipmentService::class);
            $shipmentService->calculateProfitLoss($ebitNote->getShipment());
            $this->repository->save($ebitNote->getShipment());
        };
        $this->repository->getEntityManager()->wrapInTransaction($callback);
    }

    public function checkRecord(EbitNote $entity): void
    {
        if (
            $entity->getType() !== EbitNoteType::RecordPayment
            && $entity->getType() !== EbitNoteType::RecordReceipt
        ) return;
        $this->checkParentRecord($entity->getParentNote());
    }

    public function checkParentRecord(EbitNote $parent): void
    {
        [$parentPaid,] = $this->repository->getPaidInvoice($parent);
        if ($parent->getAmount()->getAmount() <= (float) $parentPaid) {
            $parent->setStatus(EbitNoteStatus::Done);
            if ($parent->getParentNote()) {
                $parent->getParentNote()->setStatus(EbitNoteStatus::Done);
            }
        } else {
            $parent->setStatus(EbitNoteStatus::Active);
            if ($parent->getParentNote()) {
                $parent->getParentNote()->setStatus(EbitNoteStatus::Active);
            }
        }
        $this->repository->save($parent);
        if ($parent->getParentNote()) {
            $this->repository->save($parent->getParentNote());
        }
    }

    public function sendEmail(EbitNote $ebitNote, Request $request): void
    {
        $pdf = $this->getPdfByLanguage($ebitNote, $request, $request->request->get('language'));
        $result = $this->mailService->sendUsingUserSmtp(
            $request->request->get('toAddress'),
            $request->request->get('title'),
            'email/ebit.html.twig',
            [
                'content' => $request->request->get('content'),
                'ebit' => $ebitNote,
            ],
            EntityType::EbitNote,
            $ebitNote->getId(),
            $pdf
        );
        if ($result && $ebitNote->getStatus() === EbitNoteStatus::Pending) {
            $ebitNote->setStatus(EbitNoteStatus::Sent);
            $this->repository->save($ebitNote);
        }
    }

    public function downloadMonthlyPdf(Request $request): mixed
    {
        $type = EbitNoteType::from($request->request->get('type'));
        $month = $request->request->get('month');
        $language = $request->request->get('language');
        $ebitNotes = $this->repository->getByMonth($type, $month);
        $zippedMedia = $this->mediaService->repository->findOneBy([
            'type' => 'zip',
            'parentType' => $type->value . 'monthlyzip',
            'parentId' => str_replace('-', '', $month),
            'language' => $language,
        ]);
        $canUseZipped = !is_null($zippedMedia);
        $pdfMedias = [];
        foreach ($ebitNotes as $ebitNote) {
            if ($canUseZipped && $ebitNote->getCreatedDate() >= $zippedMedia->getCreatedDate()) {
                $canUseZipped = false;
            }
            $pdfMedias[] = $this->getPdfByLanguage($ebitNote, $request, $language);
        }
        if ($canUseZipped) {
            return $zippedMedia;
        }
        $zip = new \ZipArchive();
        $zipName = $type->value . '-' . $month . '.zip';
        if ($zip->open($zipName, ZipArchive::CREATE) == TRUE) {
            foreach ($pdfMedias as $pdfMedia) {
                $zip->addFromString($pdfMedia->getName(), $this->mediaStorage->read($pdfMedia->getPath()));
            }
            $zip->close();
        }
        $zippedMedia = $this->mediaService->saveString($zipName, file_get_contents($zipName), [
            'parentId' => (int) str_replace('-', '', $month),
            'parentType' => $type->value . 'monthlyzip',
            'parentProperty' => 'zip',
            'language' => $language,
        ]);
        unlink($zipName);
        return $zippedMedia;
    }

    public function updateExchangeRate(EbitNote $ebitNote, array $exchangeRates): void
    {
        $ebitNote->setExchangeRates($exchangeRates);
        $amountNoTax = 0;
        $tax = 0;
        foreach ($ebitNote->getChargeItems() as $chargeItem) {
            $this->changePriceRate($chargeItem, $exchangeRates);
            $amountNoTax += $chargeItem->getAmount()->getAmount();
            $tax += $chargeItem->getTax()->getAmount();
            $this->chargeItemRepository->save($chargeItem);
        }
        $currency = $ebitNote->getCurrency();
        $ebitNote->setAmountNoTax(new Money($amountNoTax, $currency, $exchangeRates[$currency]));
        $ebitNote->setTax(new Money($tax, $currency, $exchangeRates[$currency]));
        $ebitNote->setAmount(new Money($amountNoTax + $tax, $currency, $exchangeRates[$currency]));
        $langMap = ['VND' => 'vi', 'USD' => 'en', 'EUR' => 'en'];
        $words = NumberToWords::transformCurrency(
            $langMap[$currency] ?? 'en',
            $ebitNote->getAmount()->getAmount(),
            $currency
        );
        $ebitNote->setAmountInWords($words);
        $this->repository->save($ebitNote);
    }

    private function getPdfByLanguage(EbitNote $ebitNote, Request $request, string $language): mixed
    {
        $data = $this->pdfData($ebitNote, $request, $language);
        $data['renderMode'] = 'pdf';
        date_default_timezone_set($request->request->get('timezone'));
        $pdf = $this->mediaService->generatePdf($ebitNote, $data, $language, false);
        return $pdf;
    }

    public function pdfData(mixed $entity, Request $request, string $language): array
    {
        $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
        $companyInvoice = $company->getDefaultInvoiceInfo();
        $shipment = $entity->getShipment();
        $booking = $shipment->getBooking();
        $isRecord = $entity->getType() === EbitNoteType::RecordReceipt
            || $entity->getType() === EbitNoteType::RecordPayment;
        
        return [
            'company' => $company,
            'companyInvoice' => $companyInvoice,
            'ebitNote' => $entity,
            'booking' => $booking,
            'shipment' => $shipment,
            'quote' => $shipment->getQuote(),
            'basePath' => $this->requestStack->getCurrentRequest()->getUriForPath(''),
            'filename' => $entity->getCode() . '_' . $language . '.pdf',
            'template' => $isRecord ? 'pdf/record.html.twig' : 'pdf/ebitNote.html.twig',
        ];
    }

    private function changePriceRate(ChargeItem $entity, array $exchangeRates): void
    {
        $price = $entity->getPrice();
        if (is_null($price->getCurrency())) return;
        $priceMoney = new Money($price->getAmount(), $price->getCurrency(), $exchangeRates[$price->getCurrency()]);
        $entity->setPrice($priceMoney);
        $chargePrice = $entity->getChargePrice();
        $chargePriceMoney = new Money(
            $this->commonService->convertMoney($priceMoney, $chargePrice->getCurrency(), $exchangeRates[$chargePrice->getCurrency()]),
            $chargePrice->getCurrency(),
            $exchangeRates[$chargePrice->getCurrency()]
        );
        $entity->setChargePrice($chargePriceMoney);
        $amountMoney = new Money(
            $entity->getQuantity() * $chargePriceMoney->getAmount(),
            $chargePrice->getCurrency(),
            $exchangeRates[$chargePrice->getCurrency()]
        );
        $entity->setAmount($amountMoney);
        $taxCurrency = $entity->getTax()->getCurrency();
        $taxMoney = new Money(
            $amountMoney->getAmount() * $entity->getTaxGroup()->getAmount() / 100,
            $taxCurrency,
            $exchangeRates[$taxCurrency]
        );
        $entity->setTax($taxMoney);
    }
}
