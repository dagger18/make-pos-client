<?php
namespace App\Serializer;
use App\Module\Finance\Entity\ChargeItem;
use App\Module\Finance\Entity\ExchangeRate;
use App\Module\Finance\Service\ExchangeRateGroupService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ChargeItemNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Lazy]
    protected ExchangeRateGroupService $exchangeRateGroupService,
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,
    protected RequestStack $requestStack,
  ) {
  }

  public function normalize($object, string $format = null, array $context = []): array
  {
    $request = $this->requestStack->getCurrentRequest();
    $timezone = new \DateTimeZone(
      $request->query->has('timezone') 
      ? $request->query->get('timezone') : 'Asia/Ho_Chi_Minh'
    );
    $currency = $context['reportCurrency'] ?? 'VND';
    $exchangeRateGroupService = $this->exchangeRateGroupService;
    $ebitNoteCallback = function (object $innerObject, object $outerObject, string $attributeName, string $format = null, array $context = []): ?array {
        return [
          'id' => $innerObject->getId(),
          'code' => $innerObject->getCode(),
          'invoiceInfo' => $innerObject->getInvoiceInfo(),
          'paymentMethod' => $innerObject->getPaymentMethod(),
        ];
    };
    $data = $this->normalizer->normalize(
      $object, 
      $format, 
      [
        ...$context, 
        AbstractNormalizer::CALLBACKS => [
          'ebitNote' => $ebitNoteCallback,
        ],
      ]
    );
    $data['chargePriceText'] = $exchangeRateGroupService->formatMoney(
      $object->getChargePrice(), 
      $currency, 
      $object->getEbitNote()->getExchangeRates()
    );
    $data['amountText'] = $exchangeRateGroupService->formatMoney(
      $object->getAmount(), 
      $currency, 
      $object->getEbitNote()->getExchangeRates()
    );
    $data['taxText'] = $exchangeRateGroupService->formatMoney(
      $object->getTax(), 
      $currency, 
      $object->getEbitNote()->getExchangeRates()
    );
    $noteDate = (clone $object->getEbitNote()->getNoteDate())->setTimezone($timezone);
    $data['noteDateText'] = $noteDate->format('d/m/Y');
    return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        ChargeItem::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof ChargeItem
      ;
  }
}