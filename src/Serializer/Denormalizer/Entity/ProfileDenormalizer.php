<?php
namespace App\Serializer\Denormalizer\Entity;
use App\Module\Core\Entity\User;
use App\Module\Core\Service\CommonService;
use App\Module\Core\Service\UserService;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ProfileDenormalizer implements DenormalizerInterface
{
    public function __construct(
        #[Lazy]
        protected UserService $userService,
        #[Lazy]
        protected CommonService $commonService
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed {
        $userRepository = $this->userService->repository;
        $entity = $context[AbstractNormalizer::OBJECT_TO_POPULATE]
            ?? $userRepository->find($data['id']);
        $props = [
            'firstName', 'lastName', 'title', 'phone',
            'smtpServer', 'smtpPort', 'smtpUsername', 'smtpPassword',
            'tableConfig'
        ];
        forEach($props as $prop) {
            if(isset($data[$prop])) {
                if($prop === 'smtpPassword') {
                    if($data[$prop] === '*********') 
                        continue;
                    $data[$prop] = $this->commonService->encrypt($data[$prop]);
                }
                $entity->{'set' . ucfirst($prop)}($data[$prop]);
            }
        }
        return $entity;
    }
    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true
        ];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return 
            (
                (isset($context[0]) && $context[0] === 'UPDATING_PROFILE')
                || (isset($context[1]) && $context[1] === 'UPDATING_PROFILE')
            )
            && $type === User::class
        ;
    }
}