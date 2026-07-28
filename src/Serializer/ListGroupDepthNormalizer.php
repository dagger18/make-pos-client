<?php
namespace App\Serializer;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[AutoconfigureTag('serializer.normalizer', ['priority' => 10])]
class ListGroupDepthNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const PROCESSED = '_list_depth_normalizer_processed';

    public function getSupportedTypes(?string $format): array
    {
        return ['object' => false];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        $raw = $context['groups'] ?? null;
        $groups = is_array($raw) ? $raw : (array) $raw;
        return is_object($data)
            && in_array('list', $groups, true)
            && !isset(($context[self::PROCESSED] ?? [])[spl_object_id($data)]);
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $depth = $context['_list_depth'] ?? 0;

        $context[self::PROCESSED][spl_object_id($object)] = true;

        if ($depth >= 1) {
            $groups = is_array($context['groups'] ?? null) ? $context['groups'] : [$context['groups']];
            $context['groups'] = array_map(fn(string $g) => $g === 'list' ? 'subList' : $g, $groups);
        }

        $context['_list_depth'] = $depth + 1;

        return $this->normalizer->normalize($object, $format, $context);
    }
}
