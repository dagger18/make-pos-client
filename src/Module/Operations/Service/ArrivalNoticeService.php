<?php

namespace App\Module\Operations\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Enum\Magnum;
use App\Module\Operations\Repository\ArrivalNoticeRepository;
use Symfony\Component\HttpFoundation\Request;

class ArrivalNoticeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public ArrivalNoticeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function pdfData(mixed $arrivalNotice, Request $request, string $language): array
    {
        $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
        $shipment = $arrivalNotice->getShipment();
        $booking = $shipment->getBooking();
        return [
            'company' => $company,
            'arrivalNotice' => $arrivalNotice,
            'shipment' => $shipment,
            'booking' => $booking,
            'basePath' => $request->getUriForPath(''),
            'filename' => 'ArrivalNotice_' . $shipment->getCode() . '_' . $language . '.pdf',
            'template' => 'pdf/arrival-notice.html.twig',
        ];
    }
}
