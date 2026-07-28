<?php

namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\WarehouseReceiptRepository;

#[ORM\Entity(repositoryClass: WarehouseReceiptRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WarehouseReceipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column]
    private int $facilityId = 0;

    #[ORM\Column(nullable: true)]
    private ?int $consolId = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $receiptNumber = '';

    #[ORM\Column(length: 16)]
    private string $receiptType = 'INBOUND';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $vehiclePlate = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $driverName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $driverIdRef = null;

    #[ORM\Column]
    private int $piecesReceived = 0;

    #[ORM\Column(nullable: true)]
    private ?int $piecesExpected = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3)]
    private string $grossWeightKg = '0';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $volumeCbm = null;

    #[ORM\Column(length: 16)]
    private string $conditionCode = 'GOOD';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $damageNotes = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $temperatureC = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $storageZone = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $storageLocation = null;

    #[ORM\Column(nullable: true)]
    private ?int $receivedById = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $receivedAt;

    #[ORM\Column]
    private bool $milestoneWritten = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $releasedAt = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $releasedTo = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $releaseDriver = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $releaseDoRef = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        $this->createdAt = new \DateTime();
        if (!isset($this->receivedAt)) {
            $this->receivedAt = new \DateTime();
        }
    }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }

    public function getFacilityId(): int { return $this->facilityId; }
    public function setFacilityId(int $v): static { $this->facilityId = $v; return $this; }

    public function getConsolId(): ?int { return $this->consolId; }
    public function setConsolId(?int $v): static { $this->consolId = $v; return $this; }

    public function getReceiptNumber(): string { return $this->receiptNumber; }
    public function setReceiptNumber(string $v): static { $this->receiptNumber = $v; return $this; }

    public function getReceiptType(): string { return $this->receiptType; }
    public function setReceiptType(string $v): static { $this->receiptType = $v; return $this; }

    public function getVehiclePlate(): ?string { return $this->vehiclePlate; }
    public function setVehiclePlate(?string $v): static { $this->vehiclePlate = $v; return $this; }

    public function getDriverName(): ?string { return $this->driverName; }
    public function setDriverName(?string $v): static { $this->driverName = $v; return $this; }

    public function getDriverIdRef(): ?string { return $this->driverIdRef; }
    public function setDriverIdRef(?string $v): static { $this->driverIdRef = $v; return $this; }

    public function getPiecesReceived(): int { return $this->piecesReceived; }
    public function setPiecesReceived(int $v): static { $this->piecesReceived = $v; return $this; }

    public function getPiecesExpected(): ?int { return $this->piecesExpected; }
    public function setPiecesExpected(?int $v): static { $this->piecesExpected = $v; return $this; }

    public function getGrossWeightKg(): string { return $this->grossWeightKg; }
    public function setGrossWeightKg(string $v): static { $this->grossWeightKg = $v; return $this; }

    public function getVolumeCbm(): ?string { return $this->volumeCbm; }
    public function setVolumeCbm(?string $v): static { $this->volumeCbm = $v; return $this; }

    public function getConditionCode(): string { return $this->conditionCode; }
    public function setConditionCode(string $v): static { $this->conditionCode = $v; return $this; }

    public function getDamageNotes(): ?string { return $this->damageNotes; }
    public function setDamageNotes(?string $v): static { $this->damageNotes = $v; return $this; }

    public function getTemperatureC(): ?string { return $this->temperatureC; }
    public function setTemperatureC(?string $v): static { $this->temperatureC = $v; return $this; }

    public function getStorageZone(): ?string { return $this->storageZone; }
    public function setStorageZone(?string $v): static { $this->storageZone = $v; return $this; }

    public function getStorageLocation(): ?string { return $this->storageLocation; }
    public function setStorageLocation(?string $v): static { $this->storageLocation = $v; return $this; }

    public function getReceivedById(): ?int { return $this->receivedById; }
    public function setReceivedById(?int $v): static { $this->receivedById = $v; return $this; }

    public function getReceivedAt(): \DateTimeInterface { return $this->receivedAt; }
    public function setReceivedAt(\DateTimeInterface $v): static { $this->receivedAt = $v; return $this; }

    public function isMilestoneWritten(): bool { return $this->milestoneWritten; }
    public function setMilestoneWritten(bool $v): static { $this->milestoneWritten = $v; return $this; }

    public function getReleasedAt(): ?\DateTimeInterface { return $this->releasedAt; }
    public function setReleasedAt(?\DateTimeInterface $v): static { $this->releasedAt = $v; return $this; }

    public function getReleasedTo(): ?string { return $this->releasedTo; }
    public function setReleasedTo(?string $v): static { $this->releasedTo = $v; return $this; }

    public function getReleaseDriver(): ?string { return $this->releaseDriver; }
    public function setReleaseDriver(?string $v): static { $this->releaseDriver = $v; return $this; }

    public function getReleaseDoRef(): ?string { return $this->releaseDoRef; }
    public function setReleaseDoRef(?string $v): static { $this->releaseDoRef = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
