<?php 

namespace App\Serializer\Denormalizer;

use App\Module\Reporting\Entity\DatasetFilter;

use App\Module\Core\Entity\ComponentSerie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class DoctrineEntityDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    
    use DenormalizerAwareTrait;
    public function __construct(
        private ContainerInterface $container
    ) {
    }
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if (str_ends_with($type, '[]')) {
            return false;
        }
        $isEntityType = strpos($type, 'App\\Entity\\') === 0
            || (strpos($type, 'App\\') === 0 && str_contains($type, '\\Entity\\'));
        $isId = is_int($data) || (is_string($data) && ctype_digit($data));
        return $isEntityType && $isId;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->container->get('doctrine.orm.entity_manager')->getReference($type, (int) $data);
    }

    

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true];
    }
}