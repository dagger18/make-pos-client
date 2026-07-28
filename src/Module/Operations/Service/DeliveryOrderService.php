<?php

namespace App\Module\Operations\Service;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Enum\Magnum;
use App\Module\Operations\Repository\DeliveryOrderRepository;
use Symfony\Component\HttpFoundation\Request;

class DeliveryOrderService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public DeliveryOrderRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function pdfData(mixed $deliveryOrder, Request $request, string $language): array
    {
        $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
        $shipment = $deliveryOrder->getShipment();
        $booking = $shipment->getBooking();
        return [
            'company' => $company,
            'deliveryOrder' => $deliveryOrder,
            'shipment' => $shipment,
            'booking' => $booking,
            'basePath' => $request->getUriForPath(''),
            'filename' => 'DeliveryOrder_' . $shipment->getCode() . '_' . $language . '.pdf',
            'template' => 'pdf/delivery-order.html.twig',
        ];
    }
}
