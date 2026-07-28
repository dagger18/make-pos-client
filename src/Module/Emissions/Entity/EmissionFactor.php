<?php
declare(strict_types=1);
namespace App\Module\Emissions\Entity;

use App\Module\Emissions\Repository\EmissionFactorRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmissionFactorRepository::class)]
#[ORM\Table(name: 'emission_factor')]
class EmissionFactor
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $transportMode;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vehicleType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $fuelType = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $sizeClass = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, nullable: true)]
    private ?string $loadFactor = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 6)]
    private string $efTtw;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 6)]
    private string $efWtw;

    #[ORM\Column(length: 32)]
    private string $methodology;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $effectiveFrom;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    #[ORM\Column(length: 128)]
    private string $source;

    #[ORM\Column]
    private \DateTime $createdAt;

    public function getId(): ?int { return $this->id; }

    public function getTransportMode(): string { return $this->transportMode; }
    public function setTransportMode(string $v): static { $this->transportMode = $v; return $this; }

    public function getVehicleType(): ?string { return $this->vehicleType; }
    public function setVehicleType(?string $v): static { $this->vehicleType = $v; return $this; }

    public function getFuelType(): ?string { return $this->fuelType; }
    public function setFuelType(?string $v): static { $this->fuelType = $v; return $this; }

    public function getSizeClass(): ?string { return $this->sizeClass; }
    public function setSizeClass(?string $v): static { $this->sizeClass = $v; return $this; }

    public function getLoadFactor(): ?string { return $this->loadFactor; }
    public function setLoadFactor(?string $v): static { $this->loadFactor = $v; return $this; }

    public function getEfTtw(): string { return $this->efTtw; }
    public function setEfTtw(string $v): static { $this->efTtw = $v; return $this; }

    public function getEfWtw(): string { return $this->efWtw; }
    public function setEfWtw(string $v): static { $this->efWtw = $v; return $this; }

    public function getMethodology(): string { return $this->methodology; }
    public function setMethodology(string $v): static { $this->methodology = $v; return $this; }

    public function getEffectiveFrom(): \DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(\DateTimeInterface $v): static { $this->effectiveFrom = $v; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $v): static { $this->effectiveTo = $v; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static { $this->source = $v; return $this; }

    public function getCreatedAt(): \DateTime { return $this->createdAt; }
    public function setCreatedAt(\DateTime $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'transportMode' => $this->transportMode,
            'vehicleType'   => $this->vehicleType,
            'fuelType'      => $this->fuelType,
            'sizeClass'     => $this->sizeClass,
            'loadFactor'    => $this->loadFactor !== null ? (float) $this->loadFactor : null,
            'efTtw'         => (float) $this->efTtw,
            'efWtw'         => (float) $this->efWtw,
            'methodology'   => $this->methodology,
            'effectiveFrom' => $this->effectiveFrom->format('Y-m-d'),
            'effectiveTo'   => $this->effectiveTo?->format('Y-m-d'),
            'source'        => $this->source,
            'createdAt'     => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
