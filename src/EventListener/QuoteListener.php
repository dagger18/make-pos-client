<?php
namespace App\EventListener;

use App\Module\Quote\Entity\Quote;
use Doctrine\ORM\Events;
use App\Module\Quote\Entity\QuotePrice;
use Psr\Log\LoggerInterface;
use App\Module\Core\Enum\EntityType;
use App\Module\Quote\Service\QuoteService;
use App\Module\Operations\Service\BookingService;
use Doctrine\ORM\EntityManager;
use App\Module\Operations\Service\InstructionService;
use App\Module\Operations\Repository\BookingRepository;
use Doctrine\ORM\PersistentCollection;
use App\Module\Operations\Enum\ShipmentActivityType;
use App\Module\Operations\Service\ShipmentActivityService;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::postUpdate, entity: Quote::class)]
class QuoteListener
{
    public function __construct(
        protected QuoteService $quoteService,
        protected BookingService $bookingService,
        protected InstructionService $instructionService,
        protected ShipmentActivityService $shipmentActivityService,
        protected LoggerInterface $logger,
        protected RequestStack $requestStack,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'serializer.normalizer.object')]
        protected ObjectNormalizer $normalizer,
    ) {}

    public function postUpdate(Quote $entity, PostUpdateEventArgs $args): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $shipmentId = $request->request->get('shipment');
        $this->logger->info('checking request', [$request->attributes->get('_route')]);
        if($request->request->has('makeShipment')
            || str_ends_with($request->attributes->get('_route'), 'makeshipment')
        ) return;
        /** @var EntityManager $entityManager */
        $entityManager = $args->getObjectManager();
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($entity);
        $this->logger->info('changeSet quote', [$changeSet]);
        // on quote update, reflect some props to booking ??
        $this->bookingService->reflectToBookingOnUpdateQuote($entity, $changeSet);
        $instruction = $entity->getShipment()->getInstruction();
        $this->quoteService->reflectToInstruction($instruction, $entity);
        $this->instructionService->repository->save($instruction);
        unset($changeSet['updatedDate']);
        unset($changeSet['createdDate']);
        $changeSet = $this->quoteService->normalizeChangeSet($changeSet, $entity);

        /** @var PersistentCollection $prices */
        $prices = $entity->getPrices();
        if ($prices->isDirty()) {
            $olds = [];
            forEach($prices->getSnapshot() as $old) {
                $olds[] = $this->normalizeQuotePrice($old);
            }
            $news = [];
            forEach($prices as $new) {
                $news[] = $this->normalizeQuotePrice($new);
            }
            $changeSet['prices'] = [$olds, $news];
        }
        if(empty($changeSet)) return;
        $this->shipmentActivityService->addActivity(
            $entity->getShipment()->getId(),
            ShipmentActivityType::SubUpdate,
            EntityType::Quote,
            null,
            $changeSet
        );

    }
    public function normalizeQuotePrice(QuotePrice $quotePrice) {
        $idCallback = function (?object $innerObject): ?int {
            return $innerObject ? $innerObject->getId() : null;
        };
        return $this->normalizer->normalize($quotePrice, null, [
            AbstractNormalizer::CALLBACKS => [
            'quote' => $idCallback,
            'charge' => $idCallback,
            'provider' => $idCallback,
            'createdBy' => $idCallback,
            ]
        ]);
    }
}
