<?php
namespace App\EventListener;

use App\Module\Finance\Entity\EbitNote;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use App\Module\Core\Enum\EntityType;
use Doctrine\ORM\EntityManager;
use App\Module\Finance\Service\EbitNoteService;
use App\Module\Operations\Enum\ShipmentActivityType;
use App\Module\Operations\Service\ShipmentActivityService;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::postUpdate, entity: EbitNote::class)]
#[AsEntityListener(event: Events::postPersist, entity: EbitNote::class)]
#[AsEntityListener(event: Events::postRemove, entity: EbitNote::class)]
class EbitNoteListener
{
    public function __construct(
        protected EbitNoteService $ebitNoteService,
        protected ShipmentActivityService $shipmentActivityService,
        protected LoggerInterface $logger,
        protected RequestStack $requestStack,
    ) {

    }
    public function postPersist(EbitNote $entity, PostPersistEventArgs $args): void
    {
        $this->shipmentActivityService->addActivity(
            $entity->getShipment()->getId(),
            ShipmentActivityType::SubCreate,
            EntityType::EbitNote,
            $entity->getCode()
        );
    }
    public function postUpdate(EbitNote $entity, PostUpdateEventArgs $args): void
    {
        /** @var EntityManager $entityManager */
        $entityManager = $args->getObjectManager();
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($entity);
        // $this->logger->info('changeSet', [$changeSet]);
        unset($changeSet['updatedDate']);
        if(empty($changeSet)) {
            return;
        }
        $normalizedChangeSet = $this->ebitNoteService->normalizeChangeSet($changeSet, $entity);
        if(empty($normalizedChangeSet)) {
            return;
        }
        $this->shipmentActivityService->addActivity(
            $entity->getShipment()->getId(),
            ShipmentActivityType::SubUpdate,
            EntityType::EbitNote,
            $entity->getCode(),
            $normalizedChangeSet
        );
    }
    public function postRemove(EbitNote $entity, PostRemoveEventArgs $args): void
    {
        $this->shipmentActivityService->addActivity(
            $entity->getShipment()->getId(),
            ShipmentActivityType::SubDelete,
            EntityType::EbitNote,
            $entity->getCode()
        );

    }
}
