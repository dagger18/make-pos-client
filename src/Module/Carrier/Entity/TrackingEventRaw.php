<?php
namespace App\Module\Carrier\Entity;

use App\Module\Carrier\Repository\TrackingEventRawRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackingEventRawRepository::class)]
#[ORM\HasLifecycleCallbacks]
class TrackingEventRaw
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TrackingRequest $trackingRequest = null;

    #[ORM\Column(length: 32)]
    private string $source;

    #[ORM\Column(type: Types::JSON)]
    private array $rawPayload = [];

    #[ORM\Column]
    private bool $isProcessed = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $processedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $receivedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->receivedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getTrackingRequest(): ?TrackingRequest { return $this->trackingRequest; }
    public function setTrackingRequest(?TrackingRequest $v): static { $this->trackingRequest = $v; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $v): static { $this->source = $v; return $this; }
    public function getRawPayload(): array { return $this->rawPayload; }
    public function setRawPayload(array $v): static { $this->rawPayload = $v; return $this; }
    public function isProcessed(): bool { return $this->isProcessed; }
    public function setIsProcessed(bool $v): static { $this->isProcessed = $v; return $this; }
    public function getProcessedAt(): ?\DateTimeInterface { return $this->processedAt; }
    public function setProcessedAt(?\DateTimeInterface $v): static { $this->processedAt = $v; return $this; }
    public function getError(): ?string { return $this->error; }
    public function setError(?string $v): static { $this->error = $v; return $this; }
    public function getReceivedAt(): ?\DateTimeInterface { return $this->receivedAt; }
}
