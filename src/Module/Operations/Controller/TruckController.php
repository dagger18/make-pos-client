<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Carrier\Repository\ProviderRepository;
use App\Module\Operations\Entity\Truck;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Module\Operations\Repository\TruckRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/truck')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class TruckController extends AbstractController
{
    public function __construct(
        private readonly TruckRepository $truckRepository,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ProviderRepository $providerRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($t) => $this->serialize($t),
            $this->truckRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $truck = $this->hydrate(new Truck(), $body);
        $truck->setShipment($shipment);

        $errors = $this->validate($truck);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->truckRepository->save($truck);
        return $this->json($this->serialize($truck), Response::HTTP_CREATED);
    }

    #[Route('/{truckId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $truckId, Request $request): JsonResponse
    {
        $truck = $this->truckRepository->find($truckId);
        if (!$truck || $truck->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($truck, $body);

        $errors = $this->validate($truck);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->truckRepository->save($truck);
        return $this->json($this->serialize($truck));
    }

    #[Route('/{truckId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $truckId): JsonResponse
    {
        $truck = $this->truckRepository->find($truckId);
        if (!$truck || $truck->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->truckRepository->delete($truck);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Truck $truck, array $body): Truck
    {
        if (isset($body['truckType']))                   $truck->setTruckType($body['truckType']);
        if (array_key_exists('payloadKg', $body))        $truck->setPayloadKg($body['payloadKg'] !== null ? (string) $body['payloadKg'] : null);
        if (array_key_exists('truckPlate', $body))       $truck->setTruckPlate($body['truckPlate'] ?: null);
        if (array_key_exists('driverName', $body))       $truck->setDriverName($body['driverName'] ?: null);
        if (array_key_exists('pickupAddress', $body))    $truck->setPickupAddress($body['pickupAddress'] ?: null);
        if (array_key_exists('deliveryAddress', $body))  $truck->setDeliveryAddress($body['deliveryAddress'] ?: null);
        if (array_key_exists('podSignedBy', $body))      $truck->setPodSignedBy($body['podSignedBy'] ?: null);
        if (array_key_exists('podImageUrl', $body))      $truck->setPodImageUrl($body['podImageUrl'] ?: null);

        foreach (['scheduledPickup', 'scheduledDelivery', 'actualPickup', 'actualDelivery'] as $field) {
            if (array_key_exists($field, $body)) {
                $setter = 'set' . ucfirst($field);
                $truck->$setter($body[$field] ? new \DateTime($body[$field]) : null);
            }
        }

        if (array_key_exists('haulierId', $body)) {
            $truck->setHaulier($body['haulierId'] ? $this->providerRepository->find((int) $body['haulierId']) : null);
        }

        return $truck;
    }

    private function validate(Truck $truck): array
    {
        $errors = [];
        if ($truck->getTruckType() === '') {
            $errors[] = 'truckType is required.';
        } elseif (!in_array($truck->getTruckType(), ['BOX', 'CURTAINSIDER', 'FLATBED', 'REEFER', 'TANKER'], true)) {
            $errors[] = 'truckType must be one of: BOX, CURTAINSIDER, FLATBED, REEFER, TANKER';
        }
        return $errors;
    }

    private function serialize(Truck $truck): array
    {
        return [
            'id'                => $truck->getId(),
            'truckType'         => $truck->getTruckType(),
            'payloadKg'         => $truck->getPayloadKg(),
            'truckPlate'        => $truck->getTruckPlate(),
            'driverName'        => $truck->getDriverName(),
            'haulier'           => $truck->getHaulier() ? ['id' => $truck->getHaulier()->getId(), 'name' => $truck->getHaulier()->getName()] : null,
            'pickupAddress'     => $truck->getPickupAddress(),
            'deliveryAddress'   => $truck->getDeliveryAddress(),
            'scheduledPickup'   => $truck->getScheduledPickup()?->format(\DateTimeInterface::ATOM),
            'scheduledDelivery' => $truck->getScheduledDelivery()?->format(\DateTimeInterface::ATOM),
            'actualPickup'      => $truck->getActualPickup()?->format(\DateTimeInterface::ATOM),
            'actualDelivery'    => $truck->getActualDelivery()?->format(\DateTimeInterface::ATOM),
            'podSignedBy'       => $truck->getPodSignedBy(),
            'podImageUrl'       => $truck->getPodImageUrl(),
            'createdAt'         => $truck->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'         => $truck->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
