<?php
namespace App\EventListener;

use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Entity\ShipmentMilestone;
use App\Message\NotificationTriggerMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class NotificationEventListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof ShipmentMilestone && $entity->getId()) {
            $this->bus->dispatch(new NotificationTriggerMessage(
                'milestone.created',
                $entity->getId()
            ));
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!($entity instanceof Shipment)) {
            return;
        }
        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($entity);
        if (!isset($changeSet['status'])) {
            return;
        }
        [$oldStatus, $newStatus] = $changeSet['status'];
        $this->bus->dispatch(new NotificationTriggerMessage(
            'shipment.status_changed',
            $entity->getId(),
            [
                'oldStatus' => $oldStatus?->value ?? (string) $oldStatus,
                'newStatus' => $newStatus?->value ?? (string) $newStatus,
            ]
        ));
    }
}
