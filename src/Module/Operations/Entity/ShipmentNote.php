<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\User;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Enum\NoteType;
use App\Module\Operations\Enum\NoteVisibility;
use App\Module\Operations\Repository\ShipmentNoteRepository;

#[ORM\Entity(repositoryClass: ShipmentNoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ShipmentNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 16, enumType: NoteType::class)]
    private NoteType $noteType = NoteType::Internal;

    #[ORM\Column(type: Types::TEXT)]
    private string $body = '';

    #[ORM\Column]
    private bool $isPinned = false;

    #[ORM\Column(length: 16, enumType: NoteVisibility::class)]
    private NoteVisibility $visibleTo = NoteVisibility::Internal;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $shipment): static { $this->shipment = $shipment; return $this; }

    public function getNoteType(): NoteType { return $this->noteType; }
    public function setNoteType(NoteType $noteType): static { $this->noteType = $noteType; return $this; }

    public function getBody(): string { return $this->body; }
    public function setBody(string $body): static { $this->body = $body; return $this; }

    public function isPinned(): bool { return $this->isPinned; }
    public function setIsPinned(bool $isPinned): static { $this->isPinned = $isPinned; return $this; }

    public function getVisibleTo(): NoteVisibility { return $this->visibleTo; }
    public function setVisibleTo(NoteVisibility $visibleTo): static { $this->visibleTo = $visibleTo; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
