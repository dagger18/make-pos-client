<?php
namespace App\Module\Integration\Entity;

use App\Module\Integration\Repository\PortalTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortalTokenRepository::class)]
class PortalToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PortalUser $portalUser = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private int $expiresAt;

    public function getId(): ?int { return $this->id; }
    public function getPortalUser(): ?PortalUser { return $this->portalUser; }
    public function setPortalUser(?PortalUser $v): static { $this->portalUser = $v; return $this; }
    public function getToken(): string { return $this->token; }
    public function setToken(string $v): static { $this->token = $v; return $this; }
    public function getExpiresAt(): int { return $this->expiresAt; }
    public function setExpiresAt(int $v): static { $this->expiresAt = $v; return $this; }
    public function isExpired(): bool { return time() > $this->expiresAt; }
}
