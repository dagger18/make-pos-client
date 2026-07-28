<?php

namespace App\Module\Operations\Entity;

use App\Module\Crm\Entity\Client;
use App\Module\Crm\Entity\Contact;

use App\Module\Operations\Enum\PartyRole;
use App\Module\Operations\Repository\ShipmentPartyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentPartyRepository::class)]
#[ORM\UniqueConstraint(name: 'uq_party_role', columns: ['shipment_id', 'role'])]
#[ORM\HasLifecycleCallbacks]
class ShipmentParty
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32, enumType: PartyRole::class)]
    private ?PartyRole $role = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Contact $contact = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $reference = null;

    #[ORM\Column]
    private bool $isAlsoNotify = false;

    #[ORM\Column(type: Types::JSON)]
    private array $addressSnapshot = [];

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $shipment): static { $this->shipment = $shipment; return $this; }

    public function getRole(): ?PartyRole { return $this->role; }
    public function setRole(PartyRole $role): static { $this->role = $role; return $this; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getContact(): ?Contact { return $this->contact; }
    public function setContact(?Contact $contact): static { $this->contact = $contact; return $this; }

    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $this->reference = $reference; return $this; }

    public function isAlsoNotify(): bool { return $this->isAlsoNotify; }
    public function setIsAlsoNotify(bool $isAlsoNotify): static { $this->isAlsoNotify = $isAlsoNotify; return $this; }

    public function getAddressSnapshot(): array { return $this->addressSnapshot; }
    public function setAddressSnapshot(array $addressSnapshot): static { $this->addressSnapshot = $addressSnapshot; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
