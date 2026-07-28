<?php
namespace App\Module\Carrier\Service;

use App\Module\Operations\Entity\ShipmentMilestone;
use App\Module\Carrier\Entity\TrackingRequest;
use App\Module\Carrier\Enum\MilestoneCode;
use App\Module\Carrier\Repository\CarrierEventMappingRepository;
use App\Module\Operations\Repository\ShipmentMilestoneRepository;

class TrackingMilestoneWriterService
{
    public function __construct(
        private readonly CarrierEventMappingRepository $mappingRepository,
        private readonly ShipmentMilestoneRepository   $milestoneRepository,
    ) {}

    /**
     * @param array<int, array{eventCode: string, eventDescription: string, eventDate: string, location: string|null}> $events
     */
    public function writeEvents(TrackingRequest $request, string $source, array $events): void
    {
        $shipment = $request->getShipment();
        $shipmentId = $shipment->getId();

        foreach ($events as $event) {
            $code = $event['eventCode'] ?? '';
            $mapping = $this->mappingRepository->findByCarrierAndCode($source, $code);
            if (!$mapping || !$mapping->getMilestoneCode()) {
                continue;
            }

            $milestoneCode = $mapping->getMilestoneCode();
            $existing = $this->milestoneRepository->findByShipmentAndCode($shipmentId, $milestoneCode);

            if ($existing && $existing->getSource() === 'MANUAL') {
                continue;
            }

            if (!$existing) {
                $existing = (new ShipmentMilestone())
                    ->setShipment($shipment)
                    ->setMilestoneCode($milestoneCode);
            }

            $eventDate = isset($event['eventDate'])
                ? new \DateTime($event['eventDate'])
                : new \DateTime();

            $existing->setActualDate($eventDate);
            $existing->setSource('AUTOMATED');
            $existing->recalculateException();

            $this->milestoneRepository->save($existing);
        }
    }
}
