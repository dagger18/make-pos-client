<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\SubEntity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\InstructionRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: InstructionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Instruction implements SubEntity
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $atd = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $ata = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shipperName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shipperAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shipperAccountNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $consigneeName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $consigneeAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $consigneeAccountNumber = null;

    #[ORM\Column(nullable: true)]
    private ?bool $notifyConsignee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notifyName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notifyAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $agentName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $agentAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $agentCity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $agentIataCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accountNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referenceNumber = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $toCarrier1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $toCarrier2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $toCarrier3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $flight1 = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $flight1Date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $declaredValueCarriage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $amountInsurance = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $billLadingIssueDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billLadingIssuePlace = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shipperSignature = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shippingMarks = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalInfo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $byCarrier1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $byCarrier2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $byCarrier3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $flight2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $optionalInfo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $declaredValueCustom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sci = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $natureQuantityGoods = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $carrierSignature = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commodity = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $grossWeight = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $grossWeightUnit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $volume = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chargeableWeight = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rateCharge = null;

    #[ORM\Column(nullable: true)]
    private ?bool $rateChargeAsAgreed = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packageCount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $packageType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rateClass = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commodityItemNumber = null;

    #[ORM\Column(nullable: true)]
    private ?array $dimension = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $costTerm = null;

    #[ORM\Column(nullable: true)]
    private ?bool $costAsAgreed = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $weightCharge = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $valuationCharge = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tax = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dueAgent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $dueCarrier = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $costTotals = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $otherCharges = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $currencyRate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ccChargesInDestCurrency = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $chargesAtDestination = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totalCollectCharges = null;

    #[ORM\Column(nullable: true)]
    private ?array $instructionCharges = null;

    #[ORM\Column(nullable: true, type: 'InstructionContainerCollectionType')]
    private ?array $containers = [];

    public function __construct()
    {
        $this->containers = [];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAtd(): ?\DateTimeInterface
    {
        return $this->atd;
    }

    public function setAtd(?\DateTimeInterface $atd): static
    {
        $this->atd = $atd;

        return $this;
    }

    public function getAta(): ?\DateTimeInterface
    {
        return $this->ata;
    }

    public function setAta(?\DateTimeInterface $ata): static
    {
        $this->ata = $ata;

        return $this;
    }

    public function getShipperName(): ?string
    {
        return $this->shipperName;
    }

    public function setShipperName(?string $shipperName): static
    {
        $this->shipperName = $shipperName;

        return $this;
    }

    public function getShipperAddress(): ?string
    {
        return $this->shipperAddress;
    }

    public function setShipperAddress(?string $shipperAddress): static
    {
        $this->shipperAddress = $shipperAddress;

        return $this;
    }

    public function getShipperAccountNumber(): ?string
    {
        return $this->shipperAccountNumber;
    }

    public function setShipperAccountNumber(?string $shipperAccountNumber): static
    {
        $this->shipperAccountNumber = $shipperAccountNumber;

        return $this;
    }

    public function getConsigneeName(): ?string
    {
        return $this->consigneeName;
    }

    public function setConsigneeName(?string $consigneeName): static
    {
        $this->consigneeName = $consigneeName;

        return $this;
    }

    public function getConsigneeAddress(): ?string
    {
        return $this->consigneeAddress;
    }

    public function setConsigneeAddress(?string $consigneeAddress): static
    {
        $this->consigneeAddress = $consigneeAddress;

        return $this;
    }

    public function getConsigneeAccountNumber(): ?string
    {
        return $this->consigneeAccountNumber;
    }

    public function setConsigneeAccountNumber(?string $consigneeAccountNumber): static
    {
        $this->consigneeAccountNumber = $consigneeAccountNumber;

        return $this;
    }

    public function isNotifyConsignee(): ?bool
    {
        return $this->notifyConsignee;
    }

    public function setNotifyConsignee(?bool $notifyConsignee): static
    {
        $this->notifyConsignee = $notifyConsignee;

        return $this;
    }

    public function getNotifyName(): ?string
    {
        return $this->notifyName;
    }

    public function setNotifyName(?string $notifyName): static
    {
        $this->notifyName = $notifyName;

        return $this;
    }

    public function getNotifyAddress(): ?string
    {
        return $this->notifyAddress;
    }

    public function setNotifyAddress(?string $notifyAddress): static
    {
        $this->notifyAddress = $notifyAddress;

        return $this;
    }

    public function getAgentName(): ?string
    {
        return $this->agentName;
    }

    public function setAgentName(?string $agentName): static
    {
        $this->agentName = $agentName;

        return $this;
    }

    public function getAgentAddress(): ?string
    {
        return $this->agentAddress;
    }

    public function setAgentAddress(?string $agentAddress): static
    {
        $this->agentAddress = $agentAddress;

        return $this;
    }

    public function getAgentCity(): ?string
    {
        return $this->agentCity;
    }

    public function setAgentCity(?string $agentCity): static
    {
        $this->agentCity = $agentCity;

        return $this;
    }

    public function getAgentIataCode(): ?string
    {
        return $this->agentIataCode;
    }

    public function setAgentIataCode(?string $agentIataCode): static
    {
        $this->agentIataCode = $agentIataCode;

        return $this;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(?string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): static
    {
        $this->referenceNumber = $referenceNumber;

        return $this;
    }

    public function getToCarrier1(): ?string
    {
        return $this->toCarrier1;
    }

    public function setToCarrier1(?string $toCarrier1): static
    {
        $this->toCarrier1 = $toCarrier1;

        return $this;
    }

    public function getToCarrier2(): ?string
    {
        return $this->toCarrier2;
    }

    public function setToCarrier2(?string $toCarrier2): static
    {
        $this->toCarrier2 = $toCarrier2;

        return $this;
    }

    public function getToCarrier3(): ?string
    {
        return $this->toCarrier3;
    }

    public function setToCarrier3(?string $toCarrier3): static
    {
        $this->toCarrier3 = $toCarrier3;

        return $this;
    }

    public function getFlight1(): ?string
    {
        return $this->flight1;
    }

    public function setFlight1(?string $flight1): static
    {
        $this->flight1 = $flight1;

        return $this;
    }

    public function getFlight1Date(): ?\DateTimeInterface
    {
        return $this->flight1Date;
    }

    public function setFlight1Date(?\DateTimeInterface $flight1Date): static
    {
        $this->flight1Date = $flight1Date;

        return $this;
    }

    public function getDeclaredValueCarriage(): ?string
    {
        return $this->declaredValueCarriage;
    }

    public function setDeclaredValueCarriage(?string $declaredValueCarriage): static
    {
        $this->declaredValueCarriage = $declaredValueCarriage;

        return $this;
    }

    public function getAmountInsurance(): ?string
    {
        return $this->amountInsurance;
    }

    public function setAmountInsurance(?string $amountInsurance): static
    {
        $this->amountInsurance = $amountInsurance;

        return $this;
    }

    public function getBillLadingIssueDate(): ?\DateTimeInterface
    {
        return $this->billLadingIssueDate;
    }

    public function setBillLadingIssueDate(?\DateTimeInterface $billLadingIssueDate): static
    {
        $this->billLadingIssueDate = $billLadingIssueDate;

        return $this;
    }

    public function getBillLadingIssuePlace(): ?string
    {
        return $this->billLadingIssuePlace;
    }

    public function setBillLadingIssuePlace(?string $billLadingIssuePlace): static
    {
        $this->billLadingIssuePlace = $billLadingIssuePlace;

        return $this;
    }

    public function getShipperSignature(): ?string
    {
        return $this->shipperSignature;
    }

    public function setShipperSignature(?string $shipperSignature): static
    {
        $this->shipperSignature = $shipperSignature;

        return $this;
    }

    public function getShippingMarks(): ?string
    {
        return $this->shippingMarks;
    }

    public function setShippingMarks(?string $shippingMarks): static
    {
        $this->shippingMarks = $shippingMarks;

        return $this;
    }

    public function getAdditionalInfo(): ?string
    {
        return $this->additionalInfo;
    }

    public function setAdditionalInfo(?string $additionalInfo): static
    {
        $this->additionalInfo = $additionalInfo;

        return $this;
    }

    public function getByCarrier1(): ?string
    {
        return $this->byCarrier1;
    }

    public function setByCarrier1(?string $byCarrier1): static
    {
        $this->byCarrier1 = $byCarrier1;

        return $this;
    }

    public function getByCarrier2(): ?string
    {
        return $this->byCarrier2;
    }

    public function setByCarrier2(?string $byCarrier2): static
    {
        $this->byCarrier2 = $byCarrier2;

        return $this;
    }

    public function getByCarrier3(): ?string
    {
        return $this->byCarrier3;
    }

    public function setByCarrier3(?string $byCarrier3): static
    {
        $this->byCarrier3 = $byCarrier3;

        return $this;
    }

    public function getFlight2(): ?string
    {
        return $this->flight2;
    }

    public function setFlight2(?string $flight2): static
    {
        $this->flight2 = $flight2;

        return $this;
    }

    public function getOptionalInfo(): ?string
    {
        return $this->optionalInfo;
    }

    public function setOptionalInfo(?string $optionalInfo): static
    {
        $this->optionalInfo = $optionalInfo;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getDeclaredValueCustom(): ?string
    {
        return $this->declaredValueCustom;
    }

    public function setDeclaredValueCustom(?string $declaredValueCustom): static
    {
        $this->declaredValueCustom = $declaredValueCustom;

        return $this;
    }

    public function getSci(): ?string
    {
        return $this->sci;
    }

    public function setSci(?string $sci): static
    {
        $this->sci = $sci;

        return $this;
    }

    public function getNatureQuantityGoods(): ?string
    {
        return $this->natureQuantityGoods;
    }

    public function setNatureQuantityGoods(?string $natureQuantityGoods): static
    {
        $this->natureQuantityGoods = $natureQuantityGoods;

        return $this;
    }

    public function getCarrierSignature(): ?string
    {
        return $this->carrierSignature;
    }

    public function setCarrierSignature(?string $carrierSignature): static
    {
        $this->carrierSignature = $carrierSignature;

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

    public function getGrossWeight(): ?string
    {
        return $this->grossWeight;
    }

    public function setGrossWeight(?string $grossWeight): static
    {
        $this->grossWeight = $grossWeight;

        return $this;
    }

    public function getGrossWeightUnit(): ?string
    {
        return $this->grossWeightUnit;
    }

    public function setGrossWeightUnit(?string $grossWeightUnit): static
    {
        $this->grossWeightUnit = $grossWeightUnit;

        return $this;
    }

    public function getVolume(): ?string
    {
        return $this->volume;
    }

    public function setVolume(?string $volume): static
    {
        $this->volume = $volume;

        return $this;
    }

    public function getChargeableWeight(): ?string
    {
        return $this->chargeableWeight;
    }

    public function setChargeableWeight(?string $chargeableWeight): static
    {
        $this->chargeableWeight = $chargeableWeight;

        return $this;
    }

    public function getRateCharge(): ?string
    {
        return $this->rateCharge;
    }

    public function setRateCharge(?string $rateCharge): static
    {
        $this->rateCharge = $rateCharge;

        return $this;
    }

    public function isRateChargeAsAgreed(): ?bool
    {
        return $this->rateChargeAsAgreed;
    }

    public function setRateChargeAsAgreed(?bool $rateChargeAsAgreed): static
    {
        $this->rateChargeAsAgreed = $rateChargeAsAgreed;

        return $this;
    }

    public function getPackageCount(): ?string
    {
        return $this->packageCount;
    }

    public function setPackageCount(?string $packageCount): static
    {
        $this->packageCount = $packageCount;

        return $this;
    }

    public function getPackageType(): ?string
    {
        return $this->packageType;
    }

    public function setPackageType(?string $packageType): static
    {
        $this->packageType = $packageType;

        return $this;
    }

    public function getRateClass(): ?string
    {
        return $this->rateClass;
    }

    public function setRateClass(?string $rateClass): static
    {
        $this->rateClass = $rateClass;

        return $this;
    }

    public function getCommodityItemNumber(): ?string
    {
        return $this->commodityItemNumber;
    }

    public function setCommodityItemNumber(?string $commodityItemNumber): static
    {
        $this->commodityItemNumber = $commodityItemNumber;

        return $this;
    }

    public function getDimension(): ?array
    {
        return $this->dimension;
    }

    public function setDimension(?array $dimension): static
    {
        $this->dimension = $dimension;

        return $this;
    }

    public function getCostTerm(): ?string
    {
        return $this->costTerm;
    }

    public function setCostTerm(?string $costTerm): static
    {
        $this->costTerm = $costTerm;

        return $this;
    }

    public function isCostAsAgreed(): ?bool
    {
        return $this->costAsAgreed;
    }

    public function setCostAsAgreed(?bool $costAsAgreed): static
    {
        $this->costAsAgreed = $costAsAgreed;

        return $this;
    }

    public function getWeightCharge(): ?string
    {
        return $this->weightCharge;
    }

    public function setWeightCharge(?string $weightCharge): static
    {
        $this->weightCharge = $weightCharge;

        return $this;
    }

    public function getValuationCharge(): ?string
    {
        return $this->valuationCharge;
    }

    public function setValuationCharge(?string $valuationCharge): static
    {
        $this->valuationCharge = $valuationCharge;

        return $this;
    }

    public function getTax(): ?string
    {
        return $this->tax;
    }

    public function setTax(?string $tax): static
    {
        $this->tax = $tax;

        return $this;
    }

    public function getDueAgent(): ?string
    {
        return $this->dueAgent;
    }

    public function setDueAgent(?string $dueAgent): static
    {
        $this->dueAgent = $dueAgent;

        return $this;
    }

    public function getDueCarrier(): ?string
    {
        return $this->dueCarrier;
    }

    public function setDueCarrier(?string $dueCarrier): static
    {
        $this->dueCarrier = $dueCarrier;

        return $this;
    }

    public function getCostTotals(): ?string
    {
        return $this->costTotals;
    }

    public function setCostTotals(?string $costTotals): static
    {
        $this->costTotals = $costTotals;

        return $this;
    }

    public function getOtherCharges(): ?string
    {
        return $this->otherCharges;
    }

    public function setOtherCharges(?string $otherCharges): static
    {
        $this->otherCharges = $otherCharges;

        return $this;
    }

    public function getCurrencyRate(): ?string
    {
        return $this->currencyRate;
    }

    public function setCurrencyRate(?string $currencyRate): static
    {
        $this->currencyRate = $currencyRate;

        return $this;
    }

    public function getCcChargesInDestCurrency(): ?string
    {
        return $this->ccChargesInDestCurrency;
    }

    public function setCcChargesInDestCurrency(?string $ccChargesInDestCurrency): static
    {
        $this->ccChargesInDestCurrency = $ccChargesInDestCurrency;

        return $this;
    }

    public function getChargesAtDestination(): ?string
    {
        return $this->chargesAtDestination;
    }

    public function setChargesAtDestination(?string $chargesAtDestination): static
    {
        $this->chargesAtDestination = $chargesAtDestination;

        return $this;
    }

    public function getTotalCollectCharges(): ?string
    {
        return $this->totalCollectCharges;
    }

    public function setTotalCollectCharges(?string $totalCollectCharges): static
    {
        $this->totalCollectCharges = $totalCollectCharges;

        return $this;
    }

    public function getInstructionCharges(): ?array
    {
        return $this->instructionCharges;
    }

    public function setInstructionCharges(?array $instructionCharges): static
    {
        $this->instructionCharges = $instructionCharges;

        return $this;
    }

    public function getContainers(): ?array
    {
        return $this->containers;
    }

    public function addContainer(InstructionContainer $container): static
    { 
        if(is_null($this->containers) || count($this->containers) === 0) {
            $this->containers = [];
        }
        $this->containers[] = $container;
        return $this;
    }

    public function removeContainer(InstructionContainer $container): static
    {
        forEach($this->containers as $index=>$q) {
            if($q === $container ) {
                unset($this->containers[$index]);
            }
        }

        return $this;
    }
}
