<?php
namespace App\Module\Integration\Entity;

use App\Module\Integration\Repository\IntegrationMessageRepository;
use App\Module\Operations\Entity\Shipment;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegrationMessageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class IntegrationMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8)]
    private string $direction = '';       // INBOUND / OUTBOUND

    #[ORM\Column(length: 16)]
    private string $protocol = '';        // REST / EDIFACT / XML / EMAIL / SFTP

    #[ORM\Column(length: 64)]
    private string $messageType = '';     // BOOKING / SI / CUSTOMS_DECL / TRACKING / RATE_CARD / STATUS

    #[ORM\Column(length: 32)]
    private string $partnerType = '';     // CARRIER / CUSTOMS / PORT / AGENT / AGGREGATOR

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $partnerName = null;

    #[ORM\ManyToOne(targetEntity: Shipment::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $messageRef = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $payload = '';         // raw message content (JSON / EDIFACT / XML)

    #[ORM\Column(length: 16)]
    private string $status = 'PENDING';   // PENDING / SENT / RECEIVED / ACK / REJECTED / FAILED

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $receivedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $ackAt = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $errorCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $retryCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getDirection(): string { return $this->direction; }
    public function setDirection(string $v): static { $this->direction = $v; return $this; }

    public function getProtocol(): string { return $this->protocol; }
    public function setProtocol(string $v): static { $this->protocol = $v; return $this; }

    public function getMessageType(): string { return $this->messageType; }
    public function setMessageType(string $v): static { $this->messageType = $v; return $this; }

    public function getPartnerType(): string { return $this->partnerType; }
    public function setPartnerType(string $v): static { $this->partnerType = $v; return $this; }

    public function getPartnerName(): ?string { return $this->partnerName; }
    public function setPartnerName(?string $v): static { $this->partnerName = $v; return $this; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $v): static { $this->shipment = $v; return $this; }

    public function getMessageRef(): ?string { return $this->messageRef; }
    public function setMessageRef(?string $v): static { $this->messageRef = $v; return $this; }

    public function getPayload(): string { return $this->payload; }
    public function setPayload(string $v): static { $this->payload = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }
    public function setSentAt(?\DateTimeInterface $v): static { $this->sentAt = $v; return $this; }

    public function getReceivedAt(): ?\DateTimeInterface { return $this->receivedAt; }
    public function setReceivedAt(?\DateTimeInterface $v): static { $this->receivedAt = $v; return $this; }

    public function getAckAt(): ?\DateTimeInterface { return $this->ackAt; }
    public function setAckAt(?\DateTimeInterface $v): static { $this->ackAt = $v; return $this; }

    public function getErrorCode(): ?string { return $this->errorCode; }
    public function setErrorCode(?string $v): static { $this->errorCode = $v; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }

    public function getRetryCount(): int { return $this->retryCount; }
    public function setRetryCount(int $v): static { $this->retryCount = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
