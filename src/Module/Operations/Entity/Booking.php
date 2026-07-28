<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\Port;

use App\Module\Core\Entity\SubEntity;

use App\Module\Quote\Enum\FreightTerm;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\BookingRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Booking implements SubEntity
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codeReference = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $bookingDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bookingTo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;
    // todo: add service mode here

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeReceipt = null;

    #[ORM\ManyToOne]
    private ?Port $portLoading = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pickup = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $etd = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vesselNo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $warehouse = null;

    #[ORM\ManyToOne]
    private ?Port $portDischarge = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeDelivery = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $destination = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $eta = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $feederVessel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $feederVoyage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motherVessel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $motherVoyage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeStuffing = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateStuffing = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cyCutOff = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $siCutOff = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $vgmCutOff = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $gateIn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $temperature = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $vent = null;

    #[ORM\Column(length: 255, nullable: true, enumType: FreightTerm::class)]
    private ?FreightTerm $freightTerms = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $terms = null;

    #[ORM\Column(nullable: true)]
    private array $cargoVolume = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactWarehouse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codeWarehouse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $specialRemark = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $contactDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $pickupDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commodities = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sailingRef = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $flightRef = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billLadingType = null;

    // todo: add transit ports and parties (shipper / consignee) here
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getCodeReference(): ?string
    {
        return $this->codeReference;
    }

    public function setCodeReference(?string $codeReference): static
    {
        $this->codeReference = $codeReference;

        return $this;
    }

    public function getBookingDate(): ?\DateTimeInterface
    {
        return $this->bookingDate;
    }

    public function setBookingDate(?\DateTimeInterface $bookingDate): static
    {
        $this->bookingDate = $bookingDate;

        return $this;
    }

    public function getBookingTo(): ?string
    {
        return $this->bookingTo;
    }

    public function setBookingTo(?string $bookingTo): static
    {
        $this->bookingTo = $bookingTo;

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

    public function getPlaceReceipt(): ?string
    {
        return $this->placeReceipt;
    }

    public function setPlaceReceipt(?string $placeReceipt): static
    {
        $this->placeReceipt = $placeReceipt;

        return $this;
    }

    public function getPortLoading(): ?Port
    {
        return $this->portLoading;
    }

    public function setPortLoading(?Port $portLoading): static
    {
        $this->portLoading = $portLoading;

        return $this;
    }

    public function getPickup(): ?string
    {
        return $this->pickup;
    }

    public function setPickup(?string $pickup): static
    {
        $this->pickup = $pickup;

        return $this;
    }

    public function getEtd(): ?\DateTimeInterface
    {
        return $this->etd;
    }

    public function setEtd(?\DateTimeInterface $etd): static
    {
        $this->etd = $etd;

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

    public function getSailingRef(): ?string
    {
        return $this->sailingRef;
    }

    public function setSailingRef(?string $sailingRef): static
    {
        $this->sailingRef = $sailingRef;
        return $this;
    }

    public function getFlightRef(): ?string
    {
        return $this->flightRef;
    }

    public function setFlightRef(?string $flightRef): static
    {
        $this->flightRef = $flightRef;
        return $this;
    }

    public function getWarehouse(): ?string
    {
        return $this->warehouse;
    }

    public function setWarehouse(?string $warehouse): static
    {
        $this->warehouse = $warehouse;

        return $this;
    }

    public function getPortDischarge(): ?Port
    {
        return $this->portDischarge;
    }

    public function setPortDischarge(?Port $portDischarge): static
    {
        $this->portDischarge = $portDischarge;

        return $this;
    }

    public function getPlaceDelivery(): ?string
    {
        return $this->placeDelivery;
    }

    public function setPlaceDelivery(?string $placeDelivery): static
    {
        $this->placeDelivery = $placeDelivery;

        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(?string $destination): static
    {
        $this->destination = $destination;

        return $this;
    }

    public function getEta(): ?\DateTimeInterface
    {
        return $this->eta;
    }

    public function setEta(?\DateTimeInterface $eta): static
    {
        $this->eta = $eta;

        return $this;
    }

    public function getFeederVessel(): ?string
    {
        return $this->feederVessel;
    }

    public function setFeederVessel(?string $feederVessel): static
    {
        $this->feederVessel = $feederVessel;

        return $this;
    }

    public function getFeederVoyage(): ?string
    {
        return $this->feederVoyage;
    }

    public function setFeederVoyage(?string $feederVoyage): static
    {
        $this->feederVoyage = $feederVoyage;

        return $this;
    }

    public function getMotherVessel(): ?string
    {
        return $this->motherVessel;
    }

    public function setMotherVessel(?string $motherVessel): static
    {
        $this->motherVessel = $motherVessel;

        return $this;
    }

    public function getMotherVoyage(): ?string
    {
        return $this->motherVoyage;
    }

    public function setMotherVoyage(?string $motherVoyage): static
    {
        $this->motherVoyage = $motherVoyage;

        return $this;
    }

    public function getPlaceStuffing(): ?string
    {
        return $this->placeStuffing;
    }

    public function setPlaceStuffing(?string $placeStuffing): static
    {
        $this->placeStuffing = $placeStuffing;

        return $this;
    }

    public function getDateStuffing(): ?\DateTimeInterface
    {
        return $this->dateStuffing;
    }

    public function setDateStuffing(?\DateTimeInterface $dateStuffing): static
    {
        $this->dateStuffing = $dateStuffing;

        return $this;
    }

    public function getCyCutOff(): ?\DateTimeInterface
    {
        return $this->cyCutOff;
    }

    public function setCyCutOff(?\DateTimeInterface $cyCutOff): static
    {
        $this->cyCutOff = $cyCutOff;

        return $this;
    }

    public function getSiCutOff(): ?\DateTimeInterface
    {
        return $this->siCutOff;
    }

    public function setSiCutOff(?\DateTimeInterface $siCutOff): static
    {
        $this->siCutOff = $siCutOff;

        return $this;
    }

    public function getVgmCutOff(): ?\DateTimeInterface
    {
        return $this->vgmCutOff;
    }

    public function setVgmCutOff(?\DateTimeInterface $vgmCutOff): static
    {
        $this->vgmCutOff = $vgmCutOff;

        return $this;
    }

    public function getGateIn(): ?\DateTimeInterface
    {
        return $this->gateIn;
    }

    public function setGateIn(?\DateTimeInterface $gateIn): static
    {
        $this->gateIn = $gateIn;

        return $this;
    }

    public function getTemperature(): ?string
    {
        return $this->temperature;
    }

    public function setTemperature(?string $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function getVent(): ?string
    {
        return $this->vent;
    }

    public function setVent(?string $vent): static
    {
        $this->vent = $vent;

        return $this;
    }

    public function getFreightTerms(): ?FreightTerm
    {
        return $this->freightTerms;
    }

    public function setFreightTerms(?FreightTerm $freightTerms): static
    {
        $this->freightTerms = $freightTerms;

        return $this;
    }

    public function getTerms(): ?string
    {
        return $this->terms;
    }

    public function setTerms(?string $terms): static
    {
        $this->terms = $terms;

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

    public function getContactNumber(): ?string
    {
        return $this->contactNumber;
    }

    public function setContactNumber(?string $contactNumber): static
    {
        $this->contactNumber = $contactNumber;

        return $this;
    }

    public function getContactWarehouse(): ?string
    {
        return $this->contactWarehouse;
    }

    public function setContactWarehouse(?string $contactWarehouse): static
    {
        $this->contactWarehouse = $contactWarehouse;

        return $this;
    }

    public function getCodeWarehouse(): ?string
    {
        return $this->codeWarehouse;
    }

    public function setCodeWarehouse(?string $codeWarehouse): static
    {
        $this->codeWarehouse = $codeWarehouse;

        return $this;
    }

    public function getSpecialRemark(): ?string
    {
        return $this->specialRemark;
    }

    public function setSpecialRemark(?string $specialRemark): static
    {
        $this->specialRemark = $specialRemark;

        return $this;
    }

    public function getContactDate(): ?\DateTimeInterface
    {
        return $this->contactDate;
    }

    public function setContactDate(?\DateTimeInterface $contactDate): static
    {
        $this->contactDate = $contactDate;

        return $this;
    }

    public function getPickupDate(): ?\DateTimeInterface
    {
        return $this->pickupDate;
    }

    public function setPickupDate(?\DateTimeInterface $pickupDate): static
    {
        $this->pickupDate = $pickupDate;

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

    public function getBillLadingType(): ?string
    {
        return $this->billLadingType;
    }

    public function setBillLadingType(?string $billLadingType): static
    {
        $this->billLadingType = $billLadingType;

        return $this;
    }

}
