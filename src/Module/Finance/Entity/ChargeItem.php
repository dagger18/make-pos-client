<?php

namespace App\Module\Finance\Entity;

use App\Module\Core\Entity\Department;
use App\Module\Core\Entity\Money;

use Doctrine\ORM\Mapping as ORM;
use App\Module\Finance\Repository\ChargeItemRepository;
use App\Misc\Attribute\NullableEmbeddable;
use App\Misc\Attribute\ContainsNullableEmbeddable;
use App\Module\Finance\Enum\PayableAt;
use App\Module\Core\Enum\VisibleTo;

#[ORM\Entity(repositoryClass: ChargeItemRepository::class)]
#[ContainsNullableEmbeddable]
class ChargeItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?Charge $charge = null;

    #[ORM\Column(length: 255)]
    private ?string $chargeName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chargeType = null;

    #[ORM\Column(length: 255)]
    private ?string $chargeTypeName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $calculationType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?float $quantity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerName = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $price;

    #[ORM\ManyToOne]
    private ?TaxGroup $taxGroup = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $tax;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $chargePrice;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $amount;

    #[ORM\ManyToOne(inversedBy: 'chargeItems', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?EbitNote $ebitNote = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    #[ORM\Column(type: "string", length: 16, enumType: PayableAt::class, nullable: true)]
    private ?PayableAt $payableAt = null;

    #[ORM\Column(type: "string", length: 16, enumType: VisibleTo::class, nullable: true)]
    private ?VisibleTo $visibleTo = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $expectedAmount = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $taxCode = null;

    #[ORM\Column(name: 'tax_pct', type: 'decimal', precision: 6, scale: 4, options: ['default' => '0.0000'])]
    private string $taxRate = '0.0000';

    #[ORM\Column(options: ['default' => false])]
    private bool $isZeroRated = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isExempt = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isReverseCharge = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharge(): ?Charge
    {
        return $this->charge;
    }

    public function setCharge(?Charge $charge): static
    {
        $this->charge = $charge;

        return $this;
    }

    public function getChargeName(): ?string
    {
        return $this->chargeName;
    }

    public function setChargeName(string $chargeName): static
    {
        $this->chargeName = $chargeName;

        return $this;
    }

    public function getChargeType(): ?string
    {
        return $this->chargeType;
    }

    public function setChargeType(?string $chargeType): static
    {
        $this->chargeType = $chargeType;

        return $this;
    }

    public function getChargeTypeName(): ?string
    {
        return $this->chargeTypeName;
    }

    public function setChargeTypeName(string $chargeTypeName): static
    {
        $this->chargeTypeName = $chargeTypeName;

        return $this;
    }

    public function getCalculationType(): ?string
    {
        return $this->calculationType;
    }

    public function setCalculationType(?string $calculationType): static
    {
        $this->calculationType = $calculationType;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function setQuantity(?float $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(?string $providerName): static
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getPrice(): ?Money
    {
        return $this->price;
    }

    public function setPrice(?Money $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getTaxGroup(): ?TaxGroup
    {
        return $this->taxGroup;
    }

    public function setTaxGroup(?TaxGroup $taxGroup): static
    {
        $this->taxGroup = $taxGroup;

        return $this;
    }

    public function getTax(): ?Money
    {
        return $this->tax;
    }

    public function setTax(?Money $tax): static
    {
        $this->tax = $tax;

        return $this;
    }

    public function getChargePrice(): ?Money
    {
        return $this->chargePrice;
    }

    public function setChargePrice(?Money $chargePrice): static
    {
        $this->chargePrice = $chargePrice;

        return $this;
    }

    public function getAmount(): ?Money
    {
        return $this->amount;
    }

    public function setAmount(?Money $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getEbitNote(): ?EbitNote
    {
        return $this->ebitNote;
    }

    public function setEbitNote(?EbitNote $ebitNote): static
    {
        $this->ebitNote = $ebitNote;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): static
    {
        $this->department = $department;

        return $this;
    }

    public function getPayableAt(): ?PayableAt
    {
        return $this->payableAt;
    }

    public function setPayableAt(?PayableAt $payableAt): static
    {
        $this->payableAt = $payableAt;

        return $this;
    }

    public function getVisibleTo(): ?VisibleTo
    {
        return $this->visibleTo;
    }

    public function setVisibleTo(?VisibleTo $visibleTo): static
    {
        $this->visibleTo = $visibleTo;

        return $this;
    }

    public function getExpectedAmount(): ?Money { return $this->expectedAmount; }
    public function setExpectedAmount(?Money $m): static { $this->expectedAmount = $m; return $this; }
    public function getTaxCode(): ?string { return $this->taxCode; }
    public function setTaxCode(?string $v): static { $this->taxCode = $v; return $this; }
    public function getTaxRate(): float { return (float) $this->taxRate; }
    public function setTaxRate(float $v): static { $this->taxRate = (string) $v; return $this; }
    public function isZeroRated(): bool { return $this->isZeroRated; }
    public function setIsZeroRated(bool $v): static { $this->isZeroRated = $v; return $this; }
    public function isExempt(): bool { return $this->isExempt; }
    public function setIsExempt(bool $v): static { $this->isExempt = $v; return $this; }
    public function isReverseCharge(): bool { return $this->isReverseCharge; }
    public function setIsReverseCharge(bool $v): static { $this->isReverseCharge = $v; return $this; }
}
