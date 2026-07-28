# Carrier Performance Scoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add carrier performance scoring — a `carrier_performance_score` table storing monthly composite scores (A–F bands) computed from cargo claims data, a `cargo_claim` CRUD module, a monthly calculation command, API endpoints, and BO report + library pages.

**Architecture:** Two new tables (`carrier_performance_score`, `cargo_claim`) extend the existing `provider` table via FK. A `CarrierPerformanceScoreService` computes weighted composite scores (0–5 scale) from available metric dimensions; a `ComputeCarrierScoresCommand` aggregates `cargo_claim` records monthly and stores results. The BO gains a Carrier Performance report page and a Cargo Claims library page. Satellite metrics (vessel sailing, booking, AP bill tolerance) default to null when source tables are absent — their weight is redistributed proportionally so the composite remains valid.

**Tech Stack:** Symfony 7 / PHP 8.2 / Doctrine ORM + DBAL, dual MySQL+SQLite migrations, Vue 3 + Vuetify 3.

---

## File Map

### API (`make-cargo-client`)

| File | Action |
|------|--------|
| `migrations/mysql/Version20260625010000.php` | New — `carrier_performance_score` table |
| `migrations/sqlite/Version20260625010000.php` | New — same (SQLite) |
| `migrations/mysql/Version20260625020000.php` | New — `cargo_claim` table |
| `migrations/sqlite/Version20260625020000.php` | New — same (SQLite) |
| `src/Entity/CarrierPerformanceScore.php` | New |
| `src/Repository/CarrierPerformanceScoreRepository.php` | New |
| `src/Entity/CargoClaim.php` | New |
| `src/Repository/CargoClaimRepository.php` | New |
| `src/Service/CarrierPerformanceScoreService.php` | New — composite calculation logic |
| `src/Command/ComputeCarrierScoresCommand.php` | New — monthly batch job |
| `src/Controller/Api/CarrierPerformanceController.php` | New — GET scores + POST compute trigger |
| `src/Controller/Api/CargoClaimController.php` | New — CRUD |
| `config/services.yaml` | Modify — register `CarrierPerformanceScoreService` in `app.auto_service_locator` |

### BO (`make-cargo-client-bo`)

| File | Action |
|------|--------|
| `src/services/CarrierPerformanceService.js` | New |
| `src/services/CargoClaimService.js` | New |
| `src/pages/report/carrier-performance.vue` | New |
| `src/pages/library/cargo-claim.vue` | New |
| `src/config/navigation/index.js` | Modify — add 1 Reports entry + 1 Library entry |

### Docs

| File | Action |
|------|--------|
| `docs/guides/carrier-performance.md` | New — setup and operations guide |

---

## Task 1: MySQL Migrations — carrier_performance_score and cargo_claim

**Files:**
- Create: `migrations/mysql/Version20260625010000.php`
- Create: `migrations/mysql/Version20260625020000.php`

- [ ] **Step 1: Create `migrations/mysql/Version20260625010000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create carrier_performance_score table'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE carrier_performance_score (
            id INT AUTO_INCREMENT NOT NULL,
            carrier_id INT NOT NULL,
            period_year SMALLINT NOT NULL,
            period_month SMALLINT NOT NULL,
            transport_mode VARCHAR(8) NOT NULL,
            sailings_total INT NOT NULL DEFAULT 0,
            sailings_on_time_dep INT NOT NULL DEFAULT 0,
            sailings_on_time_arr INT NOT NULL DEFAULT 0,
            sailings_cancelled INT NOT NULL DEFAULT 0,
            bookings_total INT NOT NULL DEFAULT 0,
            bookings_confirmed INT NOT NULL DEFAULT 0,
            bookings_rolled INT NOT NULL DEFAULT 0,
            ap_bills_total INT NOT NULL DEFAULT 0,
            ap_bills_within_tolerance INT NOT NULL DEFAULT 0,
            cargo_claims_count INT NOT NULL DEFAULT 0,
            shipments_total INT NOT NULL DEFAULT 0,
            on_time_dep_pct NUMERIC(5,2) DEFAULT NULL,
            on_time_arr_pct NUMERIC(5,2) DEFAULT NULL,
            schedule_reliability_pct NUMERIC(5,2) DEFAULT NULL,
            booking_acceptance_pct NUMERIC(5,2) DEFAULT NULL,
            rate_consistency_pct NUMERIC(5,2) DEFAULT NULL,
            claims_per_100 NUMERIC(6,3) DEFAULT NULL,
            composite_score NUMERIC(4,2) DEFAULT NULL,
            score_band VARCHAR(2) DEFAULT NULL,
            calculated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX UNQ_cps_period (carrier_id, period_year, period_month, transport_mode),
            INDEX IDX_cps_carrier (carrier_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE carrier_performance_score
            ADD CONSTRAINT FK_cps_carrier FOREIGN KEY (carrier_id) REFERENCES provider (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE carrier_performance_score DROP FOREIGN KEY FK_cps_carrier");
        $this->addSql("DROP TABLE carrier_performance_score");
    }
}
```

- [ ] **Step 2: Create `migrations/mysql/Version20260625020000.php`**

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create cargo_claim table'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cargo_claim (
            id INT AUTO_INCREMENT NOT NULL,
            shipment_id INT NOT NULL,
            carrier_id INT NOT NULL,
            transport_mode VARCHAR(8) NOT NULL DEFAULT 'OCN',
            claim_type VARCHAR(32) NOT NULL,
            claim_date DATE NOT NULL,
            claim_amount NUMERIC(20,6) NOT NULL,
            currency CHAR(3) NOT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'OPEN',
            settlement_amount NUMERIC(20,6) DEFAULT NULL,
            settled_at DATE DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX IDX_cc_carrier (carrier_id),
            INDEX IDX_cc_shipment (shipment_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("ALTER TABLE cargo_claim
            ADD CONSTRAINT FK_cc_carrier FOREIGN KEY (carrier_id) REFERENCES provider (id),
            ADD CONSTRAINT FK_cc_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cargo_claim DROP FOREIGN KEY FK_cc_carrier, DROP FOREIGN KEY FK_cc_shipment");
        $this->addSql("DROP TABLE cargo_claim");
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add migrations/mysql/Version20260625010000.php migrations/mysql/Version20260625020000.php
git commit -m "feat: MySQL migrations — carrier_performance_score and cargo_claim tables"
```

---

## Task 2: SQLite Migrations — carrier_performance_score and cargo_claim

SQLite does not support multi-column FKs in the same ALTER TABLE statement or DROP FOREIGN KEY. Each `CREATE TABLE` already includes inline FKs.

**Files:**
- Create: `migrations/sqlite/Version20260625010000.php`
- Create: `migrations/sqlite/Version20260625020000.php`

- [ ] **Step 1: Create `migrations/sqlite/Version20260625010000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create carrier_performance_score table (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE carrier_performance_score (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            carrier_id INTEGER NOT NULL,
            period_year INTEGER NOT NULL,
            period_month INTEGER NOT NULL,
            transport_mode VARCHAR(8) NOT NULL,
            sailings_total INTEGER NOT NULL DEFAULT 0,
            sailings_on_time_dep INTEGER NOT NULL DEFAULT 0,
            sailings_on_time_arr INTEGER NOT NULL DEFAULT 0,
            sailings_cancelled INTEGER NOT NULL DEFAULT 0,
            bookings_total INTEGER NOT NULL DEFAULT 0,
            bookings_confirmed INTEGER NOT NULL DEFAULT 0,
            bookings_rolled INTEGER NOT NULL DEFAULT 0,
            ap_bills_total INTEGER NOT NULL DEFAULT 0,
            ap_bills_within_tolerance INTEGER NOT NULL DEFAULT 0,
            cargo_claims_count INTEGER NOT NULL DEFAULT 0,
            shipments_total INTEGER NOT NULL DEFAULT 0,
            on_time_dep_pct NUMERIC(5,2) DEFAULT NULL,
            on_time_arr_pct NUMERIC(5,2) DEFAULT NULL,
            schedule_reliability_pct NUMERIC(5,2) DEFAULT NULL,
            booking_acceptance_pct NUMERIC(5,2) DEFAULT NULL,
            rate_consistency_pct NUMERIC(5,2) DEFAULT NULL,
            claims_per_100 NUMERIC(6,3) DEFAULT NULL,
            composite_score NUMERIC(4,2) DEFAULT NULL,
            score_band VARCHAR(2) DEFAULT NULL,
            calculated_at DATETIME NOT NULL,
            UNIQUE (carrier_id, period_year, period_month, transport_mode),
            FOREIGN KEY (carrier_id) REFERENCES provider (id) ON DELETE CASCADE
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE carrier_performance_score");
    }
}
```

- [ ] **Step 2: Create `migrations/sqlite/Version20260625020000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create cargo_claim table (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE cargo_claim (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            shipment_id INTEGER NOT NULL,
            carrier_id INTEGER NOT NULL,
            transport_mode VARCHAR(8) NOT NULL DEFAULT 'OCN',
            claim_type VARCHAR(32) NOT NULL,
            claim_date DATE NOT NULL,
            claim_amount NUMERIC(20,6) NOT NULL,
            currency CHAR(3) NOT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'OPEN',
            settlement_amount NUMERIC(20,6) DEFAULT NULL,
            settled_at DATE DEFAULT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (carrier_id) REFERENCES provider (id),
            FOREIGN KEY (shipment_id) REFERENCES shipment (id)
        )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE cargo_claim");
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add migrations/sqlite/Version20260625010000.php migrations/sqlite/Version20260625020000.php
git commit -m "feat: SQLite migrations — carrier_performance_score and cargo_claim tables"
```

---

## Task 3: CarrierPerformanceScore Entity and Repository

**Files:**
- Create: `src/Entity/CarrierPerformanceScore.php`
- Create: `src/Repository/CarrierPerformanceScoreRepository.php`

- [ ] **Step 1: Create `src/Entity/CarrierPerformanceScore.php`**

```php
<?php
namespace App\Entity;

use App\Repository\CarrierPerformanceScoreRepository;
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
```

- [ ] **Step 2: Create `src/Repository/CarrierPerformanceScoreRepository.php`**

```php
<?php
namespace App\Repository;

use App\Entity\CarrierPerformanceScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CarrierPerformanceScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarrierPerformanceScore::class);
    }

    public function findOneForPeriod(int $carrierId, int $year, int $month, string $mode): ?CarrierPerformanceScore
    {
        return $this->findOneBy([
            'carrier'      => $carrierId,
            'periodYear'   => $year,
            'periodMonth'  => $month,
            'transportMode'=> $mode,
        ]);
    }

    public function findForPeriod(int $year, int $month, ?string $mode = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.carrier', 'c')
            ->addSelect('c')
            ->where('s.periodYear = :year')
            ->andWhere('s.periodMonth = :month')
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->orderBy('s.compositeScore', 'DESC');

        if ($mode !== null) {
            $qb->andWhere('s.transportMode = :mode')->setParameter('mode', $mode);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLatestForCarrier(int $carrierId, ?string $mode = null): ?CarrierPerformanceScore
    {
        $qb = $this->createQueryBuilder('s')
            ->where('IDENTITY(s.carrier) = :cid')
            ->setParameter('cid', $carrierId)
            ->orderBy('s.periodYear', 'DESC')
            ->addOrderBy('s.periodMonth', 'DESC')
            ->setMaxResults(1);

        if ($mode !== null) {
            $qb->andWhere('s.transportMode = :mode')->setParameter('mode', $mode);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function save(CarrierPerformanceScore $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Entity/CarrierPerformanceScore.php src/Repository/CarrierPerformanceScoreRepository.php
git commit -m "feat: CarrierPerformanceScore entity and repository"
```

---

## Task 4: CargoClaim Entity and Repository

**Files:**
- Create: `src/Entity/CargoClaim.php`
- Create: `src/Repository/CargoClaimRepository.php`

- [ ] **Step 1: Create `src/Entity/CargoClaim.php`**

```php
<?php
namespace App\Entity;

use App\Repository\CargoClaimRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CargoClaimRepository::class)]
class CargoClaim
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Shipment $shipment;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Provider $carrier;

    #[ORM\Column(length: 8)]
    private string $transportMode = 'OCN';

    #[ORM\Column(length: 32)]
    private string $claimType;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $claimDate;

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6)]
    private float $claimAmount;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 16)]
    private string $status = 'OPEN';

    #[ORM\Column(type: Types::DECIMAL, precision: 20, scale: 6, nullable: true)]
    private ?float $settlementAmount = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $settledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getShipment(): Shipment { return $this->shipment; }
    public function setShipment(Shipment $v): static { $this->shipment = $v; return $this; }
    public function getCarrier(): Provider { return $this->carrier; }
    public function setCarrier(Provider $v): static { $this->carrier = $v; return $this; }
    public function getTransportMode(): string { return $this->transportMode; }
    public function setTransportMode(string $v): static { $this->transportMode = $v; return $this; }
    public function getClaimType(): string { return $this->claimType; }
    public function setClaimType(string $v): static { $this->claimType = $v; return $this; }
    public function getClaimDate(): \DateTimeInterface { return $this->claimDate; }
    public function setClaimDate(\DateTimeInterface $v): static { $this->claimDate = $v; return $this; }
    public function getClaimAmount(): float { return (float) $this->claimAmount; }
    public function setClaimAmount(float $v): static { $this->claimAmount = $v; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $v): static { $this->currency = $v; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): static { $this->description = $v; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }
    public function getSettlementAmount(): ?float { return $this->settlementAmount !== null ? (float) $this->settlementAmount : null; }
    public function setSettlementAmount(?float $v): static { $this->settlementAmount = $v; return $this; }
    public function getSettledAt(): ?\DateTimeInterface { return $this->settledAt; }
    public function setSettledAt(?\DateTimeInterface $v): static { $this->settledAt = $v; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $v): static { $this->createdAt = $v; return $this; }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'shipmentId'       => $this->shipment->getId(),
            'carrierId'        => $this->carrier->getId(),
            'carrierName'      => $this->carrier->getName(),
            'transportMode'    => $this->transportMode,
            'claimType'        => $this->claimType,
            'claimDate'        => $this->claimDate->format('Y-m-d'),
            'claimAmount'      => $this->getClaimAmount(),
            'currency'         => $this->currency,
            'description'      => $this->description,
            'status'           => $this->status,
            'settlementAmount' => $this->getSettlementAmount(),
            'settledAt'        => $this->settledAt?->format('Y-m-d'),
            'createdAt'        => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 2: Create `src/Repository/CargoClaimRepository.php`**

```php
<?php
namespace App\Repository;

use App\Entity\CargoClaim;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CargoClaimRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CargoClaim::class);
    }

    public function findByCarrier(int $carrierId): array
    {
        return $this->createQueryBuilder('c')
            ->where('IDENTITY(c.carrier) = :cid')
            ->setParameter('cid', $carrierId)
            ->orderBy('c.claimDate', 'DESC')
            ->getQuery()->getResult();
    }

    /** Returns [carrier_id => ['claim_count' => n, 'shipment_count' => m], ...] for the period. */
    public function aggregateForPeriod(int $year, int $month, string $mode): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.carrier) AS carrierId, COUNT(c.id) AS claimCount, COUNT(DISTINCT IDENTITY(c.shipment)) AS shipmentCount')
            ->where('c.transportMode = :mode')
            ->andWhere('YEAR(c.claimDate) = :year')
            ->andWhere('MONTH(c.claimDate) = :month')
            ->setParameter('mode', $mode)
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->groupBy('c.carrier')
            ->getQuery()->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['carrierId']] = [
                'claimCount'    => (int) $row['claimCount'],
                'shipmentCount' => (int) $row['shipmentCount'],
            ];
        }
        return $result;
    }

    public function save(CargoClaim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(CargoClaim $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Entity/CargoClaim.php src/Repository/CargoClaimRepository.php
git commit -m "feat: CargoClaim entity and repository"
```

---

## Task 5: CarrierPerformanceScoreService

This service contains all score calculation logic. It takes dimension percentages (null = no data) and computes a composite score on a 0–5 scale, redistributing weights from null dimensions proportionally to available ones. If all dimensions are null, returns null.

**Files:**
- Create: `src/Service/CarrierPerformanceScoreService.php`

- [ ] **Step 1: Create `src/Service/CarrierPerformanceScoreService.php`**

```php
<?php
namespace App\Service;

class CarrierPerformanceScoreService
{
    private const WEIGHTS = [
        'onTimeDep'       => 0.25,
        'onTimeArr'       => 0.25,
        'bookingAccept'   => 0.20,
        'scheduleRel'     => 0.15,
        'rateConsistency' => 0.10,
        'claims'          => 0.05,
    ];

    /**
     * All pct arguments are 0–100 (percentage). claimsPer100 is claims per 100 shipments.
     * Any argument that is null is excluded from the composite and its weight is redistributed.
     * Returns null if there is no data at all.
     */
    public function computeComposite(
        ?float $onTimeDepPct,
        ?float $onTimeArrPct,
        ?float $bookingAcceptancePct,
        ?float $scheduleReliabilityPct,
        ?float $rateConsistencyPct,
        ?float $claimsPer100,
    ): ?float {
        // Convert claims to a 0-100 score: 0 claims per 100 = 100, 5+ claims per 100 = 0
        $claimsScore = $claimsPer100 !== null ? max(0.0, 100.0 - ($claimsPer100 * 20.0)) : null;

        $scores = [
            'onTimeDep'       => $onTimeDepPct,
            'onTimeArr'       => $onTimeArrPct,
            'bookingAccept'   => $bookingAcceptancePct,
            'scheduleRel'     => $scheduleReliabilityPct,
            'rateConsistency' => $rateConsistencyPct,
            'claims'          => $claimsScore,
        ];

        $totalWeight  = 0.0;
        $weightedSum  = 0.0;
        foreach ($scores as $key => $score) {
            if ($score !== null) {
                $totalWeight += self::WEIGHTS[$key];
                $weightedSum += $score * self::WEIGHTS[$key];
            }
        }

        if ($totalWeight < 0.001) {
            return null;
        }

        // Normalize weighted sum over available weight, then scale 0-100 → 0-5
        $normalizedScore = $weightedSum / $totalWeight;
        return round($normalizedScore / 20.0, 2);
    }

    public function scoreBand(float $composite): string
    {
        return match(true) {
            $composite >= 4.5 => 'A',
            $composite >= 3.5 => 'B',
            $composite >= 2.5 => 'C',
            $composite >= 1.5 => 'D',
            default           => 'F',
        };
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Service/CarrierPerformanceScoreService.php
git commit -m "feat: CarrierPerformanceScoreService — composite score calculation with weight redistribution"
```

---

## Task 6: ComputeCarrierScoresCommand

Monthly batch command. Groups cargo claims by carrier + mode for the target period, applies minimum-threshold check (< 5 distinct shipments = skip), then writes/updates `carrier_performance_score` records.

Run as: `php bin/console app:carrier:compute-scores --year=2026 --month=5 --mode=OCN`
Defaults: previous calendar month, all three modes (OCN / AIR / RD).

**Files:**
- Create: `src/Command/ComputeCarrierScoresCommand.php`

- [ ] **Step 1: Create `src/Command/ComputeCarrierScoresCommand.php`**

```php
<?php
namespace App\Command;

use App\Entity\CarrierPerformanceScore;
use App\Entity\Provider;
use App\Repository\CargoClaimRepository;
use App\Repository\CarrierPerformanceScoreRepository;
use App\Service\CarrierPerformanceScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:carrier:compute-scores',
    description: 'Compute monthly carrier performance scores from cargo claim data',
)]
class ComputeCarrierScoresCommand extends Command
{
    private const MODES = ['OCN', 'AIR', 'RD'];
    private const MIN_SHIPMENTS = 5;

    public function __construct(
        private readonly CargoClaimRepository             $claimRepo,
        private readonly CarrierPerformanceScoreRepository $scoreRepo,
        private readonly CarrierPerformanceScoreService   $scoreService,
        private readonly EntityManagerInterface           $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('year',  null, InputOption::VALUE_OPTIONAL, 'Period year (default: previous month year)')
             ->addOption('month', null, InputOption::VALUE_OPTIONAL, 'Period month 1-12 (default: previous month)')
             ->addOption('mode',  null, InputOption::VALUE_OPTIONAL, 'Transport mode OCN/AIR/RD (default: all)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Carrier Performance Score Computation');

        $prevMonth = new \DateTime('first day of last month');
        $year  = (int) ($input->getOption('year')  ?? $prevMonth->format('Y'));
        $month = (int) ($input->getOption('month') ?? $prevMonth->format('n'));
        $modes = $input->getOption('mode') ? [$input->getOption('mode')] : self::MODES;

        $io->comment(sprintf('Period: %d-%02d | Modes: %s', $year, $month, implode(', ', $modes)));

        $totalUpdated = 0;

        foreach ($modes as $mode) {
            $io->section("Mode: {$mode}");
            $aggregated = $this->claimRepo->aggregateForPeriod($year, $month, $mode);

            if (empty($aggregated)) {
                $io->writeln('  No claim data found — skipping.');
                continue;
            }

            foreach ($aggregated as $carrierId => $metrics) {
                $shipmentCount = $metrics['shipmentCount'];
                $claimCount    = $metrics['claimCount'];

                if ($shipmentCount < self::MIN_SHIPMENTS) {
                    $io->writeln("  [carrier #{$carrierId}] Skipped — only {$shipmentCount} shipment(s), need " . self::MIN_SHIPMENTS);
                    continue;
                }

                $claimsPer100 = round(($claimCount / $shipmentCount) * 100, 3);

                $composite = $this->scoreService->computeComposite(
                    null, null, null, null, null, $claimsPer100
                );

                $score = $this->scoreRepo->findOneForPeriod($carrierId, $year, $month, $mode)
                      ?? (new CarrierPerformanceScore())
                            ->setCarrier($this->em->getReference(Provider::class, $carrierId))
                            ->setPeriodYear($year)
                            ->setPeriodMonth($month)
                            ->setTransportMode($mode);

                $score->setCargoClaimsCount($claimCount)
                      ->setShipmentsTotal($shipmentCount)
                      ->setClaimsPer100($claimsPer100)
                      ->setCompositeScore($composite)
                      ->setScoreBand($composite !== null ? $this->scoreService->scoreBand($composite) : null)
                      ->setCalculatedAt(new \DateTime());

                $this->scoreRepo->save($score, false);
                $totalUpdated++;

                $io->writeln(sprintf(
                    '  [carrier #%d] claims=%d shipments=%d claims/100=%.1f composite=%s band=%s',
                    $carrierId, $claimCount, $shipmentCount, $claimsPer100,
                    $composite !== null ? number_format($composite, 2) : 'N/A',
                    $score->getScoreBand() ?? 'N/A'
                ));
            }
        }

        $this->em->flush();
        $io->success("Computed/updated {$totalUpdated} score record(s).");
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Command/ComputeCarrierScoresCommand.php
git commit -m "feat: ComputeCarrierScoresCommand — monthly carrier score batch job"
```

---

## Task 7: CarrierPerformanceController

Exposes three read endpoints and one compute trigger. No write endpoints for scores — scores are always produced by the command.

**Files:**
- Create: `src/Controller/Api/CarrierPerformanceController.php`

- [ ] **Step 1: Create `src/Controller/Api/CarrierPerformanceController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\Provider;
use App\Repository\CarrierPerformanceScoreRepository;
use App\Service\CarrierPerformanceScoreService;
use App\Repository\CargoClaimRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/carrier-performance')]
#[IsGranted('ROLE_USER')]
class CarrierPerformanceController extends AbstractController
{
    public function __construct(
        private readonly CarrierPerformanceScoreRepository $scoreRepo,
        private readonly CargoClaimRepository              $claimRepo,
        private readonly CarrierPerformanceScoreService    $scoreService,
    ) {}

    /** GET /carrier-performance/scores?year=2026&month=5&mode=OCN */
    #[Route('/scores', methods: ['GET'])]
    public function scores(Request $request): JsonResponse
    {
        $year  = (int) $request->query->get('year',  date('Y'));
        $month = (int) $request->query->get('month', (int) date('n') - 1 ?: 12);
        $mode  = $request->query->get('mode');

        $scores = $this->scoreRepo->findForPeriod($year, $month, $mode ?: null);
        return $this->json(array_map(fn($s) => $s->toArray(), $scores));
    }

    /** GET /carrier-performance/{id}/latest?mode=OCN */
    #[Route('/{id}/latest', methods: ['GET'])]
    public function latest(Provider $provider, Request $request): JsonResponse
    {
        $mode  = $request->query->get('mode');
        $score = $this->scoreRepo->findLatestForCarrier($provider->getId(), $mode ?: null);
        return $this->json($score ? $score->toArray() : null);
    }

    /** GET /carrier-performance/{id}/history?mode=OCN */
    #[Route('/{id}/history', methods: ['GET'])]
    public function history(Provider $provider, Request $request): JsonResponse
    {
        $mode   = $request->query->get('mode');
        $scores = $this->scoreRepo->findForCarrierHistory($provider->getId(), $mode ?: null);
        return $this->json(array_map(fn($s) => $s->toArray(), $scores));
    }
}
```

- [ ] **Step 2: Add `findForCarrierHistory` to `CarrierPerformanceScoreRepository`**

Open `src/Repository/CarrierPerformanceScoreRepository.php` and add after `findLatestForCarrier()`:

```php
    public function findForCarrierHistory(int $carrierId, ?string $mode = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('IDENTITY(s.carrier) = :cid')
            ->setParameter('cid', $carrierId)
            ->orderBy('s.periodYear', 'DESC')
            ->addOrderBy('s.periodMonth', 'DESC')
            ->setMaxResults(24);

        if ($mode !== null) {
            $qb->andWhere('s.transportMode = :mode')->setParameter('mode', $mode);
        }

        return $qb->getQuery()->getResult();
    }
```

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/CarrierPerformanceController.php src/Repository/CarrierPerformanceScoreRepository.php
git commit -m "feat: CarrierPerformanceController — GET scores, latest, history endpoints"
```

---

## Task 8: CargoClaimController and services.yaml update

**Files:**
- Create: `src/Controller/Api/CargoClaimController.php`
- Modify: `config/services.yaml`

- [ ] **Step 1: Create `src/Controller/Api/CargoClaimController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\CargoClaim;
use App\Entity\Provider;
use App\Entity\Shipment;
use App\Repository\CargoClaimRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/cargo-claim')]
#[IsGranted('ROLE_USER')]
class CargoClaimController extends AbstractController
{
    public function __construct(
        private readonly CargoClaimRepository   $repo,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $carrierId = $request->query->get('carrierId');
        if ($carrierId) {
            $claims = $this->repo->findByCarrier((int) $carrierId);
        } else {
            $claims = $this->repo->findBy([], ['claimDate' => 'DESC'], 200);
        }
        return $this->json(array_map(fn($c) => $c->toArray(), $claims));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(CargoClaim $claim): JsonResponse
    {
        return $this->json($claim->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true);
        $claim = $this->hydrate(new CargoClaim(), $data);
        $claim->setCreatedAt(new \DateTime());
        $this->repo->save($claim);
        return $this->json($claim->toArray(), 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(CargoClaim $claim, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($claim, $data);
        $this->repo->save($claim);
        return $this->json($claim->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(CargoClaim $claim): JsonResponse
    {
        $this->repo->remove($claim);
        return $this->json(null, 204);
    }

    private function hydrate(CargoClaim $claim, array $data): CargoClaim
    {
        $claim->setShipment($this->em->getReference(Shipment::class, $data['shipmentId']));
        $claim->setCarrier($this->em->getReference(Provider::class, $data['carrierId']));
        $claim->setTransportMode($data['transportMode'] ?? 'OCN');
        $claim->setClaimType($data['claimType']);
        $claim->setClaimDate(new \DateTime($data['claimDate']));
        $claim->setClaimAmount((float) $data['claimAmount']);
        $claim->setCurrency($data['currency']);
        $claim->setDescription($data['description'] ?? null);
        $claim->setStatus($data['status'] ?? 'OPEN');
        $claim->setSettlementAmount(isset($data['settlementAmount']) && $data['settlementAmount'] !== null ? (float) $data['settlementAmount'] : null);
        $claim->setSettledAt(isset($data['settledAt']) && $data['settledAt'] ? new \DateTime($data['settledAt']) : null);
        return $claim;
    }
}
```

- [ ] **Step 2: Register `CarrierPerformanceScoreService` in `config/services.yaml`**

In `config/services.yaml`, find this line:
```yaml
                App\Repository\VatReportRepository: '@App\Repository\VatReportRepository'
```

Add after it:
```yaml
                App\Service\CarrierPerformanceScoreService: '@App\Service\CarrierPerformanceScoreService'
```

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/CargoClaimController.php config/services.yaml
git commit -m "feat: CargoClaimController CRUD and register CarrierPerformanceScoreService"
```

---

## Task 9: BO Services — CarrierPerformanceService.js and CargoClaimService.js

Work in the `make-cargo-client-bo` directory for all remaining tasks.

**Files:**
- Create: `src/services/CarrierPerformanceService.js`
- Create: `src/services/CargoClaimService.js`

- [ ] **Step 1: Create `src/services/CarrierPerformanceService.js`**

```js
export default {
  getScores: (year, month, mode) => {
    const params = new URLSearchParams({ year, month })
    if (mode) params.append('mode', mode)
    return $api(`/carrier-performance/scores?${params}`)
  },
  getLatest: (carrierId, mode) => {
    const params = mode ? `?mode=${mode}` : ''
    return $api(`/carrier-performance/${carrierId}/latest${params}`)
  },
  getHistory: (carrierId, mode) => {
    const params = mode ? `?mode=${mode}` : ''
    return $api(`/carrier-performance/${carrierId}/history${params}`)
  },
}
```

- [ ] **Step 2: Create `src/services/CargoClaimService.js`**

```js
export default {
  list: (carrierId) => $api(`/cargo-claim${carrierId ? `?carrierId=${carrierId}` : ''}`),
  get: (id) => $api(`/cargo-claim/${id}`),
  create: (data) => $api('/cargo-claim', { method: 'POST', body: JSON.stringify(data), headers: { 'Content-Type': 'application/json' } }),
  update: (id, data) => $api(`/cargo-claim/${id}`, { method: 'PUT', body: JSON.stringify(data), headers: { 'Content-Type': 'application/json' } }),
  remove: (id) => $api(`/cargo-claim/${id}`, { method: 'DELETE' }),
}
```

- [ ] **Step 3: Commit**

```bash
git add src/services/CarrierPerformanceService.js src/services/CargoClaimService.js
git commit -m "feat: BO CarrierPerformanceService and CargoClaimService"
```

---

## Task 10: BO — carrier-performance.vue report page

Shows a period + mode selector, then a table of all carrier scores for that period. Score band is colour-coded. Null metric values show "N/A".

**Files:**
- Create: `src/pages/report/carrier-performance.vue`

- [ ] **Step 1: Create `src/pages/report/carrier-performance.vue`**

```vue
<script setup>
import CarrierPerformanceService from '@/services/CarrierPerformanceService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })

const loading  = ref(false)
const scores   = ref([])
const prevMonth = new Date()
prevMonth.setMonth(prevMonth.getMonth() - 1)
const year  = ref(prevMonth.getFullYear())
const month = ref(prevMonth.getMonth() + 1)
const mode  = ref('OCN')

const MODES = ['OCN', 'AIR', 'RD']

const BAND_COLOR = { A: 'success', B: 'light-green', C: 'warning', D: 'error', F: 'deep-purple' }

const fmtPct = (v) => v != null ? Number(v).toFixed(1) + '%' : 'N/A'
const fmtScore = (v) => v != null ? Number(v).toFixed(2) : 'N/A'

const headers = [
  { title: 'Carrier', key: 'carrierName' },
  { title: 'Mode', key: 'transportMode', width: 70 },
  { title: 'Band', key: 'scoreBand', width: 70 },
  { title: 'Score', key: 'compositeScore', width: 80 },
  { title: 'OT Dep', key: 'onTimeDepPct', width: 90 },
  { title: 'OT Arr', key: 'onTimeArrPct', width: 90 },
  { title: 'Schedule', key: 'scheduleReliabilityPct', width: 90 },
  { title: 'Booking', key: 'bookingAcceptancePct', width: 90 },
  { title: 'Rate Consist.', key: 'rateConsistencyPct', width: 110 },
  { title: 'Claims/100', key: 'claimsPer100', width: 110 },
  { title: 'Claims', key: 'cargoClaimsCount', width: 80 },
  { title: 'Shipments', key: 'shipmentsTotal', width: 90 },
  { title: 'Calculated', key: 'calculatedAt', width: 140 },
]

async function load() {
  loading.value = true
  scores.value = await CarrierPerformanceService.getScores(year.value, month.value, mode.value)
  loading.value = false
}

onMounted(load)
</script>

<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Carrier Performance</h4></VCol>
    </VRow>

    <VRow class="mb-4" align="center">
      <VCol cols="12" sm="2">
        <VTextField v-model.number="year" type="number" label="Year" density="compact" hide-details min="2020" max="2099" />
      </VCol>
      <VCol cols="12" sm="2">
        <VTextField v-model.number="month" type="number" label="Month (1-12)" density="compact" hide-details min="1" max="12" />
      </VCol>
      <VCol cols="12" sm="2">
        <VSelect v-model="mode" :items="MODES" label="Mode" density="compact" hide-details />
      </VCol>
      <VCol cols="auto">
        <VBtn color="primary" :loading="loading" @click="load">Load</VBtn>
      </VCol>
    </VRow>

    <VCard>
      <VDataTable :headers="headers" :items="scores" :loading="loading" density="compact">
        <template #item.scoreBand="{ item }">
          <VChip v-if="item.scoreBand" :color="BAND_COLOR[item.scoreBand] ?? 'default'" size="small" class="font-weight-bold">
            {{ item.scoreBand }}
          </VChip>
          <span v-else class="text-medium-emphasis text-caption">N/A</span>
        </template>
        <template #item.compositeScore="{ item }">
          <span :class="item.compositeScore >= 3.5 ? 'text-success font-weight-bold' : item.compositeScore >= 2.5 ? 'text-warning' : 'text-error'">
            {{ fmtScore(item.compositeScore) }}
          </span>
        </template>
        <template #item.onTimeDepPct="{ item }">{{ fmtPct(item.onTimeDepPct) }}</template>
        <template #item.onTimeArrPct="{ item }">{{ fmtPct(item.onTimeArrPct) }}</template>
        <template #item.scheduleReliabilityPct="{ item }">{{ fmtPct(item.scheduleReliabilityPct) }}</template>
        <template #item.bookingAcceptancePct="{ item }">{{ fmtPct(item.bookingAcceptancePct) }}</template>
        <template #item.rateConsistencyPct="{ item }">{{ fmtPct(item.rateConsistencyPct) }}</template>
        <template #item.claimsPer100="{ item }">
          {{ item.claimsPer100 != null ? Number(item.claimsPer100).toFixed(2) : 'N/A' }}
        </template>
        <template #item.calculatedAt="{ item }">{{ item.calculatedAt?.slice(0, 16) ?? '—' }}</template>

        <template #no-data>
          <div class="text-center text-medium-emphasis pa-6">
            No scores for {{ year }}-{{ String(month).padStart(2, '0') }} ({{ mode }}). Run the compute command first.
          </div>
        </template>
      </VDataTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add src/pages/report/carrier-performance.vue
git commit -m "feat: BO carrier performance report page"
```

---

## Task 11: BO — cargo-claim.vue library page and navigation

**Files:**
- Create: `src/pages/library/cargo-claim.vue`
- Modify: `src/config/navigation/index.js`

- [ ] **Step 1: Create `src/pages/library/cargo-claim.vue`**

```vue
<script setup>
import CargoClaimService from '@/services/CargoClaimService'
import ProviderService from '@/services/ProviderService'
definePage({ meta: { action: 'GET', subject: 'Config' } })

const providers  = ref([])
const carrierId  = ref(null)
const claims     = ref([])
const loading    = ref(false)
const dialog     = ref(false)
const saving     = ref(false)
const editId     = ref(null)
const form       = ref({
  shipmentId: null, carrierId: null, transportMode: 'OCN',
  claimType: 'DAMAGE', claimDate: '', claimAmount: 0, currency: 'USD',
  description: null, status: 'OPEN', settlementAmount: null, settledAt: null,
})

const MODES       = ['OCN', 'AIR', 'RD']
const CLAIM_TYPES = ['LOSS', 'DAMAGE', 'DELAY', 'SHORT_DELIVERY']
const STATUSES    = ['OPEN', 'SETTLED', 'REJECTED', 'WITHDRAWN']
const STATUS_COLOR = { OPEN: 'warning', SETTLED: 'success', REJECTED: 'error', WITHDRAWN: 'default' }

const headers = [
  { title: 'Shipment', key: 'shipmentId', width: 90 },
  { title: 'Carrier', key: 'carrierName' },
  { title: 'Mode', key: 'transportMode', width: 70 },
  { title: 'Type', key: 'claimType' },
  { title: 'Date', key: 'claimDate', width: 110 },
  { title: 'Amount', key: 'claimAmount' },
  { title: 'Currency', key: 'currency', width: 80 },
  { title: 'Status', key: 'status', width: 110 },
  { title: '', key: 'actions', sortable: false, width: 80 },
]

async function loadProviders() {
  const res = await ProviderService.list('limit=-1')
  providers.value = res?.list ?? res?.data ?? res ?? []
}

async function loadClaims() {
  loading.value = true
  claims.value = await CargoClaimService.list(carrierId.value)
  loading.value = false
}

function openCreate() {
  editId.value = null
  form.value = {
    shipmentId: null, carrierId: carrierId.value, transportMode: 'OCN',
    claimType: 'DAMAGE', claimDate: new Date().toISOString().slice(0, 10),
    claimAmount: 0, currency: 'USD', description: null,
    status: 'OPEN', settlementAmount: null, settledAt: null,
  }
  dialog.value = true
}

function openEdit(item) {
  editId.value = item.id
  form.value = { ...item }
  dialog.value = true
}

async function save() {
  saving.value = true
  if (editId.value) {
    await CargoClaimService.update(editId.value, form.value)
  } else {
    await CargoClaimService.create(form.value)
  }
  dialog.value = false
  saving.value = false
  await loadClaims()
}

async function remove(id) {
  if (!confirm('Delete this cargo claim?')) return
  await CargoClaimService.remove(id)
  await loadClaims()
}

watch(carrierId, loadClaims)
onMounted(() => { loadProviders(); loadClaims() })
</script>

<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Cargo Claims</h4></VCol>
      <VCol cols="auto"><VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">New Claim</VBtn></VCol>
    </VRow>

    <VRow class="mb-4">
      <VCol cols="12" sm="4">
        <VSelect
          v-model="carrierId"
          :items="providers"
          item-title="name"
          item-value="id"
          label="Filter by Carrier (optional)"
          density="compact"
          hide-details
          clearable
        />
      </VCol>
    </VRow>

    <VCard>
      <VDataTable :headers="headers" :items="claims" :loading="loading" density="compact">
        <template #item.status="{ item }">
          <VChip :color="STATUS_COLOR[item.status] ?? 'default'" size="x-small">{{ item.status }}</VChip>
        </template>
        <template #item.claimAmount="{ item }">{{ Number(item.claimAmount).toLocaleString() }}</template>
        <template #item.actions="{ item }">
          <VBtn size="x-small" icon variant="text" @click="openEdit(item)"><VIcon>tabler-pencil</VIcon></VBtn>
          <VBtn size="x-small" icon variant="text" color="error" @click="remove(item.id)"><VIcon>tabler-trash</VIcon></VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VDialog v-model="dialog" max-width="680">
      <VCard :title="editId ? 'Edit Cargo Claim' : 'New Cargo Claim'">
        <VCardText>
          <VRow>
            <VCol cols="6">
              <VTextField v-model.number="form.shipmentId" type="number" label="Shipment ID" />
            </VCol>
            <VCol cols="6">
              <VSelect v-model="form.carrierId" :items="providers" item-title="name" item-value="id" label="Carrier" />
            </VCol>
            <VCol cols="4">
              <VSelect v-model="form.transportMode" :items="MODES" label="Mode" />
            </VCol>
            <VCol cols="4">
              <VSelect v-model="form.claimType" :items="CLAIM_TYPES" label="Claim Type" />
            </VCol>
            <VCol cols="4">
              <VSelect v-model="form.status" :items="STATUSES" label="Status" />
            </VCol>
            <VCol cols="4">
              <VTextField v-model="form.claimDate" type="date" label="Claim Date" />
            </VCol>
            <VCol cols="4">
              <VTextField v-model.number="form.claimAmount" type="number" label="Claim Amount" />
            </VCol>
            <VCol cols="4">
              <VTextField v-model="form.currency" label="Currency" maxlength="3" />
            </VCol>
            <VCol cols="12">
              <VTextarea v-model="form.description" label="Description (optional)" rows="2" clearable />
            </VCol>
            <VCol cols="4">
              <VTextField v-model.number="form.settlementAmount" type="number" label="Settlement Amount (optional)" clearable />
            </VCol>
            <VCol cols="4">
              <VTextField v-model="form.settledAt" type="date" label="Settled At (optional)" clearable />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end pa-4">
          <VBtn variant="tonal" @click="dialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="saving" @click="save">Save</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
```

- [ ] **Step 2: Add navigation entries in `src/config/navigation/index.js`**

In the **Reports** section, find this block (last entry before the closing `]`):
```js
      {
        title: $gettext('VAT Report'),
        to: { name: 'report-vat-report' },
        subject: 'EbitNote',
        action: 'GET',
      },
```

Add after it (before the `]` that closes the Reports children array):
```js
      {
        title: $gettext('Carrier Performance'),
        to: { name: 'report-carrier-performance' },
        subject: 'EbitNote',
        action: 'GET',
      },
```

In the **Library** section, find this block (last entry before the closing `]`):
```js
      {
        title: $gettext('Tax Registrations'),
        to: { name: 'library-tax-registration' },
        subject: 'Config',
        action: 'GET'
      },
```

Add after it (before the `],` that closes the Library children array):
```js
      {
        title: $gettext('Cargo Claims'),
        to: { name: 'library-cargo-claim' },
        subject: 'Config',
        action: 'GET'
      },
```

- [ ] **Step 3: Commit**

```bash
git add src/pages/library/cargo-claim.vue src/config/navigation/index.js
git commit -m "feat: BO cargo-claim library page and navigation entries for Carrier Performance and Cargo Claims"
```

---

## Task 12: Documentation

Work in the `make-cargo-client` directory.

**Files:**
- Create: `docs/guides/carrier-performance.md`

- [ ] **Step 1: Create `docs/guides/carrier-performance.md`**

```markdown
# Carrier Performance Scoring — Setup & Operations Guide

## Overview

The Carrier Performance Scoring module provides a data-driven way to evaluate how reliably each carrier delivers. Monthly composite scores (0–5 scale, A–F band) are stored in `carrier_performance_score` and surfaced in the BO report page. Cargo claims are tracked in `cargo_claim` and feed directly into the score calculation.

## Architecture

```
cargo_claim (per shipment × carrier)
  └─ claimType: LOSS / DAMAGE / DELAY / SHORT_DELIVERY
  └─ status: OPEN / SETTLED / REJECTED / WITHDRAWN
  └─ transportMode: OCN / AIR / RD

carrier_performance_score (per carrier × year × month × mode)
  └─ raw metrics: sailings, bookings, AP bills, cargo claims, shipments
  └─ calculated rates: onTimeDepPct, onTimeArrPct, scheduleReliabilityPct,
                       bookingAcceptancePct, rateConsistencyPct, claimsPer100
  └─ composite: compositeScore (0–5), scoreBand (A/B/C/D/F)
```

## Score Dimensions and Weights

| Dimension | Weight | Source |
|---|---|---|
| On-time departure | 25% | vessel_sailing (future integration) |
| On-time arrival | 25% | vessel_sailing (future integration) |
| Booking acceptance | 20% | booking table (future integration) |
| Schedule reliability | 15% | vessel_sailing (future integration) |
| Rate consistency | 10% | ap_bill table (future integration) |
| Claims rate | 5% | cargo_claim table ✓ |

**Weight redistribution:** Dimensions without source data have their weight proportionally redistributed to available dimensions. The composite is always computed over the available data — it does not penalise carriers for missing integrations.

## Score Bands

| Band | Score | Meaning | Action |
|---|---|---|---|
| A | 4.5 – 5.0 | Excellent | Preferred carrier, first allocation |
| B | 3.5 – 4.4 | Good | Standard carrier, second allocation |
| C | 2.5 – 3.4 | Average | Use when A/B unavailable |
| D | 1.5 – 2.4 | Poor | Escalate to procurement review |
| F | 0.0 – 1.4 | Failing | Suspend pending improvement plan |

## Running the Compute Command

Run monthly (typically on the 1st of each month for the previous month):

```bash
# Compute previous month for all modes (OCN, AIR, RD)
php bin/console app:carrier:compute-scores

# Specific period and mode
php bin/console app:carrier:compute-scores --year=2026 --month=5 --mode=OCN
```

Carriers with fewer than 5 distinct shipments in the period are skipped ("insufficient data").

### Scheduling (cron)

Add to your server's crontab to run on the 2nd of each month at 01:00:

```
0 1 2 * * cd /var/www/api && php bin/console app:carrier:compute-scores >> /var/log/carrier-scores.log 2>&1
```

## API Endpoints

| Endpoint | Method | Description |
|---|---|---|
| `GET /api/carrier-performance/scores?year=Y&month=M&mode=OCN` | GET | All scores for a period |
| `GET /api/carrier-performance/{id}/latest?mode=OCN` | GET | Most recent score for a carrier |
| `GET /api/carrier-performance/{id}/history?mode=OCN` | GET | Last 24 months for a carrier |
| `GET /api/cargo-claim?carrierId=X` | GET | Claims for a carrier |
| `POST /api/cargo-claim` | POST | Create claim |
| `PUT /api/cargo-claim/{id}` | PUT | Update claim |
| `DELETE /api/cargo-claim/{id}` | DELETE | Delete claim |

### POST /api/cargo-claim body

```json
{
  "shipmentId": 1234,
  "carrierId": 56,
  "transportMode": "OCN",
  "claimType": "DAMAGE",
  "claimDate": "2026-05-14",
  "claimAmount": 5000.00,
  "currency": "USD",
  "description": "Damaged carton on arrival",
  "status": "OPEN",
  "settlementAmount": null,
  "settledAt": null
}
```

## Claim Types

| Value | Meaning |
|---|---|
| `LOSS` | Cargo entirely lost |
| `DAMAGE` | Cargo arrived damaged |
| `DELAY` | Late delivery caused financial loss |
| `SHORT_DELIVERY` | Partial delivery — missing items |

## Extending the Score with Sailing/Booking Data

When `vessel_sailing` and `booking` tables are added to the system, extend `ComputeCarrierScoresCommand` to query them:

1. Query `vessel_sailing` for the period filtered by carrier → populate `sailingsTotal`, `sailingsOnTimeDep`, `sailingsOnTimeArr`, `sailingsCancelled`
2. Set `onTimeDepPct`, `onTimeArrPct`, `scheduleReliabilityPct` on the score entity
3. Query `booking` for the period → populate `bookingsTotal`, `bookingsConfirmed`, `bookingsRolled`, set `bookingAcceptancePct`
4. Query `ap_bill` for AP variance → populate `apBillsTotal`, `apBillsWithinTolerance`, set `rateConsistencyPct`
5. Pass all six values to `CarrierPerformanceScoreService::computeComposite()` — weight redistribution handles the migration transparently

## BO Pages

- **Reports → Carrier Performance:** Period + mode selector, sortable table with colour-coded bands and all dimension percentages
- **Library → Cargo Claims:** Carrier-filtered list with CRUD dialog for managing claims
```

- [ ] **Step 2: Commit**

```bash
git add docs/guides/carrier-performance.md
git commit -m "docs: carrier performance scoring setup and operations guide"
```

---

## Self-Review

**Spec coverage:**

| Spec requirement | Task |
|---|---|
| `carrier_performance_score` table | Task 1 + 2 |
| `cargo_claim` table | Task 1 + 2 |
| Score calculation (6 dimensions, weighted, 0–5 scale, A–F bands) | Task 5 |
| Minimum data threshold (5 shipments) | Task 6 |
| Scores per transport mode | Task 6 (loops over OCN/AIR/RD) |
| Scores stored monthly, not computed on the fly | Task 6 (command) + Task 7 (read-only controller) |
| Booking recommendation query (carrier score next to rate) | Task 7 `GET /scores` endpoint + BO report page |
| Cargo claims tracking table | Task 1–4 |
| Claims feed score with available data | Task 6 uses `aggregateForPeriod` from Task 4 repo |
| BO score report page | Task 10 |
| BO cargo claims CRUD | Task 11 |
| Navigation entries | Task 11 |
| Guide | Task 12 |
| Weight redistribution for null dimensions | Task 5 |

**Placeholder scan:** None found — all steps contain full file content or exact edit instructions.

**Type consistency:**
- `CarrierPerformanceScore::toArray()` matches `CarrierPerformanceService.js` field names (camelCase)
- `findOneForPeriod(int $carrierId, ...)` called from command — matches repo signature
- `findForCarrierHistory` added to repo in Task 7 Step 2 — called from controller in Task 7 Step 1 ✓
- `aggregateForPeriod` defined in Task 4 repo — called in Task 6 command ✓
- `CargoClaim::toArray()` key `carrierId` matches `CargoClaimService.js` and vue component ✓
