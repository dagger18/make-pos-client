<?php
namespace App\Serializer\Denormalizer;
use App\Module\Core\Entity\Department;
use App\Module\Core\Service\DepartmentService;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class RelationNullableDenormalizer implements DenormalizerInterface
{
    public function __construct(
        #[Lazy]
        protected DepartmentService $departmentService,
    ) {
    }
    private $supportedClasses = [
        Department::class => 'department',
    ];
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed {
        if($data === 'isNull' || $data === '' || $data === null) {
            return null;
        }
        $serviceMap = [
            Department::class => $this->departmentService,
        ];
        $repo = $serviceMap[$type]->repository;
        $id = is_array($data) ? ($data['id'] ?? null) : $data;
        return $id ? $repo->find($id) : null;
    }
    public function getSupportedTypes(?string $format): array
    {
        return array_combine(
            array_keys($this->supportedClasses),
            array_fill(0, count($this->supportedClasses), true)
        );
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null  , array $context = [] ): bool
    {
        if(!isset($context['deserialization_path'])) return false;
        forEach($this->supportedClasses as $class => $property) {
            if($class === $type && $property === $context['deserialization_path']) {
                return true;
            }
        }
        return false;
    }
}
