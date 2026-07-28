<?php

namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\WarehouseFacilityRepository;

#[ORM\Entity(repositoryClass: WarehouseFacilityRepository::class)]
#[ORM\HasLifecycleCallbacks]
class WarehouseFacility
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $locationCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalAreaSqm = null;

    #[ORM\Column(nullable: true)]
    private ?int $reeferCapacity = null;

    #[ORM\Column]
    private bool $bonded = false;

    #[ORM\Column]
    private bool $dangerousGoodsApproved = false;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\PrePersist]
    public function prePersist(): void { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $v): static { $this->name = $v; return $this; }

    public function getLocationCode(): ?string { return $this->locationCode; }
    public function setLocationCode(?string $v): static { $this->locationCode = $v; return $this; }

    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $v): static { $this->address = $v; return $this; }

    public function getTotalAreaSqm(): ?string { return $this->totalAreaSqm; }
    public function setTotalAreaSqm(?string $v): static { $this->totalAreaSqm = $v; return $this; }

    public function getReeferCapacity(): ?int { return $this->reeferCapacity; }
    public function setReeferCapacity(?int $v): static { $this->reeferCapacity = $v; return $this; }

    public function isBonded(): bool { return $this->bonded; }
    public function setBonded(bool $v): static { $this->bonded = $v; return $this; }

    public function isDangerousGoodsApproved(): bool { return $this->dangerousGoodsApproved; }
    public function setDangerousGoodsApproved(bool $v): static { $this->dangerousGoodsApproved = $v; return $this; }

    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function setContactPhone(?string $v): static { $this->contactPhone = $v; return $this; }

    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(?string $v): static { $this->contactEmail = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
}
