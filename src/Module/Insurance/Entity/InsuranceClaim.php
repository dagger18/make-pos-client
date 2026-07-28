<?php
namespace App\Module\Insurance\Entity;

use App\Module\Carrier\Entity\Provider;
use App\Module\Operations\Entity\Shipment;
use App\Module\Insurance\Repository\InsuranceClaimRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsuranceClaimRepository::class)]
class InsuranceClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private InsuranceCertificate $certificate;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 64, unique: true)]
    private string $claimNumber;

    #[ORM\Column(length: 32)]
    private string $claimType; // TOTAL_LOSS / PARTIAL_LOSS / DAMAGE / THEFT / DELAY

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $incidentDate;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $incidentLocation = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $claimedAmount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 32)]
    private string $status = 'FILED';
    // FILED / SURVEYOR_APPOINTED / UNDER_ASSESSMENT / APPROVED / REJECTED / SETTLED / WITHDRAWN

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Provider $surveyor = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $surveyorRef = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $approvedAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $deductibleApplied = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $netSettlement = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $settledDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getCertificate(): InsuranceCertificate { return $this->certificate; }
    public function setCertificate(InsuranceCertificate $v): static { $this->certificate = $v; return $this; }
    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $v): static { $this->shipment = $v; return $this; }
    public function getClaimNumber(): string { return $this->claimNumber; }
    public function setClaimNumber(string $v): static { $this->claimNumber = $v; return $this; }
    public function getClaimType(): string { return $this->claimType; }
    public function setClaimType(string $v): static { $this->claimType = $v; return $this; }
    public function getIncidentDate(): \DateTimeInterface { return $this->incidentDate; }
    public function setIncidentDate(\DateTimeInterface $v): static { $this->incidentDate = $v; return $this; }
    public function getIncidentLocation(): ?string { return $this->incidentLocation; }
    public function setIncidentLocation(?string $v): static { $this->incidentLocation = $v; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $v): static { $this->description = $v; return $this; }
    public function getClaimedAmount(): float { return (float) $this->claimedAmount; }
    public function setClaimedAmount(float $v): static { $this->claimedAmount = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getSurveyor(): ?Provider { return $this->surveyor; }
    public function setSurveyor(?Provider $v): static { $this->surveyor = $v; return $this; }
    public function getSurveyorRef(): ?string { return $this->surveyorRef; }
    public function setSurveyorRef(?string $v): static { $this->surveyorRef = $v; return $this; }
    public function getApprovedAmount(): ?float { return $this->approvedAmount !== null ? (float) $this->approvedAmount : null; }
    public function setApprovedAmount(?float $v): static { $this->approvedAmount = $v; return $this; }
    public function getDeductibleApplied(): ?float { return $this->deductibleApplied !== null ? (float) $this->deductibleApplied : null; }
    public function setDeductibleApplied(?float $v): static { $this->deductibleApplied = $v; return $this; }
    public function getNetSettlement(): ?float { return $this->netSettlement !== null ? (float) $this->netSettlement : null; }
    public function setNetSettlement(?float $v): static { $this->netSettlement = $v; return $this; }
    public function getSettledDate(): ?\DateTimeInterface { return $this->settledDate; }
    public function setSettledDate(?\DateTimeInterface $v): static { $this->settledDate = $v; return $this; }
    public function getRejectionReason(): ?string { return $this->rejectionReason; }
    public function setRejectionReason(?string $v): static { $this->rejectionReason = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'certificateId'    => $this->certificate->getId(),
            'certificateNumber'=> $this->certificate->getCertificateNumber(),
            'shipmentId'       => $this->shipment->getId(),
            'claimNumber'      => $this->claimNumber,
            'claimType'        => $this->claimType,
            'incidentDate'     => $this->incidentDate->format('Y-m-d'),
            'incidentLocation' => $this->incidentLocation,
            'description'      => $this->description,
            'claimedAmount'    => $this->getClaimedAmount(),
            'currency'         => $this->currency,
            'status'           => $this->status,
            'surveyor'         => $this->surveyor ? ['id' => $this->surveyor->getId(), 'name' => $this->surveyor->getName()] : null,
            'surveyorRef'      => $this->surveyorRef,
            'approvedAmount'   => $this->getApprovedAmount(),
            'deductibleApplied'=> $this->getDeductibleApplied(),
            'netSettlement'    => $this->getNetSettlement(),
            'settledDate'      => $this->settledDate?->format('Y-m-d'),
            'rejectionReason'  => $this->rejectionReason,
            'createdAt'        => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
