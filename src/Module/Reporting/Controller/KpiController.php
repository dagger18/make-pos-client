<?php
namespace App\Module\Reporting\Controller;

use App\Module\Reporting\Repository\KpiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/kpi')]
#[IsGranted('ROLE_USER')]
#[AppModule('reporting')]
class KpiController extends AbstractController
{
    public function __construct(private readonly KpiRepository $repo) {}

    #[Route('/on-time-rate', methods: ['GET'])]
    public function onTimeRate(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getOnTimeRate($from, $to));
    }

    #[Route('/conversion-rate', methods: ['GET'])]
    public function conversionRate(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getConversionRate($from, $to));
    }

    #[Route('/operator-productivity', methods: ['GET'])]
    public function operatorProductivity(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getOperatorProductivity($from, $to));
    }

    #[Route('/dso', methods: ['GET'])]
    public function dso(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json(['avg_dso' => $this->repo->getDso($from, $to)]);
    }
}
