<?php
declare(strict_types=1);
namespace App\Module\Compliance\Entity;

use App\Module\Compliance\Repository\SanctionsListRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SanctionsListRepository::class)]
#[ORM\Table(name: 'sanctions_list')]
#[ORM\Index(columns: ['list_name'], name: 'IDX_sl_list')]
#[ORM\Index(columns: ['is_active'], name: 'IDX_sl_active')]
class SanctionsList
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $listName;

    #[ORM\Column(length: 255)]
    private string $listedName;

    /** JSON-encoded array of alias strings */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aliases = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $entityType = null;

    /** JSON-encoded array of sanction program strings */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $programs = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $listedDate = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $sourceRef = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $lastUpdated;

    public function getId(): ?int { return $this->id; }

    public function getListName(): string { return $this->listName; }
    public function setListName(string $v): static { $this->listName = $v; return $this; }

    public function getListedName(): string { return $this->listedName; }
    public function setListedName(string $v): static { $this->listedName = $v; return $this; }

    public function getAliases(): ?array { return $this->aliases; }
    public function setAliases(?array $v): static { $this->aliases = $v; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $v): static { $this->countryCode = $v; return $this; }

    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(?string $v): static { $this->entityType = $v; return $this; }

    public function getPrograms(): ?array { return $this->programs; }
    public function setPrograms(?array $v): static { $this->programs = $v; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $v): static { $this->reason = $v; return $this; }

    public function getListedDate(): ?\DateTimeInterface { return $this->listedDate; }
    public function setListedDate(?\DateTimeInterface $v): static { $this->listedDate = $v; return $this; }

    public function getSourceRef(): ?string { return $this->sourceRef; }
    public function setSourceRef(?string $v): static { $this->sourceRef = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }

    public function getLastUpdated(): \DateTimeInterface { return $this->lastUpdated; }
    public function setLastUpdated(\DateTimeInterface $v): static { $this->lastUpdated = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'listName'    => $this->listName,
            'listedName'  => $this->listedName,
            'aliases'     => $this->aliases ?? [],
            'countryCode' => $this->countryCode,
            'entityType'  => $this->entityType,
            'programs'    => $this->programs ?? [],
            'reason'      => $this->reason,
            'listedDate'  => $this->listedDate?->format('Y-m-d'),
            'sourceRef'   => $this->sourceRef,
            'isActive'    => $this->isActive,
            'lastUpdated' => $this->lastUpdated->format('Y-m-d'),
        ];
    }
}
