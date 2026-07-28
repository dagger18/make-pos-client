<?php
declare(strict_types=1);
namespace App\Module\Lc\Entity;

use App\Module\Lc\Repository\LcDiscrepancyRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LcDiscrepancyRepository::class)]
#[ORM\Table(name: 'lc_discrepancy')]
class LcDiscrepancy
{
    const SEVERITIES = ['FATAL', 'WARNING'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LetterOfCredit $lc;

    #[ORM\Column(length: 64)]
    private string $checkCode;

    #[ORM\Column(length: 8)]
    private string $severity = 'FATAL';

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $documentType = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $detectedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column]
    private bool $isWaived = false;

    #[ORM\Column]
    private bool $waivedByBank = false;

    public function __construct()
    {
        $this->detectedAt = new \DateTime();
    }

    public function getId(): int { return $this->id; }

    public function getLc(): LetterOfCredit { return $this->lc; }
    public function setLc(LetterOfCredit $v): static { $this->lc = $v; return $this; }

    public function getCheckCode(): string { return $this->checkCode; }
    public function setCheckCode(string $v): static { $this->checkCode = $v; return $this; }

    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $v): static { $this->severity = $v; return $this; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $v): static { $this->description = $v; return $this; }

    public function getDocumentType(): ?string { return $this->documentType; }
    public function setDocumentType(?string $v): static { $this->documentType = $v; return $this; }

    public function getDetectedAt(): \DateTimeInterface { return $this->detectedAt; }

    public function getResolvedAt(): ?\DateTimeInterface { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTimeInterface $v): static { $this->resolvedAt = $v; return $this; }

    public function getResolution(): ?string { return $this->resolution; }
    public function setResolution(?string $v): static { $this->resolution = $v; return $this; }

    public function isWaived(): bool { return $this->isWaived; }
    public function setIsWaived(bool $v): static { $this->isWaived = $v; return $this; }

    public function isWaivedByBank(): bool { return $this->waivedByBank; }
    public function setWaivedByBank(bool $v): static { $this->waivedByBank = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'lcId'         => $this->lc->getId(),
            'checkCode'    => $this->checkCode,
            'severity'     => $this->severity,
            'description'  => $this->description,
            'documentType' => $this->documentType,
            'detectedAt'   => $this->detectedAt->format('Y-m-d H:i:s'),
            'resolvedAt'   => $this->resolvedAt?->format('Y-m-d H:i:s'),
            'resolution'   => $this->resolution,
            'isWaived'     => $this->isWaived,
            'waivedByBank' => $this->waivedByBank,
            'isOpen'       => !$this->isWaived && $this->resolvedAt === null,
        ];
    }
}
