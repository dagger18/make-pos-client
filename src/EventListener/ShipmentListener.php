<?php
namespace App\EventListener;

use App\Module\Operations\Entity\Shipment;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use App\Module\Core\Enum\EntityType;
use Doctrine\ORM\EntityManager;
use App\Module\Carrier\Enum\MilestoneCode;
use App\Module\Operations\Service\ShipmentService;
use App\Module\Operations\Enum\ShipmentStatus;
use Doctrine\ORM\PersistentCollection;
use App\Module\Operations\Enum\ShipmentActivityType;
use App\Module\Operations\Service\ShipmentActivityService;
use App\Module\Operations\Service\ShipmentMilestoneService;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::postUpdate, entity: Shipment::class)]
#[AsEntityListener(event: Events::postPersist, entity: Shipment::class)]
#[AsEntityListener(event: Events::postRemove, entity: Shipment::class)]
class ShipmentListener
{
    public function __construct(
        protected ShipmentService $shipmentService,
        protected ShipmentActivityService $shipmentActivityService,
        protected ShipmentMilestoneService $milestoneService,
        protected LoggerInterface $logger,
        protected RequestStack $requestStack,
    ) {
    }
    public function postPersist(Shipment $entity, PostPersistEventArgs $args): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $routeName = $request->attributes->get('_route');
        $type = null;
        $subEntityType = null;
        $subEntityId = null;
        // from quote
        if($request->request->has('makeShipment')
            || str_ends_with($routeName, 'makeshipment')
        ) {
            $type = ShipmentActivityType::Create;
            $subEntityType = EntityType::Quote;
            $subEntityId = $entity->getQuote()->getCode();
        }
        if(str_ends_with($routeName, 'replicate')) {
            $fromShipment = $this->shipmentService->repository->find((int) $request->attributes->get('id'));
            $type = ShipmentActivityType::Replicate;
            $subEntityType = EntityType::Shipment;
            $subEntityId = $fromShipment->getCode() ?? $fromShipment->getId();
        }
        //$this->logger->info('shipment listener', [$entity, $request, $activity, $request->attributes->get('id')]);
        $this->shipmentActivityService->addActivity(
            $entity->getId(),
            $type,
            $subEntityType,
            $subEntityId
        );

        $this->milestoneService->autoWrite(
            $entity,
            MilestoneCode::JobCreated,
            $entity->getCreatedDate() ?? new \DateTime(),
            'SYSTEM'
        );
    }
    public function postUpdate(Shipment $entity, PostUpdateEventArgs $args): void
    {
        if ($entity->noLog) {
            return;
        }
        /** @var EntityManager $entityManager */
        $entityManager = $args->getObjectManager();

        // set code for shipment when change status from pending => active
        /** @var PersistentCollection $documents */
        $documents = $entity->getDocuments();
        if ($documents->isDirty()) {
            $inserts = $documents->getInsertDiff();
            if(count($inserts) > 0) {
                $this->shipmentActivityService->addActivity(
                    $entity->getId(),
                    ShipmentActivityType::SubCreate,
                    EntityType::Document,
                    $inserts[0]->getName()
                );
                return;
            }
            return;
        }
        /** @var PersistentCollection $users */
        $users = $entity->getAssignedUsers();
        if ($users->isDirty()) {
            $inserts = $users->getInsertDiff();
            if(count($inserts) > 0) {
                $names = array_map(function($us) {
                    return $us->getFullName();
                }, $inserts);
                $this->shipmentActivityService->addActivity(
                    $entity->getId(),
                    ShipmentActivityType::SubCreate,
                    EntityType::User,
                    implode(', ', $names)
                );
                return;
            }
            return;
        }
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($entity);
        // $this->logger->info('changeSet', [$changeSet]);
        if(isset($changeSet['status'])) {
            list($oldStatus, $newStatus) = $changeSet['status'];

            $this->logger->info('statuses', [$newStatus, $oldStatus]);
            if(
                ($oldStatus === ShipmentStatus::Pending->value
                || $oldStatus === ShipmentStatus::Draft->value)
                && $newStatus === ShipmentStatus::Active->value
                && is_null($entity->getCode())
            ) {
                $this->logger->info('do set Code', [$entity]);
                $this->shipmentService->setCode($entity);
                $this->shipmentService->subscribeAllUserAssignedToClient($entity);

            }
            if(
                $oldStatus === ShipmentStatus::Active->value
                && $newStatus === ShipmentStatus::Completed->value
            ) {
                $entity->setCompletedDate(new \DateTime());
                $this->shipmentService->setCommission($entity);
                $this->milestoneService->autoWrite($entity, MilestoneCode::JobClosed, new \DateTime(), 'SYSTEM');
            }
            $this->shipmentActivityService->addActivity(
                $entity->getId(),
                ShipmentActivityType::UpdateStatus,
                null,
                null,
                [$oldStatus,$newStatus]
            );

        } else {
            if (isset($changeSet['subStatus'])) {
                [$oldSub, $newSub] = $changeSet['subStatus'];
                $this->shipmentActivityService->addActivity(
                    $entity->getId(),
                    ShipmentActivityType::UpdateSubStatus,
                    null,
                    null,
                    [
                        is_string($oldSub) ? $oldSub : $oldSub?->value,
                        is_string($newSub) ? $newSub : $newSub?->value,
                    ]
                );
                return;
            }
            if (isset($changeSet['isOnHold'])) {
                [, $newOnHold] = $changeSet['isOnHold'];
                $this->shipmentActivityService->addActivity(
                    $entity->getId(),
                    ShipmentActivityType::UpdateHold,
                    null,
                    $newOnHold ? $entity->getHoldReason() : null,
                    [$changeSet['isOnHold'][0], $changeSet['isOnHold'][1]]
                );
                return;
            }
            unset($changeSet['updatedDate']);
            unset($changeSet['code']);
            unset($changeSet['completedDate']);
            if(empty($changeSet)) {
                return;
            }
            $normalizedChangeSet = $this->shipmentService->normalizeChangeSet($changeSet, $entity);
            if(empty($normalizedChangeSet)) {
                return;
            }
            $this->shipmentActivityService->addActivity(
                $entity->getId(),
                ShipmentActivityType::Update,
                null,
                null,
                $normalizedChangeSet
            );
        }

    }
    public function postRemove(Shipment $entity, PostRemoveEventArgs $args): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $this->shipmentActivityService->addActivity(
            (int) $request->attributes->get('id'),
            ShipmentActivityType::Delete,
            null,
            $entity->getCode()
        );
    }
}
