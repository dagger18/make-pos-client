<?php
declare(strict_types=1);
namespace App\Module\Emissions\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Emissions\Entity\ShipmentEmission;
use App\Module\Emissions\Repository\EmissionFactorRepository;
use App\Module\Emissions\Repository\ShipmentEmissionRepository;
use App\Module\Emissions\Service\EmissionCalculationService;
use App\Module\Operations\Entity\Shipment;
use App\Module\Operations\Repository\ShipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/emissions')]
#[IsGranted('ROLE_USER')]
#[AppModule('reporting')]
class ShipmentEmissionController extends AbstractController
{
    public function __construct(
        private readonly ShipmentEmissionRepository $repo,
        private readonly EmissionFactorRepository   $efRepo,
        private readonly EmissionCalculationService $calculator,
        private readonly EntityManagerInterface     $em,
    ) {}

    #[Route('/shipment/{shipmentId}', methods: ['GET'])]
    public function listForShipment(int $shipmentId): JsonResponse
    {
        $records = $this->repo->findByShipment($shipmentId);
        return $this->json(array_map(fn($r) => $r->toArray(), $records));
    }

    #[Route('/report', methods: ['GET'])]
    public function report(Request $request): JsonResponse
    {
        $from = $request->query->get('from') ? new \DateTime($request->query->get('from')) : null;
        $to   = $request->query->get('to')   ? new \DateTime($request->query->get('to'))   : null;
        $mode = $request->query->get('mode');

        $records = $this->repo->findForReport($from, $to, $mode ?: null);
        return $this->json(array_map(fn($r) => $r->toArray(), $records));
    }

    #[Route('/calculate/{shipmentId}', methods: ['POST'])]
    public function calculate(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->em->find(Shipment::class, $shipmentId);
        if (!$shipment) {
            return $this->json(['error' => $this->trans('Shipment not found')], 404);
        }

        $data         = json_decode($request->getContent(), true);
        $transportMode = strtoupper($data['transportMode'] ?? '');
        if (!$transportMode) {
            return $this->json(['error' => $this->trans('transportMode is required')], 422);
        }

        try {
            $record = $this->calculator->calculateAndSave(
                shipment:       $shipment,
                transportMode:  $transportMode,
                manualDistanceKm: isset($data['distanceKm']) && $data['distanceKm'] > 0 ? (float) $data['distanceKm'] : null,
                legSequence:    (int) ($data['legSequence'] ?? 1),
                legDescription: $data['legDescription'] ?? null,
                calculatedBy:   'USER',
            );
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json($record->toArray(), 201);
    }

    #[Route('/manual', methods: ['POST'])]
    public function manual(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $shipment = $this->em->find(Shipment::class, (int) $data['shipmentId']);
        if (!$shipment) {
            return $this->json(['error' => $this->trans('Shipment not found')], 404);
        }

        $distanceKm   = (float) ($data['distanceKm'] ?? 0);
        $weightTonnes = (float) ($data['cargoWeightTonnes'] ?? 1);
        $tonneKm      = round($distanceKm * $weightTonnes, 4);

        $ef = $this->efRepo->find((int) ($data['emissionFactorId'] ?? 0));

        $co2eTtw = $ef ? round($tonneKm * (float) $ef->getEfTtw(), 4) : (float) ($data['co2eTtwKg'] ?? 0);
        $co2eWtw = $ef ? round($tonneKm * (float) $ef->getEfWtw(), 4) : (float) ($data['co2eWtwKg'] ?? 0);

        $record = new ShipmentEmission();
        $record->setShipment($shipment);
        $record->setTransportMode(strtoupper($data['transportMode']));
        $record->setEmissionFactor($ef);
        $record->setDistanceKm((string) $distanceKm);
        $record->setCargoWeightTonnes((string) $weightTonnes);
        $record->setTonneKm((string) $tonneKm);
        $record->setCo2eTtwKg((string) $co2eTtw);
        $record->setCo2eWtwKg((string) $co2eWtw);
        $record->setMethodology($data['methodology'] ?? ($ef?->getMethodology() ?? 'GLEC_V3'));
        $record->setIsEstimate((bool) ($data['isEstimate'] ?? true));
        $record->setLegSequence((int) ($data['legSequence'] ?? 1));
        $record->setLegDescription($data['legDescription'] ?? 'Manual entry');
        $record->setCalculatedAt(new \DateTime());
        $record->setCalculatedBy('USER');

        $this->repo->save($record);
        return $this->json($record->toArray(), 201);
    }

    #[Route('/record/{id}', methods: ['DELETE'])]
    public function delete(ShipmentEmission $emission): JsonResponse
    {
        $this->repo->remove($emission);
        return $this->json(null, 204);
    }
}
