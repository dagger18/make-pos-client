<?php
namespace App\Module\Quote\Entity;

use App\Module\Quote\Repository\RateImportRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateImportRowRepository::class)]
class RateImportRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rows')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RateImportJob $importJob = null;

    #[ORM\Column]
    private int $rowNumber;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $polCode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $podCode = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $containerType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $chargeCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $newBuyingAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $newSellingAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $currentBuyingAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4, nullable: true)]
    private ?string $changePct = null;

    #[ORM\Column]
    private bool $isSanityFlagged = false;

    /** NEW | UPDATE | SKIP | ERROR */
    #[ORM\Column(length: 16)]
    private string $action = 'NEW';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?int $existingRateId = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $previousValidUntil = null;

    public function getId(): ?int { return $this->id; }

    public function getImportJob(): ?RateImportJob { return $this->importJob; }
    public function setImportJob(?RateImportJob $v): static { $this->importJob = $v; return $this; }

    public function getRowNumber(): int { return $this->rowNumber; }
    public function setRowNumber(int $v): static { $this->rowNumber = $v; return $this; }

    public function getPolCode(): ?string { return $this->polCode; }
    public function setPolCode(?string $v): static { $this->polCode = $v; return $this; }

    public function getPodCode(): ?string { return $this->podCode; }
    public function setPodCode(?string $v): static { $this->podCode = $v; return $this; }

    public function getContainerType(): ?string { return $this->containerType; }
    public function setContainerType(?string $v): static { $this->containerType = $v; return $this; }

    public function getChargeCode(): ?string { return $this->chargeCode; }
    public function setChargeCode(?string $v): static { $this->chargeCode = $v; return $this; }

    public function getNewBuyingAmount(): ?string { return $this->newBuyingAmount; }
    public function setNewBuyingAmount(?string $v): static { $this->newBuyingAmount = $v; return $this; }

    public function getNewSellingAmount(): ?string { return $this->newSellingAmount; }
    public function setNewSellingAmount(?string $v): static { $this->newSellingAmount = $v; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }

    public function getCurrentBuyingAmount(): ?string { return $this->currentBuyingAmount; }
    public function setCurrentBuyingAmount(?string $v): static { $this->currentBuyingAmount = $v; return $this; }

    public function getChangePct(): ?string { return $this->changePct; }
    public function setChangePct(?string $v): static { $this->changePct = $v; return $this; }

    public function isIsSanityFlagged(): bool { return $this->isSanityFlagged; }
    public function setIsSanityFlagged(bool $v): static { $this->isSanityFlagged = $v; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }

    public function getExistingRateId(): ?int { return $this->existingRateId; }
    public function setExistingRateId(?int $v): static { $this->existingRateId = $v; return $this; }

    public function getPreviousValidUntil(): ?\DateTimeInterface { return $this->previousValidUntil; }
    public function setPreviousValidUntil(?\DateTimeInterface $v): static { $this->previousValidUntil = $v; return $this; }
}
