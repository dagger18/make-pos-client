<?php

namespace App\Module\Quote\Entity;

use App\Module\Core\Entity\Port;
use App\Module\Core\Entity\User;
use App\Module\Operations\Entity\ShipmentMode;
use App\Module\Carrier\Entity\Provider;
use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\Contact;

use App\Module\Operations\Entity\Shipment;

use App\Module\Quote\Enum\QuoteStatus;
use App\Module\Core\Enum\ServiceType;
use App\Module\Core\Enum\WeekDay;
use App\Module\Core\Enum\VolumeType;
use Doctrine\DBAL\Types\Types;
use App\Module\Operations\Enum\ShipmentType;
use App\Module\Core\Enum\TransportType;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Quote\Repository\QuoteRepository;
use Doctrine\Common\Collections\Collection;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Provider $provider = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    private ?Contact $contactPerson = null;

    #[ORM\Column(length: 255, enumType: TransportType::class)]
    private ?TransportType $transportType;

    #[ORM\Column(length: 16, enumType: ServiceType::class, nullable: true)]
    private ?ServiceType $serviceType = null;

    #[ORM\ManyToOne]
    private ?ShipmentMode $shipmentMode = null;

    #[ORM\Column(length: 255, enumType: ShipmentType::class)]
    private ?ShipmentType $shipmentType;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originDoor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Port $originPort = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $destinationDoor = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Port $destinationPort = null;

    #[ORM\Column(length: 255, enumType: VolumeType::class)]
    private ?VolumeType $cargoVolumeType;

    #[ORM\Column(nullable: true)]
    private array $cargoVolume = [];

    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuotePrice::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $prices;

    #[ORM\Column(nullable: true)]
    private array $subTotal = [];

    #[ORM\Column(type: "decimal", precision: 15, scale: 4, nullable: true)]
    private ?float $totalBuying = null;

    #[ORM\Column(type: "decimal", precision: 15, scale: 4, nullable: true)]
    private ?float $totalSelling = null;

    #[ORM\Column(type: "decimal", precision: 15, scale: 4, nullable: true)]
    private ?float $totalProfit = null;

    #[ORM\Column]
    private array $exchangeRates = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true, enumType: WeekDay::class)]
    private array $schedule = [];

    #[ORM\Column(nullable: true)]
    private ?int $transitTime = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remark = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $estimatedDeparture = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cargoReady = null;

    #[ORM\ManyToOne]
    private ?Incoterm $incoterm = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commodities = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(length: 255, enumType: QuoteStatus::class)]
    private ?QuoteStatus $status;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $transitPort = null;

    #[ORM\OneToOne(mappedBy: 'quote', cascade: ['persist', 'remove', 'detach'])]
    private ?Shipment $shipment = null;

    #[ORM\ManyToOne]
    private ?Provider $carrier = null;

    #[ORM\ManyToOne]
    private ?Port $subOriginPort = null;

    #[ORM\ManyToOne]
    private ?Port $subDestinationPort = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $quoteCurrency = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subVesselNo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vesselNo = null;

    public function __construct()
    {
        $this->prices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getContactPerson(): ?Contact
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?Contact $contactPerson): static
    {
        $this->contactPerson = $contactPerson;

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

    public function getServiceType(): ?ServiceType
    {
        return $this->serviceType;
    }

    public function setServiceType(?ServiceType $serviceType): static
    {
        $this->serviceType = $serviceType;

        return $this;
    }

    public function getShipmentMode(): ?ShipmentMode
    {
        return $this->shipmentMode;
    }

    public function setShipmentMode(?ShipmentMode $shipmentMode): static
    {
        $this->shipmentMode = $shipmentMode;

        return $this;
    }

    public function getShipmentType(): ?ShipmentType
    {
        return $this->shipmentType;
    }

    public function setShipmentType(ShipmentType $shipmentType): static
    {
        $this->shipmentType = $shipmentType;

        return $this;
    }

    public function getOriginDoor(): ?string
    {
        return $this->originDoor;
    }

    public function setOriginDoor(?string $originDoor): static
    {
        $this->originDoor = $originDoor;

        return $this;
    }

    public function getOriginPort(): ?Port
    {
        return $this->originPort;
    }

    public function setOriginPort(?Port $originPort): static
    {
        $this->originPort = $originPort;

        return $this;
    }

    public function getDestinationDoor(): ?string
    {
        return $this->destinationDoor;
    }

    public function setDestinationDoor(?string $destinationDoor): static
    {
        $this->destinationDoor = $destinationDoor;

        return $this;
    }

    public function getDestinationPort(): ?Port
    {
        return $this->destinationPort;
    }

    public function setDestinationPort(?Port $destinationPort): static
    {
        $this->destinationPort = $destinationPort;

        return $this;
    }

    public function getCargoVolumeType(): ?VolumeType
    {
        return $this->cargoVolumeType;
    }

    public function setCargoVolumeType(VolumeType $cargoVolumeType): static
    {
        $this->cargoVolumeType = $cargoVolumeType;

        return $this;
    }

    public function getCargoVolume(): array
    {
        return $this->cargoVolume;
    }

    public function setCargoVolume(?array $cargoVolume): static
    {
        $this->cargoVolume = $cargoVolume;

        return $this;
    }

    /**
     * @return Collection<int, QuotePrice>
     */
    public function getPrices(): Collection
    {
        return $this->prices;
    }

    public function addPrice(QuotePrice $price): static
    {
        if (!$this->prices->contains($price)) {
            $this->prices->add($price);
            $price->setQuote($this);
        }

        return $this;
    }

    public function removePrice(QuotePrice $price): static
    {
        if ($this->prices->removeElement($price)) {
            // set the owning side to null (unless already changed)
            if ($price->getQuote() === $this) {
                $price->setQuote(null);
            }
        }

        return $this;
    }

    public function getSubTotal(): array
    {
        return $this->subTotal;
    }

    public function setSubTotal(?array $subTotal): static
    {
        $this->subTotal = $subTotal;

        return $this;
    }

    public function getTotalBuying(): ?float
    {
        return $this->totalBuying;
    }

    public function setTotalBuying(?float $totalBuying): static
    {
        $this->totalBuying = $totalBuying;

        return $this;
    }

    public function getTotalSelling(): ?float
    {
        return $this->totalSelling;
    }

    public function setTotalSelling(?float $totalSelling): static
    {
        $this->totalSelling = $totalSelling;

        return $this;
    }

    public function getTotalProfit(): ?float
    {
        return $this->totalProfit;
    }

    public function setTotalProfit(?float $totalProfit): static
    {
        $this->totalProfit = $totalProfit;

        return $this;
    }

    public function getExchangeRates(): array
    {
        return $this->exchangeRates;
    }

    public function setExchangeRates(array $exchangeRates): static
    {
        $this->exchangeRates = $exchangeRates;

        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;

        return $this;
    }

    public function getSchedule(): array
    {
        return $this->schedule;
    }

    public function setSchedule(?array $schedule): static
    {
        $this->schedule = $schedule;

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

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): static
    {
        $this->remark = $remark;

        return $this;
    }

    public function getEstimatedDeparture(): ?\DateTimeInterface
    {
        return $this->estimatedDeparture;
    }

    public function setEstimatedDeparture(?\DateTimeInterface $estimatedDeparture): static
    {
        $this->estimatedDeparture = $estimatedDeparture;

        return $this;
    }

    public function getCargoReady(): ?\DateTimeInterface
    {
        return $this->cargoReady;
    }

    public function setCargoReady(?\DateTimeInterface $cargoReady): static
    {
        $this->cargoReady = $cargoReady;

        return $this;
    }

    public function getIncoterm(): ?Incoterm
    {
        return $this->incoterm;
    }

    public function setIncoterm(?Incoterm $incoterm): static
    {
        $this->incoterm = $incoterm;

        return $this;
    }

    public function getCommodities(): ?string
    {
        return $this->commodities;
    }

    public function setCommodities(?string $commodities): static
    {
        $this->commodities = $commodities;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getStatus(): ?QuoteStatus
    {
        return $this->status;
    }

    public function setStatus(QuoteStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getTransitPort(): ?string
    {
        return $this->transitPort;
    }

    public function setTransitPort(?string $transitPort): static
    {
        $this->transitPort = $transitPort;

        return $this;
    }

    public function getShipment(): ?Shipment
    {
        return $this->shipment;
    }

    public function setShipment(Shipment $shipment): static
    {
        // set the owning side of the relation if necessary
        if ($shipment->getQuote() !== $this) {
            $shipment->setQuote($this);
        }

        $this->shipment = $shipment;

        return $this;
    }

    public function getCarrier(): ?Provider
    {
        return $this->carrier;
    }

    public function setCarrier(?Provider $carrier): static
    {
        $this->carrier = $carrier;

        return $this;
    }

    public function getSubOriginPort(): ?Port
    {
        return $this->subOriginPort;
    }

    public function setSubOriginPort(?Port $subOriginPort): static
    {
        $this->subOriginPort = $subOriginPort;

        return $this;
    }

    public function getSubDestinationPort(): ?Port
    {
        return $this->subDestinationPort;
    }

    public function setSubDestinationPort(?Port $subDestinationPort): static
    {
        $this->subDestinationPort = $subDestinationPort;

        return $this;
    }

    public function getQuoteCurrency(): ?string
    {
        return $this->quoteCurrency;
    }

    public function setQuoteCurrency(?string $quoteCurrency): static
    {
        $this->quoteCurrency = $quoteCurrency;

        return $this;
    }

    public function getSubVesselNo(): ?string
    {
        return $this->subVesselNo;
    }

    public function setSubVesselNo(?string $subVesselNo): static
    {
        $this->subVesselNo = $subVesselNo;

        return $this;
    }

    public function getVesselNo(): ?string
    {
        return $this->vesselNo;
    }

    public function setVesselNo(?string $vesselNo): static
    {
        $this->vesselNo = $vesselNo;

        return $this;
    }
}
