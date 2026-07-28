<?php
namespace App\Serializer;
use App\Module\Core\Entity\Media;
use App\Module\Core\Service\MediaService;
use App\Module\Core\Service\UserService;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class MediaNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Lazy]
    protected MediaService $mediaService,
    #[Lazy]
    protected UserService $userService,
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,
    protected CacheManager $cacheManager,
  ) {
  }

  public function normalize($object, ?string $format = null, array $context = []): array
  {
      $data = $this->normalizer->normalize($object, $format, $context);
      $data['thumbnailPath'] = $data['path'] ? $this->cacheManager->getBrowserPath($data['path'], 'thumbnail') : null;
      unset($data['path']);

      if (!empty($data['createdBy'])) {
          $user = $this->userService->repository->find($data['createdBy']);
          if ($user) {
              $logoPath = $user->getLogo()?->getPath();
              $data['createdBy'] = [
                  'id'        => $user->getId(),
                  'firstName' => $user->getFirstName(),
                  'lastName'  => $user->getLastName(),
                  'fullName'  => $user->getFullName(),
                  'logo'      => [
                      'thumbnailPath' => $logoPath
                          ? $this->cacheManager->getBrowserPath($logoPath, 'thumbnail')
                          : null,
                  ],
              ];
          }
      }

      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
          Media::class => true
      ];
  }

  public function supportsNormalization($data, ?string $format = null, array $context = []): bool
  {
      return $data instanceof Media;
  }
}
