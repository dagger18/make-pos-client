<?php
namespace App\Module\Finance\Entity;

use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\Client;

use App\Module\Finance\Enum\CreditStatus;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Finance\Repository\CreditLimitHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreditLimitHistoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CreditLimitHistory
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(length: 32)]
    private string $changeType = 'STATUS_CHANGE'; // STATUS_CHANGE | LIMIT_CHANGE | AUTO_ESCALATION

    #[ORM\Column(length: 16, nullable: true, enumType: CreditStatus::class)]
    private ?CreditStatus $oldStatus = null;

    #[ORM\Column(length: 16, nullable: true, enumType: CreditStatus::class)]
    private ?CreditStatus $newStatus = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $oldLimitAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $newLimitAmount = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    public function getId(): ?int { return $this->id; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getChangedBy(): ?User { return $this->changedBy; }
    public function setChangedBy(?User $changedBy): static { $this->changedBy = $changedBy; return $this; }

    public function getChangeType(): string { return $this->changeType; }
    public function setChangeType(string $changeType): static { $this->changeType = $changeType; return $this; }

    public function getOldStatus(): ?CreditStatus { return $this->oldStatus; }
    public function setOldStatus(?CreditStatus $oldStatus): static { $this->oldStatus = $oldStatus; return $this; }

    public function getNewStatus(): ?CreditStatus { return $this->newStatus; }
    public function setNewStatus(?CreditStatus $newStatus): static { $this->newStatus = $newStatus; return $this; }

    public function getOldLimitAmount(): ?float { return $this->oldLimitAmount !== null ? (float) $this->oldLimitAmount : null; }
    public function setOldLimitAmount(?float $oldLimitAmount): static { $this->oldLimitAmount = $oldLimitAmount !== null ? (string) $oldLimitAmount : null; return $this; }

    public function getNewLimitAmount(): ?float { return $this->newLimitAmount !== null ? (float) $this->newLimitAmount : null; }
    public function setNewLimitAmount(?float $newLimitAmount): static { $this->newLimitAmount = $newLimitAmount !== null ? (string) $newLimitAmount : null; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $currency): static { $this->currency = $currency; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }
}
