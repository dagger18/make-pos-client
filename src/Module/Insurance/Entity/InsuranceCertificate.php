<?php
namespace App\Module\Insurance\Entity;

use App\Module\Core\Entity\User;
use App\Module\Operations\Entity\Shipment;
use App\Module\Insurance\Repository\InsuranceCertificateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsuranceCertificateRepository::class)]
class InsuranceCertificate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $certificateNumber;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private InsurancePolicy $policy;

    // Insured parties
    #[ORM\Column(length: 255)]
    private string $insuredName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $beneficiaryName = null;

    // Cargo
    #[ORM\Column(type: Types::TEXT)]
    private string $goodsDescription;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $packing = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $marksNumbers = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $hsCode = null;

    // Route
    #[ORM\Column(length: 128, nullable: true)]
    private ?string $vesselOrFlight = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $voyageOrFlightNo = null;

    #[ORM\Column(length: 128)]
    private string $polName;

    #[ORM\Column(length: 128)]
    private string $podName;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $etd = null;

    // Value and premium
    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $cargoValue;

    #[ORM\Column(length: 3)]
    private string $valueCurrency;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $insuredAmount;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $premiumAmount;

    #[ORM\Column(length: 3)]
    private string $premiumCurrency;

    #[ORM\Column(length: 32)]
    private string $coverageScope = 'ALL_RISK';

    // Status
    #[ORM\Column(length: 16)]
    private string $status = 'ISSUED'; // ISSUED / CANCELLED / CLAIMED

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $issueDate;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $issuedBy = null;

    // Revenue
    #[ORM\Column]
    private bool $isInvoiced = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getCertificateNumber(): string { return $this->certificateNumber; }
    public function setCertificateNumber(string $v): static { $this->certificateNumber = $v; return $this; }
    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $v): static { $this->shipment = $v; return $this; }
    public function getPolicy(): InsurancePolicy { return $this->policy; }
    public function setPolicy(InsurancePolicy $v): static { $this->policy = $v; return $this; }
    public function getInsuredName(): string { return $this->insuredName; }
    public function setInsuredName(string $v): static { $this->insuredName = $v; return $this; }
    public function getBeneficiaryName(): ?string { return $this->beneficiaryName; }
    public function setBeneficiaryName(?string $v): static { $this->beneficiaryName = $v; return $this; }
    public function getGoodsDescription(): string { return $this->goodsDescription; }
    public function setGoodsDescription(string $v): static { $this->goodsDescription = $v; return $this; }
    public function getPacking(): ?string { return $this->packing; }
    public function setPacking(?string $v): static { $this->packing = $v; return $this; }
    public function getMarksNumbers(): ?string { return $this->marksNumbers; }
    public function setMarksNumbers(?string $v): static { $this->marksNumbers = $v; return $this; }
    public function getHsCode(): ?string { return $this->hsCode; }
    public function setHsCode(?string $v): static { $this->hsCode = $v; return $this; }
    public function getVesselOrFlight(): ?string { return $this->vesselOrFlight; }
    public function setVesselOrFlight(?string $v): static { $this->vesselOrFlight = $v; return $this; }
    public function getVoyageOrFlightNo(): ?string { return $this->voyageOrFlightNo; }
    public function setVoyageOrFlightNo(?string $v): static { $this->voyageOrFlightNo = $v; return $this; }
    public function getPolName(): string { return $this->polName; }
    public function setPolName(string $v): static { $this->polName = $v; return $this; }
    public function getPodName(): string { return $this->podName; }
    public function setPodName(string $v): static { $this->podName = $v; return $this; }
    public function getEtd(): ?\DateTimeInterface { return $this->etd; }
    public function setEtd(?\DateTimeInterface $v): static { $this->etd = $v; return $this; }
    public function getCargoValue(): float { return (float) $this->cargoValue; }
    public function setCargoValue(float $v): static { $this->cargoValue = $v; return $this; }
    public function getValueCurrency(): string { return $this->valueCurrency; }
    public function setValueCurrency(string $v): static { $this->valueCurrency = $v; return $this; }
    public function getInsuredAmount(): float { return (float) $this->insuredAmount; }
    public function setInsuredAmount(float $v): static { $this->insuredAmount = $v; return $this; }
    public function getPremiumAmount(): float { return (float) $this->premiumAmount; }
    public function setPremiumAmount(float $v): static { $this->premiumAmount = $v; return $this; }
    public function getPremiumCurrency(): string { return $this->premiumCurrency; }
    public function setPremiumCurrency(string $v): static { $this->premiumCurrency = $v; return $this; }
    public function getCoverageScope(): string { return $this->coverageScope; }
    public function setCoverageScope(string $v): static { $this->coverageScope = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getIssueDate(): \DateTimeInterface { return $this->issueDate; }
    public function setIssueDate(\DateTimeInterface $v): static { $this->issueDate = $v; return $this; }
    public function getIssuedBy(): ?User { return $this->issuedBy; }
    public function setIssuedBy(?User $v): static { $this->issuedBy = $v; return $this; }
    public function isInvoiced(): bool { return $this->isInvoiced; }
    public function setIsInvoiced(bool $v): static { $this->isInvoiced = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'certificateNumber' => $this->certificateNumber,
            'shipmentId'        => $this->shipment->getId(),
            'policy'            => ['id' => $this->policy->getId(), 'policyNumber' => $this->policy->getPolicyNumber()],
            'insuredName'       => $this->insuredName,
            'beneficiaryName'   => $this->beneficiaryName,
            'goodsDescription'  => $this->goodsDescription,
            'packing'           => $this->packing,
            'marksNumbers'      => $this->marksNumbers,
            'hsCode'            => $this->hsCode,
            'vesselOrFlight'    => $this->vesselOrFlight,
            'voyageOrFlightNo'  => $this->voyageOrFlightNo,
            'polName'           => $this->polName,
            'podName'           => $this->podName,
            'etd'               => $this->etd?->format('Y-m-d'),
            'cargoValue'        => $this->getCargoValue(),
            'valueCurrency'     => $this->valueCurrency,
            'insuredAmount'     => $this->getInsuredAmount(),
            'premiumAmount'     => $this->getPremiumAmount(),
            'premiumCurrency'   => $this->premiumCurrency,
            'coverageScope'     => $this->coverageScope,
            'status'            => $this->status,
            'issueDate'         => $this->issueDate->format('Y-m-d'),
            'issuedBy'          => $this->issuedBy ? $this->issuedBy->getName() : null,
            'isInvoiced'        => $this->isInvoiced,
            'createdAt'         => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
