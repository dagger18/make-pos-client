<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Operations\Entity\RailBooking;
use App\Module\Operations\Repository\RailBookingRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/rail-booking')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class RailBookingController extends AbstractController
{
    public function __construct(
        private readonly RailBookingRepository $railBookingRepository,
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($rb) => $this->serialize($rb),
            $this->railBookingRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $rb = $this->hydrate(new RailBooking(), $body);
        $rb->setShipment($shipment);

        $this->railBookingRepository->save($rb);
        return $this->json($this->serialize($rb), Response::HTTP_CREATED);
    }

    #[Route('/{rbId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $rbId, Request $request): JsonResponse
    {
        $rb = $this->railBookingRepository->find($rbId);
        if (!$rb || $rb->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($rb, $body);

        $this->railBookingRepository->save($rb);
        return $this->json($this->serialize($rb));
    }

    #[Route('/{rbId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $rbId): JsonResponse
    {
        $rb = $this->railBookingRepository->find($rbId);
        if (!$rb || $rb->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->railBookingRepository->delete($rb);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(RailBooking $rb, array $body): RailBooking
    {
        if (array_key_exists('trainService', $body))     $rb->setTrainService($body['trainService'] ?: null);
        if (array_key_exists('departureIcd', $body))     $rb->setDepartureIcd($body['departureIcd'] ?: null);
        if (array_key_exists('arrivalIcd', $body))       $rb->setArrivalIcd($body['arrivalIcd'] ?: null);
        if (array_key_exists('operator', $body))         $rb->setOperator($body['operator'] ?: null);
        if (array_key_exists('cimWaybillNumber', $body)) $rb->setCimWaybillNumber($body['cimWaybillNumber'] ?: null);
        if (array_key_exists('cimWaybillDate', $body))   $rb->setCimWaybillDate($body['cimWaybillDate'] ? new \DateTime($body['cimWaybillDate']) : null);
        if (array_key_exists('departureDate', $body))    $rb->setDepartureDate($body['departureDate'] ? new \DateTime($body['departureDate']) : null);
        if (array_key_exists('arrivalDate', $body))      $rb->setArrivalDate($body['arrivalDate'] ? new \DateTime($body['arrivalDate']) : null);
        if (array_key_exists('containerCount', $body))   $rb->setContainerCount($body['containerCount'] !== null ? (int) $body['containerCount'] : null);
        if (array_key_exists('note', $body))             $rb->setNote($body['note'] ?: null);
        return $rb;
    }

    private function serialize(RailBooking $rb): array
    {
        return [
            'id'               => $rb->getId(),
            'trainService'     => $rb->getTrainService(),
            'departureIcd'     => $rb->getDepartureIcd(),
            'arrivalIcd'       => $rb->getArrivalIcd(),
            'operator'         => $rb->getOperator(),
            'cimWaybillNumber' => $rb->getCimWaybillNumber(),
            'cimWaybillDate'   => $rb->getCimWaybillDate()?->format('Y-m-d'),
            'departureDate'    => $rb->getDepartureDate()?->format(\DateTimeInterface::ATOM),
            'arrivalDate'      => $rb->getArrivalDate()?->format(\DateTimeInterface::ATOM),
            'containerCount'   => $rb->getContainerCount(),
            'note'             => $rb->getNote(),
            'createdAt'        => $rb->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'        => $rb->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
