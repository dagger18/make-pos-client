<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\User;

use App\Module\Carrier\Enum\MilestoneCode;
use App\Module\Operations\Repository\ShipmentMilestoneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentMilestoneRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ShipmentMilestone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32, enumType: MilestoneCode::class)]
    private ?MilestoneCode $milestoneCode = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $plannedDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualDate = null;

    #[ORM\Column]
    private bool $isException = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $exceptionHours = null;

    #[ORM\Column(length: 16)]
    private string $source = 'MANUAL';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarks = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function recalculateException(): void
    {
        if ($this->actualDate && $this->plannedDate) {
            $diffSeconds = $this->actualDate->getTimestamp() - $this->plannedDate->getTimestamp();
            $hours = round($diffSeconds / 3600, 2);
            $this->exceptionHours = (string) $hours;
            $this->isException = $hours > 0;
        } else {
            $this->exceptionHours = null;
            $this->isException = false;
        }
    }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $shipment): static { $this->shipment = $shipment; return $this; }

    public function getMilestoneCode(): ?MilestoneCode { return $this->milestoneCode; }
    public function setMilestoneCode(MilestoneCode $code): static { $this->milestoneCode = $code; return $this; }

    public function getPlannedDate(): ?\DateTimeInterface { return $this->plannedDate; }
    public function setPlannedDate(?\DateTimeInterface $d): static { $this->plannedDate = $d; return $this; }

    public function getActualDate(): ?\DateTimeInterface { return $this->actualDate; }
    public function setActualDate(?\DateTimeInterface $d): static { $this->actualDate = $d; return $this; }

    public function isException(): bool { return $this->isException; }
    public function getExceptionHours(): ?string { return $this->exceptionHours; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }

    public function getRemarks(): ?string { return $this->remarks; }
    public function setRemarks(?string $remarks): static { $this->remarks = $remarks; return $this; }

    public function getUpdatedBy(): ?User { return $this->updatedBy; }
    public function setUpdatedBy(?User $user): static { $this->updatedBy = $user; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
