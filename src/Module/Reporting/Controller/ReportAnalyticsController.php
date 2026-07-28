<?php
namespace App\Module\Reporting\Controller;

use App\Module\Reporting\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Module\Core\Service\CapacityService;

#[Route('/report/analytics')]
#[IsGranted('ROLE_USER')]
#[AppModule('reporting')]
class ReportAnalyticsController extends AbstractController
{
    public function __construct(
        private readonly ReportRepository $repo,
        private readonly CapacityService $capacityService,
    ) {}

    #[Route('/customer-profitability', methods: ['GET'])]
    public function customerProfitability(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getCustomerProfitability($from, $to));
    }

    #[Route('/top-lanes', methods: ['GET'])]
    public function topLanes(Request $request): JsonResponse
    {
        $from  = $request->query->get('from', date('Y-m-01'));
        $to    = $request->query->get('to', date('Y-m-d'));
        $limit = (int) $request->query->get('limit', 20);
        return $this->json($this->repo->getTopLanes($from, $to, $limit));
    }

    #[Route('/exceptions', methods: ['GET'])]
    public function exceptions(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getExceptions($from, $to));
    }

    #[Route('/operational-dashboard', methods: ['GET'])]
    public function operationalDashboard(): JsonResponse
    {
        return $this->json($this->repo->getOperationalDashboard());
    }

    #[Route('/dashboard-stats', methods: ['GET'])]
    public function dashboardStats(): JsonResponse
    {
        return $this->json($this->repo->getDashboardStats());
    }

    #[Route('/capacity-usage', methods: ['GET'])]
    public function capacityUsage(): JsonResponse
    {
        return $this->json($this->capacityService->getCapacityUsage());
    }
}
