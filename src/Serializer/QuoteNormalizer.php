<?php
namespace App\Serializer;
use App\Module\Quote\Entity\Quote;
use App\Module\Finance\Entity\ExchangeRate;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class QuoteNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,

  ) {
  }

  public function normalize($object, string $format = null, array $context = []): array
  {
      $shipmentCallback = function (?object $innerObject, object $outerObject, string $attributeName, string $format = null, array $context = []): ?array {
          if(!$innerObject) return null;
          return ['id' => $innerObject->getId()];
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
      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        Quote::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof Quote
      ;
  }
}