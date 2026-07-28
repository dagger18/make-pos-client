<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\SubEntity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\ArrivalNoticeRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: ArrivalNoticeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ArrivalNotice implements SubEntity
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Shipment $shipment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $issueDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $consigneeName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $consigneeAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $destinationChargesNote = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requiredDocuments = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $shipment): static { $this->shipment = $shipment; return $this; }

    public function getIssueDate(): ?\DateTimeInterface { return $this->issueDate; }
    public function setIssueDate(?\DateTimeInterface $issueDate): static { $this->issueDate = $issueDate; return $this; }

    public function getConsigneeName(): ?string { return $this->consigneeName; }
    public function setConsigneeName(?string $consigneeName): static { $this->consigneeName = $consigneeName; return $this; }

    public function getConsigneeAddress(): ?string { return $this->consigneeAddress; }
    public function setConsigneeAddress(?string $consigneeAddress): static { $this->consigneeAddress = $consigneeAddress; return $this; }

    public function getDestinationChargesNote(): ?string { return $this->destinationChargesNote; }
    public function setDestinationChargesNote(?string $destinationChargesNote): static { $this->destinationChargesNote = $destinationChargesNote; return $this; }

    public function getRequiredDocuments(): ?array { return $this->requiredDocuments; }
    public function setRequiredDocuments(?array $requiredDocuments): static { $this->requiredDocuments = $requiredDocuments; return $this; }

    public function getNote(): ?string { return $this->note; }
    public function setNote(?string $note): static { $this->note = $note; return $this; }
}
