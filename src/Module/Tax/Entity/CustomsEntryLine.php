<?php
namespace App\Module\Tax\Entity;

use App\Module\Tax\Repository\CustomsEntryLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomsEntryLineRepository::class)]
class CustomsEntryLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CustomsEntry::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CustomsEntry $customsEntry = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $lineNumber = 0;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $hsCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryOfOrigin = null;

    #[ORM\Column(nullable: true)]
    private ?int $packages = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $netWeightKg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 3, nullable: true)]
    private ?string $grossWeightKg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 4, nullable: true)]
    private ?string $quantity = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $uom = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $lineValue = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $valueCurrency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 4, nullable: true)]
    private ?string $dutyRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $dutyAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 4, nullable: true)]
    private ?string $vatRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?string $vatAmount = null;

    #[ORM\Column]
    private bool $isRestricted = false;

    public function getId(): ?int { return $this->id; }

    public function getCustomsEntry(): ?CustomsEntry { return $this->customsEntry; }
    public function setCustomsEntry(?CustomsEntry $customsEntry): static { $this->customsEntry = $customsEntry; return $this; }

    public function getLineNumber(): int { return $this->lineNumber; }
    public function setLineNumber(int $lineNumber): static { $this->lineNumber = $lineNumber; return $this; }

    public function getHsCode(): ?string { return $this->hsCode; }
    public function setHsCode(?string $hsCode): static { $this->hsCode = $hsCode; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getCountryOfOrigin(): ?string { return $this->countryOfOrigin; }
    public function setCountryOfOrigin(?string $countryOfOrigin): static { $this->countryOfOrigin = $countryOfOrigin; return $this; }

    public function getPackages(): ?int { return $this->packages; }
    public function setPackages(?int $packages): static { $this->packages = $packages; return $this; }

    public function getNetWeightKg(): ?string { return $this->netWeightKg; }
    public function setNetWeightKg(?string $netWeightKg): static { $this->netWeightKg = $netWeightKg; return $this; }

    public function getGrossWeightKg(): ?string { return $this->grossWeightKg; }
    public function setGrossWeightKg(?string $grossWeightKg): static { $this->grossWeightKg = $grossWeightKg; return $this; }

    public function getQuantity(): ?string { return $this->quantity; }
    public function setQuantity(?string $quantity): static { $this->quantity = $quantity; return $this; }

    public function getUom(): ?string { return $this->uom; }
    public function setUom(?string $uom): static { $this->uom = $uom; return $this; }

    public function getUnitPrice(): ?string { return $this->unitPrice; }
    public function setUnitPrice(?string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }

    public function getLineValue(): ?string { return $this->lineValue; }
    public function setLineValue(?string $lineValue): static { $this->lineValue = $lineValue; return $this; }

    public function getValueCurrency(): ?string { return $this->valueCurrency; }
    public function setValueCurrency(?string $valueCurrency): static { $this->valueCurrency = $valueCurrency; return $this; }

    public function getDutyRate(): ?string { return $this->dutyRate; }
    public function setDutyRate(?string $dutyRate): static { $this->dutyRate = $dutyRate; return $this; }

    public function getDutyAmount(): ?string { return $this->dutyAmount; }
    public function setDutyAmount(?string $dutyAmount): static { $this->dutyAmount = $dutyAmount; return $this; }

    public function getVatRate(): ?string { return $this->vatRate; }
    public function setVatRate(?string $vatRate): static { $this->vatRate = $vatRate; return $this; }

    public function getVatAmount(): ?string { return $this->vatAmount; }
    public function setVatAmount(?string $vatAmount): static { $this->vatAmount = $vatAmount; return $this; }

    public function isRestricted(): bool { return $this->isRestricted; }
    public function setIsRestricted(bool $isRestricted): static { $this->isRestricted = $isRestricted; return $this; }
}
