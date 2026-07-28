<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Entity\User;
use App\Entity\Agent;
use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\UserToken;
use App\Misc\Enum\CapacityType;
use App\Module\Core\Service\CapacityService;
use App\Module\Core\Service\UserService;
use App\Module\Core\Enum\UserStatus;
use App\Module\Core\Service\ConfigService;
use App\Module\Core\Repository\UserTokenRepository;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Routing\Annotation\Route;
use App\Module\Core\Service\UserTokenService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ApiAuthController extends AbstractController
{
    /**
     * @param TraceableAdapter $cache
    */
    #[Route('/auth', name: 'api_auth', methods: ['POST'])]
    public function index(
        #[CurrentUser] User $user,
        UserTokenService $userTokenService,
        UserService $userService
    ): JsonResponse
    {
        if ($user->getStatus() !== UserStatus::Active) {
            return new JsonResponse(['error' => $this->trans('Wrong credentials')], JsonResponse::HTTP_OK);
        }
        return $this->json([
            'accessToken' => $userTokenService->getUserToken($user, false),
            'user'  => $user,
            'userAbilityRules' => $userService->getUserPermissions($user)
        ]);
    }

    #[Route('/register', name: 'api_app_register', methods: ['POST'])]
    public function register(UserService $userService, Request $request): JsonResponse
    {
        $data = $request->request->all();
        // check token
        $user = $userService->getUserFromToken($data['token'], UserStatus::Pending);
        if(!$user) return $this->json([
            'result' => $this->trans('invalid token')
        ]);
        return $this->json([
            'result' => 'success',
            'user' => $userService->finishRegister($user, $data)
        ]);
    }
    
    #[Route('/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(UserService $userService, Request $request, CapacityService $capacityService): JsonResponse
    {
        $data = $request->request->all();
        // check token
        $user = $userService->repository->findOneBy(['email' => $data['email']]);
        if(!$user) return $this->json([
            'result' => $this->trans('invalid token')
        ]);
        $capacityService->assertAllowed(CapacityType::ResetPassword);
        $userService->forgotPassword($user);
        $capacityService->recordUsage(CapacityType::ResetPassword);
        return $this->json([
            'result' => 'success'
        ]);
    }

    #[Route('/login-with-token', methods: ['POST'])]
    public function loginWithToken(
      UserService $userService, 
      Request $request,
      ConfigService $configService,
      UserTokenService $userTokenService
      ): JsonResponse
    {
        $data = $request->request->all();
        // check token
        $user = $userService->repository->findOneBy(['email' => $data['email']]);
        if(!$user) return $this->json([
            'result' => $this->trans('invalid token')
        ]);
        $organizationToken = json_decode($configService->getConfigValue('organization'), true)['token'];
        $token = hash_hmac('sha256', $user->getPassword(), $organizationToken);
        if(hash_equals($token, $data['token'])) {
            return $this->json([
                'accessToken' => $userTokenService->getUserToken($user, false),
                'user'  => $user,
                'userAbilityRules' => $userService->getUserPermissions($user)
            ]);
        }
        return $this->json([
            'result' => $this->trans('invalid token')
        ]);
    }

}
