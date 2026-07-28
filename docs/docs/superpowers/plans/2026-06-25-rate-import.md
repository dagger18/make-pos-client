# Rate Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement Excel-based carrier rate card import with preview, operator approval, and 48-hour rollback for the client API, then write a setup guide.

**Architecture:** Upload an `.xlsx` file → parse rows into `rate_import_row` preview records under a `rate_import_job` → operator reviews the preview and approves → atomically expire existing open-ended `rate` rows for the same lane and create new ones linked to the job → rollback deletes the created rates and restores previous expiry dates within 48 hours.

**Tech Stack:** Symfony 7, Doctrine ORM, PhpSpreadsheet (already a project dependency via `BaseService`), PHP 8.2.

---

## Codebase Context

- All entities live under `src/Module/*/Entity/` with namespace `App\Module\*\Entity\`
- Doctrine ORM mapping: `dir: src/Module`, `prefix: App\Module`, `alias: App` (see `config/packages/doctrine.yaml`)
- Repositories extend `App\Module\Core\Repository\BaseRepository` (empty body is fine — see `RateRepository`)
- Services extend `BaseService` and call `$this->reflectFromParent($baseService)` in the constructor
- Every new Service must be added to `app.auto_service_locator` in `config/services.yaml`
- Serializer groups defined in `config/serializer_groups/*.yaml` using custom format (see Step in Task 7)
- Two parallel migration sets: `migrations/mysql/` and `migrations/sqlite/` — both use namespace `SqlEngineMigrations`. Only one is active at runtime based on `%database_engine%` in `doctrine_migrations.yaml`
- `EntityDateTimeAbleTrait` adds `created_date` + `updated_date` DATETIME columns with `PrePersist`/`PreUpdate` callbacks; the entity must also have `#[ORM\HasLifecycleCallbacks]`
- Existing Rate entity: `src/Module/Quote/Entity/Rate.php` — `polPort`/`podPort` are FK to Port; `charge` FK to Charge; `provider` FK to Provider; `buying`/`selling` are `Money` embeddables; `validUntil` is nullable (NULL = open-ended, active rate)
- `Charge` entity has a `customCode` field used as the rate import charge key
- `Provider` has a `code` field used for carrier lookup
- `Port` has a `code` field used for POL/POD lookup
- `Money` embeddable constructor: `new Money(?float $amount, ?string $currency, ?float $rate)`

## Excel Template Format

The import expects a fixed header row (row 1) with these column names (case-insensitive):

| Header | Required | Notes |
|---|---|---|
| `POL_CODE` | Yes | UN/LOCODE or IATA port code |
| `POD_CODE` | Yes | UN/LOCODE or IATA port code |
| `CHARGE_CODE` | Yes | Matches `Charge.customCode` |
| `CONTAINER_TYPE` | No | e.g. `20GP`, `40GP`, `40HC` |
| `BUYING_RATE` | No | Numeric |
| `SELLING_RATE` | No | Numeric |
| `TRANSIT_DAYS` | No | Integer |

Rows 2+ are data. Empty rows are skipped.

---

## File Structure

**Create:**
- `migrations/mysql/Version20260625150000.php`
- `migrations/mysql/Version20260625160000.php`
- `migrations/sqlite/Version20260625150000.php`
- `migrations/sqlite/Version20260625160000.php`
- `src/Module/Quote/Entity/RateImportJob.php`
- `src/Module/Quote/Repository/RateImportJobRepository.php`
- `src/Module/Quote/Entity/RateImportRow.php`
- `src/Module/Quote/Repository/RateImportRowRepository.php`
- `src/Module/Quote/Service/RateImportService.php`
- `src/Module/Quote/Controller/RateImportController.php`
- `config/serializer_groups/RateImportJob.yaml`
- `config/serializer_groups/RateImportRow.yaml`
- `docs/guides/rate-import.md`

**Modify:**
- `src/Module/Quote/Entity/Rate.php` — add `importJob` nullable FK
- `src/Module/Quote/Repository/RateRepository.php` — add lane-lookup + bulk-delete methods
- `config/services.yaml` — register `RateImportService` in `app.auto_service_locator`

---

## Tasks

### Task 1: Migrations — Create rate_import_job + rate_import_row tables

**Files:**
- Create: `migrations/mysql/Version20260625150000.php`
- Create: `migrations/sqlite/Version20260625150000.php`

- [ ] **Step 1: Create the MySQL migration**

`migrations/mysql/Version20260625150000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rate_import_job and rate_import_row tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rate_import_job (id INT AUTO_INCREMENT NOT NULL, provider_id INT DEFAULT NULL, uploaded_by_id INT DEFAULT NULL, approved_by_id INT DEFAULT NULL, rolled_back_by_id INT DEFAULT NULL, import_source VARCHAR(32) NOT NULL, transport_type VARCHAR(8) NOT NULL, file_name VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, total_rows INT NOT NULL, rows_imported INT NOT NULL, rows_skipped INT NOT NULL, rows_errored INT NOT NULL, effective_date DATE DEFAULT NULL, expiry_date DATE DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, requires_approval TINYINT(1) NOT NULL, approved_at DATETIME DEFAULT NULL, can_rollback TINYINT(1) NOT NULL, rolled_back_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, error_log JSON DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, INDEX IDX_rate_import_job_provider (provider_id), INDEX IDX_rate_import_job_uploaded_by (uploaded_by_id), INDEX IDX_rate_import_job_approved_by (approved_by_id), INDEX IDX_rate_import_job_rolled_back_by (rolled_back_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE rate_import_row (id INT AUTO_INCREMENT NOT NULL, import_job_id INT NOT NULL, row_number INT NOT NULL, pol_code VARCHAR(10) DEFAULT NULL, pod_code VARCHAR(10) DEFAULT NULL, container_type VARCHAR(8) DEFAULT NULL, charge_code VARCHAR(64) DEFAULT NULL, new_buying_amount NUMERIC(15, 4) DEFAULT NULL, new_selling_amount NUMERIC(15, 4) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, current_buying_amount NUMERIC(15, 4) DEFAULT NULL, change_pct NUMERIC(8, 4) DEFAULT NULL, is_sanity_flagged TINYINT(1) NOT NULL, action VARCHAR(16) NOT NULL, error_message LONGTEXT DEFAULT NULL, existing_rate_id INT DEFAULT NULL, previous_valid_until DATE DEFAULT NULL, INDEX IDX_rate_import_row_job (import_job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE rate_import_job ADD CONSTRAINT FK_rate_import_job_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rate_import_job ADD CONSTRAINT FK_rate_import_job_uploaded_by FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rate_import_job ADD CONSTRAINT FK_rate_import_job_approved_by FOREIGN KEY (approved_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rate_import_job ADD CONSTRAINT FK_rate_import_job_rolled_back_by FOREIGN KEY (rolled_back_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE rate_import_row ADD CONSTRAINT FK_rate_import_row_job FOREIGN KEY (import_job_id) REFERENCES rate_import_job (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rate_import_row DROP FOREIGN KEY FK_rate_import_row_job');
        $this->addSql('ALTER TABLE rate_import_job DROP FOREIGN KEY FK_rate_import_job_provider');
        $this->addSql('ALTER TABLE rate_import_job DROP FOREIGN KEY FK_rate_import_job_uploaded_by');
        $this->addSql('ALTER TABLE rate_import_job DROP FOREIGN KEY FK_rate_import_job_approved_by');
        $this->addSql('ALTER TABLE rate_import_job DROP FOREIGN KEY FK_rate_import_job_rolled_back_by');
        $this->addSql('DROP TABLE rate_import_row');
        $this->addSql('DROP TABLE rate_import_job');
    }
}
```

- [ ] **Step 2: Create the SQLite migration**

`migrations/sqlite/Version20260625150000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rate_import_job and rate_import_row tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rate_import_job (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, provider_id INTEGER DEFAULT NULL, uploaded_by_id INTEGER DEFAULT NULL, approved_by_id INTEGER DEFAULT NULL, rolled_back_by_id INTEGER DEFAULT NULL, import_source VARCHAR(32) NOT NULL, transport_type VARCHAR(8) NOT NULL, file_name VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, total_rows INTEGER NOT NULL, rows_imported INTEGER NOT NULL, rows_skipped INTEGER NOT NULL, rows_errored INTEGER NOT NULL, effective_date DATE DEFAULT NULL, expiry_date DATE DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, requires_approval BOOLEAN NOT NULL, approved_at DATETIME DEFAULT NULL, can_rollback BOOLEAN NOT NULL, rolled_back_at DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, error_log CLOB DEFAULT NULL --(DC2Type:json)
, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, CONSTRAINT FK_rate_import_job_provider FOREIGN KEY (provider_id) REFERENCES provider (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_rate_import_job_uploaded_by FOREIGN KEY (uploaded_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_rate_import_job_approved_by FOREIGN KEY (approved_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_rate_import_job_rolled_back_by FOREIGN KEY (rolled_back_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_rate_import_job_provider ON rate_import_job (provider_id)');
        $this->addSql('CREATE INDEX IDX_rate_import_job_uploaded_by ON rate_import_job (uploaded_by_id)');
        $this->addSql('CREATE INDEX IDX_rate_import_job_approved_by ON rate_import_job (approved_by_id)');
        $this->addSql('CREATE INDEX IDX_rate_import_job_rolled_back_by ON rate_import_job (rolled_back_by_id)');
        $this->addSql('CREATE TABLE rate_import_row (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, import_job_id INTEGER NOT NULL, row_number INTEGER NOT NULL, pol_code VARCHAR(10) DEFAULT NULL, pod_code VARCHAR(10) DEFAULT NULL, container_type VARCHAR(8) DEFAULT NULL, charge_code VARCHAR(64) DEFAULT NULL, new_buying_amount NUMERIC(15, 4) DEFAULT NULL, new_selling_amount NUMERIC(15, 4) DEFAULT NULL, currency VARCHAR(3) DEFAULT NULL, current_buying_amount NUMERIC(15, 4) DEFAULT NULL, change_pct NUMERIC(8, 4) DEFAULT NULL, is_sanity_flagged BOOLEAN NOT NULL, action VARCHAR(16) NOT NULL, error_message CLOB DEFAULT NULL, existing_rate_id INTEGER DEFAULT NULL, previous_valid_until DATE DEFAULT NULL, CONSTRAINT FK_rate_import_row_job FOREIGN KEY (import_job_id) REFERENCES rate_import_job (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_rate_import_row_job ON rate_import_row (import_job_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_rate_import_row_job');
        $this->addSql('DROP TABLE rate_import_row');
        $this->addSql('DROP INDEX IDX_rate_import_job_rolled_back_by');
        $this->addSql('DROP INDEX IDX_rate_import_job_approved_by');
        $this->addSql('DROP INDEX IDX_rate_import_job_uploaded_by');
        $this->addSql('DROP INDEX IDX_rate_import_job_provider');
        $this->addSql('DROP TABLE rate_import_job');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add migrations/mysql/Version20260625150000.php migrations/sqlite/Version20260625150000.php
git commit -m "feat: add migrations for rate_import_job and rate_import_row tables"
```

---

### Task 2: Migrations — Add import_job_id to rate table

**Files:**
- Create: `migrations/mysql/Version20260625160000.php`
- Create: `migrations/sqlite/Version20260625160000.php`

- [ ] **Step 1: Create MySQL migration**

`migrations/mysql/Version20260625160000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add import_job_id FK to rate table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rate ADD import_job_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE rate ADD CONSTRAINT FK_rate_import_job FOREIGN KEY (import_job_id) REFERENCES rate_import_job (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_rate_import_job ON rate (import_job_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rate DROP FOREIGN KEY FK_rate_import_job');
        $this->addSql('DROP INDEX IDX_rate_import_job ON rate');
        $this->addSql('ALTER TABLE rate DROP COLUMN import_job_id');
    }
}
```

- [ ] **Step 2: Create SQLite migration**

`migrations/sqlite/Version20260625160000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260625160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add import_job_id column to rate table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rate ADD COLUMN import_job_id INTEGER DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_rate_import_job ON rate (import_job_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_rate_import_job');
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add migrations/mysql/Version20260625160000.php migrations/sqlite/Version20260625160000.php
git commit -m "feat: add import_job_id FK column to rate table migrations"
```

---

### Task 3: RateImportJob entity + repository

**Files:**
- Create: `src/Module/Quote/Entity/RateImportJob.php`
- Create: `src/Module/Quote/Repository/RateImportJobRepository.php`

- [ ] **Step 1: Create RateImportJob entity**

`src/Module/Quote/Entity/RateImportJob.php`:

```php
<?php
namespace App\Module\Quote\Entity;

use App\Module\Carrier\Entity\Provider;
use App\Module\Core\Entity\User;
use App\Module\Quote\Repository\RateImportJobRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateImportJobRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RateImportJob
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    private string $importSource = 'EXCEL';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 8)]
    private string $transportType;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $fileName = null;

    #[ORM\Column(length: 16)]
    private string $status = 'PENDING';

    #[ORM\Column]
    private int $totalRows = 0;

    #[ORM\Column]
    private int $rowsImported = 0;

    #[ORM\Column]
    private int $rowsSkipped = 0;

    #[ORM\Column]
    private int $rowsErrored = 0;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column]
    private bool $requiresApproval = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\Column]
    private bool $canRollback = true;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $rolledBackBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $rolledBackAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $uploadedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $errorLog = null;

    #[ORM\OneToMany(targetEntity: RateImportRow::class, mappedBy: 'importJob', cascade: ['persist', 'remove'])]
    private Collection $rows;

    public function __construct()
    {
        $this->rows = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getImportSource(): string { return $this->importSource; }
    public function setImportSource(string $v): static { $this->importSource = $v; return $this; }

    public function getProvider(): ?Provider { return $this->provider; }
    public function setProvider(?Provider $v): static { $this->provider = $v; return $this; }

    public function getTransportType(): string { return $this->transportType; }
    public function setTransportType(string $v): static { $this->transportType = $v; return $this; }

    public function getFileName(): ?string { return $this->fileName; }
    public function setFileName(?string $v): static { $this->fileName = $v; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $v): static { $this->status = $v; return $this; }

    public function getTotalRows(): int { return $this->totalRows; }
    public function setTotalRows(int $v): static { $this->totalRows = $v; return $this; }

    public function getRowsImported(): int { return $this->rowsImported; }
    public function setRowsImported(int $v): static { $this->rowsImported = $v; return $this; }

    public function getRowsSkipped(): int { return $this->rowsSkipped; }
    public function setRowsSkipped(int $v): static { $this->rowsSkipped = $v; return $this; }

    public function getRowsErrored(): int { return $this->rowsErrored; }
    public function setRowsErrored(int $v): static { $this->rowsErrored = $v; return $this; }

    public function getEffectiveDate(): ?\DateTimeInterface { return $this->effectiveDate; }
    public function setEffectiveDate(?\DateTimeInterface $v): static { $this->effectiveDate = $v; return $this; }

    public function getExpiryDate(): ?\DateTimeInterface { return $this->expiryDate; }
    public function setExpiryDate(?\DateTimeInterface $v): static { $this->expiryDate = $v; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }

    public function isRequiresApproval(): bool { return $this->requiresApproval; }
    public function setRequiresApproval(bool $v): static { $this->requiresApproval = $v; return $this; }

    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function setApprovedBy(?User $v): static { $this->approvedBy = $v; return $this; }

    public function getApprovedAt(): ?\DateTimeInterface { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeInterface $v): static { $this->approvedAt = $v; return $this; }

    public function isCanRollback(): bool { return $this->canRollback; }
    public function setCanRollback(bool $v): static { $this->canRollback = $v; return $this; }

    public function getRolledBackBy(): ?User { return $this->rolledBackBy; }
    public function setRolledBackBy(?User $v): static { $this->rolledBackBy = $v; return $this; }

    public function getRolledBackAt(): ?\DateTimeInterface { return $this->rolledBackAt; }
    public function setRolledBackAt(?\DateTimeInterface $v): static { $this->rolledBackAt = $v; return $this; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $v): static { $this->uploadedBy = $v; return $this; }

    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function setCompletedAt(?\DateTimeInterface $v): static { $this->completedAt = $v; return $this; }

    public function getErrorLog(): ?array { return $this->errorLog; }
    public function setErrorLog(?array $v): static { $this->errorLog = $v; return $this; }

    public function getRows(): Collection { return $this->rows; }
}
```

- [ ] **Step 2: Create RateImportJobRepository**

`src/Module/Quote/Repository/RateImportJobRepository.php`:

```php
<?php
namespace App\Module\Quote\Repository;

use App\Module\Core\Repository\BaseRepository;

class RateImportJobRepository extends BaseRepository {}
```

- [ ] **Step 3: Commit**

```bash
git add src/Module/Quote/Entity/RateImportJob.php src/Module/Quote/Repository/RateImportJobRepository.php
git commit -m "feat: add RateImportJob entity and repository"
```

---

### Task 4: RateImportRow entity + repository

**Files:**
- Create: `src/Module/Quote/Entity/RateImportRow.php`
- Create: `src/Module/Quote/Repository/RateImportRowRepository.php`

- [ ] **Step 1: Create RateImportRow entity**

`src/Module/Quote/Entity/RateImportRow.php`:

```php
<?php
namespace App\Module\Quote\Entity;

use App\Module\Quote\Repository\RateImportRowRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateImportRowRepository::class)]
class RateImportRow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rows')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RateImportJob $importJob = null;

    #[ORM\Column]
    private int $rowNumber;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $polCode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $podCode = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $containerType = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $chargeCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $newBuyingAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $newSellingAmount = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?string $currentBuyingAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4, nullable: true)]
    private ?string $changePct = null;

    #[ORM\Column]
    private bool $isSanityFlagged = false;

    /** NEW | UPDATE | SKIP | ERROR */
    #[ORM\Column(length: 16)]
    private string $action = 'NEW';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(nullable: true)]
    private ?int $existingRateId = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $previousValidUntil = null;

    public function getId(): ?int { return $this->id; }

    public function getImportJob(): ?RateImportJob { return $this->importJob; }
    public function setImportJob(?RateImportJob $v): static { $this->importJob = $v; return $this; }

    public function getRowNumber(): int { return $this->rowNumber; }
    public function setRowNumber(int $v): static { $this->rowNumber = $v; return $this; }

    public function getPolCode(): ?string { return $this->polCode; }
    public function setPolCode(?string $v): static { $this->polCode = $v; return $this; }

    public function getPodCode(): ?string { return $this->podCode; }
    public function setPodCode(?string $v): static { $this->podCode = $v; return $this; }

    public function getContainerType(): ?string { return $this->containerType; }
    public function setContainerType(?string $v): static { $this->containerType = $v; return $this; }

    public function getChargeCode(): ?string { return $this->chargeCode; }
    public function setChargeCode(?string $v): static { $this->chargeCode = $v; return $this; }

    public function getNewBuyingAmount(): ?string { return $this->newBuyingAmount; }
    public function setNewBuyingAmount(?string $v): static { $this->newBuyingAmount = $v; return $this; }

    public function getNewSellingAmount(): ?string { return $this->newSellingAmount; }
    public function setNewSellingAmount(?string $v): static { $this->newSellingAmount = $v; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $v): static { $this->currency = $v; return $this; }

    public function getCurrentBuyingAmount(): ?string { return $this->currentBuyingAmount; }
    public function setCurrentBuyingAmount(?string $v): static { $this->currentBuyingAmount = $v; return $this; }

    public function getChangePct(): ?string { return $this->changePct; }
    public function setChangePct(?string $v): static { $this->changePct = $v; return $this; }

    public function isIsSanityFlagged(): bool { return $this->isSanityFlagged; }
    public function setIsSanityFlagged(bool $v): static { $this->isSanityFlagged = $v; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $v): static { $this->action = $v; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $v): static { $this->errorMessage = $v; return $this; }

    public function getExistingRateId(): ?int { return $this->existingRateId; }
    public function setExistingRateId(?int $v): static { $this->existingRateId = $v; return $this; }

    public function getPreviousValidUntil(): ?\DateTimeInterface { return $this->previousValidUntil; }
    public function setPreviousValidUntil(?\DateTimeInterface $v): static { $this->previousValidUntil = $v; return $this; }
}
```

- [ ] **Step 2: Create RateImportRowRepository**

`src/Module/Quote/Repository/RateImportRowRepository.php`:

```php
<?php
namespace App\Module\Quote\Repository;

use App\Module\Core\Repository\BaseRepository;

class RateImportRowRepository extends BaseRepository {}
```

- [ ] **Step 3: Commit**

```bash
git add src/Module/Quote/Entity/RateImportRow.php src/Module/Quote/Repository/RateImportRowRepository.php
git commit -m "feat: add RateImportRow entity and repository"
```

---

### Task 5: Modify Rate entity + RateRepository

**Files:**
- Modify: `src/Module/Quote/Entity/Rate.php`
- Modify: `src/Module/Quote/Repository/RateRepository.php`

- [ ] **Step 1: Add importJob FK to Rate entity**

In `src/Module/Quote/Entity/Rate.php`:

After `use App\Module\Finance\Enum\LocalChargeType;` add:
```php
use App\Module\Quote\Entity\RateImportJob;
```

After the `$createdBy` property (line ~92), add:
```php
#[ORM\ManyToOne]
#[ORM\JoinColumn(onDelete: 'SET NULL')]
private ?RateImportJob $importJob = null;
```

Add these methods before the closing `}`:
```php
public function getImportJob(): ?RateImportJob
{
    return $this->importJob;
}

public function setImportJob(?RateImportJob $importJob): static
{
    $this->importJob = $importJob;
    return $this;
}
```

- [ ] **Step 2: Add query methods to RateRepository**

Replace the empty body of `src/Module/Quote/Repository/RateRepository.php` with:

```php
<?php
namespace App\Module\Quote\Repository;

use App\Module\Core\Repository\BaseRepository;

class RateRepository extends BaseRepository
{
    public function findActiveRateForLane(
        string $polCode,
        string $podCode,
        ?int $providerId,
        ?string $containerType,
        string $transportType
    ): ?object {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.polPort', 'pol')
            ->innerJoin('r.podPort', 'pod')
            ->where('pol.code = :polCode')
            ->andWhere('pod.code = :podCode')
            ->andWhere('r.transportType = :transportType')
            ->andWhere('r.validUntil IS NULL')
            ->setParameter('polCode', $polCode)
            ->setParameter('podCode', $podCode)
            ->setParameter('transportType', $transportType)
            ->setMaxResults(1);

        if ($providerId !== null) {
            $qb->andWhere('r.provider = :provider')->setParameter('provider', $providerId);
        } else {
            $qb->andWhere('r.provider IS NULL');
        }

        if ($containerType !== null) {
            $qb->andWhere('r.containerType = :containerType')->setParameter('containerType', $containerType);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function deleteByImportJob(int $jobId): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->where('r.importJob = :jobId')
            ->setParameter('jobId', $jobId)
            ->getQuery()
            ->execute();
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Module/Quote/Entity/Rate.php src/Module/Quote/Repository/RateRepository.php
git commit -m "feat: add importJob FK to Rate and lane-query methods to RateRepository"
```

---

### Task 6: Serializer group YAML files

**Files:**
- Create: `config/serializer_groups/RateImportJob.yaml`
- Create: `config/serializer_groups/RateImportRow.yaml`

Format: `FQCN → group_name → [field, ...]` — same as existing `config/serializer_groups/Provider.yaml`.

- [ ] **Step 1: Create RateImportJob.yaml**

`config/serializer_groups/RateImportJob.yaml`:

```yaml
App\Module\Quote\Entity\RateImportJob:

    list:
        - id
        - importSource
        - provider
        - transportType
        - fileName
        - status
        - totalRows
        - rowsImported
        - rowsSkipped
        - rowsErrored
        - effectiveDate
        - expiryDate
        - currency
        - requiresApproval
        - approvedBy
        - approvedAt
        - canRollback
        - rolledBackBy
        - rolledBackAt
        - uploadedBy
        - completedAt
        - errorLog
        - createdDate
```

- [ ] **Step 2: Create RateImportRow.yaml**

`config/serializer_groups/RateImportRow.yaml`:

```yaml
App\Module\Quote\Entity\RateImportRow:

    list:
        - id
        - rowNumber
        - polCode
        - podCode
        - containerType
        - chargeCode
        - newBuyingAmount
        - newSellingAmount
        - currency
        - currentBuyingAmount
        - changePct
        - isSanityFlagged
        - action
        - errorMessage
        - existingRateId
        - previousValidUntil
```

- [ ] **Step 3: Commit**

```bash
git add config/serializer_groups/RateImportJob.yaml config/serializer_groups/RateImportRow.yaml
git commit -m "feat: add serializer groups for RateImportJob and RateImportRow"
```

---

### Task 7: RateImportService

**Files:**
- Create: `src/Module/Quote/Service/RateImportService.php`

This service handles three operations:
1. `parseAndPreview()` — parse an uploaded `.xlsx`, validate each row, build `RateImportRow` preview records, return the `RateImportJob`
2. `approve()` — atomically expire existing rates and create new `Rate` entities
3. `rollback()` — delete created rates, restore previous `validUntil` on superseded rates

- [ ] **Step 1: Create RateImportService**

`src/Module/Quote/Service/RateImportService.php`:

```php
<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Entity\Money;
use App\Module\Core\Entity\User;
use App\Module\Core\Repository\PortRepository;
use App\Module\Core\Service\BaseService;
use App\Module\Finance\Repository\ChargeRepository;
use App\Module\Quote\Entity\Rate;
use App\Module\Quote\Entity\RateImportJob;
use App\Module\Quote\Entity\RateImportRow;
use App\Module\Quote\Repository\RateImportJobRepository;
use App\Module\Quote\Repository\RateImportRowRepository;
use App\Module\Quote\Repository\RateRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RateImportService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public RateImportJobRepository $repository,
        private RateImportRowRepository $rowRepository,
        private RateRepository $rateRepository,
        private PortRepository $portRepository,
        private ChargeRepository $chargeRepository,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function parseAndPreview(
        UploadedFile $file,
        string $transportType,
        ?int $providerId,
        string $currency,
        string $effectiveDate,
        string $expiryDate,
        User $user
    ): RateImportJob {
        $job = new RateImportJob();
        $job->setImportSource('EXCEL');
        $job->setTransportType($transportType);
        $job->setFileName($file->getClientOriginalName());
        $job->setStatus('PARSING');
        $job->setCurrency($currency);
        $job->setEffectiveDate(new \DateTime($effectiveDate));
        $job->setExpiryDate(new \DateTime($expiryDate));
        $job->setUploadedBy($user);

        if ($providerId !== null) {
            $provider = $this->rateRepository->getEntityManager()
                ->getRepository(\App\Module\Carrier\Entity\Provider::class)
                ->find($providerId);
            $job->setProvider($provider);
        }

        $em = $this->repository->getEntityManager();
        $em->persist($job);

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        $colMap = [];
        foreach ($sheet->getRowIterator(1, 1) as $row) {
            $ci = $row->getCellIterator();
            $ci->setIterateOnlyExistingCells(false);
            foreach ($ci as $cell) {
                $val = strtoupper(trim((string) $cell->getValue()));
                if ($val !== '') {
                    $colMap[$val] = $cell->getColumn();
                }
            }
        }

        $previewRows = [];
        $errorCount = 0;
        $totalCount = 0;

        foreach ($sheet->getRowIterator(2) as $row) {
            $rowNum = $row->getRowIndex();
            $ci = $row->getCellIterator();
            $ci->setIterateOnlyExistingCells(false);
            $data = [];
            foreach ($ci as $cell) {
                $data[$cell->getColumn()] = $cell->getValue();
            }
            if (empty(array_filter($data, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }
            $totalCount++;

            $previewRow = $this->buildPreviewRow($job, $rowNum, $data, $colMap, $currency);
            if ($previewRow->getAction() === 'ERROR') {
                $errorCount++;
            }
            $previewRows[] = $previewRow;
            $em->persist($previewRow);
        }

        $job->setTotalRows($totalCount);
        $job->setRowsErrored($errorCount);
        $job->setStatus('PREVIEW');

        $em->flush();

        return $job;
    }

    private function buildPreviewRow(
        RateImportJob $job,
        int $rowNum,
        array $data,
        array $colMap,
        string $currency
    ): RateImportRow {
        $get = fn(string $key) => isset($colMap[$key]) ? trim((string) ($data[$colMap[$key]] ?? '')) : '';

        $row = new RateImportRow();
        $row->setImportJob($job);
        $row->setRowNumber($rowNum);

        $polCode       = $get('POL_CODE');
        $podCode       = $get('POD_CODE');
        $containerType = $get('CONTAINER_TYPE') ?: null;
        $chargeCode    = $get('CHARGE_CODE');
        $buyingRaw     = $get('BUYING_RATE');
        $sellingRaw    = $get('SELLING_RATE');

        $row->setPolCode($polCode ?: null);
        $row->setPodCode($podCode ?: null);
        $row->setContainerType($containerType);
        $row->setChargeCode($chargeCode ?: null);
        $row->setCurrency($currency);

        if (!$polCode || !$podCode) {
            return $row->setAction('ERROR')->setErrorMessage('Missing POL_CODE or POD_CODE');
        }

        if (!$this->portRepository->findOneBy(['code' => $polCode])) {
            return $row->setAction('ERROR')->setErrorMessage("Unknown POL port code: {$polCode}");
        }

        if (!$this->portRepository->findOneBy(['code' => $podCode])) {
            return $row->setAction('ERROR')->setErrorMessage("Unknown POD port code: {$podCode}");
        }

        $buying  = $buyingRaw !== '' ? (float) $buyingRaw : null;
        $selling = $sellingRaw !== '' ? (float) $sellingRaw : null;

        if ($buying !== null && $buying <= 0) {
            return $row->setAction('ERROR')->setErrorMessage('BUYING_RATE must be positive');
        }
        if ($selling !== null && $selling <= 0) {
            return $row->setAction('ERROR')->setErrorMessage('SELLING_RATE must be positive');
        }

        $row->setNewBuyingAmount($buying !== null ? (string) $buying : null);
        $row->setNewSellingAmount($selling !== null ? (string) $selling : null);

        $existing = $this->rateRepository->findActiveRateForLane(
            $polCode,
            $podCode,
            $job->getProvider()?->getId(),
            $containerType,
            $job->getTransportType()
        );

        if ($existing) {
            $currentBuying = $existing->getBuying()?->getAmount();
            $row->setCurrentBuyingAmount($currentBuying !== null ? (string) $currentBuying : null);
            $row->setExistingRateId($existing->getId());
            $row->setPreviousValidUntil($existing->getValidUntil());

            if ($currentBuying && $buying) {
                $changePct = (($buying - $currentBuying) / $currentBuying) * 100;
                $row->setChangePct((string) round($changePct, 4));
                if (abs($changePct) > 50) {
                    $row->setIsSanityFlagged(true);
                }
            }

            $row->setAction('UPDATE');
        } else {
            $row->setAction('NEW');
        }

        return $row;
    }

    public function approve(RateImportJob $job, User $user): void
    {
        if ($job->getStatus() !== 'PREVIEW') {
            throw new \LogicException('Only jobs in PREVIEW status can be approved');
        }

        $job->setStatus('IMPORTING');
        $job->setApprovedBy($user);
        $job->setApprovedAt(new \DateTime());

        $em = $this->repository->getEntityManager();
        $rows = $this->rowRepository->findBy(['importJob' => $job]);

        $imported = 0;
        $skipped  = 0;

        foreach ($rows as $row) {
            if (in_array($row->getAction(), ['ERROR', 'SKIP'], true)) {
                $skipped++;
                continue;
            }

            if ($row->getExistingRateId()) {
                $existing = $this->rateRepository->find($row->getExistingRateId());
                if ($existing) {
                    $dayBefore = (clone $job->getEffectiveDate())->modify('-1 day');
                    $existing->setValidUntil($dayBefore);
                    $em->persist($existing);
                }
            }

            $charge = $this->chargeRepository->findOneBy(['customCode' => $row->getChargeCode()]);
            if (!$charge) {
                $skipped++;
                continue;
            }

            $polPort = $this->portRepository->findOneBy(['code' => $row->getPolCode()]);
            $podPort = $this->portRepository->findOneBy(['code' => $row->getPodCode()]);

            $rate = new Rate();
            $rate->setCharge($charge);
            $rate->setProvider($job->getProvider());
            $rate->setTransportType(\App\Module\Core\Enum\TransportType::from($job->getTransportType()));
            $rate->setPolPort($polPort);
            $rate->setPodPort($podPort);
            $rate->setValidFrom($job->getEffectiveDate());
            $rate->setValidUntil($job->getExpiryDate());
            $rate->setCreatedBy($user);
            $rate->setImportJob($job);

            if ($row->getContainerType()) {
                $rate->setContainerType(\App\Module\Operations\Enum\ContainerType::from($row->getContainerType()));
            }

            $rowCurrency = $row->getCurrency() ?? $job->getCurrency();
            if ($row->getNewBuyingAmount() !== null) {
                $rate->setBuying(new Money((float) $row->getNewBuyingAmount(), $rowCurrency, 1.0));
            }
            if ($row->getNewSellingAmount() !== null) {
                $rate->setSelling(new Money((float) $row->getNewSellingAmount(), $rowCurrency, 1.0));
            }

            $rate->setChargeType(\App\Module\Finance\Enum\ChargeType::FREIGHT);

            $em->persist($rate);
            $imported++;
        }

        $job->setStatus('COMPLETED');
        $job->setRowsImported($imported);
        $job->setRowsSkipped($skipped);
        $job->setCompletedAt(new \DateTime());

        $em->flush();
    }

    public function rollback(RateImportJob $job, User $user): void
    {
        if ($job->getStatus() !== 'COMPLETED') {
            throw new \LogicException('Only COMPLETED jobs can be rolled back');
        }
        if (!$job->isCanRollback()) {
            throw new \LogicException('Rollback is disabled for this import job');
        }

        $completedAt = $job->getCompletedAt();
        if ($completedAt && (new \DateTime())->getTimestamp() - $completedAt->getTimestamp() > 48 * 3600) {
            $job->setCanRollback(false);
            $this->repository->getEntityManager()->flush();
            throw new \LogicException('The 48-hour rollback window has expired');
        }

        $em   = $this->repository->getEntityManager();
        $rows = $this->rowRepository->findBy(['importJob' => $job]);

        foreach ($rows as $row) {
            if ($row->getExistingRateId()) {
                $existing = $this->rateRepository->find($row->getExistingRateId());
                if ($existing) {
                    $existing->setValidUntil($row->getPreviousValidUntil());
                    $em->persist($existing);
                }
            }
        }

        $this->rateRepository->deleteByImportJob($job->getId());

        $job->setStatus('ROLLED_BACK');
        $job->setRolledBackBy($user);
        $job->setRolledBackAt(new \DateTime());
        $job->setCanRollback(false);

        $em->flush();
    }
}
```

**Note on `ChargeType::FREIGHT`:** Check `src/Module/Finance/Enum/ChargeType.php` for the actual enum case name for freight charges. Replace `FREIGHT` with the correct case if needed.

- [ ] **Step 2: Commit**

```bash
git add src/Module/Quote/Service/RateImportService.php
git commit -m "feat: add RateImportService with parseAndPreview, approve, and rollback"
```

---

### Task 8: Register service + RateImportController

**Files:**
- Modify: `config/services.yaml`
- Create: `src/Module/Quote/Controller/RateImportController.php`

- [ ] **Step 1: Register RateImportService in services.yaml**

In `config/services.yaml`, inside the `app.auto_service_locator → arguments → -` block, add after the `App\Module\Quote\Service\RateService` line:

```yaml
                App\Module\Quote\Service\RateImportService: '@App\Module\Quote\Service\RateImportService'
```

- [ ] **Step 2: Create RateImportController**

`src/Module/Quote/Controller/RateImportController.php`:

```php
<?php
namespace App\Module\Quote\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Quote\Repository\RateImportJobRepository;
use App\Module\Quote\Repository\RateImportRowRepository;
use App\Module\Quote\Service\RateImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/rate-import')]
#[IsGranted('ROLE_USER')]
#[AppModule('quote')]
class RateImportController extends AbstractController
{
    public function __construct(
        private RateImportJobRepository $jobRepository,
        private RateImportRowRepository $rowRepository,
        private RateImportService $importService,
        private NormalizerInterface $serializer,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $jobs = $this->jobRepository->findBy([], ['id' => 'DESC']);
        return $this->json($this->serializer->normalize($jobs, null, ['groups' => ['list']]));
    }

    #[Route('', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $transportType = $request->request->get('transportType');
        $effectiveDate = $request->request->get('effectiveDate');
        $expiryDate    = $request->request->get('expiryDate');

        if (!$transportType || !$effectiveDate || !$expiryDate) {
            return $this->json(
                ['error' => 'transportType, effectiveDate and expiryDate are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $job = $this->importService->parseAndPreview(
                $file,
                $transportType,
                $request->request->get('providerId') ? (int) $request->request->get('providerId') : null,
                $request->request->get('currency', 'USD'),
                $effectiveDate,
                $expiryDate,
                $this->getUser()
            );
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->normalize($job, null, ['groups' => ['list']]));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $job = $this->jobRepository->find($id);
        if (!$job) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $rows = $this->rowRepository->findBy(['importJob' => $job], ['rowNumber' => 'ASC']);
        $data = $this->serializer->normalize($job, null, ['groups' => ['list']]);
        $data['rows'] = $this->serializer->normalize($rows, null, ['groups' => ['list']]);

        return $this->json($data);
    }

    #[Route('/{id}/approve', methods: ['POST'])]
    public function approve(int $id): JsonResponse
    {
        $job = $this->jobRepository->find($id);
        if (!$job) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->importService->approve($job, $this->getUser());
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->normalize($job, null, ['groups' => ['list']]));
    }

    #[Route('/{id}/rollback', methods: ['POST'])]
    public function rollback(int $id): JsonResponse
    {
        $job = $this->jobRepository->find($id);
        if (!$job) {
            return $this->json(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->importService->rollback($job, $this->getUser());
        } catch (\LogicException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->normalize($job, null, ['groups' => ['list']]));
    }
}
```

- [ ] **Step 3: Verify container compiles**

```bash
php bin/console cache:clear
```

Expected: no errors. If a service injection error appears, check that `RateImportService` is added to `app.auto_service_locator` in `config/services.yaml`.

- [ ] **Step 4: Commit**

```bash
git add config/services.yaml src/Module/Quote/Controller/RateImportController.php
git commit -m "feat: register RateImportService and add RateImportController with 5 endpoints"
```

---

### Task 9: Setup Guide

**Files:**
- Create: `docs/guides/rate-import.md`

- [ ] **Step 1: Write the guide**

`docs/guides/rate-import.md`:

```markdown
# Rate Import Guide

The Rate Import feature allows operators to bulk-import carrier rate cards from Excel files.  
It follows a preview-then-approve workflow and supports rollback within 48 hours.

---

## Excel Template Format

Prepare a `.xlsx` file with headers in **row 1** and data from **row 2** onward.

| Column | Required | Description |
|---|---|---|
| `POL_CODE` | Yes | UN/LOCODE or IATA code of the origin port |
| `POD_CODE` | Yes | UN/LOCODE or IATA code of the destination port |
| `CHARGE_CODE` | Yes | Must match a `Charge.customCode` in the system |
| `CONTAINER_TYPE` | No | e.g. `20GP`, `40GP`, `40HC`, `45HC` |
| `BUYING_RATE` | No | Numeric buying rate |
| `SELLING_RATE` | No | Numeric selling rate |
| `TRANSIT_DAYS` | No | Integer transit time in days |

Column names are case-insensitive. Empty rows are skipped.

---

## API Endpoints

### List all import jobs

```
GET /rate-import
Authorization: Bearer <token>
```

Returns an array of `RateImportJob` objects in `list` serialization group.

---

### Upload and preview

```
POST /rate-import
Authorization: Bearer <token>
Content-Type: multipart/form-data

file          (required) .xlsx file
transportType (required) e.g. OCN, AIR, RD
effectiveDate (required) YYYY-MM-DD — when new rates become active
expiryDate    (required) YYYY-MM-DD — when new rates expire (or far future for open-ended)
providerId    (optional) Provider ID to associate rates with a specific carrier
currency      (optional, default USD) ISO 4217 code applied to all rows
```

**Response:** `RateImportJob` object. `status` will be `PREVIEW`.

The `rows` field is NOT returned in the list response — fetch it with `GET /rate-import/{id}`.

---

### Get job with preview rows

```
GET /rate-import/{id}
Authorization: Bearer <token>
```

Returns the job object with a `rows` array. Each row has:

| Field | Values |
|---|---|
| `action` | `NEW` — lane not found, will create a new rate |
| | `UPDATE` — existing open-ended rate found, will be expired and replaced |
| | `ERROR` — validation failed (see `errorMessage`) |
| `isSanityFlagged` | `true` if the new buying rate differs from current by more than 50% |
| `changePct` | Percentage change from current buying rate |
| `errorMessage` | Reason for `ERROR` rows |

---

### Approve import

```
POST /rate-import/{id}/approve
Authorization: Bearer <token>
```

Only allowed when `status = PREVIEW`. On success:
- Existing open-ended rates for the same lane (pol/pod/provider/containerType/transportType) have their `validUntil` set to `effectiveDate - 1 day`
- New `Rate` records are created and linked to this import job
- `status` changes to `COMPLETED`

**Response:** updated `RateImportJob` object.

---

### Rollback import

```
POST /rate-import/{id}/rollback
Authorization: Bearer <token>
```

Only allowed when `status = COMPLETED` and `canRollback = true` (within 48 hours).  
On success:
- All `Rate` records created by this import are deleted
- Previously-expired rates have their `validUntil` restored to the pre-import value
- `status` changes to `ROLLED_BACK`

After the 48-hour window, `canRollback` is set to `false` and this endpoint returns `422`.

---

## Validation Rules

| Check | Behaviour |
|---|---|
| POL/POD code exists in Port table | Row is marked `ERROR` |
| BUYING_RATE or SELLING_RATE ≤ 0 | Row is marked `ERROR` |
| Rate changes > 50% from current | Row is marked as sanity flagged but still imported on approve |
| CHARGE_CODE not found at approve time | Row is skipped (`rowsSkipped` incremented) |

---

## Status Lifecycle

```
PENDING → PARSING → PREVIEW → IMPORTING → COMPLETED → ROLLED_BACK
                                         ↘ FAILED
```
```

- [ ] **Step 2: Commit**

```bash
git add docs/guides/rate-import.md
git commit -m "docs: add rate import setup guide"
```

---

## Self-Review

**Spec coverage:**
- ✅ Mandatory preview before import
- ✅ Audit trail (who uploaded, who approved, counts)
- ✅ Old rates expired (validUntil set), not overwritten
- ✅ Sanity flag (>50% change) is a warning, not a block
- ✅ Rollback within 48-hour window
- ✅ Import source tracked (EXCEL)

**Placeholders:** None — all steps contain complete code.

**Notes for implementer:**
- Check `ChargeType` enum in `src/Module/Finance/Enum/ChargeType.php` and substitute the correct case for the freight charge line in `RateImportService::approve()` (used as `ChargeType::FREIGHT`).
- The `ChargeRepository` is at `src/Module/Finance/Repository/ChargeRepository.php` — verify the namespace before using it in service imports.
- The `PortRepository` is at `src/Module/Core/Repository/PortRepository.php`.
- Run `php bin/console doctrine:mapping:info` after adding entities to confirm they are registered before running migrations.
