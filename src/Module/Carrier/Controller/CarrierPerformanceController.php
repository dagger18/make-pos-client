<?php
namespace App\Module\Carrier\Controller;

use App\Module\Carrier\Entity\Provider;
use App\Module\Carrier\Repository\CarrierPerformanceScoreRepository;
use App\Module\Carrier\Service\CarrierPerformanceScoreService;
use App\Module\Carrier\Repository\CargoClaimRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/carrier-performance')]
#[IsGranted('ROLE_USER')]
#[AppModule('carrier')]
class CarrierPerformanceController extends AbstractController
{
    public function __construct(
        private readonly CarrierPerformanceScoreRepository $scoreRepo,
        private readonly CargoClaimRepository              $claimRepo,
        private readonly CarrierPerformanceScoreService    $scoreService,
    ) {}

    /** GET /carrier-performance/scores?year=2026&month=5&mode=OCN */
    #[Route('/scores', methods: ['GET'])]
    public function scores(Request $request): JsonResponse
    {
        $prevMonth = new \DateTime('first day of last month');
        $year  = (int) $request->query->get('year',  $prevMonth->format('Y'));
        $month = (int) $request->query->get('month', $prevMonth->format('n'));
        $mode  = $request->query->get('mode');

        $scores = $this->scoreRepo->findForPeriod($year, $month, $mode ?: null);
        return $this->json(array_map(fn($s) => $s->toArray(), $scores));
    }

    /** GET /carrier-performance/{id}/latest?mode=OCN */
    #[Route('/{id}/latest', methods: ['GET'])]
    public function latest(Provider $provider, Request $request): JsonResponse
    {
        $mode  = $request->query->get('mode');
        $score = $this->scoreRepo->findLatestForCarrier($provider->getId(), $mode ?: null);
        return $this->json($score ? $score->toArray() : null);
    }

    /** GET /carrier-performance/{id}/history?mode=OCN */
    #[Route('/{id}/history', methods: ['GET'])]
    public function history(Provider $provider, Request $request): JsonResponse
    {
        $mode   = $request->query->get('mode');
        $scores = $this->scoreRepo->findForCarrierHistory($provider->getId(), $mode ?: null);
        return $this->json(array_map(fn($s) => $s->toArray(), $scores));
    }
}
