<?php
namespace App\Serializer;
use App\Module\Core\Entity\User;
use App\Module\Core\Entity\UserGroup;
use App\Module\Operations\Entity\ShipmentActivity;
use App\Module\Operations\Service\ShipmentService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ShipmentActivityNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Lazy]
    protected ShipmentService $shipmentService,
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,

  ) {
  }

  public function normalize($object, string $format = null, array $context = []): array
  {
      $data = $this->normalizer->normalize(
        $object,
        $format,
        $context
      );

      $data['shipment'] = [
        'code' => $this->shipmentService
          ->repository->find($object->getShipmentId())->getCode()
      ];

      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        ShipmentActivity::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof ShipmentActivity 
      ;
  }
}