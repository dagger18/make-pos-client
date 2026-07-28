<?php
namespace App\Module\Quote\Entity;

use App\Module\Carrier\Entity\Provider;
use App\Module\Core\Entity\User;
use App\Module\Core\Enum\TransportType;
use App\Module\Quote\Repository\RateImportJobRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateImportJobRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RateImportJob
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $importSource = 'EXCEL';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 8, enumType: TransportType::class)]
    private TransportType $transportType;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(length: 16)]
    private string $status = 'PENDING';

    #[ORM\Column]
    private int $totalRows = 0;

    #[ORM\Column]
    private int $rowsImported = 0;

    #[ORM\Column]
    private int $rowsSkipped = 0;

    #[ORM\Column]
    private int $rowsErrored = 0;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column]
    private bool $requiresApproval = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column]
    private bool $canRollback = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $rolledBackBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $rolledBackAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $errorLog = null;

    #[ORM\OneToMany(targetEntity: RateImportRow::class, mappedBy: 'importJob', cascade: ['persist', 'remove'])]
    private Collection $rows;

    public function __construct()
    {
        $this->rows = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getImportSource(): string { return $this->importSource; }
    public function setImportSource(string $v): static { $this->importSource = $v; return $this; }

    public function getProvider(): ?Provider { return $this->provider; }
    public function setProvider(?Provider $v): static { $this->provider = $v; return $this; }

    public function getTransportType(): TransportType { return $this->transportType; }
    public function setTransportType(TransportType $v): static { $this->transportType = $v; return $this; }

    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $v): static { $this->fileName = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getTotalRows(): int { return $this->totalRows; }
    public function setTotalRows(int $v): static { $this->totalRows = $v; return $this; }

    public function getRowsImported(): int { return $this->rowsImported; }
    public function setRowsImported(int $v): static { $this->rowsImported = $v; return $this; }

    public function getRowsSkipped(): int { return $this->rowsSkipped; }
    public function setRowsSkipped(int $v): static { $this->rowsSkipped = $v; return $this; }

    public function getRowsErrored(): int { return $this->rowsErrored; }
    public function setRowsErrored(int $v): static { $this->rowsErrored = $v; return $this; }

    public function getEffectiveDate(): ?\DateTimeInterface { return $this->effectiveDate; }
    public function setEffectiveDate(?\DateTimeInterface $v): static { $this->effectiveDate = $v; return $this; }

    public function getExpiryDate(): ?\DateTimeInterface { return $this->expiryDate; }
    public function setExpiryDate(?\DateTimeInterface $v): static { $this->expiryDate = $v; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }

    public function isRequiresApproval(): bool { return $this->requiresApproval; }
    public function setRequiresApproval(bool $v): static { $this->requiresApproval = $v; return $this; }

    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $v): static { $this->approvedBy = $v; return $this; }

    public function getApprovedAt(): ?\DateTimeInterface { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeInterface $v): static { $this->approvedAt = $v; return $this; }

    public function isCanRollback(): bool { return $this->canRollback; }
    public function setCanRollback(bool $v): static { $this->canRollback = $v; return $this; }

    public function getRolledBackBy(): ?User { return $this->rolledBackBy; }
    public function setRolledBackBy(?User $v): static { $this->rolledBackBy = $v; return $this; }

    public function getRolledBackAt(): ?\DateTimeInterface { return $this->rolledBackAt; }
    public function setRolledBackAt(?\DateTimeInterface $v): static { $this->rolledBackAt = $v; return $this; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $v): static { $this->uploadedBy = $v; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $v): static { $this->completedAt = $v; return $this; }

    public function getErrorLog(): ?array { return $this->errorLog; }
    public function setErrorLog(?array $v): static { $this->errorLog = $v; return $this; }

    public function getRows(): Collection { return $this->rows; }
}
