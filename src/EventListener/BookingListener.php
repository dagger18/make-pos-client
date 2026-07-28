<?php
namespace App\EventListener;

use App\Module\Operations\Entity\Booking;
use App\Module\Core\Enum\EntityType;
use App\Module\Operations\Enum\ShipmentActivityType;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Operations\Service\BookingService;
use App\Module\Operations\Service\ShipmentActivityService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsEntityListener(event: Events::postUpdate, entity: Booking::class)]
class BookingListener
{
    public function __construct(
        protected BookingService $bookingService,
        protected ShipmentActivityService $shipmentActivityService,
        protected ShipmentRepository $shipmentRepository,
        protected LoggerInterface $logger,
        protected RequestStack $requestStack,
    ) {}
    
    public function postUpdate(Booking $entity, PostUpdateEventArgs $args): void
    {

        $request = $this->requestStack->getCurrentRequest();
        if(str_ends_with($request->attributes->get('_route'), 'api_quote_update')) return;
        $shipmentId = $request->request->get('parentId')
            ?? $this->shipmentRepository->getShipmentFromBooking($entity->getId());
        if ($shipmentId === null) return;
        $entityManager = $args->getObjectManager();
        /** @var EntityManager $entityManager */
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($entity);
        // $this->logger->info('changeSet', [$changeSet]);
        
        unset($changeSet['updatedDate']);
        unset($changeSet['createdDate']);
        $changeSet = $this->bookingService->normalizeChangeSet($changeSet, $entity);
        if(empty($changeSet)) return;
        $this->shipmentActivityService->addActivity(
            $shipmentId,
            ShipmentActivityType::SubUpdate, 
            EntityType::Booking, 
            null,
            $changeSet
        );
        
    }
}