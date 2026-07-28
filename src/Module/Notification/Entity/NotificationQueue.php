<?php
namespace App\Module\Notification\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Notification\Repository\NotificationQueueRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationQueueRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationQueue
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $ruleKey = null;

    #[ORM\Column(length: 32)]
    private string $recipientType = 'USER';

    #[ORM\Column(nullable: true)]
    private ?int $recipientId = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $recipientEmail = null;

    #[ORM\Column(length: 16)]
    private string $channel = 'EMAIL';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column(length: 8)]
    private string $priority = 'NORMAL';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $scheduledAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(length: 16)]
    private string $status = 'PENDING';

    #[ORM\Column]
    private int $attemptCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $providerRef = null;

    public function getId(): ?int { return $this->id; }
    public function getRuleKey(): ?string { return $this->ruleKey; }
    public function setRuleKey(?string $v): static { $this->ruleKey = $v; return $this; }
    public function getRecipientType(): string { return $this->recipientType; }
    public function setRecipientType(string $v): static { $this->recipientType = $v; return $this; }
    public function getRecipientId(): ?int { return $this->recipientId; }
    public function setRecipientId(?int $v): static { $this->recipientId = $v; return $this; }
    public function getRecipientEmail(): ?string { return $this->recipientEmail; }
    public function setRecipientEmail(?string $v): static { $this->recipientEmail = $v; return $this; }
    public function getChannel(): string { return $this->channel; }
    public function setChannel(string $v): static { $this->channel = $v; return $this; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $v): static { $this->subject = $v; return $this; }
    public function getBody(): string { return $this->body; }
    public function setBody(string $v): static { $this->body = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
    public function getScheduledAt(): \DateTimeInterface { return $this->scheduledAt; }
    public function setScheduledAt(\DateTimeInterface $v): static { $this->scheduledAt = $v; return $this; }
    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }
    public function setSentAt(?\DateTimeInterface $v): static { $this->sentAt = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getAttemptCount(): int { return $this->attemptCount; }
    public function setAttemptCount(int $v): static { $this->attemptCount = $v; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $v): static { $this->lastError = $v; return $this; }
    public function getProviderRef(): ?string { return $this->providerRef; }
    public function setProviderRef(?string $v): static { $this->providerRef = $v; return $this; }
}
