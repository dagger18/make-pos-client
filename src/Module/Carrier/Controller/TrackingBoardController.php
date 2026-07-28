<?php
namespace App\Module\Carrier\Controller;

use App\Module\Carrier\Repository\TrackingRequestRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tracking-requests')]
#[IsGranted('ROLE_USER')]
#[AppModule('carrier')]
class TrackingBoardController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function list(Request $request, TrackingRequestRepository $repo): JsonResponse
    {
        $status = $request->query->get('status') ?: null;
        $type   = $request->query->get('type') ?: null;

        $items = $repo->findAllFiltered($status, $type);

        $result = array_map(fn($item) => [
            'id'            => $item->getId(),
            'trackingType'  => $item->getTrackingType(),
            'trackingRef'   => $item->getTrackingRef(),
            'carrierScac'   => $item->getCarrierScac(),
            'status'        => $item->getStatus(),
            'shipmentId'    => $item->getShipment()->getId(),
            'shipmentRef'   => $item->getShipment()->getReferenceId(),
            'lastEventAt'   => $item->getLastEventAt()?->format(\DateTimeInterface::ATOM),
            'lastCheckedAt' => $item->getLastCheckedAt()?->format(\DateTimeInterface::ATOM),
            'errorCount'    => $item->getErrorCount(),
            'lastError'     => $item->getLastError(),
        ], $items);

        return $this->json($result);
    }
}
