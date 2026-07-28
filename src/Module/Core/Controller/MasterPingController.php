<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Service\ConfigService;
use App\Module\Core\Service\InterServiceTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/public/master')]
class MasterPingController extends AbstractController
{
    #[Route('/maximum-users', methods: ['PUT'])]
    public function updateMaximumUsers(
        Request $request,
        ConfigService $configService,
        InterServiceTokenService $interServiceTokenService,
    ): JsonResponse
    {
        if (!$interServiceTokenService->validate($request->headers->get('X-Service-Token', ''))) {
            return $this->json(['error' => $this->trans('Invalid service token.')], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $maximumUsers = $data['maximumUsers'] ?? null;

        if (!is_int($maximumUsers) || $maximumUsers < 0) {
            return $this->json(['error' => $this->trans('maximumUsers must be a non-negative integer.')], Response::HTTP_BAD_REQUEST);
        }

        $configService->setConfig('maximumUser', (string) $maximumUsers);

        return $this->json(['maximumUsers' => $maximumUsers]);
    }
}
