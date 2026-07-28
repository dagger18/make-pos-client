<?php
namespace App\Module\Carrier\Entity;

use App\Module\Operations\Entity\Shipment;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Carrier\Repository\TrackingRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TrackingRequest
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32)]
    private string $trackingType;

    #[ORM\Column(length: 64)]
    private string $trackingRef;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $carrierScac = null;

    #[ORM\Column(length: 16)]
    private string $status = 'ACTIVE';

    #[ORM\Column(nullable: true)]
    private ?int $masterJobId = null;

    #[ORM\Column(length: 64)]
    private string $webhookSecret;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastCheckedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastEventAt = null;

    #[ORM\Column]
    private int $errorCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    public function getId(): ?int { return $this->id; }
    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }
    public function getTrackingType(): string { return $this->trackingType; }
    public function setTrackingType(string $v): static { $this->trackingType = $v; return $this; }
    public function getTrackingRef(): string { return $this->trackingRef; }
    public function setTrackingRef(string $v): static { $this->trackingRef = $v; return $this; }
    public function getCarrierScac(): ?string { return $this->carrierScac; }
    public function setCarrierScac(?string $v): static { $this->carrierScac = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getMasterJobId(): ?int { return $this->masterJobId; }
    public function setMasterJobId(?int $v): static { $this->masterJobId = $v; return $this; }
    public function getWebhookSecret(): string { return $this->webhookSecret; }
    public function setWebhookSecret(string $v): static { $this->webhookSecret = $v; return $this; }
    public function getLastCheckedAt(): ?\DateTimeInterface { return $this->lastCheckedAt; }
    public function setLastCheckedAt(?\DateTimeInterface $v): static { $this->lastCheckedAt = $v; return $this; }
    public function getLastEventAt(): ?\DateTimeInterface { return $this->lastEventAt; }
    public function setLastEventAt(?\DateTimeInterface $v): static { $this->lastEventAt = $v; return $this; }
    public function getErrorCount(): int { return $this->errorCount; }
    public function setErrorCount(int $v): static { $this->errorCount = $v; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $v): static { $this->lastError = $v; return $this; }
}
