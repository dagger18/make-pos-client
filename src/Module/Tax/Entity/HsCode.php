<?php
namespace App\Module\Tax\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Tax\Repository\HsCodeRepository;

#[ORM\Entity(repositoryClass: HsCodeRepository::class)]
class HsCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[ORM\Column(length: 500)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $level = null;

    #[ORM\Column(nullable: true)]
    private ?int $digits = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $hsVersion = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getLevel(): ?int { return $this->level; }
    public function setLevel(?int $level): static { $this->level = $level; return $this; }

    public function getDigits(): ?int { return $this->digits; }
    public function setDigits(?int $digits): static { $this->digits = $digits; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $countryCode): static { $this->countryCode = $countryCode; return $this; }

    public function getHsVersion(): ?string { return $this->hsVersion; }
    public function setHsVersion(?string $hsVersion): static { $this->hsVersion = $hsVersion; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }

    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): static { $this->parent = $parent; return $this; }
}
