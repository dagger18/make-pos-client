<?php
declare(strict_types=1);
namespace App\Module\Emissions\Entity;

use App\Module\Emissions\Repository\SeaDistanceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeaDistanceRepository::class)]
#[ORM\Table(name: 'sea_distance')]
#[ORM\UniqueConstraint(name: 'UQ_sea_distance_pair', columns: ['pol_code', 'pod_code'])]
class SeaDistance
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private string $polCode;

    #[ORM\Column(length: 10)]
    private string $podCode;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $distanceKm;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $viaCanal = null;

    #[ORM\Column(length: 32)]
    private string $source = 'SEAROUTES';

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int { return $this->id; }

    public function getPolCode(): string { return $this->polCode; }
    public function setPolCode(string $v): static { $this->polCode = strtoupper($v); return $this; }

    public function getPodCode(): string { return $this->podCode; }
    public function setPodCode(string $v): static { $this->podCode = strtoupper($v); return $this; }

    public function getDistanceKm(): string { return $this->distanceKm; }
    public function setDistanceKm(string $v): static { $this->distanceKm = $v; return $this; }

    public function getViaCanal(): ?string { return $this->viaCanal; }
    public function setViaCanal(?string $v): static { $this->viaCanal = $v; return $this; }

    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static { $this->source = $v; return $this; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeInterface $v): static { $this->updatedAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'polCode'     => $this->polCode,
            'podCode'     => $this->podCode,
            'distanceKm'  => (float) $this->distanceKm,
            'viaCanal'    => $this->viaCanal,
            'source'      => $this->source,
            'updatedAt'   => $this->updatedAt?->format('Y-m-d'),
        ];
    }
}
