<?php
namespace App\Module\Tax\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Tax\Repository\DutyRateRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: DutyRateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DutyRate
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HsCode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HsCode $hsCode = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $importCountry = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $exportCountry = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rateType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ftaName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $dutyRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $vatRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $exciseRate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }

    public function getHsCode(): ?HsCode { return $this->hsCode; }
    public function setHsCode(?HsCode $hsCode): static { $this->hsCode = $hsCode; return $this; }

    public function getImportCountry(): ?string { return $this->importCountry; }
    public function setImportCountry(?string $importCountry): static { $this->importCountry = $importCountry; return $this; }

    public function getExportCountry(): ?string { return $this->exportCountry; }
    public function setExportCountry(?string $exportCountry): static { $this->exportCountry = $exportCountry; return $this; }

    public function getRateType(): ?string { return $this->rateType; }
    public function setRateType(?string $rateType): static { $this->rateType = $rateType; return $this; }

    public function getFtaName(): ?string { return $this->ftaName; }
    public function setFtaName(?string $ftaName): static { $this->ftaName = $ftaName; return $this; }

    public function getDutyRate(): ?string { return $this->dutyRate; }
    public function setDutyRate(?string $dutyRate): static { $this->dutyRate = $dutyRate; return $this; }

    public function getVatRate(): ?string { return $this->vatRate; }
    public function setVatRate(?string $vatRate): static { $this->vatRate = $vatRate; return $this; }

    public function getExciseRate(): ?string { return $this->exciseRate; }
    public function setExciseRate(?string $exciseRate): static { $this->exciseRate = $exciseRate; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }
}
