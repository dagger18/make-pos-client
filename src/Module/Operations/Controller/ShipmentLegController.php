<?php
namespace App\Module\Operations\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Repository\ShipmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shipment/{shipmentId}/legs')]
#[IsGranted('ROLE_USER')]
#[AppModule('operations')]
class ShipmentLegController extends AbstractController
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(int $shipmentId): JsonResponse
    {
        $parent = $this->shipmentRepository->find($shipmentId);
        if (!$parent) throw $this->createNotFoundException();

        $legs = $this->shipmentRepository->findBy(
            ['parentJobId' => $shipmentId],
            ['id' => 'ASC']
        );

        return $this->json(array_map(fn($leg) => $this->serializeLeg($leg), $legs));
    }

    #[Route('', methods: ['POST'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $parent = $this->shipmentRepository->find($shipmentId);
        if (!$parent) throw $this->createNotFoundException();

        $body = json_decode($request->getContent(), true) ?? [];

        $leg = new Shipment();
        $leg->setParentJobId($shipmentId);

        if (isset($body['code'])) $leg->setCode($body['code']);
        if (isset($body['note'])) $leg->setNote($body['note']);

        $this->shipmentRepository->save($leg);
        return $this->json($this->serializeLeg($leg), Response::HTTP_CREATED);
    }

    #[Route('/{legId}', methods: ['DELETE'])]
    public function delete(int $shipmentId, int $legId): JsonResponse
    {
        $leg = $this->shipmentRepository->find($legId);
        if (!$leg || $leg->getParentJobId() !== $shipmentId) throw $this->createNotFoundException();

        $this->shipmentRepository->delete($leg);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serializeLeg(Shipment $leg): array
    {
        return [
            'id'          => $leg->getId(),
            'parentJobId' => $leg->getParentJobId(),
            'code'        => $leg->getCode(),
            'note'        => $leg->getNote(),
        ];
    }
}
