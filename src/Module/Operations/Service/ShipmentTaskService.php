<?php

namespace App\Module\Operations\Service;

use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Entity\ShipmentTask;
use App\Module\Operations\Enum\ShipmentType;
use App\Module\Operations\Enum\TaskType;
use App\Module\Core\Enum\TransportType;
use App\Module\Operations\Repository\ShipmentTaskRepository;

class ShipmentTaskService
{
    public function __construct(
        private readonly ShipmentTaskRepository $repository,
    ) {}

    public function seedTasks(Shipment $shipment): void
    {
        if ($this->repository->hasTasksForShipment($shipment->getId())) {
            return;
        }

        $transport = $shipment->getQuote()->getTransportType();
        $type      = $shipment->getQuote()->getShipmentType();

        $templates = $this->getTemplate($transport, $type);
        if (empty($templates)) {
            return;
        }

        $tasks = [];
        foreach ($templates as $i => $tpl) {
            $task = (new ShipmentTask())
                ->setShipment($shipment)
                ->setTitle($tpl['title'])
                ->setTaskType(TaskType::from($tpl['type']))
                ->setIsMandatory($tpl['mandatory'])
                ->setMilestoneGate($tpl['gate'] ?? null)
                ->setSortOrder($i + 1);
            $tasks[] = $task;
        }

        $this->repository->saveAll($tasks);
    }

    private function getTemplate(TransportType $transport, ShipmentType $type): array
    {
        return match (true) {
            $transport === TransportType::Ocean && $type === ShipmentType::Export => [
                ['title' => 'Confirm booking with carrier',          'type' => 'BOOKING',   'mandatory' => true,  'gate' => 'CARGO_BOOKED'],
                ['title' => 'Collect packing list from shipper',    'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'SI_SUBMITTED'],
                ['title' => 'Collect commercial invoice from shipper', 'type' => 'DOCUMENT', 'mandatory' => true, 'gate' => 'SI_SUBMITTED'],
                ['title' => 'Submit shipping instruction to carrier', 'type' => 'BOOKING',  'mandatory' => true,  'gate' => 'SI_SUBMITTED'],
                ['title' => 'Obtain and submit VGM',                 'type' => 'BOOKING',   'mandatory' => true,  'gate' => 'VGM_SUBMITTED'],
                ['title' => 'Issue House Bill of Lading',            'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'ON_BOARD'],
                ['title' => 'File export customs declaration',       'type' => 'CUSTOMS',   'mandatory' => true,  'gate' => 'ON_BOARD'],
                ['title' => 'Send pre-alert to overseas agent',      'type' => 'FOLLOW_UP', 'mandatory' => true,  'gate' => 'VESSEL_DEPARTED'],
                ['title' => 'Issue AR invoice to shipper',           'type' => 'INVOICE',   'mandatory' => true,  'gate' => 'POD_RECEIVED'],
            ],
            $transport === TransportType::Ocean && $type === ShipmentType::Import => [
                ['title' => 'Request original BL from overseas agent', 'type' => 'FOLLOW_UP', 'mandatory' => true,  'gate' => 'VESSEL_ARRIVED'],
                ['title' => 'File import customs entry',               'type' => 'CUSTOMS',   'mandatory' => true,  'gate' => 'CUSTOMS_RELEASED'],
                ['title' => 'Arrange customs clearance',               'type' => 'CUSTOMS',   'mandatory' => true,  'gate' => 'CUSTOMS_RELEASED'],
                ['title' => 'Arrange inland delivery',                 'type' => 'TRANSPORT', 'mandatory' => false, 'gate' => 'DELIVERED'],
                ['title' => 'Obtain signed POD from consignee',        'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'POD_RECEIVED'],
                ['title' => 'Issue AR invoice to consignee',           'type' => 'INVOICE',   'mandatory' => true,  'gate' => 'POD_RECEIVED'],
            ],
            $transport === TransportType::Air && $type === ShipmentType::Export => [
                ['title' => 'Book space with airline',                 'type' => 'BOOKING',   'mandatory' => true,  'gate' => 'CARGO_BOOKED'],
                ['title' => 'Collect DG declaration (if applicable)',  'type' => 'DOCUMENT',  'mandatory' => false, 'gate' => 'CARGO_ACCEPTED'],
                ['title' => 'Prepare House Air Waybill',               'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'CARGO_ACCEPTED'],
                ['title' => 'File export customs declaration',         'type' => 'CUSTOMS',   'mandatory' => true,  'gate' => 'FLIGHT_DEPARTED'],
                ['title' => 'Send flight pre-alert to agent',          'type' => 'FOLLOW_UP', 'mandatory' => true,  'gate' => 'FLIGHT_DEPARTED'],
                ['title' => 'Issue AR invoice',                        'type' => 'INVOICE',   'mandatory' => true,  'gate' => 'POD_RECEIVED'],
            ],
            $transport === TransportType::Air && $type === ShipmentType::Import => [
                ['title' => 'Collect arrival notice from airline',     'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'FLIGHT_ARRIVED'],
                ['title' => 'File import customs entry',               'type' => 'CUSTOMS',   'mandatory' => true,  'gate' => 'CUSTOMS_RELEASED'],
                ['title' => 'Arrange cargo collection from airport',   'type' => 'TRANSPORT', 'mandatory' => true,  'gate' => 'DELIVERED'],
                ['title' => 'Obtain signed POD',                       'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'POD_RECEIVED'],
                ['title' => 'Issue AR invoice to consignee',           'type' => 'INVOICE',   'mandatory' => true,  'gate' => 'POD_RECEIVED'],
            ],
            $transport === TransportType::Road && $type === ShipmentType::Export => [
                ['title' => 'Arrange origin pickup',                   'type' => 'TRANSPORT', 'mandatory' => true,  'gate' => 'PICKUP_COMPLETED'],
                ['title' => 'Prepare CMR Waybill',                     'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'PICKUP_COMPLETED'],
                ['title' => 'File export customs declaration',         'type' => 'CUSTOMS',   'mandatory' => true,  'gate' => 'BORDER_CROSSED'],
                ['title' => 'Confirm delivery with consignee',         'type' => 'FOLLOW_UP', 'mandatory' => true,  'gate' => 'DELIVERED'],
                ['title' => 'Collect signed CMR from driver',          'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'POD_RECEIVED'],
            ],
            $transport === TransportType::Road && $type === ShipmentType::Import => [
                ['title' => 'Arrange cargo pickup from origin',        'type' => 'TRANSPORT', 'mandatory' => true,  'gate' => 'PICKUP_COMPLETED'],
                ['title' => 'File import customs declaration',         'type' => 'CUSTOMS',   'mandatory' => true,  'gate' => 'CUSTOMS_RELEASED'],
                ['title' => 'Arrange last-mile delivery',              'type' => 'TRANSPORT', 'mandatory' => false, 'gate' => 'DELIVERED'],
                ['title' => 'Obtain signed POD',                       'type' => 'DOCUMENT',  'mandatory' => true,  'gate' => 'POD_RECEIVED'],
                ['title' => 'Issue AR invoice to consignee',           'type' => 'INVOICE',   'mandatory' => true,  'gate' => 'POD_RECEIVED'],
            ],
            default => [],
        };
    }
}
