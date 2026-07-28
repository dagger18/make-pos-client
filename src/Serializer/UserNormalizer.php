<?php
namespace App\Serializer;
use App\Module\Core\Entity\User;
use App\Module\Core\Entity\UserGroup;
use App\Module\Core\Service\UserService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class UserNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Lazy]
    protected UserService $userService,
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

      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
        User::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof User 
      ;
  }
}