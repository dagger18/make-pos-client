<?php

namespace App\Module\Core\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Repository\UserTokenRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: UserTokenRepository::class)]
#[ORM\HasLifecycleCallbacks]
class UserToken
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $token = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $expiresAt = 0;

    #[ORM\Column(type: 'simple_array', nullable: true)]
    private array $tags = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $nullified = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(int $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function isNullified(): bool
    {
        return $this->nullified;
    }

    public function setNullified(bool $nullified): static
    {
        $this->nullified = $nullified;

        return $this;
    }
}
