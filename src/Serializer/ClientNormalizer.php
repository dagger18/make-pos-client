<?php
namespace App\Serializer;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\Client;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ClientNormalizer implements NormalizerInterface
{
  public function __construct(
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
      
      if($object instanceof Client) {
        $names = $object->getAssignedUsers()->reduce(function ($resultArray, User $user) {
            return array_merge($resultArray, [$user->getFullName()]) ;
        }, []);
        $data['assignedUsersCount'] = implode(', ', $names);
      }
      
      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        Client::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof Client 
      ;
  }
}