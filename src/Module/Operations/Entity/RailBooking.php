<?php
namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\RailBookingRepository;

#[ORM\Entity(repositoryClass: RailBookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RailBooking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $trainService = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $departureIcd = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $arrivalIcd = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $operator = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $cimWaybillNumber = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cimWaybillDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $departureDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $arrivalDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $containerCount = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

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

    public function getTrainService(): ?string { return $this->trainService; }
    public function setTrainService(?string $v): static { $this->trainService = $v; return $this; }

    public function getDepartureIcd(): ?string { return $this->departureIcd; }
    public function setDepartureIcd(?string $v): static { $this->departureIcd = $v; return $this; }

    public function getArrivalIcd(): ?string { return $this->arrivalIcd; }
    public function setArrivalIcd(?string $v): static { $this->arrivalIcd = $v; return $this; }

    public function getOperator(): ?string { return $this->operator; }
    public function setOperator(?string $v): static { $this->operator = $v; return $this; }

    public function getCimWaybillNumber(): ?string { return $this->cimWaybillNumber; }
    public function setCimWaybillNumber(?string $v): static { $this->cimWaybillNumber = $v; return $this; }

    public function getCimWaybillDate(): ?\DateTimeInterface { return $this->cimWaybillDate; }
    public function setCimWaybillDate(?\DateTimeInterface $v): static { $this->cimWaybillDate = $v; return $this; }

    public function getDepartureDate(): ?\DateTimeInterface { return $this->departureDate; }
    public function setDepartureDate(?\DateTimeInterface $v): static { $this->departureDate = $v; return $this; }

    public function getArrivalDate(): ?\DateTimeInterface { return $this->arrivalDate; }
    public function setArrivalDate(?\DateTimeInterface $v): static { $this->arrivalDate = $v; return $this; }

    public function getContainerCount(): ?int { return $this->containerCount; }
    public function setContainerCount(?int $v): static { $this->containerCount = $v; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $v): static { $this->note = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
