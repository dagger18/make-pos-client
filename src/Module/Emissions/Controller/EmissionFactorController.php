<?php
declare(strict_types=1);
namespace App\Module\Emissions\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Emissions\Entity\EmissionFactor;
use App\Module\Emissions\Repository\EmissionFactorRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/emissions/factor')]
#[IsGranted('ROLE_USER')]
#[AppModule('reporting')]
class EmissionFactorController extends AbstractController
{
    public function __construct(private readonly EmissionFactorRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = [];
        if ($mode = $request->query->get('mode')) {
            $criteria['transportMode'] = $mode;
        }
        if ($methodology = $request->query->get('methodology')) {
            $criteria['methodology'] = $methodology;
        }

        $items = $this->repo->findBy($criteria, ['transportMode' => 'ASC', 'effectiveFrom' => 'DESC']);
        $list  = array_map(fn($f) => $f->toArray(), $items);
        return $this->json($this->paginate($list, $request));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(EmissionFactor $factor): JsonResponse
    {
        return $this->json($factor->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $factor = $this->hydrate(new EmissionFactor(), $data);
        $factor->setCreatedAt(new \DateTime());
        $this->repo->save($factor);
        return $this->json($factor->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(EmissionFactor $factor, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($factor, $data);
        $this->repo->save($factor);
        return $this->json($factor->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(EmissionFactor $factor): JsonResponse
    {
        $this->repo->remove($factor);
        return $this->json(null, 204);
    }

    private function paginate(array $items, Request $request): array
    {
        $limit = (int) ($request->query->get('limit') ?? 50);
        $page  = max(1, (int) ($request->query->get('page') ?? 1));
        $total = count($items);
        if ($limit <= 0) {
            return ['list' => $items, 'total' => $total, 'totalPages' => 1, 'currentPage' => 1];
        }
        return [
            'list'        => array_values(array_slice($items, ($page - 1) * $limit, $limit)),
            'total'       => $total,
            'totalPages'  => (int) ceil($total / $limit),
            'currentPage' => $page,
        ];
    }

    private function hydrate(EmissionFactor $f, array $data): EmissionFactor
    {
        $f->setTransportMode(strtoupper($data['transportMode']));
        $f->setVehicleType($data['vehicleType'] ?? null);
        $f->setFuelType($data['fuelType'] ?? null);
        $f->setSizeClass($data['sizeClass'] ?? null);
        $f->setLoadFactor(isset($data['loadFactor']) && $data['loadFactor'] !== null ? (string) $data['loadFactor'] : null);
        $f->setEfTtw((string) $data['efTtw']);
        $f->setEfWtw((string) $data['efWtw']);
        $f->setMethodology($data['methodology'] ?? 'GLEC_V3');
        $f->setEffectiveFrom(new \DateTime($data['effectiveFrom'] ?? 'now'));
        $f->setEffectiveTo(isset($data['effectiveTo']) && $data['effectiveTo'] ? new \DateTime($data['effectiveTo']) : null);
        $f->setSource($data['source'] ?? 'GLEC Framework v3');
        return $f;
    }
}
