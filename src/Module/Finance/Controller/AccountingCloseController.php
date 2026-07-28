<?php
namespace App\Module\Finance\Controller;

use App\Misc\Attribute\AppModule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
class AccountingCloseController extends AbstractController
{
    #[Route('/accounting-close/{shipmentId}/checklist', methods: ['GET'])]
    public function checklist(int $shipmentId): JsonResponse
    {
        return $this->json(['error' => 'Not implemented for POS'], Response::HTTP_NOT_IMPLEMENTED);
    }

    #[Route('/accounting-close/{shipmentId}', methods: ['POST'])]
    public function accountingClose(int $shipmentId): JsonResponse
    {
        return $this->json(['error' => 'Not implemented for POS'], Response::HTTP_NOT_IMPLEMENTED);
    }
}
