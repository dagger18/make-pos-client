<?php
namespace App\Module\Tax\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Tax\Repository\HsRestrictionRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: HsRestrictionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HsRestriction
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
    private ?string $countryCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $restrictionType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authority = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $licenceType = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }

    public function getHsCode(): ?HsCode { return $this->hsCode; }
    public function setHsCode(?HsCode $hsCode): static { $this->hsCode = $hsCode; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $countryCode): static { $this->countryCode = $countryCode; return $this; }

    public function getRestrictionType(): ?string { return $this->restrictionType; }
    public function setRestrictionType(?string $restrictionType): static { $this->restrictionType = $restrictionType; return $this; }

    public function getAuthority(): ?string { return $this->authority; }
    public function setAuthority(?string $authority): static { $this->authority = $authority; return $this; }

    public function getLicenceType(): ?string { return $this->licenceType; }
    public function setLicenceType(?string $licenceType): static { $this->licenceType = $licenceType; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }
}
