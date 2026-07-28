<?php
declare(strict_types=1);
namespace App\Module\Carrier\Controller;

use App\Module\Core\Service\MasterSyncService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Misc\Attribute\AppModule;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/flight-schedule')]
#[AppModule('carrier')]
class FlightScheduleController extends AbstractController
{
    public function __construct(private readonly MasterSyncService $masterSyncService) {}

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $origin = trim(strtoupper($request->query->getString('origin', '')));
        $destination = trim(strtoupper($request->query->getString('destination', '')));
        $date = $request->query->getString('date', date('Y-m-d'));

        if (!$origin || !$destination) {
            return $this->json([]);
        }

        return $this->json($this->masterSyncService->searchFlightSchedules($origin, $destination, $date));
    }
}
