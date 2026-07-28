<?php
declare(strict_types=1);
namespace App\Module\Lc\Entity;

use App\Module\Lc\Repository\LetterOfCreditRepository;
use App\Module\Operations\Entity\Shipment;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LetterOfCreditRepository::class)]
#[ORM\Table(name: 'letter_of_credit')]
class LetterOfCredit
{
    const LC_TYPES  = ['IRREVOCABLE', 'REVOLVING', 'STANDBY', 'TRANSFERABLE'];
    const STATUSES  = ['OPEN', 'DOCUMENTS_PREPARED', 'PRESENTED', 'NEGOTIATED', 'PAID', 'EXPIRED', 'CANCELLED'];
    const ALLOW     = ['ALLOWED', 'NOT_ALLOWED'];
    const DOC_TYPES = ['BL', 'INVOICE', 'PACKING_LIST', 'COO', 'INSURANCE', 'INSPECTION', 'OTHER'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 64)]
    private string $lcNumber;

    #[ORM\Column(length: 16)]
    private string $lcType = 'IRREVOCABLE';

    #[ORM\Column(length: 255)]
    private string $issuingBankName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $advisingBankName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $negotiatingBankName = null;

    #[ORM\Column(length: 255)]
    private string $applicantName;

    #[ORM\Column(length: 255)]
    private string $beneficiaryName;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6)]
    private string $lcAmount;

    #[ORM\Column(length: 3)]
    private string $lcCurrency;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $issueDate;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $expiryDate;

    #[ORM\Column(length: 64)]
    private string $expiryPlace;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $shipmentBy;

    #[ORM\Column(type: 'smallint')]
    private int $presentationDays = 21;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $presentationDeadline = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $specialConditions = null;

    #[ORM\Column(length: 16)]
    private string $partialShipments = 'NOT_ALLOWED';

    #[ORM\Column(length: 16)]
    private string $transhipment = 'NOT_ALLOWED';

    #[ORM\Column(length: 16)]
    private string $status = 'OPEN';

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): int { return $this->id; }

    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $v): static { $this->shipment = $v; return $this; }

    public function getLcNumber(): string { return $this->lcNumber; }
    public function setLcNumber(string $v): static { $this->lcNumber = $v; return $this; }

    public function getLcType(): string { return $this->lcType; }
    public function setLcType(string $v): static { $this->lcType = $v; return $this; }

    public function getIssuingBankName(): string { return $this->issuingBankName; }
    public function setIssuingBankName(string $v): static { $this->issuingBankName = $v; return $this; }

    public function getAdvisingBankName(): ?string { return $this->advisingBankName; }
    public function setAdvisingBankName(?string $v): static { $this->advisingBankName = $v; return $this; }

    public function getNegotiatingBankName(): ?string { return $this->negotiatingBankName; }
    public function setNegotiatingBankName(?string $v): static { $this->negotiatingBankName = $v; return $this; }

    public function getApplicantName(): string { return $this->applicantName; }
    public function setApplicantName(string $v): static { $this->applicantName = $v; return $this; }

    public function getBeneficiaryName(): string { return $this->beneficiaryName; }
    public function setBeneficiaryName(string $v): static { $this->beneficiaryName = $v; return $this; }

    public function getLcAmount(): string { return $this->lcAmount; }
    public function setLcAmount(string $v): static { $this->lcAmount = $v; return $this; }

    public function getLcCurrency(): string { return $this->lcCurrency; }
    public function setLcCurrency(string $v): static { $this->lcCurrency = $v; return $this; }

    public function getIssueDate(): \DateTimeInterface { return $this->issueDate; }
    public function setIssueDate(\DateTimeInterface $v): static { $this->issueDate = $v; return $this; }

    public function getExpiryDate(): \DateTimeInterface { return $this->expiryDate; }
    public function setExpiryDate(\DateTimeInterface $v): static { $this->expiryDate = $v; return $this; }

    public function getExpiryPlace(): string { return $this->expiryPlace; }
    public function setExpiryPlace(string $v): static { $this->expiryPlace = $v; return $this; }

    public function getShipmentBy(): \DateTimeInterface { return $this->shipmentBy; }
    public function setShipmentBy(\DateTimeInterface $v): static { $this->shipmentBy = $v; return $this; }

    public function getPresentationDays(): int { return $this->presentationDays; }
    public function setPresentationDays(int $v): static { $this->presentationDays = $v; return $this; }

    public function getPresentationDeadline(): ?\DateTimeInterface { return $this->presentationDeadline; }
    public function setPresentationDeadline(?\DateTimeInterface $v): static { $this->presentationDeadline = $v; return $this; }

    public function getSpecialConditions(): ?string { return $this->specialConditions; }
    public function setSpecialConditions(?string $v): static { $this->specialConditions = $v; return $this; }

    public function getPartialShipments(): string { return $this->partialShipments; }
    public function setPartialShipments(string $v): static { $this->partialShipments = $v; return $this; }

    public function getTranshipment(): string { return $this->transhipment; }
    public function setTranshipment(string $v): static { $this->transhipment = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function calcPresentationDeadline(\DateTimeInterface $blDate): \DateTimeInterface
    {
        $deadline = (clone \DateTime::createFromInterface($blDate))->modify("+{$this->presentationDays} days");
        return $deadline < $this->expiryDate ? $deadline : \DateTime::createFromInterface($this->expiryDate);
    }

    public function toArray(): array
    {
        return [
            'id'                   => $this->id,
            'shipmentId'           => $this->shipment->getId(),
            'shipmentCode'         => $this->shipment->getCode(),
            'lcNumber'             => $this->lcNumber,
            'lcType'               => $this->lcType,
            'issuingBankName'      => $this->issuingBankName,
            'advisingBankName'     => $this->advisingBankName,
            'negotiatingBankName'  => $this->negotiatingBankName,
            'applicantName'        => $this->applicantName,
            'beneficiaryName'      => $this->beneficiaryName,
            'lcAmount'             => (float) $this->lcAmount,
            'lcCurrency'           => $this->lcCurrency,
            'issueDate'            => $this->issueDate->format('Y-m-d'),
            'expiryDate'           => $this->expiryDate->format('Y-m-d'),
            'expiryPlace'          => $this->expiryPlace,
            'shipmentBy'           => $this->shipmentBy->format('Y-m-d'),
            'presentationDays'     => $this->presentationDays,
            'presentationDeadline' => $this->presentationDeadline?->format('Y-m-d'),
            'specialConditions'    => $this->specialConditions,
            'partialShipments'     => $this->partialShipments,
            'transhipment'         => $this->transhipment,
            'status'               => $this->status,
            'createdAt'            => $this->createdAt->format('Y-m-d H:i:s'),
            'daysToShipmentBy'     => (new \DateTime())->diff($this->shipmentBy)->days * ($this->shipmentBy > new \DateTime() ? 1 : -1),
            'daysToPresentation'   => $this->presentationDeadline
                ? (new \DateTime())->diff($this->presentationDeadline)->days * ($this->presentationDeadline > new \DateTime() ? 1 : -1)
                : null,
        ];
    }
}
