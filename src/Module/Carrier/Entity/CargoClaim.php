<?php
namespace App\Module\Carrier\Entity;

use App\Module\Operations\Entity\Shipment;

use App\Module\Carrier\Repository\CargoClaimRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CargoClaimRepository::class)]
class CargoClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Shipment $shipment;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Provider $carrier;

    #[ORM\Column(length: 8)]
    private string $transportMode = 'OCN';

    #[ORM\Column(length: 32)]
    private string $claimType;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $claimDate;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $claimAmount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 16)]
    private string $status = 'OPEN';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $settlementAmount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $settledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $v): static { $this->shipment = $v; return $this; }
    public function getCarrier(): Provider { return $this->carrier; }
    public function setCarrier(Provider $v): static { $this->carrier = $v; return $this; }
    public function getTransportMode(): string { return $this->transportMode; }
    public function setTransportMode(string $v): static { $this->transportMode = $v; return $this; }
    public function getClaimType(): string { return $this->claimType; }
    public function setClaimType(string $v): static { $this->claimType = $v; return $this; }
    public function getClaimDate(): \DateTimeInterface { return $this->claimDate; }
    public function setClaimDate(\DateTimeInterface $v): static { $this->claimDate = $v; return $this; }
    public function getClaimAmount(): float { return (float) $this->claimAmount; }
    public function setClaimAmount(float $v): static { $this->claimAmount = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getSettlementAmount(): ?float { return $this->settlementAmount !== null ? (float) $this->settlementAmount : null; }
    public function setSettlementAmount(?float $v): static { $this->settlementAmount = $v; return $this; }
    public function getSettledAt(): ?\DateTimeInterface { return $this->settledAt; }
    public function setSettledAt(?\DateTimeInterface $v): static { $this->settledAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'shipmentId'       => $this->shipment->getId(),
            'carrierId'        => $this->carrier->getId(),
            'carrierName'      => $this->carrier->getName(),
            'transportMode'    => $this->transportMode,
            'claimType'        => $this->claimType,
            'claimDate'        => $this->claimDate->format('Y-m-d'),
            'claimAmount'      => $this->getClaimAmount(),
            'currency'         => $this->currency,
            'description'      => $this->description,
            'status'           => $this->status,
            'settlementAmount' => $this->getSettlementAmount(),
            'settledAt'        => $this->settledAt?->format('Y-m-d'),
            'createdAt'        => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
