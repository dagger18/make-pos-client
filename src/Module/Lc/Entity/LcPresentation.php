<?php
declare(strict_types=1);
namespace App\Module\Lc\Entity;

use App\Module\Lc\Repository\LcPresentationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LcPresentationRepository::class)]
#[ORM\Table(name: 'lc_presentation')]
class LcPresentation
{
    const BANK_RESPONSES = ['COMPLIANT', 'DISCREPANT', 'PENDING'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LetterOfCredit $lc;

    #[ORM\Column(length: 255)]
    private string $presentedToBankName;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $presentedAt;

    #[ORM\Column(type: 'json')]
    private array $documentsPresented = [];

    #[ORM\Column]
    private bool $hasDiscrepancies = false;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $bankResponse = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $bankResponseDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $paymentDate = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?string $paymentAmount = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): int { return $this->id; }

    public function getLc(): LetterOfCredit { return $this->lc; }
    public function setLc(LetterOfCredit $v): static { $this->lc = $v; return $this; }

    public function getPresentedToBankName(): string { return $this->presentedToBankName; }
    public function setPresentedToBankName(string $v): static { $this->presentedToBankName = $v; return $this; }

    public function getPresentedAt(): \DateTimeInterface { return $this->presentedAt; }
    public function setPresentedAt(\DateTimeInterface $v): static { $this->presentedAt = $v; return $this; }

    public function getDocumentsPresented(): array { return $this->documentsPresented; }
    public function setDocumentsPresented(array $v): static { $this->documentsPresented = $v; return $this; }

    public function isHasDiscrepancies(): bool { return $this->hasDiscrepancies; }
    public function setHasDiscrepancies(bool $v): static { $this->hasDiscrepancies = $v; return $this; }

    public function getBankResponse(): ?string { return $this->bankResponse; }
    public function setBankResponse(?string $v): static { $this->bankResponse = $v; return $this; }

    public function getBankResponseDate(): ?\DateTimeInterface { return $this->bankResponseDate; }
    public function setBankResponseDate(?\DateTimeInterface $v): static { $this->bankResponseDate = $v; return $this; }

    public function getPaymentDate(): ?\DateTimeInterface { return $this->paymentDate; }
    public function setPaymentDate(?\DateTimeInterface $v): static { $this->paymentDate = $v; return $this; }

    public function getPaymentAmount(): ?string { return $this->paymentAmount; }
    public function setPaymentAmount(?string $v): static { $this->paymentAmount = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'lcId'                => $this->lc->getId(),
            'presentedToBankName' => $this->presentedToBankName,
            'presentedAt'         => $this->presentedAt->format('Y-m-d H:i:s'),
            'documentsPresented'  => $this->documentsPresented,
            'hasDiscrepancies'    => $this->hasDiscrepancies,
            'bankResponse'        => $this->bankResponse,
            'bankResponseDate'    => $this->bankResponseDate?->format('Y-m-d'),
            'paymentDate'         => $this->paymentDate?->format('Y-m-d'),
            'paymentAmount'       => $this->paymentAmount !== null ? (float) $this->paymentAmount : null,
            'notes'               => $this->notes,
            'createdAt'           => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
