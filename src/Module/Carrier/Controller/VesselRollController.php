<?php
declare(strict_types=1);
namespace App\Module\Carrier\Controller;

use App\Module\Operations\Entity\Shipment;
use App\Module\Carrier\Entity\VesselRoll;
use App\Module\Carrier\Repository\VesselRollRepository;
use App\Misc\Attribute\AppModule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vessel-roll')]
#[AppModule('carrier')]
class VesselRollController extends AbstractController
{
    public function __construct(
        private readonly VesselRollRepository $repository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $shipmentId = $request->query->getInt('shipmentId');
        if (!$shipmentId) {
            return $this->json([]);
        }
        return $this->json(
            $this->repository->findByShipment($shipmentId),
            200,
            [],
            ['groups' => ['list']]
        );
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $shipment = $this->em->find(Shipment::class, $data['shipmentId'] ?? 0);
        if (!$shipment) {
            return $this->json(['error' => $this->trans('Shipment not found')], 404);
        }

        $roll = new VesselRoll();
        $roll->setShipment($shipment);
        $roll->setOriginalSailingRef($data['originalSailingRef'] ?? null);
        $roll->setOriginalEtd(isset($data['originalEtd']) && $data['originalEtd'] ? new \DateTimeImmutable($data['originalEtd']) : null);
        $roll->setNewSailingRef($data['newSailingRef'] ?? null);
        $roll->setNewEtd(isset($data['newEtd']) && $data['newEtd'] ? new \DateTimeImmutable($data['newEtd']) : null);
        $roll->setReason($data['reason'] ?? null);
        $roll->setRolledAt(new \DateTimeImmutable());

        $this->em->persist($roll);
        $this->em->flush();

        return $this->json($roll, 201, [], ['groups' => ['list']]);
    }

    #[Route('/{id}/notify', methods: ['PUT'])]
    public function markNotified(int $id): JsonResponse
    {
        $roll = $this->repository->find($id);
        if (!$roll) {
            return $this->json(['error' => $this->trans('Not found')], 404);
        }
        $roll->setNotifiedAt(new \DateTimeImmutable());
        $this->em->flush();
        return $this->json($roll, 200, [], ['groups' => ['list']]);
    }
}
