<?php
declare(strict_types=1);
namespace App\Module\Lc\Entity;

use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\User;
use App\Module\Lc\Repository\LcDocumentRequirementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LcDocumentRequirementRepository::class)]
#[ORM\Table(name: 'lc_document_requirement')]
class LcDocumentRequirement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LetterOfCredit $lc;

    #[ORM\Column(length: 32)]
    private string $docType;

    #[ORM\Column(type: 'smallint')]
    private int $quantityOriginals = 1;

    #[ORM\Column(type: 'smallint')]
    private int $quantityCopies = 0;

    #[ORM\Column(type: 'text')]
    private string $specificWording = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $media = null;

    #[ORM\Column]
    private bool $isReady = false;

    #[ORM\Column]
    private bool $complianceChecked = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $complianceCheckedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $complianceNotes = null;

    public function getId(): int { return $this->id; }

    public function getLc(): LetterOfCredit { return $this->lc; }
    public function setLc(LetterOfCredit $v): static { $this->lc = $v; return $this; }

    public function getDocType(): string { return $this->docType; }
    public function setDocType(string $v): static { $this->docType = $v; return $this; }

    public function getQuantityOriginals(): int { return $this->quantityOriginals; }
    public function setQuantityOriginals(int $v): static { $this->quantityOriginals = $v; return $this; }

    public function getQuantityCopies(): int { return $this->quantityCopies; }
    public function setQuantityCopies(int $v): static { $this->quantityCopies = $v; return $this; }

    public function getSpecificWording(): string { return $this->specificWording; }
    public function setSpecificWording(string $v): static { $this->specificWording = $v; return $this; }

    public function getMedia(): ?Media { return $this->media; }
    public function setMedia(?Media $v): static { $this->media = $v; return $this; }

    public function isReady(): bool { return $this->isReady; }
    public function setIsReady(bool $v): static { $this->isReady = $v; return $this; }

    public function isComplianceChecked(): bool { return $this->complianceChecked; }
    public function setComplianceChecked(bool $v): static { $this->complianceChecked = $v; return $this; }

    public function getComplianceCheckedBy(): ?User { return $this->complianceCheckedBy; }
    public function setComplianceCheckedBy(?User $v): static { $this->complianceCheckedBy = $v; return $this; }

    public function getComplianceNotes(): ?string { return $this->complianceNotes; }
    public function setComplianceNotes(?string $v): static { $this->complianceNotes = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'lcId'                => $this->lc->getId(),
            'docType'             => $this->docType,
            'quantityOriginals'   => $this->quantityOriginals,
            'quantityCopies'      => $this->quantityCopies,
            'specificWording'     => $this->specificWording,
            'mediaId'             => $this->media?->getId(),
            'isReady'             => $this->isReady,
            'complianceChecked'   => $this->complianceChecked,
            'complianceCheckedBy' => $this->complianceCheckedBy?->getId(),
            'complianceNotes'     => $this->complianceNotes,
        ];
    }
}
