<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\User;

use App\Module\Operations\Enum\TaskType;
use App\Module\Operations\Repository\ShipmentTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentTaskRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ShipmentTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 128)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 32, enumType: TaskType::class)]
    private TaskType $taskType = TaskType::Other;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $completedBy = null;

    #[ORM\Column]
    private bool $isMandatory = false;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $milestoneGate = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function isOverdue(): bool
    {
        return $this->completedAt === null
            && $this->dueDate !== null
            && $this->dueDate < new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $t): static { $this->title = $t; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function getTaskType(): TaskType { return $this->taskType; }
    public function setTaskType(TaskType $t): static { $this->taskType = $t; return $this; }

    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $u): static { $this->assignedTo = $u; return $this; }

    public function getDueDate(): ?\DateTimeInterface { return $this->dueDate; }
    public function setDueDate(?\DateTimeInterface $d): static { $this->dueDate = $d; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $d): static { $this->completedAt = $d; return $this; }

    public function getCompletedBy(): ?User { return $this->completedBy; }
    public function setCompletedBy(?User $u): static { $this->completedBy = $u; return $this; }

    public function isMandatory(): bool { return $this->isMandatory; }
    public function setIsMandatory(bool $m): static { $this->isMandatory = $m; return $this; }

    public function getMilestoneGate(): ?string { return $this->milestoneGate; }
    public function setMilestoneGate(?string $g): static { $this->milestoneGate = $g; return $this; }

    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $o): static { $this->sortOrder = $o; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
