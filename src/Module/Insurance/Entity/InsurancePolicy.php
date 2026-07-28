<?php
namespace App\Module\Insurance\Entity;

use App\Module\Carrier\Entity\Provider;
use App\Module\Insurance\Repository\InsurancePolicyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsurancePolicyRepository::class)]
class InsurancePolicy
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $policyNumber;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Provider $insurer = null;

    #[ORM\Column(length: 32)]
    private string $policyType = 'OPEN_COVER'; // OPEN_COVER / SPECIFIC_VOYAGE / LIABILITY

    #[ORM\Column(length: 32)]
    private string $coverageScope = 'ALL_RISK'; // ALL_RISK / NAMED_PERILS / TOTAL_LOSS_ONLY

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $maxPerShipment;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $maxPerConveyance = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $annualLimit = null;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 16)]
    private string $premiumBasis = 'PCT_VALUE'; // PCT_VALUE / FLAT_RATE / PER_UNIT

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 6)]
    private float $premiumRate;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $minPremium = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $deductible = null;

    #[ORM\Column(type: Types::JSON)]
    private array $modesCovered = [];

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $effectiveFrom;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $expiryDate;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getPolicyNumber(): string { return $this->policyNumber; }
    public function setPolicyNumber(string $v): static { $this->policyNumber = $v; return $this; }
    public function getInsurer(): ?Provider { return $this->insurer; }
    public function setInsurer(?Provider $v): static { $this->insurer = $v; return $this; }
    public function getPolicyType(): string { return $this->policyType; }
    public function setPolicyType(string $v): static { $this->policyType = $v; return $this; }
    public function getCoverageScope(): string { return $this->coverageScope; }
    public function setCoverageScope(string $v): static { $this->coverageScope = $v; return $this; }
    public function getMaxPerShipment(): float { return (float) $this->maxPerShipment; }
    public function setMaxPerShipment(float $v): static { $this->maxPerShipment = $v; return $this; }
    public function getMaxPerConveyance(): ?float { return $this->maxPerConveyance !== null ? (float) $this->maxPerConveyance : null; }
    public function setMaxPerConveyance(?float $v): static { $this->maxPerConveyance = $v; return $this; }
    public function getAnnualLimit(): ?float { return $this->annualLimit !== null ? (float) $this->annualLimit : null; }
    public function setAnnualLimit(?float $v): static { $this->annualLimit = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }
    public function getPremiumBasis(): string { return $this->premiumBasis; }
    public function setPremiumBasis(string $v): static { $this->premiumBasis = $v; return $this; }
    public function getPremiumRate(): float { return (float) $this->premiumRate; }
    public function setPremiumRate(float $v): static { $this->premiumRate = $v; return $this; }
    public function getMinPremium(): ?float { return $this->minPremium !== null ? (float) $this->minPremium : null; }
    public function setMinPremium(?float $v): static { $this->minPremium = $v; return $this; }
    public function getDeductible(): ?float { return $this->deductible !== null ? (float) $this->deductible : null; }
    public function setDeductible(?float $v): static { $this->deductible = $v; return $this; }
    public function getModesCovered(): array { return $this->modesCovered; }
    public function setModesCovered(array $v): static { $this->modesCovered = $v; return $this; }
    public function getEffectiveFrom(): \DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(\DateTimeInterface $v): static { $this->effectiveFrom = $v; return $this; }
    public function getExpiryDate(): \DateTimeInterface { return $this->expiryDate; }
    public function setExpiryDate(\DateTimeInterface $v): static { $this->expiryDate = $v; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'policyNumber'    => $this->policyNumber,
            'insurer'         => $this->insurer ? ['id' => $this->insurer->getId(), 'name' => $this->insurer->getName()] : null,
            'policyType'      => $this->policyType,
            'coverageScope'   => $this->coverageScope,
            'maxPerShipment'  => $this->getMaxPerShipment(),
            'maxPerConveyance'=> $this->getMaxPerConveyance(),
            'annualLimit'     => $this->getAnnualLimit(),
            'currency'        => $this->currency,
            'premiumBasis'    => $this->premiumBasis,
            'premiumRate'     => $this->getPremiumRate(),
            'minPremium'      => $this->getMinPremium(),
            'deductible'      => $this->getDeductible(),
            'modesCovered'    => $this->modesCovered,
            'effectiveFrom'   => $this->effectiveFrom->format('Y-m-d'),
            'expiryDate'      => $this->expiryDate->format('Y-m-d'),
            'isActive'        => $this->isActive,
            'createdAt'       => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
