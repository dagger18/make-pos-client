<?php
namespace App\Module\Carrier\Entity;

use App\Module\Quote\Entity\FreeTimeAgreement;
use App\Module\Operations\Entity\Shipment;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Module\Carrier\Repository\ContainerDdTrackingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContainerDdTrackingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ContainerDdTracking
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
    private string $containerNumber = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?FreeTimeAgreement $freeTimeAgreement = null;

    #[ORM\Column(length: 16)]
    private string $ddType = 'DETENTION';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $freeStartDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $freeEndDate = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $freeDays = 0;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $actualReturnDate = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $daysUsed = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $chargeableDays = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private string $accruedAmount = '0';

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column]
    private bool $isFinal = false;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastAccrualDate = null;

    #[ORM\Column]
    private bool $isInvoiced = false;

    #[ORM\Column]
    private bool $isDisputed = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $disputeReason = null;

    public function getId(): ?int { return $this->id; }

    public function getShipment(): ?Shipment { return $this->shipment; }
    public function setShipment(?Shipment $shipment): static { $this->shipment = $shipment; return $this; }

    public function getContainerNumber(): string { return $this->containerNumber; }
    public function setContainerNumber(string $containerNumber): static { $this->containerNumber = $containerNumber; return $this; }

    public function getFreeTimeAgreement(): ?FreeTimeAgreement { return $this->freeTimeAgreement; }
    public function setFreeTimeAgreement(?FreeTimeAgreement $fta): static { $this->freeTimeAgreement = $fta; return $this; }

    public function getDdType(): string { return $this->ddType; }
    public function setDdType(string $ddType): static { $this->ddType = $ddType; return $this; }

    public function getFreeStartDate(): ?\DateTimeInterface { return $this->freeStartDate; }
    public function setFreeStartDate(?\DateTimeInterface $freeStartDate): static { $this->freeStartDate = $freeStartDate; return $this; }

    public function getFreeEndDate(): ?\DateTimeInterface { return $this->freeEndDate; }
    public function setFreeEndDate(?\DateTimeInterface $freeEndDate): static { $this->freeEndDate = $freeEndDate; return $this; }

    public function getFreeDays(): int { return $this->freeDays; }
    public function setFreeDays(int $freeDays): static { $this->freeDays = $freeDays; return $this; }

    public function getActualReturnDate(): ?\DateTimeInterface { return $this->actualReturnDate; }
    public function setActualReturnDate(?\DateTimeInterface $actualReturnDate): static { $this->actualReturnDate = $actualReturnDate; return $this; }

    public function getDaysUsed(): ?int { return $this->daysUsed; }
    public function setDaysUsed(?int $daysUsed): static { $this->daysUsed = $daysUsed; return $this; }

    public function getChargeableDays(): ?int { return $this->chargeableDays; }
    public function setChargeableDays(?int $chargeableDays): static { $this->chargeableDays = $chargeableDays; return $this; }

    public function getAccruedAmount(): string { return $this->accruedAmount; }
    public function setAccruedAmount(string $accruedAmount): static { $this->accruedAmount = $accruedAmount; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }

    public function isFinal(): bool { return $this->isFinal; }
    public function setIsFinal(bool $isFinal): static { $this->isFinal = $isFinal; return $this; }

    public function getLastAccrualDate(): ?\DateTimeInterface { return $this->lastAccrualDate; }
    public function setLastAccrualDate(?\DateTimeInterface $lastAccrualDate): static { $this->lastAccrualDate = $lastAccrualDate; return $this; }

    public function isInvoiced(): bool { return $this->isInvoiced; }
    public function setIsInvoiced(bool $isInvoiced): static { $this->isInvoiced = $isInvoiced; return $this; }

    public function isDisputed(): bool { return $this->isDisputed; }
    public function setIsDisputed(bool $isDisputed): static { $this->isDisputed = $isDisputed; return $this; }

    public function getDisputeReason(): ?string { return $this->disputeReason; }
    public function setDisputeReason(?string $disputeReason): static { $this->disputeReason = $disputeReason; return $this; }

    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'shipmentId'          => $this->shipment?->getId(),
            'containerNumber'     => $this->containerNumber,
            'freeTimeAgreement'   => $this->freeTimeAgreement?->toArray(),
            'ddType'              => $this->ddType,
            'freeStartDate'       => $this->freeStartDate?->format('Y-m-d'),
            'freeEndDate'         => $this->freeEndDate?->format('Y-m-d'),
            'freeDays'            => $this->freeDays,
            'actualReturnDate'    => $this->actualReturnDate?->format('Y-m-d'),
            'daysUsed'            => $this->daysUsed,
            'chargeableDays'      => $this->chargeableDays,
            'accruedAmount'       => (float) $this->accruedAmount,
            'currency'            => $this->currency,
            'isFinal'             => $this->isFinal,
            'lastAccrualDate'     => $this->lastAccrualDate?->format('Y-m-d'),
            'isInvoiced'          => $this->isInvoiced,
            'isDisputed'          => $this->isDisputed,
            'disputeReason'       => $this->disputeReason,
            'createdDate'         => $this->createdDate?->format('Y-m-d H:i:s'),
        ];
    }
}
