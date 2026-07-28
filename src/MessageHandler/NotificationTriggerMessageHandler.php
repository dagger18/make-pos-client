<?php
namespace App\MessageHandler;

use App\Message\NotificationTriggerMessage;
use App\Module\Operations\Repository\ShipmentMilestoneRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Notification\Service\NotificationGeneratorService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NotificationTriggerMessageHandler
{
    public function __construct(
        private readonly NotificationGeneratorService $generator,
        private readonly ShipmentMilestoneRepository  $milestoneRepository,
        private readonly ShipmentRepository           $shipmentRepository,
    ) {}

    public function __invoke(NotificationTriggerMessage $message): void
    {
        match ($message->eventType) {
            'milestone.created'       => $this->handleMilestone($message->entityId),
            'shipment.status_changed' => $this->handleStatusChange($message->entityId, $message->context),
            default                   => null,
        };
    }

    private function handleMilestone(int $id): void
    {
        $milestone = $this->milestoneRepository->find($id);
        if ($milestone) {
            $this->generator->handleMilestone($milestone);
        }
    }

    private function handleStatusChange(int $id, array $context): void
    {
        $shipment = $this->shipmentRepository->find($id);
        if ($shipment) {
            $this->generator->handleStatusChange(
                $shipment,
                $context['oldStatus'] ?? '',
                $context['newStatus'] ?? ''
            );
        }
    }
}
