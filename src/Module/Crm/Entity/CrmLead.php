<?php
namespace App\Module\Crm\Entity;

use App\Module\Core\Entity\User;
use App\Module\Crm\Repository\CrmLeadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmLeadRepository::class)]
class CrmLead
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $companyName;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $industry = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $estimatedVolume = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $primaryMode = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $primaryTrade = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $source = null;
    // REFERRAL / LINKEDIN / COLD_CALL / TRADE_SHOW / INBOUND

    #[ORM\Column(length: 16)]
    private string $status = 'NEW';
    // NEW / CONTACTED / QUALIFIED / CONVERTED / DEAD

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $convertedClient = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $convertedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getCompanyName(): string { return $this->companyName; }
    public function setCompanyName(string $v): static { $this->companyName = $v; return $this; }
    public function getContactName(): ?string { return $this->contactName; }
    public function setContactName(?string $v): static { $this->contactName = $v; return $this; }
    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(?string $v): static { $this->contactEmail = $v; return $this; }
    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function setContactPhone(?string $v): static { $this->contactPhone = $v; return $this; }
    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $v): static { $this->countryCode = $v; return $this; }
    public function getIndustry(): ?string { return $this->industry; }
    public function setIndustry(?string $v): static { $this->industry = $v; return $this; }
    public function getEstimatedVolume(): ?string { return $this->estimatedVolume; }
    public function setEstimatedVolume(?string $v): static { $this->estimatedVolume = $v; return $this; }
    public function getPrimaryMode(): ?string { return $this->primaryMode; }
    public function setPrimaryMode(?string $v): static { $this->primaryMode = $v; return $this; }
    public function getPrimaryTrade(): ?string { return $this->primaryTrade; }
    public function setPrimaryTrade(?string $v): static { $this->primaryTrade = $v; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(?string $v): static { $this->source = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $v): static { $this->assignedTo = $v; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $v): static { $this->createdBy = $v; return $this; }
    public function getConvertedClient(): ?Client { return $this->convertedClient; }
    public function setConvertedClient(?Client $v): static { $this->convertedClient = $v; return $this; }
    public function getConvertedAt(): ?\DateTimeInterface { return $this->convertedAt; }
    public function setConvertedAt(?\DateTimeInterface $v): static { $this->convertedAt = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'companyName'     => $this->companyName,
            'contactName'     => $this->contactName,
            'contactEmail'    => $this->contactEmail,
            'contactPhone'    => $this->contactPhone,
            'countryCode'     => $this->countryCode,
            'industry'        => $this->industry,
            'estimatedVolume' => $this->estimatedVolume,
            'primaryMode'     => $this->primaryMode,
            'primaryTrade'    => $this->primaryTrade,
            'source'          => $this->source,
            'status'          => $this->status,
            'assignedTo'      => $this->assignedTo ? ['id' => $this->assignedTo->getId(), 'name' => $this->assignedTo->getName()] : null,
            'createdBy'       => $this->createdBy ? ['id' => $this->createdBy->getId(), 'name' => $this->createdBy->getName()] : null,
            'convertedClient' => $this->convertedClient ? ['id' => $this->convertedClient->getId(), 'name' => $this->convertedClient->getName()] : null,
            'convertedAt'     => $this->convertedAt?->format('Y-m-d H:i:s'),
            'notes'           => $this->notes,
            'createdAt'       => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
