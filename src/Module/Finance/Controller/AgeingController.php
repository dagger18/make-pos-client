<?php
namespace App\Module\Finance\Controller;

use App\Module\Finance\Repository\AgeingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/ageing')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class AgeingController extends AbstractController
{
    public function __construct(private readonly AgeingRepository $repo) {}

    #[Route('/ar', methods: ['GET'])]
    public function arAgeing(): JsonResponse
    {
        return $this->json($this->repo->getArAgeing());
    }

    #[Route('/ap', methods: ['GET'])]
    public function apAgeing(): JsonResponse
    {
        return $this->json($this->repo->getApAgeing());
    }
}
