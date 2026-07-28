<?php
namespace App\Module\Integration\Service;

use App\Module\Integration\Entity\PortalToken;
use App\Module\Integration\Entity\PortalUser;
use App\Module\Integration\Repository\PortalTokenRepository;
use App\Module\Integration\Repository\PortalUserRepository;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PortalAuthService
{
    private const TOKEN_TTL_DAYS = 10;

    public function __construct(
        private readonly PortalUserRepository      $userRepository,
        private readonly PortalTokenRepository     $tokenRepository,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function authenticate(string $email, string $password): ?PortalUser
    {
        $user = $this->userRepository->findByEmail($email);
        if (!$user || !$user->isActive()) {
            return null;
        }
        if (!$this->hasher->isPasswordValid($user, $password)) {
            return null;
        }
        $user->setLastLoginAt(new \DateTime());
        $this->userRepository->save($user);
        return $user;
    }

    public function createToken(PortalUser $user): PortalToken
    {
        $token = new PortalToken();
        $token->setPortalUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setExpiresAt(time() + (self::TOKEN_TTL_DAYS * 86400));
        return $this->tokenRepository->save($token);
    }

    public function createUser(array $data): PortalUser
    {
        $user = new PortalUser();
        $user->setEmail(strtolower(trim($data['email'])));
        $user->setPasswordHash($this->hasher->hashPassword($user, $data['password']));
        $user->setRole($data['role'] ?? 'VIEWER');
        if (isset($data['client'])) {
            $user->setClient($data['client']);
        }
        if (isset($data['contact'])) {
            $user->setContact($data['contact']);
        }
        return $this->userRepository->save($user);
    }

    public function logout(PortalUser $user): void
    {
        $this->tokenRepository->deleteByUser($user);
    }
}
