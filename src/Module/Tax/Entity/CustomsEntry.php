<?php
namespace App\Module\Tax\Entity;

use App\Module\Tax\Repository\CustomsEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomsEntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CustomsEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $shipmentId = null;

    #[ORM\Column(length: 16)]
    private string $entryType = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $entryMode = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $declarationNumber = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $entryNumber = null;

    #[ORM\Column(length: 32)]
    private string $status = 'DRAFT';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $customsOffice = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $systemCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $cifValue = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $valueCurrency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $totalDuty = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $totalVat = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $totalTax = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $submittedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $acknowledgedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $releasedAt = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $submissionRef = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'customsEntry', targetEntity: CustomsEntryLine::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['lineNumber' => 'ASC'])]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getShipmentId(): ?int { return $this->shipmentId; }
    public function setShipmentId(?int $shipmentId): static { $this->shipmentId = $shipmentId; return $this; }

    public function getEntryType(): string { return $this->entryType; }
    public function setEntryType(string $entryType): static { $this->entryType = $entryType; return $this; }

    public function getEntryMode(): ?string { return $this->entryMode; }
    public function setEntryMode(?string $entryMode): static { $this->entryMode = $entryMode; return $this; }

    public function getDeclarationNumber(): ?string { return $this->declarationNumber; }
    public function setDeclarationNumber(?string $declarationNumber): static { $this->declarationNumber = $declarationNumber; return $this; }

    public function getEntryNumber(): ?string { return $this->entryNumber; }
    public function setEntryNumber(?string $entryNumber): static { $this->entryNumber = $entryNumber; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getCustomsOffice(): ?string { return $this->customsOffice; }
    public function setCustomsOffice(?string $customsOffice): static { $this->customsOffice = $customsOffice; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $countryCode): static { $this->countryCode = $countryCode; return $this; }

    public function getSystemCode(): ?string { return $this->systemCode; }
    public function setSystemCode(?string $systemCode): static { $this->systemCode = $systemCode; return $this; }

    public function getCifValue(): ?string { return $this->cifValue; }
    public function setCifValue(?string $cifValue): static { $this->cifValue = $cifValue; return $this; }

    public function getValueCurrency(): ?string { return $this->valueCurrency; }
    public function setValueCurrency(?string $valueCurrency): static { $this->valueCurrency = $valueCurrency; return $this; }

    public function getTotalDuty(): ?string { return $this->totalDuty; }
    public function setTotalDuty(?string $totalDuty): static { $this->totalDuty = $totalDuty; return $this; }

    public function getTotalVat(): ?string { return $this->totalVat; }
    public function setTotalVat(?string $totalVat): static { $this->totalVat = $totalVat; return $this; }

    public function getTotalTax(): ?string { return $this->totalTax; }
    public function setTotalTax(?string $totalTax): static { $this->totalTax = $totalTax; return $this; }

    public function getSubmittedAt(): ?\DateTimeInterface { return $this->submittedAt; }
    public function setSubmittedAt(?\DateTimeInterface $submittedAt): static { $this->submittedAt = $submittedAt; return $this; }

    public function getAcknowledgedAt(): ?\DateTimeInterface { return $this->acknowledgedAt; }
    public function setAcknowledgedAt(?\DateTimeInterface $acknowledgedAt): static { $this->acknowledgedAt = $acknowledgedAt; return $this; }

    public function getReleasedAt(): ?\DateTimeInterface { return $this->releasedAt; }
    public function setReleasedAt(?\DateTimeInterface $releasedAt): static { $this->releasedAt = $releasedAt; return $this; }

    public function getSubmissionRef(): ?string { return $this->submissionRef; }
    public function setSubmissionRef(?string $submissionRef): static { $this->submissionRef = $submissionRef; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    public function getLines(): Collection { return $this->lines; }
    public function addLine(CustomsEntryLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setCustomsEntry($this);
        }
        return $this;
    }
    public function removeLine(CustomsEntryLine $line): static
    {
        if ($this->lines->removeElement($line)) {
            if ($line->getCustomsEntry() === $this) {
                $line->setCustomsEntry(null);
            }
        }
        return $this;
    }
}
