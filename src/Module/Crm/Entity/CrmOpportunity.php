<?php
namespace App\Module\Crm\Entity;

use App\Module\Core\Entity\User;
use App\Module\Crm\Repository\CrmOpportunityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CrmOpportunityRepository::class)]
class CrmOpportunity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?CrmLead $lead = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $client = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $transportMode = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $serviceType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $polName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $podName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $estimatedVolume = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $volumeUom = null; // TEU / TON / CBM / SHIPMENTS

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $estimatedRevenue = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(length: 32)]
    private string $stage = 'PROSPECTING';
    // PROSPECTING / QUALIFICATION / PROPOSAL / NEGOTIATION / CLOSED_WON / CLOSED_LOST

    #[ORM\Column(type: Types::SMALLINT)]
    private int $probabilityPct = 10;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expectedClose = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $competitor = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lossReason = null;
    // PRICE / SERVICE / RELATIONSHIP / CAPACITY / OTHER

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $winReason = null;

    #[ORM\Column(nullable: true)]
    private ?int $quoteId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $closedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getLead(): ?CrmLead { return $this->lead; }
    public function setLead(?CrmLead $v): static { $this->lead = $v; return $this; }
    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $v): static { $this->client = $v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): static { $this->title = $v; return $this; }
    public function getTransportMode(): ?string { return $this->transportMode; }
    public function setTransportMode(?string $v): static { $this->transportMode = $v; return $this; }
    public function getServiceType(): ?string { return $this->serviceType; }
    public function setServiceType(?string $v): static { $this->serviceType = $v; return $this; }
    public function getPolName(): ?string { return $this->polName; }
    public function setPolName(?string $v): static { $this->polName = $v; return $this; }
    public function getPodName(): ?string { return $this->podName; }
    public function setPodName(?string $v): static { $this->podName = $v; return $this; }
    public function getEstimatedVolume(): ?float { return $this->estimatedVolume !== null ? (float) $this->estimatedVolume : null; }
    public function setEstimatedVolume(?float $v): static { $this->estimatedVolume = $v !== null ? (string) $v : null; return $this; }
    public function getVolumeUom(): ?string { return $this->volumeUom; }
    public function setVolumeUom(?string $v): static { $this->volumeUom = $v; return $this; }
    public function getEstimatedRevenue(): ?float { return $this->estimatedRevenue !== null ? (float) $this->estimatedRevenue : null; }
    public function setEstimatedRevenue(?float $v): static { $this->estimatedRevenue = $v !== null ? (string) $v : null; return $this; }
    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }
    public function getStage(): string { return $this->stage; }
    public function setStage(string $v): static { $this->stage = $v; return $this; }
    public function getProbabilityPct(): int { return $this->probabilityPct; }
    public function setProbabilityPct(int $v): static { $this->probabilityPct = $v; return $this; }
    public function getExpectedClose(): ?\DateTimeInterface { return $this->expectedClose; }
    public function setExpectedClose(?\DateTimeInterface $v): static { $this->expectedClose = $v; return $this; }
    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $v): static { $this->assignedTo = $v; return $this; }
    public function getCompetitor(): ?string { return $this->competitor; }
    public function setCompetitor(?string $v): static { $this->competitor = $v; return $this; }
    public function getLossReason(): ?string { return $this->lossReason; }
    public function setLossReason(?string $v): static { $this->lossReason = $v; return $this; }
    public function getWinReason(): ?string { return $this->winReason; }
    public function setWinReason(?string $v): static { $this->winReason = $v; return $this; }
    public function getQuoteId(): ?int { return $this->quoteId; }
    public function setQuoteId(?int $v): static { $this->quoteId = $v; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }
    public function getClosedAt(): ?\DateTimeInterface { return $this->closedAt; }
    public function setClosedAt(?\DateTimeInterface $v): static { $this->closedAt = $v; return $this; }

    public function getWeightedRevenue(): float
    {
        return round(($this->estimatedRevenue ?? 0) * ($this->probabilityPct / 100), 2);
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'lead'             => $this->lead ? ['id' => $this->lead->getId(), 'companyName' => $this->lead->getCompanyName()] : null,
            'client'           => $this->client ? ['id' => $this->client->getId(), 'name' => $this->client->getName()] : null,
            'title'            => $this->title,
            'transportMode'    => $this->transportMode,
            'serviceType'      => $this->serviceType,
            'polName'          => $this->polName,
            'podName'          => $this->podName,
            'estimatedVolume'  => $this->getEstimatedVolume(),
            'volumeUom'        => $this->volumeUom,
            'estimatedRevenue' => $this->getEstimatedRevenue(),
            'weightedRevenue'  => $this->getWeightedRevenue(),
            'currency'         => $this->currency,
            'stage'            => $this->stage,
            'probabilityPct'   => $this->probabilityPct,
            'expectedClose'    => $this->expectedClose?->format('Y-m-d'),
            'assignedTo'       => $this->assignedTo ? ['id' => $this->assignedTo->getId(), 'name' => $this->assignedTo->getName()] : null,
            'competitor'       => $this->competitor,
            'lossReason'       => $this->lossReason,
            'winReason'        => $this->winReason,
            'quoteId'          => $this->quoteId,
            'notes'            => $this->notes,
            'createdAt'        => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt'        => $this->updatedAt?->format('Y-m-d H:i:s'),
            'closedAt'         => $this->closedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
