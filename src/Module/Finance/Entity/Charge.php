<?php

namespace App\Module\Finance\Entity;

use App\Module\Core\Entity\User;
use App\Module\Quote\Entity\CalculationType;
use App\Module\Quote\Entity\CustomChargeType;

use App\Module\Finance\Enum\ChargeType;
use Doctrine\DBAL\Types\Types;
use App\Module\Core\Enum\TransportType;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Finance\Repository\ChargeRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: ChargeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Charge
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, enumType: ChargeType::class)]
    private ChargeType $chargeType;

    #[ORM\Column(length: 255, enumType: TransportType::class)]
    private TransportType $transportType;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customCalcType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2, nullable: true)]
    private ?float $debitTax = null;

    #[ORM\Column(type: "decimal", precision: 5, scale: 2, nullable: true)]
    private ?float $creditTax = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    private ?CustomChargeType $customChargeType = null;

    #[ORM\ManyToOne]
    private ?CalculationType $calculationType = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getChargeType(): ?ChargeType
    {
        return $this->chargeType;
    }

    public function setChargeType(ChargeType $chargeType): static
    {
        $this->chargeType = $chargeType;

        return $this;
    }

    public function getTransportType(): ?TransportType
    {
        return $this->transportType;
    }

    public function setTransportType(TransportType $transportType): static
    {
        $this->transportType = $transportType;

        return $this;
    }

    public function getCustomCalcType(): ?string
    {
        return $this->customCalcType;
    }

    public function setCustomCalcType(?string $customCalcType): static
    {
        $this->customCalcType = $customCalcType;

        return $this;
    }

    public function getCustomCode(): ?string
    {
        return $this->customCode;
    }

    public function setCustomCode(?string $customCode): static
    {
        $this->customCode = $customCode;

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

    public function getDebitTax(): ?float
    {
        return $this->debitTax;
    }

    public function setDebitTax(?float $debitTax): static
    {
        $this->debitTax = $debitTax;

        return $this;
    }

    public function getCreditTax(): ?float
    {
        return $this->creditTax;
    }

    public function setCreditTax(?float $creditTax): static
    {
        $this->creditTax = $creditTax;

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

    public function getCustomChargeType(): ?CustomChargeType
    {
        return $this->customChargeType;
    }

    public function setCustomChargeType(?CustomChargeType $customChargeType): static
    {
        $this->customChargeType = $customChargeType;

        return $this;
    }

    public function getCalculationType(): ?CalculationType
    {
        return $this->calculationType;
    }

    public function setCalculationType(?CalculationType $calculationType): static
    {
        $this->calculationType = $calculationType;

        return $this;
    }
}
