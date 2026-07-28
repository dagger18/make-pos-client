<?php
namespace App\Serializer;
use App\Module\Quote\Entity\QuotePrice;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class QuotePriceNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,

  ) {
  }

  public function normalize($object, string $format = null, array $context = []): array
  {
      $quoteCallback = function (object $innerObject, object $outerObject, string $attributeName, string $format = null, array $context = []): array {
          return ['id' => $innerObject->getId()];
      };
      $data = $this->normalizer->normalize(
        $object, 
        $format, 
        [
          ...$context, 
          AbstractNormalizer::CALLBACKS => [
            'quote' => $quoteCallback,
          ],
        ]
      );
      
      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        QuotePrice::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof QuotePrice
      ;
  }
}