<?php
namespace App\Module\Operations\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Enum\EntityType;
use App\Module\Operations\Enum\ShipmentActivityType;
use App\Module\Operations\Repository\ShipmentActivityRepository;
use Symfony\Component\HttpFoundation\RequestStack;

class ShipmentActivityService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ShipmentActivityRepository $repository,
        protected RequestStack $requestStack,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function addActivity(
        int $shipmentId,
        ShipmentActivityType $type,
        ?EntityType $sub = null,
        int|string|null $subId = null,
        ?array $changes = null
    ): void {
        $request = $this->requestStack->getCurrentRequest();
        $routeName = $request?->attributes->get('_route') ?? '';
        if (str_ends_with($routeName, 'shipment_delete') && $type !== ShipmentActivityType::Delete) {
            return;
        }

        $record = $this->repository->findOrCreate($shipmentId);
        $record->append(
            $type->value,
            $sub?->value,
            $subId !== null ? (string) $subId : null,
            $changes ?: null,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $this->getUser()->getId()
        );
        $this->repository->save($record);
    }
}
