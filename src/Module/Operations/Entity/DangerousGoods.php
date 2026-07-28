<?php

namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\DangerousGoodsRepository;

#[ORM\Entity(repositoryClass: DangerousGoodsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DangerousGoods
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 8)]
    private string $imoClass = '';

    #[ORM\Column(length: 8)]
    private string $unNumber = '';

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $packingGroup = null;

    #[ORM\Column(length: 255)]
    private string $properName = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $technicalName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $netQuantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $grossQuantity = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $uom = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $flashPoint = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $subsidiaryRisk = null;

    #[ORM\Column]
    private bool $isMarinePollutant = false;

    #[ORM\Column]
    private bool $isLimitedQty = false;

    #[ORM\Column]
    private bool $isExceptedQty = false;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $emergencyContact = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $msdsUrl = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function prePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }

    public function getImoClass(): string { return $this->imoClass; }
    public function setImoClass(string $v): static { $this->imoClass = $v; return $this; }

    public function getUnNumber(): string { return $this->unNumber; }
    public function setUnNumber(string $v): static { $this->unNumber = $v; return $this; }

    public function getPackingGroup(): ?string { return $this->packingGroup; }
    public function setPackingGroup(?string $v): static { $this->packingGroup = $v; return $this; }

    public function getProperName(): string { return $this->properName; }
    public function setProperName(string $v): static { $this->properName = $v; return $this; }

    public function getTechnicalName(): ?string { return $this->technicalName; }
    public function setTechnicalName(?string $v): static { $this->technicalName = $v; return $this; }

    public function getNetQuantity(): ?string { return $this->netQuantity; }
    public function setNetQuantity(?string $v): static { $this->netQuantity = $v; return $this; }

    public function getGrossQuantity(): ?string { return $this->grossQuantity; }
    public function setGrossQuantity(?string $v): static { $this->grossQuantity = $v; return $this; }

    public function getUom(): ?string { return $this->uom; }
    public function setUom(?string $v): static { $this->uom = $v; return $this; }

    public function getFlashPoint(): ?string { return $this->flashPoint; }
    public function setFlashPoint(?string $v): static { $this->flashPoint = $v; return $this; }

    public function getSubsidiaryRisk(): ?string { return $this->subsidiaryRisk; }
    public function setSubsidiaryRisk(?string $v): static { $this->subsidiaryRisk = $v; return $this; }

    public function isMarinePollutant(): bool { return $this->isMarinePollutant; }
    public function setIsMarinePollutant(bool $v): static { $this->isMarinePollutant = $v; return $this; }

    public function isLimitedQty(): bool { return $this->isLimitedQty; }
    public function setIsLimitedQty(bool $v): static { $this->isLimitedQty = $v; return $this; }

    public function isExceptedQty(): bool { return $this->isExceptedQty; }
    public function setIsExceptedQty(bool $v): static { $this->isExceptedQty = $v; return $this; }

    public function getEmergencyContact(): ?string { return $this->emergencyContact; }
    public function setEmergencyContact(?string $v): static { $this->emergencyContact = $v; return $this; }

    public function getMsdsUrl(): ?string { return $this->msdsUrl; }
    public function setMsdsUrl(?string $v): static { $this->msdsUrl = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
