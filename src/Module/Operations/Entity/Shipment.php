<?php

namespace App\Module\Operations\Entity;

use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\Money;
use App\Module\Core\Entity\User;
use App\Module\Quote\Entity\Quote;
use App\Module\Finance\Entity\EbitNote;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Operations\Repository\ShipmentRepository;
use App\Misc\Attribute\NullableEmbeddable;
use Doctrine\Common\Collections\Collection;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use App\Misc\Attribute\ContainsNullableEmbeddable;
use App\Misc\Attribute\MediaProperty;
use App\Module\Operations\Enum\ShipmentStatus;
use App\Module\Operations\Enum\SubStatus;

#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ContainsNullableEmbeddable]
class Shipment
{
    use EntityDateTimeAbleTrait;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'shipment', cascade: ['persist', 'remove', 'detach'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quote $quote = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 255, enumType: ShipmentStatus::class)]
    private ?ShipmentStatus $status;

    #[ORM\ManyToMany(targetEntity: Media::class, cascade: ['persist', 'detach'])]
    #[MediaProperty]
    private Collection $documents;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedDate = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referenceId = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove', 'detach'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $revenue;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $cost;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $profit;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $pobo;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $cobo;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $accountManager = null;

    #[ORM\OneToMany(mappedBy: 'shipment', targetEntity: EbitNote::class, orphanRemoval: true, cascade: ['persist', 'detach'])]
    private Collection $ebitNotes;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $masterBill = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $houseBill = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToMany(targetEntity: User::class)]
    private Collection $assignedUsers;

    #[ORM\OneToOne(cascade: ['persist', 'remove', 'detach'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Instruction $instruction = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $commissionProvider;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $commissionClient;

    #[ORM\Column(length: 32, nullable: true, enumType: SubStatus::class)]
    private ?SubStatus $subStatus = null;

    #[ORM\Column]
    private bool $isOnHold = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $holdReason = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $salesRep = null;

    #[ORM\Column(nullable: true)]
    private ?int $consolId = null;

    #[ORM\Column(nullable: true)]
    private ?int $parentJobId = null;

    public bool $noLog = false;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->ebitNotes = new ArrayCollection();
        $this->assignedUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(Quote $quote): static
    {
        $this->quote = $quote;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getStatus(): ?ShipmentStatus
    {
        return $this->status;
    }

    public function setStatus(ShipmentStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Media $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
        }

        return $this;
    }

    public function removeDocument(Media $document): static
    {
        $this->documents->removeElement($document);

        return $this;
    }

    public function getCompletedDate(): ?\DateTimeInterface
    {
        return $this->completedDate;
    }

    public function setCompletedDate(?\DateTimeInterface $completedDate): static
    {
        $this->completedDate = $completedDate;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getReferenceId(): ?string
    {
        return $this->referenceId;
    }

    public function setReferenceId(?string $referenceId): static
    {
        $this->referenceId = $referenceId;

        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(Booking $booking): static
    {
        $this->booking = $booking;

        return $this;
    }

    public function getRevenue(): ?Money
    {
        return $this->revenue;
    }

    public function setRevenue(?Money $revenue): static
    {
        $this->revenue = $revenue;

        return $this;
    }

    public function getCost(): ?Money
    {
        return $this->cost;
    }

    public function setCost(?Money $cost): static
    {
        $this->cost = $cost;

        return $this;
    }

    public function getProfit(): ?Money
    {
        return $this->profit;
    }

    public function setProfit(?Money $profit): static
    {
        $this->profit = $profit;

        return $this;
    }

    public function getPobo(): ?Money
    {
        return $this->pobo;
    }

    public function setPobo(?Money $pobo): static
    {
        $this->pobo = $pobo;

        return $this;
    }

    public function getCobo(): ?Money
    {
        return $this->cobo;
    }

    public function setCobo(?Money $cobo): static
    {
        $this->cobo = $cobo;

        return $this;
    }

    public function getAccountManager(): ?User
    {
        return $this->accountManager;
    }

    public function setAccountManager(?User $accountManager): static
    {
        $this->accountManager = $accountManager;

        return $this;
    }

    /**
     * @return Collection<int, EbitNote>
     */
    public function getEbitNotes(): Collection
    {
        return $this->ebitNotes;
    }

    public function addEbitNote(EbitNote $ebitNote): static
    {
        if (!$this->ebitNotes->contains($ebitNote)) {
            $this->ebitNotes->add($ebitNote);
            $ebitNote->setShipment($this);
        }

        return $this;
    }

    public function removeEbitNote(EbitNote $ebitNote): static
    {
        if ($this->ebitNotes->removeElement($ebitNote)) {
            // set the owning side to null (unless already changed)
            if ($ebitNote->getShipment() === $this) {
                $ebitNote->setShipment(null);
            }
        }

        return $this;
    }

    public function getMasterBill(): ?string
    {
        return $this->masterBill;
    }

    public function setMasterBill(?string $masterBill): static
    {
        $this->masterBill = $masterBill;

        return $this;
    }

    public function getHouseBill(): ?string
    {
        return $this->houseBill;
    }

    public function setHouseBill(?string $houseBill): static
    {
        $this->houseBill = $houseBill;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getAssignedUsers(): Collection
    {
        return $this->assignedUsers;
    }

    public function addAssignedUser(User $assignedUser): static
    {
        if (!$this->assignedUsers->contains($assignedUser)) {
            $this->assignedUsers->add($assignedUser);
        }

        return $this;
    }

    public function removeAssignedUser(User $assignedUser): static
    {
        $this->assignedUsers->removeElement($assignedUser);

        return $this;
    }

    public function getInstruction(): ?Instruction
    {
        return $this->instruction;
    }

    public function setInstruction(Instruction $instruction): static
    {
        $this->instruction = $instruction;

        return $this;
    }

    public function getCommissionProvider(): ?Money
    {
        return $this->commissionProvider;
    }

    public function setCommissionProvider(?Money $commissionProvider): static
    {
        $this->commissionProvider = $commissionProvider;

        return $this;
    }

    public function getCommissionClient(): ?Money
    {
        return $this->commissionClient;
    }

    public function setCommissionClient(?Money $commissionClient): static
    {
        $this->commissionClient = $commissionClient;

        return $this;
    }

    public function getSubStatus(): ?SubStatus
    {
        return $this->subStatus;
    }

    public function setSubStatus(?SubStatus $subStatus): static
    {
        $this->subStatus = $subStatus;

        return $this;
    }

    public function isOnHold(): bool
    {
        return $this->isOnHold;
    }

    public function setIsOnHold(bool $isOnHold): static
    {
        $this->isOnHold = $isOnHold;

        return $this;
    }

    public function getHoldReason(): ?string
    {
        return $this->holdReason;
    }

    public function setHoldReason(?string $holdReason): static
    {
        $this->holdReason = $holdReason;

        return $this;
    }

    public function getSalesRep(): ?User
    {
        return $this->salesRep;
    }

    public function setSalesRep(?User $salesRep): static
    {
        $this->salesRep = $salesRep;

        return $this;
    }

    public function getConsolId(): ?int { return $this->consolId; }
    public function setConsolId(?int $id): static { $this->consolId = $id; return $this; }

    public function getParentJobId(): ?int { return $this->parentJobId; }
    public function setParentJobId(?int $id): static { $this->parentJobId = $id; return $this; }

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $accountingClosedAt = null;

    public function getAccountingClosedAt(): ?\DateTimeInterface { return $this->accountingClosedAt; }
    public function setAccountingClosedAt(?\DateTimeInterface $d): static { $this->accountingClosedAt = $d; return $this; }
}
