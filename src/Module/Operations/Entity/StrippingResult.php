<?php

namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class StrippingResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'results')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StrippingInstruction $strippingInstruction = null;

    #[ORM\Column]
    private int $shipmentId = 0;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $hblNumber = null;

    #[ORM\Column]
    private int $piecesStripped = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $weightKg = null;

    #[ORM\Column(length: 16)]
    private string $conditionCode = 'GOOD';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $damageNotes = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $storageLocation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $strippedAt;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (!isset($this->strippedAt)) {
            $this->strippedAt = new \DateTime();
        }
    }

    public function getId(): ?int { return $this->id; }

    public function getStrippingInstruction(): ?StrippingInstruction { return $this->strippingInstruction; }
    public function setStrippingInstruction(?StrippingInstruction $v): static { $this->strippingInstruction = $v; return $this; }

    public function getShipmentId(): int { return $this->shipmentId; }
    public function setShipmentId(int $v): static { $this->shipmentId = $v; return $this; }

    public function getHblNumber(): ?string { return $this->hblNumber; }
    public function setHblNumber(?string $v): static { $this->hblNumber = $v; return $this; }

    public function getPiecesStripped(): int { return $this->piecesStripped; }
    public function setPiecesStripped(int $v): static { $this->piecesStripped = $v; return $this; }

    public function getWeightKg(): ?string { return $this->weightKg; }
    public function setWeightKg(?string $v): static { $this->weightKg = $v; return $this; }

    public function getConditionCode(): string { return $this->conditionCode; }
    public function setConditionCode(string $v): static { $this->conditionCode = $v; return $this; }

    public function getDamageNotes(): ?string { return $this->damageNotes; }
    public function setDamageNotes(?string $v): static { $this->damageNotes = $v; return $this; }

    public function getStorageLocation(): ?string { return $this->storageLocation; }
    public function setStorageLocation(?string $v): static { $this->storageLocation = $v; return $this; }

    public function getStrippedAt(): \DateTimeInterface { return $this->strippedAt; }
    public function setStrippedAt(\DateTimeInterface $v): static { $this->strippedAt = $v; return $this; }
}
