<?php
namespace App\Module\Tax\Entity;

use App\Module\Core\Entity\User;

use App\Module\Crm\Entity\Partner;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Tax\Repository\CustomerTaxExemptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerTaxExemptionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CustomerTaxExemption
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Partner $partner;

    #[ORM\Column(length: 32)]
    private string $exemptionType;

    #[ORM\Column(length: 2)]
    private string $countryCode;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $exemptionRef = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $validFrom;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validTo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $documentUrl = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $verifiedBy = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $verifiedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getPartner(): Partner { return $this->partner; }
    public function setPartner(Partner $v): static { $this->partner = $v; return $this; }
    public function getExemptionType(): string { return $this->exemptionType; }
    public function setExemptionType(string $v): static { $this->exemptionType = $v; return $this; }
    public function getCountryCode(): string { return $this->countryCode; }
    public function setCountryCode(string $v): static { $this->countryCode = $v; return $this; }
    public function getExemptionRef(): ?string { return $this->exemptionRef; }
    public function setExemptionRef(?string $v): static { $this->exemptionRef = $v; return $this; }
    public function getValidFrom(): \DateTimeInterface { return $this->validFrom; }
    public function setValidFrom(\DateTimeInterface $v): static { $this->validFrom = $v; return $this; }
    public function getValidTo(): ?\DateTimeInterface { return $this->validTo; }
    public function setValidTo(?\DateTimeInterface $v): static { $this->validTo = $v; return $this; }
    public function getDocumentUrl(): ?string { return $this->documentUrl; }
    public function setDocumentUrl(?string $v): static { $this->documentUrl = $v; return $this; }
    public function getVerifiedBy(): ?User { return $this->verifiedBy; }
    public function setVerifiedBy(?User $v): static { $this->verifiedBy = $v; return $this; }
    public function getVerifiedAt(): ?\DateTimeInterface { return $this->verifiedAt; }
    public function setVerifiedAt(?\DateTimeInterface $v): static { $this->verifiedAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'partnerId'     => $this->partner->getId(),
            'exemptionType' => $this->exemptionType,
            'countryCode'   => $this->countryCode,
            'exemptionRef'  => $this->exemptionRef,
            'validFrom'     => $this->validFrom->format('Y-m-d'),
            'validTo'       => $this->validTo?->format('Y-m-d'),
            'documentUrl'   => $this->documentUrl,
            'verifiedById'  => $this->verifiedBy?->getId(),
            'verifiedAt'    => $this->verifiedAt?->format('Y-m-d'),
        ];
    }
}
