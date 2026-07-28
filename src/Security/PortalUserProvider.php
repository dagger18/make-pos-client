<?php
namespace App\Security;

use App\Module\Core\Entity\Port;

use App\Module\Integration\Repository\PortalUserRepository;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PortalUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly PortalUserRepository $repository,
        private readonly TranslatorInterface  $translator,
    ) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->repository->findByEmail($identifier);
        if (!$user) {
            throw new UserNotFoundException(sprintf($this->translator->trans('Portal user "%s" not found.'), $identifier));
        }
        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === PortalUser::class;
    }
}
