<?php
namespace App\Module\Core\Entity;

use App\Module\Notification\Repository\UserNotificationPreferenceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserNotificationPreferenceRepository::class)]
class UserNotificationPreference
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\Column(length: 64)]
    private string $ruleKey = '';

    #[ORM\Id]
    #[ORM\Column(length: 16)]
    private string $channel = '';

    #[ORM\Column]
    private bool $isEnabled = true;

    #[ORM\Column]
    private bool $digestMode = false;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $digestTime = null;

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }
    public function getRuleKey(): string { return $this->ruleKey; }
    public function setRuleKey(string $v): static { $this->ruleKey = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function isEnabled(): bool { return $this->isEnabled; }
    public function setIsEnabled(bool $v): static { $this->isEnabled = $v; return $this; }
    public function isDigestMode(): bool { return $this->digestMode; }
    public function setDigestMode(bool $v): static { $this->digestMode = $v; return $this; }
    public function getDigestTime(): ?string { return $this->digestTime; }
    public function setDigestTime(?string $v): static { $this->digestTime = $v; return $this; }
}
