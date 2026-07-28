<?php
namespace App\Module\Core\Entity;

use App\Module\Crm\Entity\Client;

use App\Module\Core\Enum\AddressType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Core\Repository\OrganisationAddressRepository;

#[ORM\Entity(repositoryClass: OrganisationAddressRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrganisationAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\Column(length: 32, enumType: AddressType::class)]
    private AddressType $addressType = AddressType::Registered;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    private string $addressLine1 = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $addressLine2 = null;

    #[ORM\Column(length: 128)]
    private string $city = '';

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $state = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 2)]
    private string $country = '';

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getAddressType(): AddressType { return $this->addressType; }
    public function setAddressType(AddressType $addressType): static { $this->addressType = $addressType; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }

    public function getAddressLine1(): string { return $this->addressLine1; }
    public function setAddressLine1(string $addressLine1): static { $this->addressLine1 = $addressLine1; return $this; }

    public function getAddressLine2(): ?string { return $this->addressLine2; }
    public function setAddressLine2(?string $addressLine2): static { $this->addressLine2 = $addressLine2; return $this; }

    public function getCity(): string { return $this->city; }
    public function setCity(string $city): static { $this->city = $city; return $this; }

    public function getState(): ?string { return $this->state; }
    public function setState(?string $state): static { $this->state = $state; return $this; }

    public function getPostalCode(): ?string { return $this->postalCode; }
    public function setPostalCode(?string $postalCode): static { $this->postalCode = $postalCode; return $this; }

    public function getCountry(): string { return $this->country; }
    public function setCountry(string $country): static { $this->country = $country; return $this; }

    public function isDefault(): bool { return $this->isDefault; }
    public function setIsDefault(bool $isDefault): static { $this->isDefault = $isDefault; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
