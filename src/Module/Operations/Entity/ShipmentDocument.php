<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\User;
use App\Module\Crm\Entity\Client;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Enum\DocType;
use App\Module\Operations\Repository\ShipmentDocumentRepository;

#[ORM\Entity(repositoryClass: ShipmentDocumentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ShipmentDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(length: 32, enumType: DocType::class)]
    private DocType $docType = DocType::Other;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $docReference = null;

    #[ORM\Column]
    private bool $isRequired = true;

    #[ORM\Column]
    private bool $isReceived = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $receivedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $receivedFrom = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Client $issuedBy = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $issueDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\ManyToOne(cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $media = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarks = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column]
    private bool $isCustomerAccessible = false;

    #[ORM\PrePersist]
    public function prePersist(): void { $this->createdAt = new \DateTime(); }

    #[ORM\PreUpdate]
    public function preUpdate(): void { $this->updatedAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $s): static { $this->shipment = $s; return $this; }

    public function getDocType(): DocType { return $this->docType; }
    public function setDocType(DocType $v): static { $this->docType = $v; return $this; }

    public function getDocReference(): ?string { return $this->docReference; }
    public function setDocReference(?string $v): static { $this->docReference = $v; return $this; }

    public function isRequired(): bool { return $this->isRequired; }
    public function setIsRequired(bool $v): static { $this->isRequired = $v; return $this; }

    public function isReceived(): bool { return $this->isReceived; }
    public function setIsReceived(bool $v): static { $this->isReceived = $v; return $this; }

    public function getReceivedAt(): ?\DateTimeInterface { return $this->receivedAt; }
    public function setReceivedAt(?\DateTimeInterface $v): static { $this->receivedAt = $v; return $this; }

    public function getReceivedFrom(): ?Client { return $this->receivedFrom; }
    public function setReceivedFrom(?Client $v): static { $this->receivedFrom = $v; return $this; }

    public function getIssuedBy(): ?Client { return $this->issuedBy; }
    public function setIssuedBy(?Client $v): static { $this->issuedBy = $v; return $this; }

    public function getIssueDate(): ?\DateTimeInterface { return $this->issueDate; }
    public function setIssueDate(?\DateTimeInterface $v): static { $this->issueDate = $v; return $this; }

    public function getExpiryDate(): ?\DateTimeInterface { return $this->expiryDate; }
    public function setExpiryDate(?\DateTimeInterface $v): static { $this->expiryDate = $v; return $this; }

    public function getMedia(): ?Media { return $this->media; }
    public function setMedia(?Media $v): static { $this->media = $v; return $this; }

    public function getRemarks(): ?string { return $this->remarks; }
    public function setRemarks(?string $v): static { $this->remarks = $v; return $this; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $v): static { $this->uploadedBy = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    public function isCustomerAccessible(): bool { return $this->isCustomerAccessible; }
    public function setIsCustomerAccessible(bool $v): static { $this->isCustomerAccessible = $v; return $this; }
}
