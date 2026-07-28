<?php
namespace App\Module\Crm\Entity;

use App\Module\Core\Entity\User;
use App\Module\Operations\Entity\Shipment;
use App\Module\Crm\Repository\SalesCommissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SalesCommissionRepository::class)]
class SalesCommission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $salesRep;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 32)]
    private string $commissionBasis;
    // PCT_REVENUE / PCT_PROFIT / FLAT

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 4, nullable: true)]
    private ?float $commissionRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $baseAmount;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $commissionAmount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 16)]
    private string $status = 'CALCULATED';
    // CALCULATED / APPROVED / PAID

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $periodMonth = null; // YYYY-MM

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getSalesRep(): User { return $this->salesRep; }
    public function setSalesRep(User $v): static { $this->salesRep = $v; return $this; }
    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $v): static { $this->shipment = $v; return $this; }
    public function getCommissionBasis(): string { return $this->commissionBasis; }
    public function setCommissionBasis(string $v): static { $this->commissionBasis = $v; return $this; }
    public function getCommissionRate(): ?float { return $this->commissionRate !== null ? (float) $this->commissionRate : null; }
    public function setCommissionRate(?float $v): static { $this->commissionRate = $v; return $this; }
    public function getBaseAmount(): float { return (float) $this->baseAmount; }
    public function setBaseAmount(float $v): static { $this->baseAmount = $v; return $this; }
    public function getCommissionAmount(): float { return (float) $this->commissionAmount; }
    public function setCommissionAmount(float $v): static { $this->commissionAmount = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getPeriodMonth(): ?string { return $this->periodMonth; }
    public function setPeriodMonth(?string $v): static { $this->periodMonth = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'salesRep'         => ['id' => $this->salesRep->getId(), 'name' => $this->salesRep->getName()],
            'shipmentId'       => $this->shipment->getId(),
            'shipmentCode'     => $this->shipment->getCode(),
            'commissionBasis'  => $this->commissionBasis,
            'commissionRate'   => $this->getCommissionRate(),
            'baseAmount'       => $this->getBaseAmount(),
            'commissionAmount' => $this->getCommissionAmount(),
            'currency'         => $this->currency,
            'status'           => $this->status,
            'periodMonth'      => $this->periodMonth,
            'createdAt'        => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
