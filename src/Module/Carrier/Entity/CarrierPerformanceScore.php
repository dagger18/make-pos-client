<?php
namespace App\Module\Carrier\Entity;

use App\Module\Carrier\Repository\CarrierPerformanceScoreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarrierPerformanceScoreRepository::class)]
#[ORM\UniqueConstraint(name: 'UNQ_cps_period', columns: ['carrier_id', 'period_year', 'period_month', 'transport_mode'])]
class CarrierPerformanceScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Provider $carrier;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $periodYear;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $periodMonth;

    #[ORM\Column(length: 8)]
    private string $transportMode;

    // Raw metrics
    #[ORM\Column] private int $sailingsTotal = 0;
    #[ORM\Column] private int $sailingsOnTimeDep = 0;
    #[ORM\Column] private int $sailingsOnTimeArr = 0;
    #[ORM\Column] private int $sailingsCancelled = 0;
    #[ORM\Column] private int $bookingsTotal = 0;
    #[ORM\Column] private int $bookingsConfirmed = 0;
    #[ORM\Column] private int $bookingsRolled = 0;
    #[ORM\Column] private int $apBillsTotal = 0;
    #[ORM\Column] private int $apBillsWithinTolerance = 0;
    #[ORM\Column] private int $cargoClaimsCount = 0;
    #[ORM\Column] private int $shipmentsTotal = 0;

    // Calculated rates (null = no data for this dimension)
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?float $onTimeDepPct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?float $onTimeArrPct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?float $scheduleReliabilityPct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?float $bookingAcceptancePct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?float $rateConsistencyPct = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 3, nullable: true)]
    private ?float $claimsPer100 = null;

    // Composite result
    #[ORM\Column(type: Types::DECIMAL, precision: 4, scale: 2, nullable: true)]
    private ?float $compositeScore = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $scoreBand = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $calculatedAt;

    public function getId(): ?int { return $this->id; }
    public function getCarrier(): Provider { return $this->carrier; }
    public function setCarrier(Provider $v): static { $this->carrier = $v; return $this; }
    public function getPeriodYear(): int { return $this->periodYear; }
    public function setPeriodYear(int $v): static { $this->periodYear = $v; return $this; }
    public function getPeriodMonth(): int { return $this->periodMonth; }
    public function setPeriodMonth(int $v): static { $this->periodMonth = $v; return $this; }
    public function getTransportMode(): string { return $this->transportMode; }
    public function setTransportMode(string $v): static { $this->transportMode = $v; return $this; }
    public function getSailingsTotal(): int { return $this->sailingsTotal; }
    public function setSailingsTotal(int $v): static { $this->sailingsTotal = $v; return $this; }
    public function getSailingsOnTimeDep(): int { return $this->sailingsOnTimeDep; }
    public function setSailingsOnTimeDep(int $v): static { $this->sailingsOnTimeDep = $v; return $this; }
    public function getSailingsOnTimeArr(): int { return $this->sailingsOnTimeArr; }
    public function setSailingsOnTimeArr(int $v): static { $this->sailingsOnTimeArr = $v; return $this; }
    public function getSailingsCancelled(): int { return $this->sailingsCancelled; }
    public function setSailingsCancelled(int $v): static { $this->sailingsCancelled = $v; return $this; }
    public function getBookingsTotal(): int { return $this->bookingsTotal; }
    public function setBookingsTotal(int $v): static { $this->bookingsTotal = $v; return $this; }
    public function getBookingsConfirmed(): int { return $this->bookingsConfirmed; }
    public function setBookingsConfirmed(int $v): static { $this->bookingsConfirmed = $v; return $this; }
    public function getBookingsRolled(): int { return $this->bookingsRolled; }
    public function setBookingsRolled(int $v): static { $this->bookingsRolled = $v; return $this; }
    public function getApBillsTotal(): int { return $this->apBillsTotal; }
    public function setApBillsTotal(int $v): static { $this->apBillsTotal = $v; return $this; }
    public function getApBillsWithinTolerance(): int { return $this->apBillsWithinTolerance; }
    public function setApBillsWithinTolerance(int $v): static { $this->apBillsWithinTolerance = $v; return $this; }
    public function getCargoClaimsCount(): int { return $this->cargoClaimsCount; }
    public function setCargoClaimsCount(int $v): static { $this->cargoClaimsCount = $v; return $this; }
    public function getShipmentsTotal(): int { return $this->shipmentsTotal; }
    public function setShipmentsTotal(int $v): static { $this->shipmentsTotal = $v; return $this; }
    public function getOnTimeDepPct(): ?float { return $this->onTimeDepPct !== null ? (float) $this->onTimeDepPct : null; }
    public function setOnTimeDepPct(?float $v): static { $this->onTimeDepPct = $v; return $this; }
    public function getOnTimeArrPct(): ?float { return $this->onTimeArrPct !== null ? (float) $this->onTimeArrPct : null; }
    public function setOnTimeArrPct(?float $v): static { $this->onTimeArrPct = $v; return $this; }
    public function getScheduleReliabilityPct(): ?float { return $this->scheduleReliabilityPct !== null ? (float) $this->scheduleReliabilityPct : null; }
    public function setScheduleReliabilityPct(?float $v): static { $this->scheduleReliabilityPct = $v; return $this; }
    public function getBookingAcceptancePct(): ?float { return $this->bookingAcceptancePct !== null ? (float) $this->bookingAcceptancePct : null; }
    public function setBookingAcceptancePct(?float $v): static { $this->bookingAcceptancePct = $v; return $this; }
    public function getRateConsistencyPct(): ?float { return $this->rateConsistencyPct !== null ? (float) $this->rateConsistencyPct : null; }
    public function setRateConsistencyPct(?float $v): static { $this->rateConsistencyPct = $v; return $this; }
    public function getClaimsPer100(): ?float { return $this->claimsPer100 !== null ? (float) $this->claimsPer100 : null; }
    public function setClaimsPer100(?float $v): static { $this->claimsPer100 = $v; return $this; }
    public function getCompositeScore(): ?float { return $this->compositeScore !== null ? (float) $this->compositeScore : null; }
    public function setCompositeScore(?float $v): static { $this->compositeScore = $v; return $this; }
    public function getScoreBand(): ?string { return $this->scoreBand; }
    public function setScoreBand(?string $v): static { $this->scoreBand = $v; return $this; }
    public function getCalculatedAt(): \DateTimeInterface { return $this->calculatedAt; }
    public function setCalculatedAt(\DateTimeInterface $v): static { $this->calculatedAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'                    => $this->id,
            'carrierId'             => $this->carrier->getId(),
            'carrierName'           => $this->carrier->getName(),
            'periodYear'            => $this->periodYear,
            'periodMonth'           => $this->periodMonth,
            'transportMode'         => $this->transportMode,
            'sailingsTotal'         => $this->sailingsTotal,
            'sailingsOnTimeDep'     => $this->sailingsOnTimeDep,
            'sailingsOnTimeArr'     => $this->sailingsOnTimeArr,
            'sailingsCancelled'     => $this->sailingsCancelled,
            'bookingsTotal'         => $this->bookingsTotal,
            'bookingsConfirmed'     => $this->bookingsConfirmed,
            'bookingsRolled'        => $this->bookingsRolled,
            'apBillsTotal'          => $this->apBillsTotal,
            'apBillsWithinTolerance'=> $this->apBillsWithinTolerance,
            'cargoClaimsCount'      => $this->cargoClaimsCount,
            'shipmentsTotal'        => $this->shipmentsTotal,
            'onTimeDepPct'          => $this->getOnTimeDepPct(),
            'onTimeArrPct'          => $this->getOnTimeArrPct(),
            'scheduleReliabilityPct'=> $this->getScheduleReliabilityPct(),
            'bookingAcceptancePct'  => $this->getBookingAcceptancePct(),
            'rateConsistencyPct'    => $this->getRateConsistencyPct(),
            'claimsPer100'          => $this->getClaimsPer100(),
            'compositeScore'        => $this->getCompositeScore(),
            'scoreBand'             => $this->scoreBand,
            'calculatedAt'          => $this->calculatedAt->format('Y-m-d H:i:s'),
        ];
    }
}
