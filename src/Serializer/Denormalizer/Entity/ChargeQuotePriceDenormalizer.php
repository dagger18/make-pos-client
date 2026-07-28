<?php
namespace App\Serializer\Denormalizer\Entity;
use App\Module\Core\Entity\User;
use App\Module\Finance\Entity\Charge;
use App\Module\Finance\Service\ChargeService;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ChargeQuotePriceDenormalizer implements DenormalizerInterface
{
    public function __construct(
        #[Lazy]
        protected ChargeService $chargeService,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed {
        if($data === 'isNull') {
            return null;
        }
        $chargeRepository = $this->chargeService->repository;
        return $chargeRepository->find($data);
    }
    public function getSupportedTypes(?string $format): array
    {
        return [
            Charge::class => true
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null  , array $context = [] ): bool
    {
        return 
            isset($context['deserialization_path'])
            && str_starts_with($context['deserialization_path'], 'prices[')
            && $context['value_type']->getClassName() === 'App\Entity\QuotePrice'
            && $type === Charge::class
        ;
    }
}