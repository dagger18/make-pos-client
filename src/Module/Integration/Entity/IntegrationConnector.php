<?php
namespace App\Module\Integration\Entity;

use App\Module\Integration\Repository\IntegrationConnectorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IntegrationConnectorRepository::class)]
#[ORM\HasLifecycleCallbacks]
class IntegrationConnector
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $connectorType = '';   // CARRIER / CUSTOMS / PORT / AGENT / AGGREGATOR

    #[ORM\Column(length: 128)]
    private string $partnerName = '';

    #[ORM\Column(length: 16)]
    private string $protocol = '';        // REST / EDIFACT / XML / SFTP

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $baseUrl = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $authType = null;     // API_KEY / OAUTH2 / BASIC / CERTIFICATE

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $credentialsRef = null;   // secrets manager key name — never plaintext

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::JSON)]
    private array $capabilities = [];     // ["BOOKING", "SI", "TRACKING", "SCHEDULE", "RATE"]

    #[ORM\Column]
    private bool $testMode = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastPingAt = null;

    #[ORM\Column(length: 16, nullable: true)]
    private ?string $lastPingStatus = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getConnectorType(): string { return $this->connectorType; }
    public function setConnectorType(string $v): static { $this->connectorType = $v; return $this; }

    public function getPartnerName(): string { return $this->partnerName; }
    public function setPartnerName(string $v): static { $this->partnerName = $v; return $this; }

    public function getProtocol(): string { return $this->protocol; }
    public function setProtocol(string $v): static { $this->protocol = $v; return $this; }

    public function getBaseUrl(): ?string { return $this->baseUrl; }
    public function setBaseUrl(?string $v): static { $this->baseUrl = $v; return $this; }

    public function getAuthType(): ?string { return $this->authType; }
    public function setAuthType(?string $v): static { $this->authType = $v; return $this; }

    public function getCredentialsRef(): ?string { return $this->credentialsRef; }
    public function setCredentialsRef(?string $v): static { $this->credentialsRef = $v; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }

    public function getCapabilities(): array { return $this->capabilities; }
    public function setCapabilities(array $v): static { $this->capabilities = $v; return $this; }

    public function isTestMode(): bool { return $this->testMode; }
    public function setTestMode(bool $v): static { $this->testMode = $v; return $this; }

    public function getLastPingAt(): ?\DateTimeInterface { return $this->lastPingAt; }
    public function setLastPingAt(?\DateTimeInterface $v): static { $this->lastPingAt = $v; return $this; }

    public function getLastPingStatus(): ?string { return $this->lastPingStatus; }
    public function setLastPingStatus(?string $v): static { $this->lastPingStatus = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
