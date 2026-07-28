<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Operations\Entity\Parcel;
use App\Module\Operations\Repository\ParcelRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/parcel')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ParcelController extends AbstractController
{
    public function __construct(
        private readonly ParcelRepository $parcelRepository,
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        return $this->json(array_map(
            fn($p) => $this->serialize($p),
            $this->parcelRepository->findByShipment($shipmentId)
        ));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $parcel = $this->hydrate(new Parcel(), $body);
        $parcel->setShipment($shipment);

        $errors = $this->validate($parcel);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->parcelRepository->save($parcel);
        return $this->json($this->serialize($parcel), Response::HTTP_CREATED);
    }

    #[Route('/{parcelId}', methods: ['PUT'])]
    public function update(int $shipmentId, int $parcelId, Request $request): JsonResponse
    {
        $parcel = $this->parcelRepository->find($parcelId);
        if (!$parcel || $parcel->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($parcel, $body);

        $errors = $this->validate($parcel);
        if ($errors) return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->parcelRepository->save($parcel);
        return $this->json($this->serialize($parcel));
    }

    #[Route('/{parcelId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $parcelId): JsonResponse
    {
        $parcel = $this->parcelRepository->find($parcelId);
        if (!$parcel || $parcel->getShipment()->getId() !== $shipmentId) throw $this->createNotFoundException();

        $this->parcelRepository->delete($parcel);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(Parcel $parcel, array $body): Parcel
    {
        if (isset($body['serviceLevel']))                $parcel->setServiceLevel($body['serviceLevel']);
        if (array_key_exists('trackingNumber', $body))   $parcel->setTrackingNumber($body['trackingNumber'] ?: null);
        if (array_key_exists('integrator', $body))       $parcel->setIntegrator($body['integrator'] ?: null);
        if (isset($body['pieces']))                      $parcel->setPieces((int) $body['pieces']);
        if (isset($body['grossWeightKg']))               $parcel->setGrossWeightKg((string) $body['grossWeightKg']);
        if (array_key_exists('declaredValue', $body))    $parcel->setDeclaredValue($body['declaredValue'] !== null ? (string) $body['declaredValue'] : null);
        if (array_key_exists('declaredCurrency', $body)) $parcel->setDeclaredCurrency($body['declaredCurrency'] ?: null);
        return $parcel;
    }

    private function validate(Parcel $parcel): array
    {
        $errors = [];
        if ($parcel->getServiceLevel() === '') {
            $errors[] = 'serviceLevel is required.';
        } elseif (!in_array($parcel->getServiceLevel(), ['ECONOMY', 'EXPRESS', 'OVERNIGHT', 'SAME-DAY'], true)) {
            $errors[] = 'serviceLevel must be one of: ECONOMY, EXPRESS, OVERNIGHT, SAME-DAY';
        }
        if ((float) $parcel->getGrossWeightKg() <= 0) $errors[] = 'grossWeightKg must be greater than 0.';
        if ($parcel->getPieces() < 1) $errors[] = 'pieces must be at least 1.';
        return $errors;
    }

    private function serialize(Parcel $parcel): array
    {
        return [
            'id'               => $parcel->getId(),
            'trackingNumber'   => $parcel->getTrackingNumber(),
            'serviceLevel'     => $parcel->getServiceLevel(),
            'integrator'       => $parcel->getIntegrator(),
            'pieces'           => $parcel->getPieces(),
            'grossWeightKg'    => $parcel->getGrossWeightKg(),
            'declaredValue'    => $parcel->getDeclaredValue(),
            'declaredCurrency' => $parcel->getDeclaredCurrency(),
            'createdAt'        => $parcel->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'        => $parcel->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
