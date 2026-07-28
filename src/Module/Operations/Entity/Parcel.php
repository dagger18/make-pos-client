<?php
namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\ParcelRepository;

#[ORM\Entity(repositoryClass: ParcelRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Parcel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 16)]
    private string $serviceLevel = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $integrator = null;

    #[ORM\Column]
    private int $pieces = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3)]
    private string $grossWeightKg = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $declaredValue = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $declaredCurrency = null;

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

    public function getTrackingNumber(): ?string { return $this->trackingNumber; }
    public function setTrackingNumber(?string $v): static { $this->trackingNumber = $v; return $this; }

    public function getServiceLevel(): string { return $this->serviceLevel; }
    public function setServiceLevel(string $v): static { $this->serviceLevel = $v; return $this; }

    public function getIntegrator(): ?string { return $this->integrator; }
    public function setIntegrator(?string $v): static { $this->integrator = $v; return $this; }

    public function getPieces(): int { return $this->pieces; }
    public function setPieces(int $v): static { $this->pieces = $v; return $this; }

    public function getGrossWeightKg(): string { return $this->grossWeightKg; }
    public function setGrossWeightKg(string $v): static { $this->grossWeightKg = $v; return $this; }

    public function getDeclaredValue(): ?string { return $this->declaredValue; }
    public function setDeclaredValue(?string $v): static { $this->declaredValue = $v; return $this; }

    public function getDeclaredCurrency(): ?string { return $this->declaredCurrency; }
    public function setDeclaredCurrency(?string $v): static { $this->declaredCurrency = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
