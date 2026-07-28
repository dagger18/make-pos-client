<?php

namespace App\Module\Operations\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Module\Operations\Repository\StrippingInstructionRepository;

#[ORM\Entity(repositoryClass: StrippingInstructionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class StrippingInstruction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $facilityId = 0;

    #[ORM\Column]
    private int $consolId = 0;

    #[ORM\Column(length: 64, unique: true)]
    private string $instructionNumber = '';

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $containerNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $containerArrival = null;

    #[ORM\Column(length: 16)]
    private string $status = 'PENDING';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\OneToMany(mappedBy: 'strippingInstruction', targetEntity: StrippingResult::class, cascade: ['persist', 'remove'])]
    private Collection $results;

    public function __construct()
    {
        $this->results = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function prePersist(): void { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getFacilityId(): int { return $this->facilityId; }
    public function setFacilityId(int $v): static { $this->facilityId = $v; return $this; }

    public function getConsolId(): int { return $this->consolId; }
    public function setConsolId(int $v): static { $this->consolId = $v; return $this; }

    public function getInstructionNumber(): string { return $this->instructionNumber; }
    public function setInstructionNumber(string $v): static { $this->instructionNumber = $v; return $this; }

    public function getContainerNumber(): ?string { return $this->containerNumber; }
    public function setContainerNumber(?string $v): static { $this->containerNumber = $v; return $this; }

    public function getContainerArrival(): ?\DateTimeInterface { return $this->containerArrival; }
    public function setContainerArrival(?\DateTimeInterface $v): static { $this->containerArrival = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getStartedAt(): ?\DateTimeInterface { return $this->startedAt; }
    public function setStartedAt(?\DateTimeInterface $v): static { $this->startedAt = $v; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $v): static { $this->completedAt = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    /** @return Collection<int, StrippingResult> */
    public function getResults(): Collection { return $this->results; }
}
