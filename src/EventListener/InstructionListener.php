<?php
namespace App\EventListener;

use Doctrine\ORM\Events;
use App\Module\Operations\Entity\Instruction;
use Psr\Log\LoggerInterface;
use App\Module\Core\Enum\EntityType;
use Doctrine\ORM\EntityManager;
use App\Module\Operations\Service\InstructionService;
use App\Module\Operations\Enum\ShipmentActivityType;
use App\Module\Operations\Service\ShipmentActivityService;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;

#[AsEntityListener(event: Events::postUpdate, entity: Instruction::class)]
class InstructionListener
{
    public function __construct(
        protected InstructionService $instructionService,
        protected ShipmentActivityService $shipmentActivityService,
        protected LoggerInterface $logger,
        protected RequestStack $requestStack,
    ) {}

    public function postUpdate(Instruction $entity, PostUpdateEventArgs $args): void
    {
        return;
        $request = $this->requestStack->getCurrentRequest();
        $shipmentId = $request->request->get('parentId');
        /** @var EntityManager $entityManager */
        $entityManager = $args->getObjectManager();
        $changeSet = $entityManager->getUnitOfWork()->getEntityChangeSet($entity);
        // $this->logger->info('changeSet', [$changeSet]);

        unset($changeSet['updatedDate']);
        unset($changeSet['createdDate']);
        $changeSet = $this->instructionService->normalizeChangeSet($changeSet, $entity);
        if(empty($changeSet)) return;
        $this->shipmentActivityService->addActivity(
            $shipmentId,
            ShipmentActivityType::SubUpdate,
            EntityType::Instruction,
            null,
            $changeSet
        );

    }
}
