<?php
declare(strict_types=1);
namespace App\Module\Carrier\Entity;

use App\Module\Core\Entity\User;

use App\Module\Operations\Entity\Shipment;

use App\Module\Carrier\Repository\VesselRollRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VesselRollRepository::class)]
class VesselRoll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Shipment $shipment;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $originalSailingRef = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $originalEtd = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $newSailingRef = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $newEtd = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $rolledBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $rolledAt;

    public function getId(): ?int { return $this->id; }
    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $shipment): static { $this->shipment = $shipment; return $this; }
    public function getOriginalSailingRef(): ?string { return $this->originalSailingRef; }
    public function setOriginalSailingRef(?string $ref): static { $this->originalSailingRef = $ref; return $this; }
    public function getOriginalEtd(): ?\DateTimeImmutable { return $this->originalEtd; }
    public function setOriginalEtd(?\DateTimeImmutable $dt): static { $this->originalEtd = $dt; return $this; }
    public function getNewSailingRef(): ?string { return $this->newSailingRef; }
    public function setNewSailingRef(?string $ref): static { $this->newSailingRef = $ref; return $this; }
    public function getNewEtd(): ?\DateTimeImmutable { return $this->newEtd; }
    public function setNewEtd(?\DateTimeImmutable $dt): static { $this->newEtd = $dt; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }
    public function getNotifiedAt(): ?\DateTimeImmutable { return $this->notifiedAt; }
    public function setNotifiedAt(?\DateTimeImmutable $dt): static { $this->notifiedAt = $dt; return $this; }
    public function getRolledBy(): ?User { return $this->rolledBy; }
    public function setRolledBy(?User $user): static { $this->rolledBy = $user; return $this; }
    public function getRolledAt(): \DateTimeImmutable { return $this->rolledAt; }
    public function setRolledAt(\DateTimeImmutable $dt): static { $this->rolledAt = $dt; return $this; }
}
