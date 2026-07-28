<?php
namespace App\Module\Notification\Entity;

use App\Module\Core\Entity\User;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Notification\Repository\InAppNotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InAppNotificationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class InAppNotification
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ruleKey = null;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(length: 8)]
    private string $priority = 'NORMAL';

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $actionUrl = null;

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $v): static { $this->user = $v; return $this; }
    public function getRuleKey(): ?string { return $this->ruleKey; }
    public function setRuleKey(?string $v): static { $this->ruleKey = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $v): static { $this->body = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $v): static { $this->isRead = $v; return $this; }
    public function getReadAt(): ?\DateTimeInterface { return $this->readAt; }
    public function setReadAt(?\DateTimeInterface $v): static { $this->readAt = $v; return $this; }
    public function getActionUrl(): ?string { return $this->actionUrl; }
    public function setActionUrl(?string $v): static { $this->actionUrl = $v; return $this; }
}
