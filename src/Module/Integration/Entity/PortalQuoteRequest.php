<?php
namespace App\Module\Integration\Entity;

use App\Module\Core\Entity\User;
use App\Module\Quote\Entity\Quote;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Integration\Repository\PortalQuoteRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PortalQuoteRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PortalQuoteRequest
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PortalUser $portalUser = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $transportMode = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $serviceType = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $destination = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cargoDescription = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $weightKg = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $volumeCbm = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $containerType = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $incoterm = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cargoReadyDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialRequirements = null;

    #[ORM\Column(length: 16)]
    private string $status = 'RECEIVED';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Quote $linkedQuote = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignedTo = null;

    public function getId(): ?int { return $this->id; }
    public function getPortalUser(): ?PortalUser { return $this->portalUser; }
    public function setPortalUser(?PortalUser $v): static { $this->portalUser = $v; return $this; }
    public function getTransportMode(): ?string { return $this->transportMode; }
    public function setTransportMode(?string $v): static { $this->transportMode = $v; return $this; }
    public function getServiceType(): ?string { return $this->serviceType; }
    public function setServiceType(?string $v): static { $this->serviceType = $v; return $this; }
    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $v): static { $this->origin = $v; return $this; }
    public function getDestination(): ?string { return $this->destination; }
    public function setDestination(?string $v): static { $this->destination = $v; return $this; }
    public function getCargoDescription(): ?string { return $this->cargoDescription; }
    public function setCargoDescription(?string $v): static { $this->cargoDescription = $v; return $this; }
    public function getWeightKg(): ?string { return $this->weightKg; }
    public function setWeightKg(?string $v): static { $this->weightKg = $v; return $this; }
    public function getVolumeCbm(): ?string { return $this->volumeCbm; }
    public function setVolumeCbm(?string $v): static { $this->volumeCbm = $v; return $this; }
    public function getContainerType(): ?string { return $this->containerType; }
    public function setContainerType(?string $v): static { $this->containerType = $v; return $this; }
    public function getIncoterm(): ?string { return $this->incoterm; }
    public function setIncoterm(?string $v): static { $this->incoterm = $v; return $this; }
    public function getCargoReadyDate(): ?\DateTimeInterface { return $this->cargoReadyDate; }
    public function setCargoReadyDate(?\DateTimeInterface $v): static { $this->cargoReadyDate = $v; return $this; }
    public function getSpecialRequirements(): ?string { return $this->specialRequirements; }
    public function setSpecialRequirements(?string $v): static { $this->specialRequirements = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getLinkedQuote(): ?Quote { return $this->linkedQuote; }
    public function setLinkedQuote(?Quote $v): static { $this->linkedQuote = $v; return $this; }
    public function getAssignedTo(): ?User { return $this->assignedTo; }
    public function setAssignedTo(?User $v): static { $this->assignedTo = $v; return $this; }
}
