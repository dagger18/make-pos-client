<?php

namespace App\Module\Operations\Controller;

use App\Module\Operations\Entity\WarehouseReceipt;
use App\Module\Operations\Repository\WarehouseReceiptRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/warehouse-receipt')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class WarehouseReceiptController extends AbstractController
{
    public function __construct(
        private readonly WarehouseReceiptRepository $receiptRepository,
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($r) => $this->serialize($r),
            $this->receiptRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $receipt = $this->hydrate(new WarehouseReceipt(), $body);
        $receipt->setShipment($shipment);

        $errors = $this->validate($receipt, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->receiptRepository->save($receipt);
        return $this->json($this->serialize($receipt), Response::HTTP_CREATED);
    }

    #[Route('/{receiptId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $receiptId, Request $request): JsonResponse
    {
        $receipt = $this->receiptRepository->find($receiptId);
        if (!$receipt || $receipt->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($receipt, $body);

        $errors = $this->validate($receipt, $body);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->receiptRepository->save($receipt);
        return $this->json($this->serialize($receipt));
    }

    #[Route('/{receiptId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $receiptId): JsonResponse
    {
        $receipt = $this->receiptRepository->find($receiptId);
        if (!$receipt || $receipt->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->receiptRepository->delete($receipt);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(WarehouseReceipt $r, array $body): WarehouseReceipt
    {
        if (isset($body['facilityId']))      $r->setFacilityId((int) $body['facilityId']);
        if (array_key_exists('consolId', $body)) $r->setConsolId($body['consolId'] !== null ? (int) $body['consolId'] : null);
        if (isset($body['receiptNumber']))   $r->setReceiptNumber($body['receiptNumber']);
        if (isset($body['receiptType']))     $r->setReceiptType($body['receiptType']);
        if (array_key_exists('vehiclePlate', $body)) $r->setVehiclePlate($body['vehiclePlate'] ?: null);
        if (array_key_exists('driverName', $body))   $r->setDriverName($body['driverName'] ?: null);
        if (array_key_exists('driverIdRef', $body))  $r->setDriverIdRef($body['driverIdRef'] ?: null);
        if (isset($body['piecesReceived']))  $r->setPiecesReceived((int) $body['piecesReceived']);
        if (array_key_exists('piecesExpected', $body)) $r->setPiecesExpected($body['piecesExpected'] !== null ? (int) $body['piecesExpected'] : null);
        if (isset($body['grossWeightKg']))   $r->setGrossWeightKg((string) $body['grossWeightKg']);
        if (array_key_exists('volumeCbm', $body))    $r->setVolumeCbm($body['volumeCbm'] !== null ? (string) $body['volumeCbm'] : null);
        if (isset($body['conditionCode']))   $r->setConditionCode($body['conditionCode']);
        if (array_key_exists('damageNotes', $body))  $r->setDamageNotes($body['damageNotes'] ?: null);
        if (array_key_exists('temperatureC', $body)) $r->setTemperatureC($body['temperatureC'] !== null ? (string) $body['temperatureC'] : null);
        if (array_key_exists('storageZone', $body))  $r->setStorageZone($body['storageZone'] ?: null);
        if (array_key_exists('storageLocation', $body)) $r->setStorageLocation($body['storageLocation'] ?: null);
        if (array_key_exists('receivedById', $body)) $r->setReceivedById($body['receivedById'] !== null ? (int) $body['receivedById'] : null);
        if (isset($body['receivedAt']))      $r->setReceivedAt(new \DateTime($body['receivedAt']));
        return $r;
    }

    private function validate(WarehouseReceipt $r, array $body): array
    {
        $errors = [];
        if ($r->getFacilityId() === 0) $errors[] = 'Facility is required.';
        if ($r->getReceiptNumber() === '') $errors[] = 'Receipt number is required.';
        if ($r->getPiecesReceived() <= 0) $errors[] = 'Pieces received must be greater than 0.';
        return $errors;
    }

    private function serialize(WarehouseReceipt $r): array
    {
        return [
            'id'              => $r->getId(),
            'shipmentId'      => $r->getShipment()?->getId(),
            'facilityId'      => $r->getFacilityId(),
            'consolId'        => $r->getConsolId(),
            'receiptNumber'   => $r->getReceiptNumber(),
            'receiptType'     => $r->getReceiptType(),
            'vehiclePlate'    => $r->getVehiclePlate(),
            'driverName'      => $r->getDriverName(),
            'driverIdRef'     => $r->getDriverIdRef(),
            'piecesReceived'  => $r->getPiecesReceived(),
            'piecesExpected'  => $r->getPiecesExpected(),
            'grossWeightKg'   => $r->getGrossWeightKg(),
            'volumeCbm'       => $r->getVolumeCbm(),
            'conditionCode'   => $r->getConditionCode(),
            'damageNotes'     => $r->getDamageNotes(),
            'temperatureC'    => $r->getTemperatureC(),
            'storageZone'     => $r->getStorageZone(),
            'storageLocation' => $r->getStorageLocation(),
            'receivedById'    => $r->getReceivedById(),
            'receivedAt'      => $r->getReceivedAt()->format(\DateTimeInterface::ATOM),
            'milestoneWritten'=> $r->isMilestoneWritten(),
            'releasedAt'      => $r->getReleasedAt()?->format(\DateTimeInterface::ATOM),
            'releasedTo'      => $r->getReleasedTo(),
            'releaseDriver'   => $r->getReleaseDriver(),
            'releaseDoRef'    => $r->getReleaseDoRef(),
            'createdAt'       => $r->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
