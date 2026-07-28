<?php
namespace App\Serializer;
use App\Module\Finance\Entity\ChargeItem;
use App\Module\Finance\Entity\EbitNote;
use App\Module\Finance\Entity\ExchangeRate;
use App\Module\Core\Entity\Money;
use App\Module\Operations\Entity\Shipment;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Finance\Repository\EbitNoteRepository;
use App\Module\Finance\Service\EbitNoteService;
use App\Module\Finance\Service\ExchangeRateGroupService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Contracts\Translation\TranslatorInterface;

class EbitNoteNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Lazy]
    protected ExchangeRateGroupService $exchangeRateGroupService,
    #[Lazy]
    protected EbitNoteService $ebitNoteService,
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,
    protected LoggerInterface $logger,
    protected RequestStack $requestStack,
    protected TranslatorInterface $translator,
  ) {
  }

  public function normalize($object, string $format = null, array $context = []): array
  {
      $currency = $context['reportCurrency'] ?? 'VND';
      $request = $this->requestStack->getCurrentRequest();
      $timezone = new \DateTimeZone(
                    $request->query->has('timezone')
                    ? $request->query->get('timezone') : 'Asia/Ho_Chi_Minh'
                  );
      $exchangeRateGroupService = $this->exchangeRateGroupService;
      $shipmentCallback = function (object $innerObject, object $outerObject, string $attributeName, string $format = null, array $context = []) use ($timezone): array {
          /** @var Shipment $innerObject */
          $etdDate = $innerObject->getBooking()->getEtd() ? (clone $innerObject->getBooking()->getEtd())->setTimezone($timezone) : null;
          return [
            'id' => $innerObject->getId(),
            'code' => $innerObject->getCode(),
            'accountManager' => $innerObject->getAccountManager()->getId(),
            'createdBy' => $innerObject->getCreatedBy()->getId(),
            'accountManagerName' => $innerObject->getAccountManager()->getFullName(),
            'etd' => $innerObject->getBooking()->getEtd(),
            'etdText' => $etdDate ? $etdDate->format('d/m/Y H:i') : null,
            'masterBill' => $innerObject->getMasterBill()
          ];
      };
      $data = $this->normalizer->normalize(
        $object, 
        $format, 
        [
          ...$context, 
          AbstractNormalizer::CALLBACKS => [
            'shipment' => $shipmentCallback,
          ],
        ]
      );
      if($object->getType() === EbitNoteType::InvoiceDebit
        || $object->getType() === EbitNoteType::InvoiceCredit
        || $object->getType() === EbitNoteType::POBO
        || $object->getType() === EbitNoteType::COBO
      ) {
        list($paid, $lastestPaidDate) = $this->ebitNoteService->repository->getPaidInvoice($object);
        $data['paid'] = (float) $paid;
        $data['lastestPaidDate'] = empty($lastestPaidDate) ? '' : str_replace(' ', 'T', $lastestPaidDate.'+00:00');
        $data['remains'] = $object->getAmount()->getAmount() - $data['paid'];
        $data['paidText'] = $exchangeRateGroupService->formatMoney(
          new Money($data['paid'], $object->getAmount()->getCurrency(), $object->getAmount()->getRate()), 
          $currency, 
          $object->getExchangeRates()
        );
        $data['remainsText'] = $exchangeRateGroupService->formatMoney(
          new Money($data['remains'], $object->getAmount()->getCurrency(), $object->getAmount()->getRate()), 
          $currency, 
          $object->getExchangeRates()
        );
      }
      $data['amountNoTaxText'] = $exchangeRateGroupService->formatMoney(
        $object->getAmountNoTax(), 
        $currency, 
        $object->getExchangeRates()
      );
      $data['taxText'] = $exchangeRateGroupService->formatMoney(
        $object->getTax(), 
        $currency, 
        $object->getExchangeRates()
      );
      $data['amountText'] = $exchangeRateGroupService->formatMoney(
        $object->getAmount(), 
        $currency, 
        $object->getExchangeRates()
      );
      $data['createdBy']['name'] = $object->getCreatedBy()->getFullName();
      $data['statusText'] = $object->getStatus()->trans($this->translator);
      $noteDate = (clone $object->getNoteDate())->setTimezone($timezone);
      $data['noteDateText'] = $noteDate->format('d/m/Y H:i');
      $dueDate = (clone $object->getDueDate())->setTimezone($timezone);
      $data['dueDateText'] = $dueDate->format('d/m/Y H:i');
      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        EbitNote::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof EbitNote
      ;
  }
}