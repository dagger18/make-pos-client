<?php
namespace App\Module\Crm\Entity;

use App\Module\Core\Entity\User;
use App\Module\Crm\Repository\CrmActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmActivityRepository::class)]
class CrmActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?CrmOpportunity $opportunity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $client = null;

    #[ORM\Column(length: 32)]
    private string $activityType;
    // CALL / EMAIL / MEETING / VISIT / QUOTE_SENT / FOLLOW_UP

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $outcome = null;
    // POSITIVE / NEUTRAL / NEGATIVE / NO_ANSWER

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $nextAction = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $nextActionDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $performedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $performedAt;

    public function getId(): ?int { return $this->id; }
    public function getOpportunity(): ?CrmOpportunity { return $this->opportunity; }
    public function setOpportunity(?CrmOpportunity $v): static { $this->opportunity = $v; return $this; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $v): static { $this->client = $v; return $this; }
    public function getActivityType(): string { return $this->activityType; }
    public function setActivityType(string $v): static { $this->activityType = $v; return $this; }
    public function getSubject(): ?string { return $this->subject; }
    public function setSubject(?string $v): static { $this->subject = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getOutcome(): ?string { return $this->outcome; }
    public function setOutcome(?string $v): static { $this->outcome = $v; return $this; }
    public function getNextAction(): ?string { return $this->nextAction; }
    public function setNextAction(?string $v): static { $this->nextAction = $v; return $this; }
    public function getNextActionDate(): ?\DateTimeInterface { return $this->nextActionDate; }
    public function setNextActionDate(?\DateTimeInterface $v): static { $this->nextActionDate = $v; return $this; }
    public function getPerformedBy(): ?User { return $this->performedBy; }
    public function setPerformedBy(?User $v): static { $this->performedBy = $v; return $this; }
    public function getPerformedAt(): \DateTimeInterface { return $this->performedAt; }
    public function setPerformedAt(\DateTimeInterface $v): static { $this->performedAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'opportunity'    => $this->opportunity ? ['id' => $this->opportunity->getId(), 'title' => $this->opportunity->getTitle()] : null,
            'client'         => $this->client ? ['id' => $this->client->getId(), 'name' => $this->client->getName()] : null,
            'activityType'   => $this->activityType,
            'subject'        => $this->subject,
            'description'    => $this->description,
            'outcome'        => $this->outcome,
            'nextAction'     => $this->nextAction,
            'nextActionDate' => $this->nextActionDate?->format('Y-m-d'),
            'performedBy'    => $this->performedBy ? ['id' => $this->performedBy->getId(), 'name' => $this->performedBy->getName()] : null,
            'performedAt'    => $this->performedAt->format('Y-m-d H:i:s'),
        ];
    }
}
