<?php
namespace App\Module\Tax\Entity;

use App\Module\Crm\Entity\Partner;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Tax\Repository\PartnerTaxRegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PartnerTaxRegistrationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PartnerTaxRegistration
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Partner $partner;

    #[ORM\Column(length: 2)]
    private string $countryCode;

    #[ORM\Column(length: 16)]
    private string $taxType;

    #[ORM\Column(length: 64)]
    private string $registrationNo;

    #[ORM\Column]
    private bool $isPrimary = false;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $effectiveFrom;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }
    public function getPartner(): Partner { return $this->partner; }
    public function setPartner(Partner $v): static { $this->partner = $v; return $this; }
    public function getCountryCode(): string { return $this->countryCode; }
    public function setCountryCode(string $v): static { $this->countryCode = $v; return $this; }
    public function getTaxType(): string { return $this->taxType; }
    public function setTaxType(string $v): static { $this->taxType = $v; return $this; }
    public function getRegistrationNo(): string { return $this->registrationNo; }
    public function setRegistrationNo(string $v): static { $this->registrationNo = $v; return $this; }
    public function isPrimary(): bool { return $this->isPrimary; }
    public function setIsPrimary(bool $v): static { $this->isPrimary = $v; return $this; }
    public function getEffectiveFrom(): \DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(\DateTimeInterface $v): static { $this->effectiveFrom = $v; return $this; }
    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $v): static { $this->effectiveTo = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'partnerId'      => $this->partner->getId(),
            'countryCode'    => $this->countryCode,
            'taxType'        => $this->taxType,
            'registrationNo' => $this->registrationNo,
            'isPrimary'      => $this->isPrimary,
            'effectiveFrom'  => $this->effectiveFrom->format('Y-m-d'),
            'effectiveTo'    => $this->effectiveTo?->format('Y-m-d'),
        ];
    }
}
