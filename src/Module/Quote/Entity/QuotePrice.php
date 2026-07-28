<?php

namespace App\Module\Quote\Entity;

use App\Module\Core\Entity\Department;
use App\Module\Core\Entity\User;
use App\Module\Finance\Entity\Charge;
use App\Module\Carrier\Entity\Provider;

use App\Module\Core\Entity\Money;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Quote\Repository\QuotePriceRepository;
use App\Misc\Attribute\NullableEmbeddable;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Misc\Attribute\ContainsNullableEmbeddable;
use App\Module\Finance\Enum\PayableAt;
use App\Module\Core\Enum\VisibleTo;

#[ORM\Entity(repositoryClass: QuotePriceRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ContainsNullableEmbeddable]
class QuotePrice
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Charge $charge = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $calculationType = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    private ?Provider $provider = null;

    #[ORM\Column(type: "decimal", precision: 12, scale: 2)]
    private ?float $quantity = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $buying;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $selling;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $amountBuying = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $amountSelling = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'prices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quote $quote = null;

    #[ORM\Column(length: 255)]
    private ?string $chargeTypeName = null;

    #[ORM\Column(length: 255)]
    private ?string $chargeName = null;

    #[ORM\Column(length: 255)]
    private ?string $providerName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chargeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    #[ORM\Column(type: "string", length: 16, enumType: PayableAt::class, nullable: true)]
    private ?PayableAt $payableAt = null;

    #[ORM\Column(type: "string", length: 16, enumType: VisibleTo::class, nullable: true)]
    private ?VisibleTo $visibleTo = null;

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

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getBuying(): ?Money
    {
        return $this->buying;
    }

    public function setBuying(Money $buying): static
    {
        $this->buying = $buying;

        return $this;
    }

    public function getSelling(): ?Money
    {
        return $this->selling;
    }

    public function setSelling(Money $selling): static
    {
        $this->selling = $selling;

        return $this;
    }

    public function getAmountBuying(): ?Money
    {
        return $this->amountBuying;
    }

    public function setAmountBuying(Money $amountBuying): static
    {
        $this->amountBuying = $amountBuying;

        return $this;
    }

    public function getAmountSelling(): ?Money
    {
        return $this->amountSelling;
    }

    public function setAmountSelling(Money $amountSelling): static
    {
        $this->amountSelling = $amountSelling;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): static
    {
        $this->quote = $quote;

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

    public function getChargeName(): ?string
    {
        return $this->chargeName;
    }

    public function setChargeName(string $chargeName): static
    {
        $this->chargeName = $chargeName;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): static
    {
        $this->providerName = $providerName;

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
}
