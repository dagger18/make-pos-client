<?php
declare(strict_types=1);
namespace App\Module\Emissions\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Emissions\Entity\SeaDistance;
use App\Module\Emissions\Repository\SeaDistanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/emissions/sea-distance')]
#[IsGranted('ROLE_USER')]
#[AppModule('reporting')]
class SeaDistanceController extends AbstractController
{
    public function __construct(private readonly SeaDistanceRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $criteria = [];
        if ($pol = $request->query->get('pol')) {
            $criteria['polCode'] = strtoupper($pol);
        }
        if ($pod = $request->query->get('pod')) {
            $criteria['podCode'] = strtoupper($pod);
        }

        $items = $this->repo->findBy($criteria, ['polCode' => 'ASC', 'podCode' => 'ASC'], 500);
        return $this->json(array_map(fn($d) => $d->toArray(), $items));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(SeaDistance $seaDistance): JsonResponse
    {
        return $this->json($seaDistance->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function upsert(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $sd   = new SeaDistance();
        $this->hydrate($sd, $data);
        $this->repo->upsert($sd);

        $saved = $this->repo->findDistance($sd->getPolCode(), $sd->getPodCode());
        return $this->json($saved?->toArray(), 201);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(SeaDistance $seaDistance): JsonResponse
    {
        $this->repo->remove($seaDistance);
        return $this->json(null, 204);
    }

    private function hydrate(SeaDistance $sd, array $data): void
    {
        $sd->setPolCode($data['polCode']);
        $sd->setPodCode($data['podCode']);
        $sd->setDistanceKm((string) $data['distanceKm']);
        $sd->setViaCanal($data['viaCanal'] ?? null);
        $sd->setSource($data['source'] ?? 'MANUAL');
        $sd->setUpdatedAt(new \DateTime());
    }
}
