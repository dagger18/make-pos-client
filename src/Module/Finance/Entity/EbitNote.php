<?php

namespace App\Module\Finance\Entity;

use App\Module\Finance\Enum\CreditNoteReason;
use App\Module\Finance\Enum\VarianceStatus;

use App\Module\Core\Entity\User;
use App\Module\Operations\Entity\Shipment;
use App\Module\Crm\Entity\Partner;

use App\Module\Core\Entity\Media;
use App\Module\Core\Entity\Money;
use App\Module\Core\Entity\SubEntity;

use App\Module\Core\Enum\EntityType;
use Doctrine\DBAL\Types\Types;
use App\Module\Finance\Enum\EbitNoteType;
use Doctrine\ORM\Mapping as ORM;
use App\Module\Finance\Enum\EbitNoteStatus;
use App\Module\Finance\Repository\EbitNoteRepository;
use App\Misc\Attribute\NullableEmbeddable;
use Doctrine\Common\Collections\Collection;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use App\Misc\Attribute\ContainsNullableEmbeddable;
use App\Misc\Attribute\MediaProperty;

#[ORM\Entity(repositoryClass: EbitNoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ContainsNullableEmbeddable]
class EbitNote implements SubEntity
{
    use EntityDateTimeAbleTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $code = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(length: 255)]
    private ?string $currency = null;

    #[ORM\ManyToOne]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $containerNo = [];

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $sealNo = [];

    #[ORM\Column(nullable: true)]
    private ?bool $showOneCurrency = null;

    #[ORM\ManyToMany(targetEntity: Media::class)]
    #[MediaProperty]
    private Collection $documents;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $noteDate = null;

    #[ORM\Column]
    private array $exchangeRates = [];

    #[ORM\Column(length: 255, enumType: EbitNoteType::class)]
    private ?EbitNoteType $type = null;

    #[ORM\ManyToOne]
    private ?Partner $collectFrom = null;

    #[ORM\ManyToOne]
    private ?Partner $payTo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $createdBy = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $amountNoTax;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $amount;

    #[ORM\OneToMany(mappedBy: 'ebitNote', targetEntity: ChargeItem::class, orphanRemoval: true, cascade: ['persist'])]
    private Collection $chargeItems;

    #[ORM\Column(length: 255, nullable: true, enumType: EntityType::class)]
    private ?EntityType $targetType;

    #[ORM\ManyToOne]
    private ?InvoiceInfo $invoiceInfo = null;

    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $tax;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $moneyScale = null;

    #[ORM\Column(length: 255, enumType: EbitNoteStatus::class)]
    private ?EbitNoteStatus $status;

    #[ORM\ManyToOne(inversedBy: 'ebitNotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shipment $shipment = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    private ?self $parentNote = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pic = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $amountInWords = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codeReference = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?float $fxGainLoss = null;

    #[ORM\Column(length: 16, nullable: true, enumType: VarianceStatus::class)]
    private ?VarianceStatus $varianceStatus = null;

    #[ORM\Column(length: 32, nullable: true, enumType: CreditNoteReason::class)]
    private ?CreditNoteReason $creditNoteReason = null;

    #[ORM\Column]
    private bool $isLocked = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vendorRef = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 4, nullable: true)]
    private ?float $withholdingTaxRate = null;

    #[ORM\Column(type: 'decimal', precision: 20, scale: 6, nullable: true)]
    private ?float $withholdingTaxAmount = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $withholdingTaxRef = null;

    public function __construct()
    {
        $this->documents = new ArrayCollection();
        $this->chargeItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(\DateTimeInterface $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getContainerNo(): array
    {
        return $this->containerNo;
    }

    public function setContainerNo(?array $containerNo): static
    {
        $this->containerNo = $containerNo;

        return $this;
    }

    public function getSealNo(): array
    {
        return $this->sealNo;
    }

    public function setSealNo(?array $sealNo): static
    {
        $this->sealNo = $sealNo;

        return $this;
    }

    public function isShowOneCurrency(): ?bool
    {
        return $this->showOneCurrency;
    }

    public function setShowOneCurrency(?bool $showOneCurrency): static
    {
        $this->showOneCurrency = $showOneCurrency;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getNoteDate(): ?\DateTimeInterface
    {
        return $this->noteDate;
    }

    public function setNoteDate(?\DateTimeInterface $noteDate): static
    {
        $this->noteDate = $noteDate;

        return $this;
    }

    public function getExchangeRates(): array
    {
        return $this->exchangeRates;
    }

    public function setExchangeRates(array $exchangeRates): static
    {
        $this->exchangeRates = $exchangeRates;

        return $this;
    }

    public function getType(): ?EbitNoteType
    {
        return $this->type;
    }

    public function setType(EbitNoteType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCollectFrom(): ?Partner
    {
        return $this->collectFrom;
    }

    public function setCollectFrom(?Partner $collectFrom): static
    {
        $this->collectFrom = $collectFrom;

        return $this;
    }

    public function getPayTo(): ?Partner
    {
        return $this->payTo;
    }

    public function setPayTo(?Partner $payTo): static
    {
        $this->payTo = $payTo;

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

    public function getAmountNoTax(): ?Money
    {
        return $this->amountNoTax;
    }

    public function setAmountNoTax(?Money $amountNoTax): static
    {
        $this->amountNoTax = $amountNoTax;

        return $this;
    }

    public function getAmount(): ?Money
    {
        return $this->amount;
    }

    public function setAmount(?Money $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @return Collection<int, ChargeItem>
     */
    public function getChargeItems(): Collection
    {
        return $this->chargeItems;
    }

    public function addChargeItem(ChargeItem $chargeItem): static
    {
        if (!$this->chargeItems->contains($chargeItem)) {
            $this->chargeItems->add($chargeItem);
            $chargeItem->setEbitNote($this);
        }

        return $this;
    }

    public function removeChargeItem(ChargeItem $chargeItem): static
    {
        if ($this->chargeItems->removeElement($chargeItem)) {
            // set the owning side to null (unless already changed)
            if ($chargeItem->getEbitNote() === $this) {
                $chargeItem->setEbitNote(null);
            }
        }

        return $this;
    }

    public function getTargetType(): ?EntityType
    {
        return $this->targetType;
    }

    public function setTargetType(?EntityType $targetType): static
    {
        $this->targetType = $targetType;

        return $this;
    }

    public function getInvoiceInfo(): ?InvoiceInfo
    {
        return $this->invoiceInfo;
    }

    public function setInvoiceInfo(?InvoiceInfo $invoiceInfo): static
    {
        $this->invoiceInfo = $invoiceInfo;

        return $this;
    }

    public function getTax(): ?Money
    {
        return $this->tax;
    }

    public function setTax(?Money $tax): static
    {
        $this->tax = $tax;

        return $this;
    }

    public function getMoneyScale(): ?int
    {
        return $this->moneyScale;
    }

    public function setMoneyScale(int $moneyScale): static
    {
        $this->moneyScale = $moneyScale;

        return $this;
    }

    public function getStatus(): ?EbitNoteStatus
    {
        return $this->status;
    }

    public function setStatus(EbitNoteStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getShipment(): ?Shipment
    {
        return $this->shipment;
    }

    public function setShipment(?Shipment $shipment): static
    {
        $this->shipment = $shipment;

        return $this;
    }

    public function getParentNote(): ?self
    {
        return $this->parentNote;
    }

    public function setParentNote(?self $parentNote): static
    {
        $this->parentNote = $parentNote;

        return $this;
    }

    public function getPic(): ?string
    {
        return $this->pic;
    }

    public function setPic(?string $pic): static
    {
        $this->pic = $pic;

        return $this;
    }

    public function getAmountInWords(): ?string
    {
        return $this->amountInWords;
    }

    public function setAmountInWords(?string $amountInWords): static
    {
        $this->amountInWords = $amountInWords;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCodeReference(): ?string
    {
        return $this->codeReference;
    }

    public function setCodeReference(?string $codeReference): static
    {
        $this->codeReference = $codeReference;

        return $this;
    }

    public function getFxGainLoss(): ?float { return $this->fxGainLoss; }
    public function setFxGainLoss(?float $v): static { $this->fxGainLoss = $v; return $this; }

    public function getVarianceStatus(): ?VarianceStatus { return $this->varianceStatus; }
    public function setVarianceStatus(?VarianceStatus $v): static { $this->varianceStatus = $v; return $this; }

    public function getCreditNoteReason(): ?CreditNoteReason { return $this->creditNoteReason; }
    public function setCreditNoteReason(?CreditNoteReason $v): static { $this->creditNoteReason = $v; return $this; }

    public function isLocked(): bool { return $this->isLocked; }
    public function setIsLocked(bool $v): static { $this->isLocked = $v; return $this; }

    public function getVendorRef(): ?string { return $this->vendorRef; }
    public function setVendorRef(?string $v): static { $this->vendorRef = $v; return $this; }

    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $u): static { $this->approvedBy = $u; return $this; }

    public function getApprovedAt(): ?\DateTimeInterface { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeInterface $d): static { $this->approvedAt = $d; return $this; }
    public function getWithholdingTaxRate(): ?float { return $this->withholdingTaxRate !== null ? (float) $this->withholdingTaxRate : null; }
    public function setWithholdingTaxRate(?float $v): static { $this->withholdingTaxRate = $v; return $this; }
    public function getWithholdingTaxAmount(): ?float { return $this->withholdingTaxAmount !== null ? (float) $this->withholdingTaxAmount : null; }
    public function setWithholdingTaxAmount(?float $v): static { $this->withholdingTaxAmount = $v; return $this; }
    public function getWithholdingTaxRef(): ?string { return $this->withholdingTaxRef; }
    public function setWithholdingTaxRef(?string $v): static { $this->withholdingTaxRef = $v; return $this; }
}
