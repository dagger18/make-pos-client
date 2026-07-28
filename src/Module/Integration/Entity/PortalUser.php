<?php
namespace App\Module\Integration\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Stub — portal user feature not yet implemented for POS.
 */
class PortalUser implements UserInterface
{
    private string $email = '';
    private bool $active = false;

    public function getEmail(): string { return $this->email; }
    public function isActive(): bool { return $this->active; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return ['ROLE_PORTAL_USER']; }
    public function eraseCredentials(): void {}
}
