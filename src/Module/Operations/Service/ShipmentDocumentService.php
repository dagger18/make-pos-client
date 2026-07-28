<?php

namespace App\Module\Operations\Service;

use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Entity\ShipmentDocument;
use App\Module\Operations\Enum\DocType;
use App\Module\Core\Enum\TransportType;
use App\Module\Operations\Repository\ShipmentDocumentRepository;

class ShipmentDocumentService
{
    public function __construct(
        private readonly ShipmentDocumentRepository $repository,
    ) {}

    /**
     * Auto-creates required document rows for a shipment based on transport mode + direction.
     * Idempotent — will not duplicate rows that already exist.
     */
    public function seedRequiredDocuments(Shipment $shipment): void
    {
        $quote = $shipment->getQuote();
        $transportType = $quote->getTransportType();
        $shipmentType  = $quote->getShipmentType()?->value ?? '';

        $required = $this->getRequiredDocTypes($transportType, $shipmentType);

        foreach ($required as $docType) {
            if ($this->repository->existsForShipmentAndType($shipment->getId(), $docType)) {
                continue;
            }
            $doc = new ShipmentDocument();
            $doc->setShipment($shipment)
                ->setDocType($docType)
                ->setIsRequired(true)
                ->setIsReceived(false);
            $this->repository->save($doc);
        }
    }

    /** @return DocType[] */
    private function getRequiredDocTypes($transportType, string $shipmentType): array
    {
        $isOcean = $transportType === TransportType::Ocean;
        $isAir   = $transportType === TransportType::Air;
        $isRoad  = $transportType === TransportType::Road;
        $isExport = in_array(strtoupper($shipmentType), ['EXP', 'XTD']);
        $isImport = in_array(strtoupper($shipmentType), ['IMP']);

        $docs = [DocType::CommercialInvoice, DocType::PackingList];

        if ($isOcean) {
            $docs[] = DocType::HBL;
            $docs[] = DocType::MBL;
            if ($isExport) {
                $docs[] = DocType::ExportDeclaration;
                $docs[] = DocType::ShippingInstruction;
                $docs[] = DocType::VGMCertificate;
            }
            if ($isImport) {
                $docs[] = DocType::ImportEntry;
                $docs[] = DocType::ArrivalNotice;
                $docs[] = DocType::DeliveryOrder;
            }
        } elseif ($isAir) {
            $docs[] = DocType::HAWB;
            $docs[] = DocType::MAWB;
            if ($isExport) $docs[] = DocType::ExportDeclaration;
            if ($isImport) $docs[] = DocType::ImportEntry;
        } elseif ($isRoad) {
            $docs[] = DocType::CMR;
            $docs[] = DocType::ExportDeclaration;
            $docs[] = DocType::ImportEntry;
        }

        return $docs;
    }
}
