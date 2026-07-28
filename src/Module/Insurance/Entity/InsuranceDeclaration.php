<?php
namespace App\Module\Insurance\Entity;

use App\Module\Insurance\Repository\InsuranceDeclarationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InsuranceDeclarationRepository::class)]
class InsuranceDeclaration
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private InsurancePolicy $policy;

    #[ORM\Column(length: 64, unique: true)]
    private string $declarationRef;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $periodFrom;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $periodTo;

    #[ORM\Column]
    private int $certificateCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $totalInsuredValue = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $totalPremium = 0;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 16)]
    private string $status = 'DRAFT'; // DRAFT / SUBMITTED / ACKNOWLEDGED

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $submittedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: 'declaration', targetEntity: InsuranceDeclarationLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getPolicy(): InsurancePolicy { return $this->policy; }
    public function setPolicy(InsurancePolicy $v): static { $this->policy = $v; return $this; }
    public function getDeclarationRef(): string { return $this->declarationRef; }
    public function setDeclarationRef(string $v): static { $this->declarationRef = $v; return $this; }
    public function getPeriodFrom(): \DateTimeInterface { return $this->periodFrom; }
    public function setPeriodFrom(\DateTimeInterface $v): static { $this->periodFrom = $v; return $this; }
    public function getPeriodTo(): \DateTimeInterface { return $this->periodTo; }
    public function setPeriodTo(\DateTimeInterface $v): static { $this->periodTo = $v; return $this; }
    public function getCertificateCount(): int { return $this->certificateCount; }
    public function setCertificateCount(int $v): static { $this->certificateCount = $v; return $this; }
    public function getTotalInsuredValue(): float { return (float) $this->totalInsuredValue; }
    public function setTotalInsuredValue(float $v): static { $this->totalInsuredValue = $v; return $this; }
    public function getTotalPremium(): float { return (float) $this->totalPremium; }
    public function setTotalPremium(float $v): static { $this->totalPremium = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getSubmittedAt(): ?\DateTimeInterface { return $this->submittedAt; }
    public function setSubmittedAt(?\DateTimeInterface $v): static { $this->submittedAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }
    public function getLines(): Collection { return $this->lines; }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'policy'            => ['id' => $this->policy->getId(), 'policyNumber' => $this->policy->getPolicyNumber()],
            'declarationRef'    => $this->declarationRef,
            'periodFrom'        => $this->periodFrom->format('Y-m-d'),
            'periodTo'          => $this->periodTo->format('Y-m-d'),
            'certificateCount'  => $this->certificateCount,
            'totalInsuredValue' => $this->getTotalInsuredValue(),
            'totalPremium'      => $this->getTotalPremium(),
            'currency'          => $this->currency,
            'status'            => $this->status,
            'submittedAt'       => $this->submittedAt?->format('Y-m-d H:i:s'),
            'createdAt'         => $this->createdAt->format('Y-m-d H:i:s'),
            'certificateIds'    => $this->lines->map(fn($l) => $l->getCertificate()->getId())->toArray(),
        ];
    }
}
