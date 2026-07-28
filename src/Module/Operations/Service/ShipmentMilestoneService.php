<?php

namespace App\Module\Operations\Service;

use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Entity\ShipmentMilestone;
use App\Module\Carrier\Enum\MilestoneCode;
use App\Module\Operations\Repository\ShipmentMilestoneRepository;

class ShipmentMilestoneService
{
    public function __construct(
        private readonly ShipmentMilestoneRepository $repository,
    ) {}

    public function autoWrite(
        Shipment $shipment,
        MilestoneCode $code,
        ?\DateTimeInterface $actualDate = null,
        string $source = 'SYSTEM',
    ): ShipmentMilestone {
        $milestone = $this->repository->findByShipmentAndCode($shipment->getId(), $code)
            ?? (new ShipmentMilestone())->setShipment($shipment)->setMilestoneCode($code);

        $milestone->setSource($source);

        if ($actualDate !== null) {
            $milestone->setActualDate($actualDate);
            $milestone->recalculateException();
        }

        $this->repository->save($milestone);
        return $milestone;
    }
}
