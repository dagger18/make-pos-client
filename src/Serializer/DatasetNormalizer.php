<?php
namespace App\Serializer;
use App\Module\Reporting\Entity\Dataset;
use App\Module\Core\Entity\Component;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class DatasetNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,

  ) {
  }

  public function normalize($object, string $format = null, array $context = []): array
  {
      $pageCallback = function (object $innerObject, object $outerObject, string $attributeName, string $format = null, array $context = []): ?array {
          return [];
      };
      $data = $this->normalizer->normalize(
        $object, 
        $format, 
        [
          ...$context, 
          AbstractNormalizer::CALLBACKS => [
            'components' => $pageCallback
          ],
        ]
      );
      
      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        Dataset::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof Dataset
      ;
  }
}