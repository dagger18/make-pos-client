<?php

namespace App\Module\Core\Controller;

use App\Misc\Attribute\AppModule;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Misc\NumberToWords\NumberToWords;
#[Route('/test')]
#[AppModule('core')]
class TestController extends AbstractController {
    #[Route('/', methods: ['GET'])]
    public function list(Request $request): JsonResponse {
       return $this->json(['dd' => 'jeje']);
    }
}