<?php
namespace App\Serializer\Denormalizer\Entity;
use App\Module\Core\Entity\User;
use App\Module\Finance\Entity\Charge;
use App\Module\Finance\Entity\ChargeItem;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;

class ChargeItemDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    public function __construct(
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed {
        $data['position'] = null;
        return $this->denormalizer->denormalize(
            $data, 
            'App\Entity\ChargeItem', null,
            [
                ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true
            ]
        );;
    }
    public function getSupportedTypes(?string $format): array
    {
        return [
            ChargeItem::class => true
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null  , array $context = [] ): bool
    {
        //header('Access-Control-Allow-Origin: *');dd($context);
        return $type === ChargeItem::class
            && isset($data['position']) && $data['position'] === ''
        ;
    }
}