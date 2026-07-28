<?php
namespace App\Serializer;

use App\Module\Operations\Entity\Booking;
use App\Module\Operations\Entity\Shipment;
use App\Module\Core\Entity\UserGroup;
use App\Module\Core\Enum\Magnum;
use Psr\Log\LoggerInterface;
use App\Module\Core\Enum\TransportType;
use App\Module\Operations\Enum\ShipmentStatus;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class BookingNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,
    protected LoggerInterface $logger,
    protected RequestStack $requestStack,
  ) {
  }
  /** @param Booking $object */
  public function normalize($object, string $format = null, array $context = []): array
  {
      $data = $this->normalizer->normalize(
        $object, 
        $format, 
        $context
      );
      $request = $this->requestStack->getCurrentRequest();
      if($request->query->has('timezone')) {
        $etd = (new \DateTime( $data['etd']))->setTimezone(new \DateTimeZone($request->query->get('timezone')));
        $data['etdText'] = $etd->format('d-M');
        $eta = (new \DateTime( $data['eta']))->setTimezone(new \DateTimeZone($request->query->get('timezone')));
        $data['etaText'] = $eta->format('d-M');
      }
      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
          Booking::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof Booking 
        && isset($context['groups'])
        && $context['groups'] === 'list'
      ;
  }
}