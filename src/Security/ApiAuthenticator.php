<?php

namespace App\Security;

use App\Module\Core\Entity\User;
use App\Module\Core\Entity\UserToken;
use App\Module\Core\Enum\UserStatus;
use App\Misc\Exception\AccessDeniedException;
use App\Module\Core\Repository\UserTokenRepository;
use App\Module\Core\Service\UserService;
use DateTime;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Component\Cache\Adapter\TraceableAdapter;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;

class ApiAuthenticator extends AbstractAuthenticator
{
    /**
     * @param TraceableAdapter $appCache
    */
    public function __construct(
        private TagAwareCacheInterface $appCache,
        private UserService $userService,
        private UserTokenRepository $userTokenRepository
    )
    {
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-W-Auth') || $request->query->has('YXV0aFRva2Vu');
    }

    public function authenticate(Request $request): Passport
    {
        if($request->query->has('YXV0aFRva2Vu')) {
            $matches = [
                'email' => $request->query->get('ZW1haWw'),
                'token' => $request->query->get('YXV0aFRva2Vu')
            ];
        } else {
            $authRegex = '/Token Email="(?P<email>[^"]+)", Token="(?P<token>[^"]+)"/';
            if (1 !== preg_match($authRegex, $request->headers->get('X-W-Auth'), $matches)
                || $matches['email'] == 'undefined'
            ) {
                throw new AccessDeniedException(AccessDeniedException::WRONG_TOKEN, 'token check');
            }
        }
        

        return new Passport(new UserBadge($matches['email']), new CustomCredentials(
            function ($credentials, User $user) use ($request) {
                if ($user->getStatus() !== UserStatus::Active) {
                    throw new AccessDeniedException(AccessDeniedException::WRONG_TOKEN, 'active check');
                }
                $tokenInDb = $this->userTokenRepository->findOneBy([
                    'token' => $credentials['token'],
                    'user' => $user
                ]);
                if(is_null($tokenInDb)) {
                    throw new AccessDeniedException(AccessDeniedException::WRONG_TOKEN, 'token check');
                }
                if($tokenInDb->isNullified()) {
                    $request->attributes->set('_auth_error', AccessDeniedException::TOKEN_NULLIFIED);
                    throw new AccessDeniedException(AccessDeniedException::TOKEN_NULLIFIED, 'token nullified');
                }
                if($tokenInDb->getExpiresAt() < time()) {
                    throw new AccessDeniedException(AccessDeniedException::WRONG_TOKEN, 'token expired');
                }
                /** @var DateTime $lastPing */
                $lastPing = clone ($user->getLastPing() ?? (new DateTime()));
                $pingTime = $lastPing->modify('+ 3 minutes');
                if(new DateTime() > $pingTime) {
                  $user->setLastPing(new \DateTime());
                  $this->userService->repository->save($user);
                }
                return true;
            },
            [
                'token'  => $matches['token']
            ]
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if ($request->attributes->get('_auth_error') === AccessDeniedException::TOKEN_NULLIFIED) {
            return new JsonResponse(['error' => AccessDeniedException::TOKEN_NULLIFIED], Response::HTTP_UNAUTHORIZED);
        }
        throw new AccessDeniedException(AccessDeniedException::WRONG_TOKEN, 'token check');
    }
}
