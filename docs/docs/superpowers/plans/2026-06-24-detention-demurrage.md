# Detention & Demurrage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add full Detention & Demurrage (D&D) management to the freight-forwarder SaaS: free-time agreements library, per-container D&D tracking on shipments, accrual calculation, nightly accrual command, and BO pages for the library and a D&D dashboard.

**Architecture:** Two new DB tables — `free_time_agreement` (carrier rate schedules with tiered rates) and `container_dd_tracking` (per-container accrual records linked to shipments). A pure calculation service (`DdCalculatorService`) computes charges from tier tables. Two standalone controllers (extending AbstractController, not CrudController) handle CRUD + actions. A nightly console command runs accrual.

**Tech Stack:** Symfony 7 / PHP 8.2 / Doctrine ORM + DBAL. Vue 3 + Vuetify 3 BO. MySQL + SQLite dual migrations.

---

## File Map

### Client API (`make-cargo-client`)

| File | Status | Purpose |
|------|--------|---------|
| `migrations/mysql/Version20260624270000.php` | Create | `free_time_agreement` table (MySQL) |
| `migrations/sqlite/Version20260624270000.php` | Create | `free_time_agreement` table (SQLite) |
| `migrations/mysql/Version20260624280000.php` | Create | `container_dd_tracking` table (MySQL) |
| `migrations/sqlite/Version20260624280000.php` | Create | `container_dd_tracking` table (SQLite) |
| `src/Entity/FreeTimeAgreement.php` | Create | ORM entity |
| `src/Repository/FreeTimeAgreementRepository.php` | Create | findAll, findByCarrier, save, remove |
| `src/Entity/ContainerDdTracking.php` | Create | ORM entity |
| `src/Repository/ContainerDdTrackingRepository.php` | Create | findByShipment, findAccruing, save, remove |
| `src/Service/DdCalculatorService.php` | Create | Tier calculation + accrual update |
| `src/Controller/Api/FreeTimeAgreementController.php` | Create | CRUD REST at `/free-time-agreement` |
| `src/Controller/Api/ContainerDdController.php` | Create | D&D actions at `/dd/...` |
| `src/Command/RunDdAccrualCommand.php` | Create | Nightly `app:dd:run-accrual` |

### Client BO (`make-cargo-client-bo`)

| File | Status | Purpose |
|------|--------|---------|
| `src/services/DdService.js` | Create | All D&D API calls |
| `src/pages/library/free-time-agreement.vue` | Create | CRUD library page with rate-tier editor |
| `src/pages/report/dd-dashboard.vue` | Create | Accruing D&D dashboard |
| `src/config/navigation/index.js` | Modify | Add Library + Reports nav entries |

---

### Task 1: Database Migrations

**Files:**
- Create: `migrations/mysql/Version20260624270000.php`
- Create: `migrations/sqlite/Version20260624270000.php`
- Create: `migrations/mysql/Version20260624280000.php`
- Create: `migrations/sqlite/Version20260624280000.php`

- [ ] **Step 1: Create MySQL migration for free_time_agreement**

Create `migrations/mysql/Version20260624270000.php`:

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624270000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create free_time_agreement table'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE free_time_agreement (
            id INT AUTO_INCREMENT NOT NULL,
            carrier_id INT NOT NULL,
            port_id INT DEFAULT NULL,
            direction VARCHAR(16) NOT NULL DEFAULT 'IMPORT',
            container_type VARCHAR(8) DEFAULT NULL,
            free_type VARCHAR(16) NOT NULL DEFAULT 'DETENTION',
            free_days SMALLINT NOT NULL DEFAULT 7,
            rate_tiers JSON NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            effective_from DATE NOT NULL,
            effective_to DATE DEFAULT NULL,
            created_date DATETIME DEFAULT NULL,
            updated_date DATETIME DEFAULT NULL,
            INDEX IDX_fta_carrier (carrier_id),
            INDEX IDX_fta_port (port_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE free_time_agreement
            ADD CONSTRAINT FK_fta_carrier FOREIGN KEY (carrier_id) REFERENCES partner (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_fta_port FOREIGN KEY (port_id) REFERENCES port (id) ON DELETE SET NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE free_time_agreement DROP FOREIGN KEY FK_fta_carrier");
        $this->addSql("ALTER TABLE free_time_agreement DROP FOREIGN KEY FK_fta_port");
        $this->addSql("DROP TABLE free_time_agreement");
    }
}
```

- [ ] **Step 2: Create SQLite migration for free_time_agreement**

Create `migrations/sqlite/Version20260624270000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624270000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create free_time_agreement table (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE free_time_agreement (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            carrier_id INTEGER NOT NULL,
            port_id INTEGER DEFAULT NULL,
            direction VARCHAR(16) NOT NULL DEFAULT 'IMPORT',
            container_type VARCHAR(8) DEFAULT NULL,
            free_type VARCHAR(16) NOT NULL DEFAULT 'DETENTION',
            free_days SMALLINT NOT NULL DEFAULT 7,
            rate_tiers CLOB NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            effective_from DATE NOT NULL,
            effective_to DATE DEFAULT NULL,
            created_date DATETIME DEFAULT NULL,
            updated_date DATETIME DEFAULT NULL
        )");
        $this->addSql("CREATE INDEX IDX_fta_carrier ON free_time_agreement (carrier_id)");
        $this->addSql("CREATE INDEX IDX_fta_port ON free_time_agreement (port_id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE free_time_agreement");
    }
}
```

- [ ] **Step 3: Create MySQL migration for container_dd_tracking**

Create `migrations/mysql/Version20260624280000.php`:

```php
<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624280000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create container_dd_tracking table'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE container_dd_tracking (
            id INT AUTO_INCREMENT NOT NULL,
            shipment_id INT NOT NULL,
            container_number VARCHAR(32) NOT NULL,
            free_time_agreement_id INT DEFAULT NULL,
            dd_type VARCHAR(16) NOT NULL DEFAULT 'DETENTION',
            free_start_date DATE NOT NULL,
            free_end_date DATE NOT NULL,
            free_days SMALLINT NOT NULL DEFAULT 0,
            actual_return_date DATE DEFAULT NULL,
            days_used SMALLINT DEFAULT NULL,
            chargeable_days SMALLINT DEFAULT NULL,
            accrued_amount NUMERIC(20,6) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            is_final TINYINT(1) NOT NULL DEFAULT 0,
            last_accrual_date DATE DEFAULT NULL,
            is_invoiced TINYINT(1) NOT NULL DEFAULT 0,
            is_disputed TINYINT(1) NOT NULL DEFAULT 0,
            dispute_reason LONGTEXT DEFAULT NULL,
            created_date DATETIME DEFAULT NULL,
            updated_date DATETIME DEFAULT NULL,
            INDEX IDX_dd_shipment (shipment_id),
            INDEX IDX_dd_fta (free_time_agreement_id),
            INDEX IDX_dd_not_final (is_final),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE container_dd_tracking
            ADD CONSTRAINT FK_dd_shipment FOREIGN KEY (shipment_id) REFERENCES shipment (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_dd_fta FOREIGN KEY (free_time_agreement_id) REFERENCES free_time_agreement (id) ON DELETE SET NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE container_dd_tracking DROP FOREIGN KEY FK_dd_shipment");
        $this->addSql("ALTER TABLE container_dd_tracking DROP FOREIGN KEY FK_dd_fta");
        $this->addSql("DROP TABLE container_dd_tracking");
    }
}
```

- [ ] **Step 4: Create SQLite migration for container_dd_tracking**

Create `migrations/sqlite/Version20260624280000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624280000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create container_dd_tracking table (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE container_dd_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            shipment_id INTEGER NOT NULL,
            container_number VARCHAR(32) NOT NULL,
            free_time_agreement_id INTEGER DEFAULT NULL,
            dd_type VARCHAR(16) NOT NULL DEFAULT 'DETENTION',
            free_start_date DATE NOT NULL,
            free_end_date DATE NOT NULL,
            free_days SMALLINT NOT NULL DEFAULT 0,
            actual_return_date DATE DEFAULT NULL,
            days_used SMALLINT DEFAULT NULL,
            chargeable_days SMALLINT DEFAULT NULL,
            accrued_amount NUMERIC(20,6) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            is_final INTEGER NOT NULL DEFAULT 0,
            last_accrual_date DATE DEFAULT NULL,
            is_invoiced INTEGER NOT NULL DEFAULT 0,
            is_disputed INTEGER NOT NULL DEFAULT 0,
            dispute_reason CLOB DEFAULT NULL,
            created_date DATETIME DEFAULT NULL,
            updated_date DATETIME DEFAULT NULL
        )");
        $this->addSql("CREATE INDEX IDX_dd_shipment ON container_dd_tracking (shipment_id)");
        $this->addSql("CREATE INDEX IDX_dd_fta ON container_dd_tracking (free_time_agreement_id)");
        $this->addSql("CREATE INDEX IDX_dd_not_final ON container_dd_tracking (is_final)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE container_dd_tracking");
    }
}
```

- [ ] **Step 5: Verify migration files exist**

Run: `ls migrations/mysql/Version202606242[78]0000.php migrations/sqlite/Version202606242[78]0000.php`

Expected: 4 files listed.

- [ ] **Step 6: Commit**

```bash
git add migrations/mysql/Version20260624270000.php migrations/sqlite/Version20260624270000.php migrations/mysql/Version20260624280000.php migrations/sqlite/Version20260624280000.php
git commit -m "feat: add migrations for free_time_agreement and container_dd_tracking"
```

---

### Task 2: FreeTimeAgreement Entity + Repository

**Files:**
- Create: `src/Entity/FreeTimeAgreement.php`
- Create: `src/Repository/FreeTimeAgreementRepository.php`

- [ ] **Step 1: Create FreeTimeAgreement entity**

Create `src/Entity/FreeTimeAgreement.php`:

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\FreeTimeAgreementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FreeTimeAgreementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FreeTimeAgreement
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $carrier = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Port $port = null;

    #[ORM\Column(length: 16)]
    private string $direction = 'IMPORT';

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $containerType = null;

    #[ORM\Column(length: 16)]
    private string $freeType = 'DETENTION';

    #[ORM\Column(type: Types::SMALLINT)]
    private int $freeDays = 7;

    #[ORM\Column(type: Types::JSON)]
    private array $rateTiers = [];

    #[ORM\Column(length: 3)]
    private string $currency = 'USD';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }

    public function getCarrier(): ?Provider { return $this->carrier; }
    public function setCarrier(?Provider $carrier): static { $this->carrier = $carrier; return $this; }

    public function getPort(): ?Port { return $this->port; }
    public function setPort(?Port $port): static { $this->port = $port; return $this; }

    public function getDirection(): string { return $this->direction; }
    public function setDirection(string $direction): static { $this->direction = $direction; return $this; }

    public function getContainerType(): ?string { return $this->containerType; }
    public function setContainerType(?string $containerType): static { $this->containerType = $containerType; return $this; }

    public function getFreeType(): string { return $this->freeType; }
    public function setFreeType(string $freeType): static { $this->freeType = $freeType; return $this; }

    public function getFreeDays(): int { return $this->freeDays; }
    public function setFreeDays(int $freeDays): static { $this->freeDays = $freeDays; return $this; }

    public function getRateTiers(): array { return $this->rateTiers; }
    public function setRateTiers(array $rateTiers): static { $this->rateTiers = $rateTiers; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'carrier'        => $this->carrier ? ['id' => $this->carrier->getId(), 'name' => $this->carrier->getName()] : null,
            'port'           => $this->port ? ['id' => $this->port->getId(), 'name' => $this->port->getName(), 'code' => $this->port->getCode()] : null,
            'direction'      => $this->direction,
            'containerType'  => $this->containerType,
            'freeType'       => $this->freeType,
            'freeDays'       => $this->freeDays,
            'rateTiers'      => $this->rateTiers,
            'currency'       => $this->currency,
            'effectiveFrom'  => $this->effectiveFrom?->format('Y-m-d'),
            'effectiveTo'    => $this->effectiveTo?->format('Y-m-d'),
            'createdDate'    => $this->createdDate?->format('Y-m-d H:i:s'),
        ];
    }
}
```

- [ ] **Step 2: Create FreeTimeAgreementRepository**

Create `src/Repository/FreeTimeAgreementRepository.php`:

```php
<?php
namespace App\Repository;

use App\Entity\FreeTimeAgreement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FreeTimeAgreementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FreeTimeAgreement::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.carrier', 'c')
            ->leftJoin('f.port', 'p')
            ->addSelect('c', 'p')
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('f.effectiveFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCarrier(int $carrierId): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.port', 'p')
            ->addSelect('p')
            ->where('f.carrier = :carrier')
            ->setParameter('carrier', $carrierId)
            ->orderBy('f.effectiveFrom', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(FreeTimeAgreement $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FreeTimeAgreement $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
```

- [ ] **Step 3: Verify no syntax errors**

Run: `php bin/console doctrine:mapping:info 2>&1 | grep -i "FreeTime"`

Expected: `App\Entity\FreeTimeAgreement` listed as OK.

- [ ] **Step 4: Commit**

```bash
git add src/Entity/FreeTimeAgreement.php src/Repository/FreeTimeAgreementRepository.php
git commit -m "feat: add FreeTimeAgreement entity and repository"
```

---

### Task 3: ContainerDdTracking Entity + Repository

**Files:**
- Create: `src/Entity/ContainerDdTracking.php`
- Create: `src/Repository/ContainerDdTrackingRepository.php`

- [ ] **Step 1: Create ContainerDdTracking entity**

Create `src/Entity/ContainerDdTracking.php`:

```php
<?php
namespace App\Entity;

use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\ContainerDdTrackingRepository;
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
```

- [ ] **Step 2: Create ContainerDdTrackingRepository**

Create `src/Repository/ContainerDdTrackingRepository.php`:

```php
<?php
namespace App\Repository;

use App\Entity\ContainerDdTracking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ContainerDdTrackingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContainerDdTracking::class);
    }

    public function findByShipment(int $shipmentId): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.freeTimeAgreement', 'f')
            ->addSelect('f')
            ->where('d.shipment = :shipment')
            ->setParameter('shipment', $shipmentId)
            ->orderBy('d.containerNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findAccruing(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.freeTimeAgreement', 'f')
            ->addSelect('f')
            ->where('d.isFinal = :false')
            ->andWhere('d.freeEndDate < :today')
            ->setParameter('false', false)
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getResult();
    }

    public function findDashboard(): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.shipment', 's')
            ->leftJoin('d.freeTimeAgreement', 'f')
            ->addSelect('s', 'f')
            ->where('d.isFinal = :false')
            ->setParameter('false', false)
            ->orderBy('d.accruedAmount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function save(ContainerDdTracking $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ContainerDdTracking $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
```

- [ ] **Step 3: Verify doctrine mapping**

Run: `php bin/console doctrine:mapping:info 2>&1 | grep -i "ContainerDd"`

Expected: `App\Entity\ContainerDdTracking` listed.

- [ ] **Step 4: Commit**

```bash
git add src/Entity/ContainerDdTracking.php src/Repository/ContainerDdTrackingRepository.php
git commit -m "feat: add ContainerDdTracking entity and repository"
```

---

### Task 4: DdCalculatorService

**Files:**
- Create: `src/Service/DdCalculatorService.php`

- [ ] **Step 1: Create DdCalculatorService**

Create `src/Service/DdCalculatorService.php`:

```php
<?php
namespace App\Service;

use App\Entity\ContainerDdTracking;

class DdCalculatorService
{
    /**
     * Calculate D&D charge from tiered rate table.
     * Rate tiers format: [{"from_day": 1, "to_day": 5, "rate_per_day": 50}, {"from_day": 6, "to_day": null, "rate_per_day": 100}]
     * to_day null means open-ended (applies to all remaining days).
     */
    public function calculateCharge(array $rateTiers, int $chargeableDays): float
    {
        if ($chargeableDays <= 0 || empty($rateTiers)) {
            return 0.0;
        }

        usort($rateTiers, fn($a, $b) => ($a['from_day'] ?? 1) <=> ($b['from_day'] ?? 1));

        $total    = 0.0;
        $daysLeft = $chargeableDays;

        foreach ($rateTiers as $tier) {
            if ($daysLeft <= 0) {
                break;
            }
            $from     = (int) ($tier['from_day'] ?? 1);
            $to       = isset($tier['to_day']) && $tier['to_day'] !== null ? (int) $tier['to_day'] : PHP_INT_MAX;
            $tierDays = min($daysLeft, $to - $from + 1);
            $total   += $tierDays * (float) ($tier['rate_per_day'] ?? 0);
            $daysLeft -= $tierDays;
        }

        return round($total, 4);
    }

    /**
     * Returns number of days beyond the free-end date as of $asOf.
     * Returns 0 if $asOf is on or before $freeEndDate.
     */
    public function computeChargeableDays(\DateTimeInterface $freeEndDate, \DateTimeInterface $asOf): int
    {
        $end = \DateTimeImmutable::createFromInterface($freeEndDate)->setTime(0, 0, 0);
        $as  = \DateTimeImmutable::createFromInterface($asOf)->setTime(0, 0, 0);

        if ($as <= $end) {
            return 0;
        }

        return (int) $end->diff($as)->days;
    }

    /**
     * Update accrual fields on a ContainerDdTracking record.
     * No-op if the record is already final or has no freeEndDate.
     */
    public function updateAccrual(ContainerDdTracking $record, \DateTimeInterface $asOf): void
    {
        if ($record->isFinal() || $record->getFreeEndDate() === null) {
            return;
        }

        $chargeable = $this->computeChargeableDays($record->getFreeEndDate(), $asOf);
        $amount     = $this->calculateCharge(
            $record->getFreeTimeAgreement()?->getRateTiers() ?? [],
            $chargeable
        );

        $record
            ->setChargeableDays($chargeable)
            ->setAccruedAmount((string) $amount)
            ->setLastAccrualDate($asOf);
    }

    /**
     * Finalise a record when the container has been returned.
     * Sets actualReturnDate, calculates final daysUsed and chargeableDays, marks isFinal=true.
     */
    public function finalise(ContainerDdTracking $record, \DateTimeInterface $returnDate): void
    {
        $record->setActualReturnDate($returnDate);

        if ($record->getFreeStartDate() !== null) {
            $start    = \DateTimeImmutable::createFromInterface($record->getFreeStartDate())->setTime(0, 0, 0);
            $returned = \DateTimeImmutable::createFromInterface($returnDate)->setTime(0, 0, 0);
            $daysUsed = (int) $start->diff($returned)->days;
            $record->setDaysUsed($daysUsed);
        }

        $this->updateAccrual($record, $returnDate);
        $record->setIsFinal(true);
    }
}
```

- [ ] **Step 2: Verify the class loads**

Run: `php bin/console debug:container App\\Service\\DdCalculatorService 2>&1`

Expected: Service listed with class `App\Service\DdCalculatorService`.

- [ ] **Step 3: Commit**

```bash
git add src/Service/DdCalculatorService.php
git commit -m "feat: add DdCalculatorService for tier-based D&D charge calculation"
```

---

### Task 5: FreeTimeAgreementController

**Files:**
- Create: `src/Controller/Api/FreeTimeAgreementController.php`

The controller provides full CRUD for the `FreeTimeAgreement` entity. It extends `AbstractController` directly (not `CrudController`) because the rate-tier editing is custom and not compatible with the generic service-locator approach.

Routes: `GET /free-time-agreement`, `GET /free-time-agreement/{id}`, `POST /free-time-agreement`, `PUT /free-time-agreement/{id}`, `DELETE /free-time-agreement/{id}`

- [ ] **Step 1: Create FreeTimeAgreementController**

Create `src/Controller/Api/FreeTimeAgreementController.php`:

```php
<?php
namespace App\Controller\Api;

use App\Entity\FreeTimeAgreement;
use App\Repository\FreeTimeAgreementRepository;
use App\Repository\PortRepository;
use App\Repository\ProviderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/free-time-agreement')]
#[IsGranted('ROLE_USER')]
class FreeTimeAgreementController extends AbstractController
{
    public function __construct(
        private readonly FreeTimeAgreementRepository $repository,
        private readonly ProviderRepository          $providerRepository,
        private readonly PortRepository              $portRepository,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $carrierId = $request->query->get('carrierId');
        $entities  = $carrierId
            ? $this->repository->findByCarrier((int) $carrierId)
            : $this->repository->findAllOrdered();

        return $this->json(array_map(fn($e) => $e->toArray(), $entities));
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($entity->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true) ?? [];
        $entity = $this->hydrate(new FreeTimeAgreement(), $data);
        $this->repository->save($entity);
        return $this->json($entity->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($entity, $data);
        $this->repository->save($entity);
        return $this->json($entity->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $entity = $this->repository->find($id);
        if (!$entity) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $this->repository->remove($entity);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(FreeTimeAgreement $entity, array $data): FreeTimeAgreement
    {
        if (isset($data['carrierId'])) {
            $carrier = $this->providerRepository->find((int) $data['carrierId']);
            $entity->setCarrier($carrier);
        }
        if (array_key_exists('portId', $data)) {
            $port = $data['portId'] ? $this->portRepository->find((int) $data['portId']) : null;
            $entity->setPort($port);
        }
        if (isset($data['direction']))     $entity->setDirection($data['direction']);
        if (array_key_exists('containerType', $data)) $entity->setContainerType($data['containerType'] ?: null);
        if (isset($data['freeType']))      $entity->setFreeType($data['freeType']);
        if (isset($data['freeDays']))      $entity->setFreeDays((int) $data['freeDays']);
        if (isset($data['rateTiers']))     $entity->setRateTiers((array) $data['rateTiers']);
        if (isset($data['currency']))      $entity->setCurrency($data['currency']);
        if (isset($data['effectiveFrom'])) $entity->setEffectiveFrom(new \DateTime($data['effectiveFrom']));
        if (array_key_exists('effectiveTo', $data)) {
            $entity->setEffectiveTo($data['effectiveTo'] ? new \DateTime($data['effectiveTo']) : null);
        }
        return $entity;
    }
}
```

- [ ] **Step 2: Verify route is registered**

Run: `php bin/console debug:router 2>&1 | grep free-time`

Expected: Lines showing GET/POST/PUT/DELETE routes for `/free-time-agreement`.

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/FreeTimeAgreementController.php
git commit -m "feat: add FreeTimeAgreementController CRUD endpoints"
```

---

### Task 6: ContainerDdController

**Files:**
- Create: `src/Controller/Api/ContainerDdController.php`

Routes:
- `GET /dd/shipment/{shipmentId}` — list D&D records for a shipment
- `POST /dd/shipment/{shipmentId}` — create a D&D record
- `PUT /dd/{id}` — update a D&D record (dates, currency, etc.)
- `POST /dd/{id}/return` — record container return (finalises record)
- `POST /dd/{id}/dispute` — mark record as disputed
- `DELETE /dd/{id}` — delete
- `GET /dd/dashboard` — all accruing (non-final) records ordered by accrued amount

The `/dd/` prefix avoids conflicts with `ShipmentController` at `/shipment/`.

- [ ] **Step 1: Create ContainerDdController**

Create `src/Controller/Api/ContainerDdController.php`:

```php
<?php
namespace App\Controller\Api;

use App\Entity\ContainerDdTracking;
use App\Repository\ContainerDdTrackingRepository;
use App\Repository\FreeTimeAgreementRepository;
use App\Repository\ShipmentRepository;
use App\Service\DdCalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dd')]
#[IsGranted('ROLE_USER')]
class ContainerDdController extends AbstractController
{
    public function __construct(
        private readonly ContainerDdTrackingRepository $repository,
        private readonly ShipmentRepository            $shipmentRepository,
        private readonly FreeTimeAgreementRepository   $ftaRepository,
        private readonly DdCalculatorService           $calculator,
    ) {}

    #[Route('/dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $records = $this->repository->findDashboard();
        return $this->json(array_map(fn($r) => $r->toArray(), $records));
    }

    #[Route('/shipment/{shipmentId}', methods: ['GET'], requirements: ['shipmentId' => '\d+'])]
    public function listByShipment(int $shipmentId): JsonResponse
    {
        $records = $this->repository->findByShipment($shipmentId);
        return $this->json(array_map(fn($r) => $r->toArray(), $records));
    }

    #[Route('/shipment/{shipmentId}', methods: ['POST'], requirements: ['shipmentId' => '\d+'])]
    public function create(int $shipmentId, Request $request): JsonResponse
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) {
            return $this->json(['error' => 'Shipment not found'], Response::HTTP_NOT_FOUND);
        }

        $data   = json_decode($request->getContent(), true) ?? [];
        $record = (new ContainerDdTracking())->setShipment($shipment);
        $this->hydrate($record, $data);
        $this->calculator->updateAccrual($record, new \DateTime('today'));
        $this->repository->save($record);

        return $this->json($record->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $this->hydrate($record, $data);
        if (!$record->isFinal()) {
            $this->calculator->updateAccrual($record, new \DateTime('today'));
        }
        $this->repository->save($record);

        return $this->json($record->toArray());
    }

    #[Route('/{id}/return', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function recordReturn(int $id, Request $request): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $data       = json_decode($request->getContent(), true) ?? [];
        $returnDate = isset($data['returnDate'])
            ? new \DateTime($data['returnDate'])
            : new \DateTime('today');

        $this->calculator->finalise($record, $returnDate);
        $this->repository->save($record);

        return $this->json($record->toArray());
    }

    #[Route('/{id}/dispute', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function dispute(int $id, Request $request): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $record->setIsDisputed(true);
        if (isset($data['reason'])) {
            $record->setDisputeReason($data['reason']);
        }
        $this->repository->save($record);

        return $this->json($record->toArray());
    }

    #[Route('/{id}', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $record = $this->repository->find($id);
        if (!$record) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }
        $this->repository->remove($record);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function hydrate(ContainerDdTracking $record, array $data): ContainerDdTracking
    {
        if (isset($data['containerNumber']))    $record->setContainerNumber($data['containerNumber']);
        if (isset($data['ddType']))             $record->setDdType($data['ddType']);
        if (isset($data['currency']))           $record->setCurrency($data['currency']);
        if (isset($data['freeDays']))           $record->setFreeDays((int) $data['freeDays']);
        if (isset($data['freeStartDate']))      $record->setFreeStartDate(new \DateTime($data['freeStartDate']));
        if (isset($data['freeEndDate']))        $record->setFreeEndDate(new \DateTime($data['freeEndDate']));
        if (array_key_exists('freeTimeAgreementId', $data)) {
            $fta = $data['freeTimeAgreementId']
                ? $this->ftaRepository->find((int) $data['freeTimeAgreementId'])
                : null;
            $record->setFreeTimeAgreement($fta);
        }
        return $record;
    }
}
```

- [ ] **Step 2: Verify routes are registered**

Run: `php bin/console debug:router 2>&1 | grep " /dd"`

Expected: Lines for GET/POST/PUT/DELETE routes under `/dd/`.

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/ContainerDdController.php
git commit -m "feat: add ContainerDdController with D&D record management endpoints"
```

---

### Task 7: RunDdAccrualCommand

**Files:**
- Create: `src/Command/RunDdAccrualCommand.php`

This command runs nightly. It finds all non-final D&D records whose free period has ended, recomputes accrual as of today, and saves. Schedule with `app:dd:run-accrual`.

- [ ] **Step 1: Create RunDdAccrualCommand**

Create `src/Command/RunDdAccrualCommand.php`:

```php
<?php
namespace App\Command;

use App\Repository\ContainerDdTrackingRepository;
use App\Service\DdCalculatorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:dd:run-accrual',
    description: 'Recompute D&D accrual for all non-final container records whose free period has ended',
)]
class RunDdAccrualCommand extends Command
{
    public function __construct(
        private readonly ContainerDdTrackingRepository $repository,
        private readonly DdCalculatorService           $calculator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $io->title('D&D Accrual Run');
        $today   = new \DateTime('today');
        $records = $this->repository->findAccruing();
        $updated = 0;

        foreach ($records as $record) {
            $before = $record->getAccruedAmount();
            $this->calculator->updateAccrual($record, $today);
            $this->repository->save($record, false);

            $io->writeln(sprintf(
                '  [%d] %s / %s: %s → %s %s',
                $record->getId(),
                $record->getShipment()?->getCode() ?? 'SHP-?',
                $record->getContainerNumber(),
                $before,
                $record->getAccruedAmount(),
                $record->getCurrency(),
            ));
            $updated++;
        }

        // Flush all at once for performance
        $this->repository->getEntityManager()->flush();

        $io->success("Updated accrual for {$updated} container record(s).");
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify command is registered**

Run: `php bin/console list app:dd 2>&1`

Expected: `app:dd:run-accrual` listed.

- [ ] **Step 3: Commit**

```bash
git add src/Command/RunDdAccrualCommand.php
git commit -m "feat: add RunDdAccrualCommand for nightly D&D accrual recomputation"
```

---

### Task 8: BO — DdService.js + Free-Time-Agreement Page

**Files (in `make-cargo-client-bo`):**
- Create: `src/services/DdService.js`
- Create: `src/pages/library/free-time-agreement.vue`

- [ ] **Step 1: Create DdService.js**

Create `src/services/DdService.js`:

```js
export default {
  // Free-Time Agreements
  listFta(carrierId = null) {
    const q = carrierId ? `?carrierId=${carrierId}` : ''
    return $api(`free-time-agreement${q}`)
  },
  getFta(id) {
    return $api(`free-time-agreement/${id}`)
  },
  createFta(data) {
    return $api('free-time-agreement', {
      method: 'POST',
      body: JSON.stringify(data),
      headers: { 'Content-Type': 'application/json' },
    })
  },
  updateFta(id, data) {
    return $api(`free-time-agreement/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
      headers: { 'Content-Type': 'application/json' },
    })
  },
  deleteFta(id) {
    return $api(`free-time-agreement/${id}`, { method: 'DELETE' })
  },

  // Container D&D Tracking
  listByShipment(shipmentId) {
    return $api(`dd/shipment/${shipmentId}`)
  },
  create(shipmentId, data) {
    return $api(`dd/shipment/${shipmentId}`, {
      method: 'POST',
      body: JSON.stringify(data),
      headers: { 'Content-Type': 'application/json' },
    })
  },
  update(id, data) {
    return $api(`dd/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data),
      headers: { 'Content-Type': 'application/json' },
    })
  },
  recordReturn(id, returnDate) {
    return $api(`dd/${id}/return`, {
      method: 'POST',
      body: JSON.stringify({ returnDate }),
      headers: { 'Content-Type': 'application/json' },
    })
  },
  dispute(id, reason) {
    return $api(`dd/${id}/dispute`, {
      method: 'POST',
      body: JSON.stringify({ reason }),
      headers: { 'Content-Type': 'application/json' },
    })
  },
  deleteTracking(id) {
    return $api(`dd/${id}`, { method: 'DELETE' })
  },
  dashboard() {
    return $api('dd/dashboard')
  },
}
```

- [ ] **Step 2: Create free-time-agreement.vue**

Create `src/pages/library/free-time-agreement.vue`:

```vue
<script setup>
import DdService from '@/services/DdService'
import ProviderService from '@/services/ProviderService'

definePage({ meta: { action: 'GET', subject: 'Config' } })

const items     = ref([])
const carriers  = ref([])
const loading   = ref(false)
const dialog    = ref(false)
const saving    = ref(false)
const deleting  = ref(false)
const form      = ref(null)

const defaultEntity = () => ({
  id: null,
  carrierId: null,
  portId: null,
  direction: 'IMPORT',
  containerType: null,
  freeType: 'DETENTION',
  freeDays: 7,
  rateTiers: [{ from_day: 1, to_day: null, rate_per_day: 0 }],
  currency: 'USD',
  effectiveFrom: new Date().toISOString().slice(0, 10),
  effectiveTo: null,
})

const entity    = ref(defaultEntity())
const isEdit    = computed(() => !!entity.value.id)

const headers = [
  { title: 'Carrier',         key: 'carrier.name' },
  { title: 'Port',            key: 'port.code', value: r => r.port?.code ?? 'Any' },
  { title: 'Direction',       key: 'direction' },
  { title: 'Type',            key: 'freeType' },
  { title: 'Container',       key: 'containerType', value: r => r.containerType ?? 'Any' },
  { title: 'Free Days',       key: 'freeDays' },
  { title: 'Currency',        key: 'currency' },
  { title: 'Effective From',  key: 'effectiveFrom' },
  { title: 'Effective To',    key: 'effectiveTo', value: r => r.effectiveTo ?? 'Open' },
  { title: '',                key: 'actions', sortable: false, align: 'end' },
]

async function load() {
  loading.value = true
  const [ftaList, carrierList] = await Promise.all([
    DdService.listFta(),
    ProviderService.list('providerType=carrier'),
  ])
  items.value    = ftaList
  carriers.value = carrierList?.data ?? carrierList ?? []
  loading.value  = false
}

function openCreate() {
  entity.value = defaultEntity()
  dialog.value = true
}

function openEdit(item) {
  entity.value = {
    id:             item.id,
    carrierId:      item.carrier?.id ?? null,
    portId:         item.port?.id ?? null,
    direction:      item.direction,
    containerType:  item.containerType,
    freeType:       item.freeType,
    freeDays:       item.freeDays,
    rateTiers:      JSON.parse(JSON.stringify(item.rateTiers)),
    currency:       item.currency,
    effectiveFrom:  item.effectiveFrom,
    effectiveTo:    item.effectiveTo,
  }
  dialog.value = true
}

function addTier() {
  const last = entity.value.rateTiers.at(-1)
  const nextFrom = last ? (last.to_day ? last.to_day + 1 : (last.from_day ?? 1) + 1) : 1
  entity.value.rateTiers.push({ from_day: nextFrom, to_day: null, rate_per_day: 0 })
}

function removeTier(idx) {
  entity.value.rateTiers.splice(idx, 1)
}

async function save() {
  const { valid } = await form.value.validate()
  if (!valid) return
  saving.value = true
  try {
    if (isEdit.value) {
      await DdService.updateFta(entity.value.id, entity.value)
    } else {
      await DdService.createFta(entity.value)
    }
    dialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function remove(item) {
  if (!confirm(`Delete agreement for ${item.carrier?.name}?`)) return
  deleting.value = true
  try {
    await DdService.deleteFta(item.id)
    await load()
  } finally {
    deleting.value = false
  }
}

onMounted(load)
</script>

<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Free Time Agreements</h4></VCol>
      <VCol cols="auto">
        <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreate">Add Agreement</VBtn>
      </VCol>
    </VRow>

    <VCard>
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        hover
      >
        <template #item.actions="{ item }">
          <VBtn icon variant="text" size="x-small" @click="openEdit(item)">
            <VIcon icon="tabler-pencil" size="18"/>
          </VBtn>
          <VBtn icon variant="text" size="x-small" color="error" @click="remove(item)">
            <VIcon icon="tabler-trash" size="18"/>
          </VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VDialog v-model="dialog" max-width="800px">
      <VCard>
        <VCardTitle class="pa-4 text-subtitle-1 font-weight-semibold">
          {{ isEdit ? 'Edit Free Time Agreement' : 'New Free Time Agreement' }}
        </VCardTitle>
        <VDivider />
        <VCardText>
          <VForm ref="form">
            <VRow>
              <VCol cols="12" sm="6">
                <VAutocomplete
                  v-model="entity.carrierId"
                  :items="carriers"
                  item-title="name"
                  item-value="id"
                  label="Carrier"
                  :rules="[v => !!v || 'Carrier is required']"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="6">
                <VTextField v-model="entity.portId" label="Port ID (optional)" density="compact" type="number" clearable />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="entity.direction"
                  :items="['IMPORT', 'EXPORT']"
                  label="Direction"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="entity.freeType"
                  :items="['DETENTION', 'DEMURRAGE', 'COMBINED']"
                  label="Free Type"
                  density="compact"
                />
              </VCol>
              <VCol cols="12" sm="4">
                <VSelect
                  v-model="entity.containerType"
                  :items="[{title: 'Any', value: null}, '20DC', '40DC', '40HC', '20RF', '40RF', '20OT', '40OT']"
                  label="Container Type"
                  density="compact"
                  clearable
                />
              </VCol>
              <VCol cols="12" sm="3">
                <VTextField v-model.number="entity.freeDays" label="Free Days" density="compact" type="number" :rules="[v => v >= 0 || 'Required']" />
              </VCol>
              <VCol cols="12" sm="3">
                <VTextField v-model="entity.currency" label="Currency" density="compact" :rules="[v => !!v || 'Required']" />
              </VCol>
              <VCol cols="12" sm="3">
                <VTextField v-model="entity.effectiveFrom" label="Effective From" density="compact" type="date" :rules="[v => !!v || 'Required']" />
              </VCol>
              <VCol cols="12" sm="3">
                <VTextField v-model="entity.effectiveTo" label="Effective To" density="compact" type="date" clearable />
              </VCol>
            </VRow>

            <VDivider class="my-3" />
            <div class="d-flex align-center mb-2">
              <span class="text-subtitle-2 font-weight-semibold">Rate Tiers</span>
              <VSpacer />
              <VBtn size="x-small" prepend-icon="tabler-plus" variant="tonal" @click="addTier">Add Tier</VBtn>
            </div>

            <VTable density="compact">
              <thead>
                <tr>
                  <th>From Day</th>
                  <th>To Day</th>
                  <th>Rate / Day</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(tier, idx) in entity.rateTiers" :key="idx">
                  <td style="width: 120px">
                    <VTextField v-model.number="tier.from_day" density="compact" type="number" hide-details />
                  </td>
                  <td style="width: 120px">
                    <VTextField v-model.number="tier.to_day" density="compact" type="number" hide-details placeholder="open" clearable />
                  </td>
                  <td>
                    <VTextField v-model.number="tier.rate_per_day" density="compact" type="number" hide-details />
                  </td>
                  <td style="width: 40px">
                    <VBtn icon variant="text" size="x-small" color="error" @click="removeTier(idx)" :disabled="entity.rateTiers.length === 1">
                      <VIcon icon="tabler-trash" size="16"/>
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VForm>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="dialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="saving" @click="save">Save</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
```

- [ ] **Step 3: Commit**

```bash
git add src/services/DdService.js src/pages/library/free-time-agreement.vue
git commit -m "feat: add DdService and free-time-agreement library page"
```

---

### Task 9: BO — D&D Dashboard + Navigation

**Files (in `make-cargo-client-bo`):**
- Create: `src/pages/report/dd-dashboard.vue`
- Modify: `src/config/navigation/index.js`

- [ ] **Step 1: Create dd-dashboard.vue**

Create `src/pages/report/dd-dashboard.vue`:

```vue
<script setup>
import DdService from '@/services/DdService'

definePage({ meta: { action: 'GET', subject: 'EbitNote' } })

const items        = ref([])
const loading      = ref(false)
const returnDialog = ref(false)
const disputeDialog = ref(false)
const selectedId   = ref(null)
const returnDate   = ref(new Date().toISOString().slice(0, 10))
const disputeReason = ref('')
const saving       = ref(false)

const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const headers = [
  { title: 'Shipment',       key: 'shipmentCode', value: r => r.shipmentCode ?? `#${r.shipmentId}` },
  { title: 'Container',      key: 'containerNumber' },
  { title: 'Type',           key: 'ddType' },
  { title: 'Free End',       key: 'freeEndDate' },
  { title: 'Chargeable Days', key: 'chargeableDays' },
  { title: 'Accrued',        key: 'accruedAmount', value: r => `${fmt(r.accruedAmount)} ${r.currency}` },
  { title: 'Last Accrual',   key: 'lastAccrualDate' },
  { title: 'Disputed',       key: 'isDisputed', value: r => r.isDisputed ? 'Yes' : '' },
  { title: '',               key: 'actions', sortable: false, align: 'end' },
]

async function load() {
  loading.value = true
  items.value   = await DdService.dashboard()
  loading.value = false
}

function openReturn(id) {
  selectedId.value = id
  returnDate.value = new Date().toISOString().slice(0, 10)
  returnDialog.value = true
}

function openDispute(id) {
  selectedId.value  = id
  disputeReason.value = ''
  disputeDialog.value = true
}

async function confirmReturn() {
  saving.value = true
  try {
    await DdService.recordReturn(selectedId.value, returnDate.value)
    returnDialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

async function confirmDispute() {
  saving.value = true
  try {
    await DdService.dispute(selectedId.value, disputeReason.value)
    disputeDialog.value = false
    await load()
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">D&D Dashboard</h4></VCol>
      <VCol cols="auto">
        <VBtn variant="tonal" :loading="loading" prepend-icon="tabler-refresh" @click="load">Refresh</VBtn>
      </VCol>
    </VRow>

    <VCard>
      <VDataTable
        :headers="headers"
        :items="items"
        :loading="loading"
        hover
      >
        <template #item.isDisputed="{ item }">
          <VChip v-if="item.isDisputed" color="warning" size="x-small">Disputed</VChip>
        </template>
        <template #item.accruedAmount="{ item }">
          <span :class="parseFloat(item.accruedAmount) > 0 ? 'text-error font-weight-bold' : ''">
            {{ fmt(item.accruedAmount) }} {{ item.currency }}
          </span>
        </template>
        <template #item.actions="{ item }">
          <VBtn
            v-if="!item.isFinal"
            size="x-small" variant="tonal" color="success" class="mr-1"
            @click="openReturn(item.id)"
          >Return</VBtn>
          <VBtn
            v-if="!item.isDisputed"
            size="x-small" variant="tonal" color="warning"
            @click="openDispute(item.id)"
          >Dispute</VBtn>
        </template>
      </VDataTable>
    </VCard>

    <VDialog v-model="returnDialog" max-width="400px">
      <VCard>
        <VCardTitle class="pa-4 text-subtitle-1 font-weight-semibold">Record Container Return</VCardTitle>
        <VCardText>
          <VTextField v-model="returnDate" type="date" label="Return Date" density="compact" />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="returnDialog = false">Cancel</VBtn>
          <VBtn color="success" :loading="saving" @click="confirmReturn">Confirm Return</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="disputeDialog" max-width="400px">
      <VCard>
        <VCardTitle class="pa-4 text-subtitle-1 font-weight-semibold">Mark as Disputed</VCardTitle>
        <VCardText>
          <VTextField v-model="disputeReason" label="Dispute Reason" density="compact" />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="disputeDialog = false">Cancel</VBtn>
          <VBtn color="warning" :loading="saving" @click="confirmDispute">Confirm</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
```

- [ ] **Step 2: Add navigation entries**

In `src/config/navigation/index.js`, add Free Time Agreements to the Library section (after Carrier Event Mappings) and D&D Dashboard to the Reports section (after Exceptions):

In the `Library` children array, after the Carrier Event Mappings entry:
```js
      {
        title: $gettext('Free Time Agreements'),
        to: { name: 'library-free-time-agreement' },
        subject: 'Config',
        action: 'GET'
      }
```

In the `Reports` children array, after the Exceptions entry:
```js
      {
        title: $gettext('D&D Dashboard'),
        to: { name: 'report-dd-dashboard' },
        subject: 'EbitNote',
        action: 'GET',
      },
```

- [ ] **Step 3: Verify navigation file is correct**

Open `src/config/navigation/index.js` and confirm both entries are present with no syntax errors.

- [ ] **Step 4: Commit**

```bash
git add src/pages/report/dd-dashboard.vue src/config/navigation/index.js
git commit -m "feat: add D&D dashboard page and navigation entries"
```

---

### Task 10: Documentation

**Files (in `make-cargo-client`):**
- Create: `docs/guides/detention-demurrage.md`

- [ ] **Step 1: Write the guide**

Create `docs/guides/detention-demurrage.md`:

```markdown
# Detention & Demurrage — Setup & Operations Guide

## Overview

The D&D module tracks per-container detention and demurrage exposure for ocean shipments.
It stores tiered rate agreements per carrier/port, calculates accruing charges daily, and
provides a dashboard for monitoring outstanding exposure.

## Architecture

```
FreeTimeAgreement (library)
  └─ Carrier + optional Port + rate tiers (JSON)
  └─ direction: IMPORT | EXPORT
  └─ freeType: DETENTION | DEMURRAGE | COMBINED

ContainerDdTracking (per shipment container)
  └─ links to Shipment (CASCADE delete)
  └─ links to FreeTimeAgreement (nullable)
  └─ freeStartDate / freeEndDate / freeDays
  └─ accrued_amount updated nightly by RunDdAccrualCommand

DdCalculatorService
  └─ calculateCharge(rateTiers, chargeableDays) → float
  └─ computeChargeableDays(freeEndDate, asOf) → int
  └─ updateAccrual(record, asOf) → void
  └─ finalise(record, returnDate) → void (sets isFinal=true)

RunDdAccrualCommand (app:dd:run-accrual — daily cron)
  └─ finds all is_final=false WHERE free_end_date < today
  └─ calls DdCalculatorService::updateAccrual per record
  └─ bulk flushes
```

## Rate Tier Format

`rate_tiers` is a JSON array stored in `free_time_agreement.rate_tiers`:

```json
[
  { "from_day": 1,  "to_day": 5,   "rate_per_day": 50 },
  { "from_day": 6,  "to_day": 10,  "rate_per_day": 75 },
  { "from_day": 11, "to_day": null, "rate_per_day": 120 }
]
```

- `to_day: null` means open-ended (applies to all remaining days).
- `from_day` / `to_day` are **1-indexed** counting from the first chargeable day (day after free period ends).

## D&D Types

| Value | Meaning |
|-------|---------|
| `DETENTION` | Charge for keeping the container at your premises |
| `DEMURRAGE` | Charge for keeping the container at the port/terminal |
| `COMBINED` | Single agreement covering both |

## Running the Accrual Command

```bash
# Run manually
php bin/console app:dd:run-accrual

# Cron (every night at 03:00)
0 3 * * * /path/to/project/bin/console app:dd:run-accrual >> /var/log/dd-accrual.log 2>&1
```

The command:
1. Queries all `container_dd_tracking` rows where `is_final = 0` AND `free_end_date < TODAY`
2. For each record, calls `DdCalculatorService::updateAccrual()` with today's date
3. Updates `chargeable_days`, `accrued_amount`, `last_accrual_date`
4. Bulk-flushes all changes

## API Endpoints

### Free Time Agreements

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/free-time-agreement` | GET | List all (optionally `?carrierId=X`) |
| `GET /api/free-time-agreement/{id}` | GET | Get one |
| `POST /api/free-time-agreement` | POST | Create |
| `PUT /api/free-time-agreement/{id}` | PUT | Update |
| `DELETE /api/free-time-agreement/{id}` | DELETE | Delete |

**POST/PUT body example:**
```json
{
  "carrierId": 42,
  "portId": null,
  "direction": "IMPORT",
  "containerType": "40HC",
  "freeType": "DETENTION",
  "freeDays": 7,
  "rateTiers": [
    { "from_day": 1, "to_day": 5, "rate_per_day": 50 },
    { "from_day": 6, "to_day": null, "rate_per_day": 100 }
  ],
  "currency": "USD",
  "effectiveFrom": "2026-01-01",
  "effectiveTo": null
}
```

### Container D&D Tracking

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/dd/dashboard` | GET | All accruing records, ordered by accrued amount |
| `GET /api/dd/shipment/{id}` | GET | D&D records for one shipment |
| `POST /api/dd/shipment/{id}` | POST | Create D&D record for a shipment container |
| `PUT /api/dd/{id}` | PUT | Update dates / currency / FTA link |
| `POST /api/dd/{id}/return` | POST | Record empty return (finalises accrual) |
| `POST /api/dd/{id}/dispute` | POST | Mark record as disputed |
| `DELETE /api/dd/{id}` | DELETE | Delete record |

**POST /api/dd/shipment/{id} body:**
```json
{
  "containerNumber": "MSKU1234567",
  "ddType": "DETENTION",
  "freeTimeAgreementId": 5,
  "freeStartDate": "2026-06-01",
  "freeEndDate": "2026-06-07",
  "freeDays": 7,
  "currency": "USD"
}
```

**POST /api/dd/{id}/return body:**
```json
{ "returnDate": "2026-06-15" }
```

**POST /api/dd/{id}/dispute body:**
```json
{ "reason": "Carrier miscounted free days" }
```

## Back-Office Features

### Library → Free Time Agreements

- Lists all free-time agreements grouped by carrier
- Add/Edit dialog with dynamic rate-tier rows (add/remove tiers inline)
- Container type can be left blank (= applies to all types)
- Port can be left blank (= applies to all ports for that carrier)

### Reports → D&D Dashboard

- Shows all accruing (non-final) D&D records sorted by `accrued_amount` DESC
- "Return" button opens a date-picker dialog → calls `/dd/{id}/return` → marks `isFinal=true`
- "Dispute" button opens a reason dialog → calls `/dd/{id}/dispute` → shows yellow chip
- Disputed rows are still accrued nightly until finalised

## Database Tables

### `free_time_agreement`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `carrier_id` | INT FK | `partner.id` ON DELETE CASCADE |
| `port_id` | INT FK | `port.id` ON DELETE SET NULL; null = any port |
| `direction` | VARCHAR(16) | `IMPORT` or `EXPORT` |
| `container_type` | VARCHAR(8) | `20DC`, `40HC`, etc.; null = any type |
| `free_type` | VARCHAR(16) | `DETENTION`, `DEMURRAGE`, `COMBINED` |
| `free_days` | SMALLINT | Number of free days included |
| `rate_tiers` | JSON | Array of `{from_day, to_day, rate_per_day}` |
| `currency` | VARCHAR(3) | ISO 4217 |
| `effective_from` | DATE | |
| `effective_to` | DATE | null = open-ended |

### `container_dd_tracking`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `shipment_id` | INT FK | `shipment.id` ON DELETE CASCADE |
| `container_number` | VARCHAR(32) | e.g. `MSKU1234567` |
| `free_time_agreement_id` | INT FK | nullable; `free_time_agreement.id` ON DELETE SET NULL |
| `dd_type` | VARCHAR(16) | `DETENTION`, `DEMURRAGE`, `COMBINED` |
| `free_start_date` | DATE | First day of free period |
| `free_end_date` | DATE | Last day of free period |
| `free_days` | SMALLINT | Snapshot of free days at creation |
| `actual_return_date` | DATE | Set when container is returned |
| `days_used` | SMALLINT | Total calendar days used |
| `chargeable_days` | SMALLINT | Days beyond free period (updated nightly) |
| `accrued_amount` | NUMERIC(20,6) | Updated nightly by accrual command |
| `currency` | VARCHAR(3) | |
| `is_final` | BOOL | True once container returned or manually closed |
| `last_accrual_date` | DATE | When accrual was last computed |
| `is_invoiced` | BOOL | Set externally when included in an invoice |
| `is_disputed` | BOOL | |
| `dispute_reason` | TEXT | |

## Files Created / Modified

### Client API (`make-cargo-client`)

| File | What |
|------|------|
| `migrations/mysql/Version20260624270000.php` | New — `free_time_agreement` table |
| `migrations/sqlite/Version20260624270000.php` | New — SQLite |
| `migrations/mysql/Version20260624280000.php` | New — `container_dd_tracking` table |
| `migrations/sqlite/Version20260624280000.php` | New — SQLite |
| `src/Entity/FreeTimeAgreement.php` | New |
| `src/Repository/FreeTimeAgreementRepository.php` | New |
| `src/Entity/ContainerDdTracking.php` | New |
| `src/Repository/ContainerDdTrackingRepository.php` | New |
| `src/Service/DdCalculatorService.php` | New |
| `src/Controller/Api/FreeTimeAgreementController.php` | New |
| `src/Controller/Api/ContainerDdController.php` | New |
| `src/Command/RunDdAccrualCommand.php` | New |

### Client BO (`make-cargo-client-bo`)

| File | What |
|------|------|
| `src/services/DdService.js` | New |
| `src/pages/library/free-time-agreement.vue` | New |
| `src/pages/report/dd-dashboard.vue` | New |
| `src/config/navigation/index.js` | Added Library + Reports nav entries |
```

- [ ] **Step 2: Commit**

```bash
git add docs/guides/detention-demurrage.md
git commit -m "docs: add detention-demurrage setup and operations guide"
```
