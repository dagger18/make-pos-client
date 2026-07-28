<?php
namespace App\Module\Operations\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Operations\Entity\Instruction;
use App\Module\Core\Enum\Magnum;
use App\Module\Operations\Repository\InstructionRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Component\HttpFoundation\Request;

class InstructionService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public InstructionRepository $repository,
        protected ShipmentRepository $shipmentRepository,
        protected ShipmentService $shipmentService,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function pdfData(mixed $instruction, Request $request, string $language): array
    {
        $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
        $shipment = $this->shipmentRepository->findOneBy(['instruction' => $instruction]);
        $booking = $shipment->getBooking();
        $documentNo = 'PMD' . $booking->getEtd()->format('Ymd') . $this->shipmentService->parseNumberFromCode($shipment);
        return [
            'company' => $company,
            'instruction' => $instruction,
            'booking' => $booking,
            'documentNo' => $documentNo,
            'basePath' => $request->getUriForPath(''),
            'filename' => 'HBL_' . $documentNo . '_' . $language . '.pdf',
            'template' => 'pdf/instruction.html.twig',
        ];
    }

    public function hawbPdfData(Instruction $instruction, Request $request, string $language): array
    {
        $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
        $shipment = $this->shipmentRepository->findOneBy(['instruction' => $instruction]);
        $booking = $shipment->getBooking();
        $documentNo = 'HAWB' . $this->shipmentService->parseNumberFromCode($shipment);
        return [
            'company' => $company,
            'instruction' => $instruction,
            'booking' => $booking,
            'shipment' => $shipment,
            'documentNo' => $documentNo,
            'basePath' => $request->getUriForPath(''),
            'filename' => 'HAWB_' . $documentNo . '_' . $language . '.pdf',
            'template' => 'pdf/hawb.html.twig',
        ];
    }

    public function packingListPdfData(Instruction $instruction, Request $request, string $language): array
    {
        $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
        $shipment = $this->shipmentRepository->findOneBy(['instruction' => $instruction]);
        $booking = $shipment->getBooking();
        $documentNo = 'PKL' . $this->shipmentService->parseNumberFromCode($shipment);
        return [
            'company' => $company,
            'instruction' => $instruction,
            'booking' => $booking,
            'shipment' => $shipment,
            'documentNo' => $documentNo,
            'basePath' => $request->getUriForPath(''),
            'filename' => 'PackingList_' . $documentNo . '_' . $language . '.pdf',
            'template' => 'pdf/packing-list.html.twig',
        ];
    }
}
