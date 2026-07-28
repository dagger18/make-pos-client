<?php
namespace App\Module\Integration\Controller;

use App\Module\Core\Entity\Port;

use App\Module\Integration\Service\PortalAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/portal')]
#[AppModule('integration')]
class PortalAuthController extends AbstractController
{
    public function __construct(
        private readonly PortalAuthService $authService,
    ) {}

    #[Route('/auth', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if (!$email || !$password) {
            return $this->json(['error' => $this->trans('Email and password are required.')], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->authService->authenticate($email, $password);
        if (!$user) {
            return $this->json(['error' => $this->trans('Invalid credentials.')], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->authService->createToken($user);

        return $this->json([
            'accessToken' => $token->getToken(),
            'user' => [
                'id'    => $user->getId(),
                'email' => $user->getEmail(),
                'role'  => $user->getRole(),
            ],
        ]);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] $user): JsonResponse
    {
        /** @var PortalUser $user */
        $client = $user->getClient();
        return $this->json([
            'id'         => $user->getId(),
            'email'      => $user->getEmail(),
            'role'       => $user->getRole(),
            'clientId'   => $client?->getId(),
            'clientName' => $client?->getName(),
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(#[CurrentUser] $user): JsonResponse
    {
        /** @var PortalUser $user */
        $this->authService->logout($user);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
