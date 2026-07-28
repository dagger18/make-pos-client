<?php
declare(strict_types=1);
namespace App\Module\Emissions\Entity;

use App\Module\Emissions\Repository\ShipmentEmissionRepository;
use App\Module\Operations\Entity\Shipment;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentEmissionRepository::class)]
#[ORM\Table(name: 'shipment_emission')]
#[ORM\Index(columns: ['shipment_id'], name: 'IDX_emission_shipment')]
class ShipmentEmission
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 8)]
    private string $transportMode;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EmissionFactor $emissionFactor = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $distanceKm;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 4)]
    private string $cargoWeightTonnes;

    #[ORM\Column(type: 'decimal', precision: 16, scale: 4)]
    private string $tonneKm;

    #[ORM\Column(type: 'decimal', precision: 16, scale: 4)]
    private string $co2eTtwKg;

    #[ORM\Column(type: 'decimal', precision: 16, scale: 4)]
    private string $co2eWtwKg;

    #[ORM\Column(length: 32)]
    private string $methodology;

    #[ORM\Column(type: 'boolean')]
    private bool $isEstimate = true;

    #[ORM\Column(type: 'smallint')]
    private int $legSequence = 1;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $legDescription = null;

    #[ORM\Column]
    private \DateTime $calculatedAt;

    #[ORM\Column(length: 32)]
    private string $calculatedBy = 'SYSTEM';

    public function getId(): ?int { return $this->id; }

    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $v): static { $this->shipment = $v; return $this; }

    public function getTransportMode(): string { return $this->transportMode; }
    public function setTransportMode(string $v): static { $this->transportMode = $v; return $this; }

    public function getEmissionFactor(): ?EmissionFactor { return $this->emissionFactor; }
    public function setEmissionFactor(?EmissionFactor $v): static { $this->emissionFactor = $v; return $this; }

    public function getDistanceKm(): string { return $this->distanceKm; }
    public function setDistanceKm(string $v): static { $this->distanceKm = $v; return $this; }

    public function getCargoWeightTonnes(): string { return $this->cargoWeightTonnes; }
    public function setCargoWeightTonnes(string $v): static { $this->cargoWeightTonnes = $v; return $this; }

    public function getTonneKm(): string { return $this->tonneKm; }
    public function setTonneKm(string $v): static { $this->tonneKm = $v; return $this; }

    public function getCo2eTtwKg(): string { return $this->co2eTtwKg; }
    public function setCo2eTtwKg(string $v): static { $this->co2eTtwKg = $v; return $this; }

    public function getCo2eWtwKg(): string { return $this->co2eWtwKg; }
    public function setCo2eWtwKg(string $v): static { $this->co2eWtwKg = $v; return $this; }

    public function getMethodology(): string { return $this->methodology; }
    public function setMethodology(string $v): static { $this->methodology = $v; return $this; }

    public function isEstimate(): bool { return $this->isEstimate; }
    public function setIsEstimate(bool $v): static { $this->isEstimate = $v; return $this; }

    public function getLegSequence(): int { return $this->legSequence; }
    public function setLegSequence(int $v): static { $this->legSequence = $v; return $this; }

    public function getLegDescription(): ?string { return $this->legDescription; }
    public function setLegDescription(?string $v): static { $this->legDescription = $v; return $this; }

    public function getCalculatedAt(): \DateTime { return $this->calculatedAt; }
    public function setCalculatedAt(\DateTime $v): static { $this->calculatedAt = $v; return $this; }

    public function getCalculatedBy(): string { return $this->calculatedBy; }
    public function setCalculatedBy(string $v): static { $this->calculatedBy = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'                 => $this->id,
            'shipmentId'         => $this->shipment->getId(),
            'shipmentCode'       => $this->shipment->getCode(),
            'transportMode'      => $this->transportMode,
            'emissionFactor'     => $this->emissionFactor
                ? ['id' => $this->emissionFactor->getId(), 'vehicleType' => $this->emissionFactor->getVehicleType(), 'sizeClass' => $this->emissionFactor->getSizeClass()]
                : null,
            'distanceKm'         => (float) $this->distanceKm,
            'cargoWeightTonnes'  => (float) $this->cargoWeightTonnes,
            'tonneKm'            => (float) $this->tonneKm,
            'co2eTtwKg'          => (float) $this->co2eTtwKg,
            'co2eWtwKg'          => (float) $this->co2eWtwKg,
            'methodology'        => $this->methodology,
            'isEstimate'         => $this->isEstimate,
            'legSequence'        => $this->legSequence,
            'legDescription'     => $this->legDescription,
            'calculatedAt'       => $this->calculatedAt->format('Y-m-d H:i:s'),
            'calculatedBy'       => $this->calculatedBy,
        ];
    }
}
