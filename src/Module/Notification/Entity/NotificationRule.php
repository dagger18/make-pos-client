<?php
namespace App\Module\Notification\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Notification\Repository\NotificationRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationRule
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $ruleKey = '';

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 32)]
    private string $triggerType = '';

    #[ORM\Column(type: Types::JSON)]
    private array $triggerConfig = [];

    #[ORM\Column(type: Types::JSON)]
    private array $recipientConfig = [];

    #[ORM\Column(type: Types::JSON)]
    private array $channels = [];

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $templateKey = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(length: 16)]
    private string $scopeType = 'GLOBAL';

    #[ORM\Column(length: 8)]
    private string $priority = 'NORMAL';

    public function getId(): ?int { return $this->id; }
    public function getRuleKey(): string { return $this->ruleKey; }
    public function setRuleKey(string $v): static { $this->ruleKey = $v; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }
    public function getTriggerType(): string { return $this->triggerType; }
    public function setTriggerType(string $v): static { $this->triggerType = $v; return $this; }
    public function getTriggerConfig(): array { return $this->triggerConfig; }
    public function setTriggerConfig(array $v): static { $this->triggerConfig = $v; return $this; }
    public function getRecipientConfig(): array { return $this->recipientConfig; }
    public function setRecipientConfig(array $v): static { $this->recipientConfig = $v; return $this; }
    public function getChannels(): array { return $this->channels; }
    public function setChannels(array $v): static { $this->channels = $v; return $this; }
    public function getTemplateKey(): ?string { return $this->templateKey; }
    public function setTemplateKey(?string $v): static { $this->templateKey = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getScopeType(): string { return $this->scopeType; }
    public function setScopeType(string $v): static { $this->scopeType = $v; return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $v): static { $this->priority = $v; return $this; }
}
