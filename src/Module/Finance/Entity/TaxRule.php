<?php
namespace App\Module\Finance\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Finance\Repository\TaxRuleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TaxRule
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 2)]
    private string $countryCode;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $chargeCategory = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $serviceType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $customerType = null;

    #[ORM\Column(length: 16)]
    private string $taxType;

    #[ORM\Column(length: 16)]
    private string $taxCode;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 4)]
    private string $taxRate = '0.0000';

    #[ORM\Column]
    private bool $isReverseCharge = false;

    #[ORM\Column]
    private bool $isZeroRated = false;

    #[ORM\Column]
    private bool $isExempt = false;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $effectiveFrom;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }
    public function getCountryCode(): string { return $this->countryCode; }
    public function setCountryCode(string $v): static { $this->countryCode = $v; return $this; }
    public function getChargeCategory(): ?string { return $this->chargeCategory; }
    public function setChargeCategory(?string $v): static { $this->chargeCategory = $v; return $this; }
    public function getServiceType(): ?string { return $this->serviceType; }
    public function setServiceType(?string $v): static { $this->serviceType = $v; return $this; }
    public function getCustomerType(): ?string { return $this->customerType; }
    public function setCustomerType(?string $v): static { $this->customerType = $v; return $this; }
    public function getTaxType(): string { return $this->taxType; }
    public function setTaxType(string $v): static { $this->taxType = $v; return $this; }
    public function getTaxCode(): string { return $this->taxCode; }
    public function setTaxCode(string $v): static { $this->taxCode = $v; return $this; }
    public function getTaxRate(): float { return (float) $this->taxRate; }
    public function setTaxRate(float $v): static { $this->taxRate = (string) $v; return $this; }
    public function isReverseCharge(): bool { return $this->isReverseCharge; }
    public function setIsReverseCharge(bool $v): static { $this->isReverseCharge = $v; return $this; }
    public function isZeroRated(): bool { return $this->isZeroRated; }
    public function setIsZeroRated(bool $v): static { $this->isZeroRated = $v; return $this; }
    public function isExempt(): bool { return $this->isExempt; }
    public function setIsExempt(bool $v): static { $this->isExempt = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getEffectiveFrom(): \DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(\DateTimeInterface $v): static { $this->effectiveFrom = $v; return $this; }
    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $v): static { $this->effectiveTo = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'countryCode'    => $this->countryCode,
            'chargeCategory' => $this->chargeCategory,
            'serviceType'    => $this->serviceType,
            'customerType'   => $this->customerType,
            'taxType'        => $this->taxType,
            'taxCode'        => $this->taxCode,
            'taxRate'        => $this->getTaxRate(),
            'isReverseCharge'=> $this->isReverseCharge,
            'isZeroRated'    => $this->isZeroRated,
            'isExempt'       => $this->isExempt,
            'description'    => $this->description,
            'effectiveFrom'  => $this->effectiveFrom->format('Y-m-d'),
            'effectiveTo'    => $this->effectiveTo?->format('Y-m-d'),
        ];
    }
}
