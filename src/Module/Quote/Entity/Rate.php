<?php

namespace App\Module\Quote\Entity;

use App\Module\Core\Entity\Port;
use App\Module\Core\Entity\User;
use App\Module\Finance\Entity\Charge;
use App\Module\Carrier\Entity\Provider;

use App\Module\Core\Entity\Money;

use App\Module\Finance\Enum\ChargeType;
use App\Module\Operations\Enum\ContainerType;
use App\Module\Quote\Enum\FreightTerm;
use Doctrine\DBAL\Types\Types;
use App\Module\Core\Enum\TransportType;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Finance\Enum\LocalChargeType;
use App\Module\Quote\Entity\RateImportJob;
use App\Module\Quote\Repository\RateRepository;
use App\Misc\Attribute\NullableEmbeddable;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Misc\Attribute\ContainsNullableEmbeddable;

#[ORM\Entity(repositoryClass: RateRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ContainsNullableEmbeddable]
class Rate
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Charge $charge = null;

    #[ORM\ManyToOne]
    private ?Provider $provider = null;

    #[ORM\Column(length: 255, nullable: true, enumType: LocalChargeType::class)]
    private ?LocalChargeType $localChargeType = null;

    #[ORM\Column(length: 255, enumType: ChargeType::class)]
    private ChargeType $chargeType;

    #[ORM\Column(length: 255, enumType: TransportType::class)]
    private TransportType $transportType;

    #[ORM\ManyToOne]
    private ?Port $localChargePort = null;

    #[ORM\ManyToOne]
    private ?Port $polPort = null;

    #[ORM\ManyToOne]
    private ?Port $podPort = null;

    #[ORM\ManyToOne]
    private ?Port $viaPort = null;

    #[ORM\Column(length: 255, nullable: true, enumType: ContainerType::class)]
    private ?ContainerType $containerType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commodity = null;

    #[ORM\Column(nullable: true)]
    private ?int $transitTime = null;

    #[ORM\Column(length: 10, nullable: true, enumType: FreightTerm::class)]
    private ?FreightTerm $freightTerm = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $buying;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $selling;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?RateImportJob $importJob = null;

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

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getLocalChargeType(): ?LocalChargeType
    {
        return $this->localChargeType;
    }

    public function setLocalChargeType(?LocalChargeType $localChargeType): static
    {
        $this->localChargeType = $localChargeType;

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

    public function getLocalChargePort(): ?Port
    {
        return $this->localChargePort;
    }

    public function setLocalChargePort(?Port $localChargePort): static
    {
        $this->localChargePort = $localChargePort;

        return $this;
    }

    public function getPolPort(): ?Port
    {
        return $this->polPort;
    }

    public function setPolPort(?Port $polPort): static
    {
        $this->polPort = $polPort;
        return $this;
    }

    public function getPodPort(): ?Port
    {
        return $this->podPort;
    }

    public function setPodPort(?Port $podPort): static
    {
        $this->podPort = $podPort;
        return $this;
    }

    public function getViaPort(): ?Port
    {
        return $this->viaPort;
    }

    public function setViaPort(?Port $viaPort): static
    {
        $this->viaPort = $viaPort;
        return $this;
    }

    public function getContainerType(): ?ContainerType
    {
        return $this->containerType;
    }

    public function setContainerType(?ContainerType $containerType): static
    {
        $this->containerType = $containerType;
        return $this;
    }

    public function getCommodity(): ?string
    {
        return $this->commodity;
    }

    public function setCommodity(?string $commodity): static
    {
        $this->commodity = $commodity;
        return $this;
    }

    public function getTransitTime(): ?int
    {
        return $this->transitTime;
    }

    public function setTransitTime(?int $transitTime): static
    {
        $this->transitTime = $transitTime;
        return $this;
    }

    public function getFreightTerm(): ?FreightTerm
    {
        return $this->freightTerm;
    }

    public function setFreightTerm(?FreightTerm $freightTerm): static
    {
        $this->freightTerm = $freightTerm;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;

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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getImportJob(): ?RateImportJob
    {
        return $this->importJob;
    }

    public function setImportJob(?RateImportJob $importJob): static
    {
        $this->importJob = $importJob;
        return $this;
    }
}
