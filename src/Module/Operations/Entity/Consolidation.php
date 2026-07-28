<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\Branch;
use App\Module\Core\Entity\Port;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\Client;

use App\Module\Operations\Enum\ConsolidationStatus;
use App\Module\Operations\Repository\ConsolidationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsolidationRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Consolidation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 8)]
    private string $transportMode = '';

    #[ORM\Column(length: 16)]
    private string $serviceType = '';

    #[ORM\Column(length: 32, enumType: ConsolidationStatus::class)]
    private ConsolidationStatus $status = ConsolidationStatus::Open;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Branch $branch = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $carrier = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vessel = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $voyage = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $mblNumber = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $flightNumber = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $mawbNumber = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Port $pol = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Port $pod = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $etd = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $eta = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $containerNumber = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $uldNumber = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cfsCutoff = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $docCutoff = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $maxWeightKg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 3, nullable: true)]
    private ?string $maxVolumeCbm = null;

    #[ORM\Column(length: 16)]
    private string $apportionmentBasis = 'WEIGHT';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $c): static { $this->code = $c; return $this; }

    public function getTransportMode(): string { return $this->transportMode; }
    public function setTransportMode(string $m): static { $this->transportMode = $m; return $this; }

    public function getServiceType(): string { return $this->serviceType; }
    public function setServiceType(string $s): static { $this->serviceType = $s; return $this; }

    public function getStatus(): ConsolidationStatus { return $this->status; }
    public function setStatus(ConsolidationStatus $s): static { $this->status = $s; return $this; }

    public function getBranch(): ?Branch { return $this->branch; }
    public function setBranch(?Branch $b): static { $this->branch = $b; return $this; }

    public function getCarrier(): ?Client { return $this->carrier; }
    public function setCarrier(?Client $c): static { $this->carrier = $c; return $this; }

    public function getVessel(): ?string { return $this->vessel; }
    public function setVessel(?string $v): static { $this->vessel = $v; return $this; }

    public function getVoyage(): ?string { return $this->voyage; }
    public function setVoyage(?string $v): static { $this->voyage = $v; return $this; }

    public function getMblNumber(): ?string { return $this->mblNumber; }
    public function setMblNumber(?string $n): static { $this->mblNumber = $n; return $this; }

    public function getFlightNumber(): ?string { return $this->flightNumber; }
    public function setFlightNumber(?string $n): static { $this->flightNumber = $n; return $this; }

    public function getMawbNumber(): ?string { return $this->mawbNumber; }
    public function setMawbNumber(?string $n): static { $this->mawbNumber = $n; return $this; }

    public function getPol(): ?Port { return $this->pol; }
    public function setPol(?Port $p): static { $this->pol = $p; return $this; }

    public function getPod(): ?Port { return $this->pod; }
    public function setPod(?Port $p): static { $this->pod = $p; return $this; }

    public function getEtd(): ?\DateTimeInterface { return $this->etd; }
    public function setEtd(?\DateTimeInterface $d): static { $this->etd = $d; return $this; }

    public function getEta(): ?\DateTimeInterface { return $this->eta; }
    public function setEta(?\DateTimeInterface $d): static { $this->eta = $d; return $this; }

    public function getContainerNumber(): ?string { return $this->containerNumber; }
    public function setContainerNumber(?string $n): static { $this->containerNumber = $n; return $this; }

    public function getUldNumber(): ?string { return $this->uldNumber; }
    public function setUldNumber(?string $n): static { $this->uldNumber = $n; return $this; }

    public function getCfsCutoff(): ?\DateTimeInterface { return $this->cfsCutoff; }
    public function setCfsCutoff(?\DateTimeInterface $d): static { $this->cfsCutoff = $d; return $this; }

    public function getDocCutoff(): ?\DateTimeInterface { return $this->docCutoff; }
    public function setDocCutoff(?\DateTimeInterface $d): static { $this->docCutoff = $d; return $this; }

    public function getMaxWeightKg(): ?string { return $this->maxWeightKg; }
    public function setMaxWeightKg(?string $w): static { $this->maxWeightKg = $w; return $this; }

    public function getMaxVolumeCbm(): ?string { return $this->maxVolumeCbm; }
    public function setMaxVolumeCbm(?string $v): static { $this->maxVolumeCbm = $v; return $this; }

    public function getApportionmentBasis(): string { return $this->apportionmentBasis; }
    public function setApportionmentBasis(string $b): static { $this->apportionmentBasis = $b; return $this; }

    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $u): static { $this->createdBy = $u; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
