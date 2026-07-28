<?php
namespace App\Module\Carrier\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Carrier\Entity\TrackingRequest;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Carrier\Repository\TrackingEventRawRepository;
use App\Module\Carrier\Repository\TrackingRequestRepository;
use App\Module\Carrier\Service\TrackingRequestService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/tracking-requests')]
#[IsGranted('ROLE_USER')]
#[AppModule('carrier')]
class TrackingRequestController extends CrudController
{
    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId, TrackingRequestRepository $repo): JsonResponse
    {
        $items = $repo->findByShipment($shipmentId);
        return $this->json(array_map(fn($r) => $this->baseService->serializer->normalize($r, null, ['groups' => 'list']), $items));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request, ShipmentRepository $shipmentRepo, TrackingRequestService $service): JsonResponse
    {
        $shipment = $shipmentRepo->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        if (empty($body['trackingType']) || empty($body['trackingRef'])) {
            return $this->json(['error' => $this->trans('trackingType and trackingRef are required.')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $req = $service->createForShipment($shipment, $body);
        return $this->json($this->baseService->serializer->normalize($req, null, ['groups' => 'detail']), Response::HTTP_CREATED);
    }

    #[Route('/{id}/pause', methods: ['PATCH'])]
    public function pause(int $shipmentId, int $id, TrackingRequestRepository $repo, TrackingRequestService $service): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $service->updateStatus($req, 'PAUSED');
        return $this->json($this->baseService->serializer->normalize($req, null, ['groups' => 'detail']));
    }

    #[Route('/{id}/resume', methods: ['PATCH'])]
    public function resume(int $shipmentId, int $id, TrackingRequestRepository $repo, TrackingRequestService $service): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $service->updateStatus($req, 'ACTIVE');
        return $this->json($this->baseService->serializer->normalize($req, null, ['groups' => 'detail']));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $id, TrackingRequestRepository $repo, TrackingRequestService $service): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $service->deleteRequest($req);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/events', methods: ['GET'])]
    public function events(int $shipmentId, int $id, TrackingRequestRepository $repo, TrackingEventRawRepository $eventRepo): JsonResponse
    {
        $req = $repo->find($id);
        if (!$req || $req->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();
        $events = $eventRepo->findByTrackingRequest($id);
        return $this->json(array_map(fn($e) => $this->baseService->serializer->normalize($e, null, ['groups' => 'list']), $events));
    }
}
