<?php
declare(strict_types=1);
namespace App\Module\Carrier\Controller;

use App\Module\Core\Service\MasterSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Misc\Attribute\AppModule;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vessel-sailing')]
#[AppModule('carrier')]
class VesselSailingController extends AbstractController
{
    public function __construct(private readonly MasterSyncService $masterSyncService) {}

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $pol = trim($request->query->getString('pol', ''));
        $pod = trim($request->query->getString('pod', ''));
        $etdFrom = $request->query->getString('etd_from', date('Y-m-d'));
        $etdTo = $request->query->getString('etd_to', date('Y-m-d', strtotime('+60 days')));

        if (!$pol || !$pod) {
            return $this->json([]);
        }

        return $this->json($this->masterSyncService->searchVesselSailings($pol, $pod, $etdFrom, $etdTo));
    }
}
