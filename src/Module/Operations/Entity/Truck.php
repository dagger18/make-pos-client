<?php
namespace App\Module\Operations\Entity;

use App\Module\Carrier\Entity\Provider;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\TruckRepository;

#[ORM\Entity(repositoryClass: TruckRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Truck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32)]
    private string $truckType = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $payloadKg = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $truckPlate = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $driverName = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Provider $haulier = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pickupAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $deliveryAddress = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledPickup = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scheduledDelivery = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualPickup = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualDelivery = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $podSignedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $podImageUrl = null;

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

    public function getTruckType(): string { return $this->truckType; }
    public function setTruckType(string $v): static { $this->truckType = $v; return $this; }

    public function getPayloadKg(): ?string { return $this->payloadKg; }
    public function setPayloadKg(?string $v): static { $this->payloadKg = $v; return $this; }

    public function getTruckPlate(): ?string { return $this->truckPlate; }
    public function setTruckPlate(?string $v): static { $this->truckPlate = $v; return $this; }

    public function getDriverName(): ?string { return $this->driverName; }
    public function setDriverName(?string $v): static { $this->driverName = $v; return $this; }

    public function getHaulier(): ?Provider { return $this->haulier; }
    public function setHaulier(?Provider $v): static { $this->haulier = $v; return $this; }

    public function getPickupAddress(): ?string { return $this->pickupAddress; }
    public function setPickupAddress(?string $v): static { $this->pickupAddress = $v; return $this; }

    public function getDeliveryAddress(): ?string { return $this->deliveryAddress; }
    public function setDeliveryAddress(?string $v): static { $this->deliveryAddress = $v; return $this; }

    public function getScheduledPickup(): ?\DateTimeInterface { return $this->scheduledPickup; }
    public function setScheduledPickup(?\DateTimeInterface $v): static { $this->scheduledPickup = $v; return $this; }

    public function getScheduledDelivery(): ?\DateTimeInterface { return $this->scheduledDelivery; }
    public function setScheduledDelivery(?\DateTimeInterface $v): static { $this->scheduledDelivery = $v; return $this; }

    public function getActualPickup(): ?\DateTimeInterface { return $this->actualPickup; }
    public function setActualPickup(?\DateTimeInterface $v): static { $this->actualPickup = $v; return $this; }

    public function getActualDelivery(): ?\DateTimeInterface { return $this->actualDelivery; }
    public function setActualDelivery(?\DateTimeInterface $v): static { $this->actualDelivery = $v; return $this; }

    public function getPodSignedBy(): ?string { return $this->podSignedBy; }
    public function setPodSignedBy(?string $v): static { $this->podSignedBy = $v; return $this; }

    public function getPodImageUrl(): ?string { return $this->podImageUrl; }
    public function setPodImageUrl(?string $v): static { $this->podImageUrl = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
