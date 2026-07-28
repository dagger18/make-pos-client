<?php
namespace App\Security;

use App\Module\Integration\Repository\PortalTokenRepository;
use App\Module\Integration\Repository\PortalUserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Contracts\Translation\TranslatorInterface;

class PortalAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly PortalUserRepository  $userRepository,
        private readonly PortalTokenRepository $tokenRepository,
        private readonly TranslatorInterface   $translator,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-W-Auth')
            || ($request->query->has('YXV0aFRva2Vu') && $request->query->has('ZW1haWw'));
    }

    public function authenticate(Request $request): Passport
    {
        $auth = $request->headers->get('X-W-Auth', '');
        if ($auth) {
            preg_match('/Email="([^"]+)"/', $auth, $emailMatch);
            preg_match('/Token="([^"]+)"/', $auth, $tokenMatch);
            $email = $emailMatch[1] ?? '';
            $rawToken = $tokenMatch[1] ?? '';
        } else {
            $email    = base64_decode($request->query->get('ZW1haWw', ''));
            $rawToken = base64_decode($request->query->get('YXV0aFRva2Vu', ''));
        }

        if (!$email || !$rawToken) {
            throw new AuthenticationException($this->translator->trans('Missing credentials.'));
        }

        $portalToken = $this->tokenRepository->findValidToken($rawToken);
        if (!$portalToken || $portalToken->getPortalUser()->getEmail() !== $email) {
            throw new AuthenticationException($this->translator->trans('Invalid or expired token.'));
        }
        if (!$portalToken->getPortalUser()->isActive()) {
            throw new AuthenticationException($this->translator->trans('Portal account is inactive.'));
        }

        return new SelfValidatingPassport(
            new UserBadge($email, fn() => $portalToken->getPortalUser())
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => $this->translator->trans('Unauthorized.')], Response::HTTP_UNAUTHORIZED);
    }
}
