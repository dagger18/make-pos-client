<?php

namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class StuffingInstructionLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?StuffingInstruction $stuffingInstruction = null;

    #[ORM\Column(nullable: true)]
    private ?int $receiptId = null;

    #[ORM\Column]
    private int $shipmentId = 0;

    #[ORM\Column]
    private int $piecesToStuff = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    private string $weightKg = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $volumeCbm = null;

    #[ORM\Column(nullable: true)]
    private ?int $loadSequence = null;

    #[ORM\Column]
    private bool $isStuffed = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $stuffedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getStuffingInstruction(): ?StuffingInstruction { return $this->stuffingInstruction; }
    public function setStuffingInstruction(?StuffingInstruction $v): static { $this->stuffingInstruction = $v; return $this; }

    public function getReceiptId(): ?int { return $this->receiptId; }
    public function setReceiptId(?int $v): static { $this->receiptId = $v; return $this; }

    public function getShipmentId(): int { return $this->shipmentId; }
    public function setShipmentId(int $v): static { $this->shipmentId = $v; return $this; }

    public function getPiecesToStuff(): int { return $this->piecesToStuff; }
    public function setPiecesToStuff(int $v): static { $this->piecesToStuff = $v; return $this; }

    public function getWeightKg(): string { return $this->weightKg; }
    public function setWeightKg(string $v): static { $this->weightKg = $v; return $this; }

    public function getVolumeCbm(): ?string { return $this->volumeCbm; }
    public function setVolumeCbm(?string $v): static { $this->volumeCbm = $v; return $this; }

    public function getLoadSequence(): ?int { return $this->loadSequence; }
    public function setLoadSequence(?int $v): static { $this->loadSequence = $v; return $this; }

    public function isStuffed(): bool { return $this->isStuffed; }
    public function setIsStuffed(bool $v): static { $this->isStuffed = $v; return $this; }

    public function getStuffedAt(): ?\DateTimeInterface { return $this->stuffedAt; }
    public function setStuffedAt(?\DateTimeInterface $v): static { $this->stuffedAt = $v; return $this; }
}
