# HS Codes & Tariff Classification Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement a complete HS Code (Harmonized System) and Tariff Classification system with four entities, standard CRUD APIs, duty calculation endpoint, BO management pages, and documentation.

**Architecture:** Four entities (`HsCode`, `DutyRate`, `HsRestriction`, `HsVersionMapping`) following the `BaseRepository` / `BaseService` / `CrudController` pattern. `HsCode` is a standalone reference entity (like `Port`) with a self-referential parent FK. The other three entities have a FK to `HsCode` with CASCADE DELETE and use `EntityDateTimeAbleTrait` for audit trails. BO provides CRUD list/form pages for all four under the Library navigation section.

**Tech Stack:** PHP 8.2 / Symfony 6, Doctrine ORM (MySQL + SQLite), Vue 3 + Vuetify 3, file-based routing via unplugin-pages (page file name drives route name).

---

### Task 1: HsCode Entity, Repository, Service, Migrations, Serializer

**Files:**
- Create: `src/Entity/HsCode.php`
- Create: `src/Repository/HsCodeRepository.php`
- Create: `src/Service/HsCodeService.php`
- Create: `migrations/mysql/Version20260624070000.php`
- Create: `migrations/sqlite/Version20260624070000.php`
- Create: `config/serializer_groups/HsCode.yaml`
- Modify: `config/services.yaml`

**Context:** All PHP files are in `d:\Projects\make-cargo-client`. The standalone entity pattern (no `SubEntity`, no `EntityDateTimeAbleTrait`) follows `src/Entity/Port.php`. The BaseService constructor pattern follows `src/Service/PortService.php`. Both MySQL and SQLite migrations share the same class name but live in separate directories. Services must be registered under `app.auto_service_locator` in `config/services.yaml` (the existing block ends at line ~111 with `App\Service\DeliveryOrderService`).

- [ ] **Step 1: Create `src/Entity/HsCode.php`**

```php
<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\HsCodeRepository;

#[ORM\Entity(repositoryClass: HsCodeRepository::class)]
class HsCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $code = null;

    #[ORM\Column(length: 500)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $level = null;

    #[ORM\Column(nullable: true)]
    private ?int $digits = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $hsVersion = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }

    public function getLevel(): ?int { return $this->level; }
    public function setLevel(?int $level): static { $this->level = $level; return $this; }

    public function getDigits(): ?int { return $this->digits; }
    public function setDigits(?int $digits): static { $this->digits = $digits; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $countryCode): static { $this->countryCode = $countryCode; return $this; }

    public function getHsVersion(): ?string { return $this->hsVersion; }
    public function setHsVersion(?string $hsVersion): static { $this->hsVersion = $hsVersion; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }

    public function getParent(): ?self { return $this->parent; }
    public function setParent(?self $parent): static { $this->parent = $parent; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/HsCodeRepository.php`**

```php
<?php
namespace App\Repository;

class HsCodeRepository extends BaseRepository {}
```

- [ ] **Step 3: Create `src/Service/HsCodeService.php`**

```php
<?php
namespace App\Service;

use App\Repository\HsCodeRepository;

class HsCodeService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public HsCodeRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
```

- [ ] **Step 4: Create `migrations/mysql/Version20260624070000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hs_code table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hs_code (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, code VARCHAR(10) NOT NULL, description VARCHAR(500) NOT NULL, level INT DEFAULT NULL, digits INT DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, hs_version VARCHAR(10) DEFAULT NULL, is_active TINYINT(1) NOT NULL DEFAULT 1, effective_from DATE DEFAULT NULL, effective_to DATE DEFAULT NULL, INDEX IDX_hs_code_parent (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hs_code ADD CONSTRAINT FK_hs_code_parent FOREIGN KEY (parent_id) REFERENCES hs_code (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hs_code DROP FOREIGN KEY FK_hs_code_parent');
        $this->addSql('DROP TABLE hs_code');
    }
}
```

- [ ] **Step 5: Create `migrations/sqlite/Version20260624070000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hs_code table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hs_code (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, code VARCHAR(10) NOT NULL, description VARCHAR(500) NOT NULL, level INTEGER DEFAULT NULL, digits INTEGER DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, hs_version VARCHAR(10) DEFAULT NULL, is_active BOOLEAN NOT NULL DEFAULT 1, effective_from DATE DEFAULT NULL, effective_to DATE DEFAULT NULL, CONSTRAINT FK_hs_code_parent FOREIGN KEY (parent_id) REFERENCES hs_code (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_hs_code_parent ON hs_code (parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE hs_code');
    }
}
```

- [ ] **Step 6: Create `config/serializer_groups/HsCode.yaml`**

```yaml
App\Entity\HsCode:

    list:
        - id
        - code
        - description
        - level
        - digits
        - countryCode
        - hsVersion
        - isActive
        - effectiveFrom
        - effectiveTo
        - parent

    detail:
        - id
        - code
        - description
        - level
        - digits
        - countryCode
        - hsVersion
        - isActive
        - effectiveFrom
        - effectiveTo
        - parent
```

- [ ] **Step 7: Register in `config/services.yaml`**

In `config/services.yaml`, inside the `app.auto_service_locator` arguments block, add after the `App\Service\DeliveryOrderService` line:

```yaml
                App\Service\HsCodeService: '@App\Service\HsCodeService'
```

- [ ] **Step 8: Commit**

```
git add src/Entity/HsCode.php src/Repository/HsCodeRepository.php src/Service/HsCodeService.php migrations/mysql/Version20260624070000.php migrations/sqlite/Version20260624070000.php config/serializer_groups/HsCode.yaml config/services.yaml
git commit -m "feat: add HsCode entity, repository, service, migrations, and serializer groups"
```

---

### Task 2: DutyRate Entity, Repository, Service, Migrations, Serializer

**Files:**
- Create: `src/Entity/DutyRate.php`
- Create: `src/Repository/DutyRateRepository.php`
- Create: `src/Service/DutyRateService.php`
- Create: `migrations/mysql/Version20260624080000.php`
- Create: `migrations/sqlite/Version20260624080000.php`
- Create: `config/serializer_groups/DutyRate.yaml`
- Modify: `config/services.yaml`

**Context:** `DutyRate` has a FK to `HsCode` and uses `EntityDateTimeAbleTrait` (namespace: `App\Misc\Traits\EntityDateTimeAbleTrait`). The trait requires `#[ORM\HasLifecycleCallbacks]` on the class. It adds `created_date` / `updated_date` columns (DATETIME NOT NULL) and exposes `getCreatedAt()` / `getUpdatedAt()` methods. DECIMAL columns map to PHP `?string` (not `?float`) — this is the codebase pattern (see `src/Entity/Quote.php`). The column type constant is `Types::DECIMAL` with `precision: 10, scale: 4`.

- [ ] **Step 1: Create `src/Entity/DutyRate.php`**

```php
<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\DutyRateRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: DutyRateRepository::class)]
#[ORM\HasLifecycleCallbacks]
class DutyRate
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HsCode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HsCode $hsCode = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $importCountry = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $exportCountry = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $rateType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ftaName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $dutyRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $vatRate = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $exciseRate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }

    public function getHsCode(): ?HsCode { return $this->hsCode; }
    public function setHsCode(?HsCode $hsCode): static { $this->hsCode = $hsCode; return $this; }

    public function getImportCountry(): ?string { return $this->importCountry; }
    public function setImportCountry(?string $importCountry): static { $this->importCountry = $importCountry; return $this; }

    public function getExportCountry(): ?string { return $this->exportCountry; }
    public function setExportCountry(?string $exportCountry): static { $this->exportCountry = $exportCountry; return $this; }

    public function getRateType(): ?string { return $this->rateType; }
    public function setRateType(?string $rateType): static { $this->rateType = $rateType; return $this; }

    public function getFtaName(): ?string { return $this->ftaName; }
    public function setFtaName(?string $ftaName): static { $this->ftaName = $ftaName; return $this; }

    public function getDutyRate(): ?string { return $this->dutyRate; }
    public function setDutyRate(?string $dutyRate): static { $this->dutyRate = $dutyRate; return $this; }

    public function getVatRate(): ?string { return $this->vatRate; }
    public function setVatRate(?string $vatRate): static { $this->vatRate = $vatRate; return $this; }

    public function getExciseRate(): ?string { return $this->exciseRate; }
    public function setExciseRate(?string $exciseRate): static { $this->exciseRate = $exciseRate; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/DutyRateRepository.php`**

```php
<?php
namespace App\Repository;

class DutyRateRepository extends BaseRepository {}
```

- [ ] **Step 3: Create `src/Service/DutyRateService.php`**

```php
<?php
namespace App\Service;

use App\Repository\DutyRateRepository;

class DutyRateService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public DutyRateRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
```

- [ ] **Step 4: Create `migrations/mysql/Version20260624080000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create duty_rate table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE duty_rate (id INT AUTO_INCREMENT NOT NULL, hs_code_id INT NOT NULL, import_country VARCHAR(2) DEFAULT NULL, export_country VARCHAR(2) DEFAULT NULL, rate_type VARCHAR(50) DEFAULT NULL, fta_name VARCHAR(100) DEFAULT NULL, duty_rate DECIMAL(10, 4) DEFAULT NULL, vat_rate DECIMAL(10, 4) DEFAULT NULL, excise_rate DECIMAL(10, 4) DEFAULT NULL, effective_from DATE DEFAULT NULL, effective_to DATE DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, INDEX IDX_duty_rate_hs_code (hs_code_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE duty_rate ADD CONSTRAINT FK_duty_rate_hs_code FOREIGN KEY (hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE duty_rate DROP FOREIGN KEY FK_duty_rate_hs_code');
        $this->addSql('DROP TABLE duty_rate');
    }
}
```

- [ ] **Step 5: Create `migrations/sqlite/Version20260624080000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create duty_rate table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE duty_rate (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, hs_code_id INTEGER NOT NULL, import_country VARCHAR(2) DEFAULT NULL, export_country VARCHAR(2) DEFAULT NULL, rate_type VARCHAR(50) DEFAULT NULL, fta_name VARCHAR(100) DEFAULT NULL, duty_rate NUMERIC(10, 4) DEFAULT NULL, vat_rate NUMERIC(10, 4) DEFAULT NULL, excise_rate NUMERIC(10, 4) DEFAULT NULL, effective_from DATE DEFAULT NULL, effective_to DATE DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, CONSTRAINT FK_duty_rate_hs_code FOREIGN KEY (hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_duty_rate_hs_code ON duty_rate (hs_code_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE duty_rate');
    }
}
```

- [ ] **Step 6: Create `config/serializer_groups/DutyRate.yaml`**

```yaml
App\Entity\DutyRate:

    list:
        - id
        - hsCode
        - importCountry
        - exportCountry
        - rateType
        - ftaName
        - dutyRate
        - vatRate
        - exciseRate
        - effectiveFrom
        - effectiveTo

    detail:
        - id
        - hsCode
        - importCountry
        - exportCountry
        - rateType
        - ftaName
        - dutyRate
        - vatRate
        - exciseRate
        - effectiveFrom
        - effectiveTo
        - createdAt
        - updatedAt
```

- [ ] **Step 7: Register in `config/services.yaml`**

Add after `App\Service\HsCodeService`:

```yaml
                App\Service\DutyRateService: '@App\Service\DutyRateService'
```

- [ ] **Step 8: Commit**

```
git add src/Entity/DutyRate.php src/Repository/DutyRateRepository.php src/Service/DutyRateService.php migrations/mysql/Version20260624080000.php migrations/sqlite/Version20260624080000.php config/serializer_groups/DutyRate.yaml config/services.yaml
git commit -m "feat: add DutyRate entity, repository, service, migrations, and serializer groups"
```

---

### Task 3: HsRestriction Entity, Repository, Service, Migrations, Serializer

**Files:**
- Create: `src/Entity/HsRestriction.php`
- Create: `src/Repository/HsRestrictionRepository.php`
- Create: `src/Service/HsRestrictionService.php`
- Create: `migrations/mysql/Version20260624090000.php`
- Create: `migrations/sqlite/Version20260624090000.php`
- Create: `config/serializer_groups/HsRestriction.yaml`
- Modify: `config/services.yaml`

**Context:** Same pattern as DutyRate — FK to HsCode, `EntityDateTimeAbleTrait`, `#[ORM\HasLifecycleCallbacks]`. Tracks import/export restrictions per country (PROHIBITED, LICENCE_REQUIRED, QUOTA).

- [ ] **Step 1: Create `src/Entity/HsRestriction.php`**

```php
<?php
namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\HsRestrictionRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: HsRestrictionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HsRestriction
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HsCode::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?HsCode $hsCode = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $restrictionType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authority = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $licenceType = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveFrom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $effectiveTo = null;

    public function getId(): ?int { return $this->id; }

    public function getHsCode(): ?HsCode { return $this->hsCode; }
    public function setHsCode(?HsCode $hsCode): static { $this->hsCode = $hsCode; return $this; }

    public function getCountryCode(): ?string { return $this->countryCode; }
    public function setCountryCode(?string $countryCode): static { $this->countryCode = $countryCode; return $this; }

    public function getRestrictionType(): ?string { return $this->restrictionType; }
    public function setRestrictionType(?string $restrictionType): static { $this->restrictionType = $restrictionType; return $this; }

    public function getAuthority(): ?string { return $this->authority; }
    public function setAuthority(?string $authority): static { $this->authority = $authority; return $this; }

    public function getLicenceType(): ?string { return $this->licenceType; }
    public function setLicenceType(?string $licenceType): static { $this->licenceType = $licenceType; return $this; }

    public function getEffectiveFrom(): ?\DateTimeInterface { return $this->effectiveFrom; }
    public function setEffectiveFrom(?\DateTimeInterface $effectiveFrom): static { $this->effectiveFrom = $effectiveFrom; return $this; }

    public function getEffectiveTo(): ?\DateTimeInterface { return $this->effectiveTo; }
    public function setEffectiveTo(?\DateTimeInterface $effectiveTo): static { $this->effectiveTo = $effectiveTo; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/HsRestrictionRepository.php`**

```php
<?php
namespace App\Repository;

class HsRestrictionRepository extends BaseRepository {}
```

- [ ] **Step 3: Create `src/Service/HsRestrictionService.php`**

```php
<?php
namespace App\Service;

use App\Repository\HsRestrictionRepository;

class HsRestrictionService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public HsRestrictionRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
```

- [ ] **Step 4: Create `migrations/mysql/Version20260624090000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hs_restriction table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hs_restriction (id INT AUTO_INCREMENT NOT NULL, hs_code_id INT NOT NULL, country_code VARCHAR(2) DEFAULT NULL, restriction_type VARCHAR(50) DEFAULT NULL, authority VARCHAR(255) DEFAULT NULL, licence_type VARCHAR(100) DEFAULT NULL, effective_from DATE DEFAULT NULL, effective_to DATE DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, INDEX IDX_hs_restriction_hs_code (hs_code_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hs_restriction ADD CONSTRAINT FK_hs_restriction_hs_code FOREIGN KEY (hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hs_restriction DROP FOREIGN KEY FK_hs_restriction_hs_code');
        $this->addSql('DROP TABLE hs_restriction');
    }
}
```

- [ ] **Step 5: Create `migrations/sqlite/Version20260624090000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hs_restriction table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hs_restriction (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, hs_code_id INTEGER NOT NULL, country_code VARCHAR(2) DEFAULT NULL, restriction_type VARCHAR(50) DEFAULT NULL, authority VARCHAR(255) DEFAULT NULL, licence_type VARCHAR(100) DEFAULT NULL, effective_from DATE DEFAULT NULL, effective_to DATE DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, CONSTRAINT FK_hs_restriction_hs_code FOREIGN KEY (hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_hs_restriction_hs_code ON hs_restriction (hs_code_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE hs_restriction');
    }
}
```

- [ ] **Step 6: Create `config/serializer_groups/HsRestriction.yaml`**

```yaml
App\Entity\HsRestriction:

    list:
        - id
        - hsCode
        - countryCode
        - restrictionType
        - authority
        - licenceType
        - effectiveFrom
        - effectiveTo

    detail:
        - id
        - hsCode
        - countryCode
        - restrictionType
        - authority
        - licenceType
        - effectiveFrom
        - effectiveTo
        - createdAt
        - updatedAt
```

- [ ] **Step 7: Register in `config/services.yaml`**

Add after `App\Service\DutyRateService`:

```yaml
                App\Service\HsRestrictionService: '@App\Service\HsRestrictionService'
```

- [ ] **Step 8: Commit**

```
git add src/Entity/HsRestriction.php src/Repository/HsRestrictionRepository.php src/Service/HsRestrictionService.php migrations/mysql/Version20260624090000.php migrations/sqlite/Version20260624090000.php config/serializer_groups/HsRestriction.yaml config/services.yaml
git commit -m "feat: add HsRestriction entity, repository, service, migrations, and serializer groups"
```

---

### Task 4: HsVersionMapping Entity, Repository, Service, Migrations, Serializer

**Files:**
- Create: `src/Entity/HsVersionMapping.php`
- Create: `src/Repository/HsVersionMappingRepository.php`
- Create: `src/Service/HsVersionMappingService.php`
- Create: `migrations/mysql/Version20260624100000.php`
- Create: `migrations/sqlite/Version20260624100000.php`
- Create: `config/serializer_groups/HsVersionMapping.yaml`
- Modify: `config/services.yaml`

**Context:** Maps old HS codes to new ones when the HS schedule updates (e.g., HS2017 → HS2022). Has two FK columns pointing to `HsCode`: `old_hs_code_id` and `new_hs_code_id`. Both use `name:` in `#[ORM\JoinColumn]` to override the default column name (Doctrine would otherwise generate `old_hs_code_id` and `new_hs_code_id` automatically from property name — using explicit `name:` makes migrations unambiguous). Both FKs CASCADE DELETE. `changeType` values: SPLIT, MERGE, RECLASSIFY.

- [ ] **Step 1: Create `src/Entity/HsVersionMapping.php`**

```php
<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\HsVersionMappingRepository;
use App\Misc\Traits\EntityDateTimeAbleTrait;

#[ORM\Entity(repositoryClass: HsVersionMappingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HsVersionMapping
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: HsCode::class)]
    #[ORM\JoinColumn(name: 'old_hs_code_id', nullable: false, onDelete: 'CASCADE')]
    private ?HsCode $oldHsCode = null;

    #[ORM\ManyToOne(targetEntity: HsCode::class)]
    #[ORM\JoinColumn(name: 'new_hs_code_id', nullable: false, onDelete: 'CASCADE')]
    private ?HsCode $newHsCode = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $oldVersion = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $newVersion = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $changeType = null;

    public function getId(): ?int { return $this->id; }

    public function getOldHsCode(): ?HsCode { return $this->oldHsCode; }
    public function setOldHsCode(?HsCode $oldHsCode): static { $this->oldHsCode = $oldHsCode; return $this; }

    public function getNewHsCode(): ?HsCode { return $this->newHsCode; }
    public function setNewHsCode(?HsCode $newHsCode): static { $this->newHsCode = $newHsCode; return $this; }

    public function getOldVersion(): ?string { return $this->oldVersion; }
    public function setOldVersion(?string $oldVersion): static { $this->oldVersion = $oldVersion; return $this; }

    public function getNewVersion(): ?string { return $this->newVersion; }
    public function setNewVersion(?string $newVersion): static { $this->newVersion = $newVersion; return $this; }

    public function getChangeType(): ?string { return $this->changeType; }
    public function setChangeType(?string $changeType): static { $this->changeType = $changeType; return $this; }
}
```

- [ ] **Step 2: Create `src/Repository/HsVersionMappingRepository.php`**

```php
<?php
namespace App\Repository;

class HsVersionMappingRepository extends BaseRepository {}
```

- [ ] **Step 3: Create `src/Service/HsVersionMappingService.php`**

```php
<?php
namespace App\Service;

use App\Repository\HsVersionMappingRepository;

class HsVersionMappingService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        public HsVersionMappingRepository $repository,
    ) {
        $this->reflectFromParent($baseService);
    }
}
```

- [ ] **Step 4: Create `migrations/mysql/Version20260624100000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hs_version_mapping table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hs_version_mapping (id INT AUTO_INCREMENT NOT NULL, old_hs_code_id INT NOT NULL, new_hs_code_id INT NOT NULL, old_version VARCHAR(10) DEFAULT NULL, new_version VARCHAR(10) DEFAULT NULL, change_type VARCHAR(50) DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, INDEX IDX_hs_version_mapping_old (old_hs_code_id), INDEX IDX_hs_version_mapping_new (new_hs_code_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hs_version_mapping ADD CONSTRAINT FK_hs_version_mapping_old FOREIGN KEY (old_hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hs_version_mapping ADD CONSTRAINT FK_hs_version_mapping_new FOREIGN KEY (new_hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hs_version_mapping DROP FOREIGN KEY FK_hs_version_mapping_old');
        $this->addSql('ALTER TABLE hs_version_mapping DROP FOREIGN KEY FK_hs_version_mapping_new');
        $this->addSql('DROP TABLE hs_version_mapping');
    }
}
```

- [ ] **Step 5: Create `migrations/sqlite/Version20260624100000.php`**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hs_version_mapping table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hs_version_mapping (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, old_hs_code_id INTEGER NOT NULL, new_hs_code_id INTEGER NOT NULL, old_version VARCHAR(10) DEFAULT NULL, new_version VARCHAR(10) DEFAULT NULL, change_type VARCHAR(50) DEFAULT NULL, created_date DATETIME NOT NULL, updated_date DATETIME NOT NULL, CONSTRAINT FK_hs_version_mapping_old FOREIGN KEY (old_hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_hs_version_mapping_new FOREIGN KEY (new_hs_code_id) REFERENCES hs_code (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_hs_version_mapping_old ON hs_version_mapping (old_hs_code_id)');
        $this->addSql('CREATE INDEX IDX_hs_version_mapping_new ON hs_version_mapping (new_hs_code_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE hs_version_mapping');
    }
}
```

- [ ] **Step 6: Create `config/serializer_groups/HsVersionMapping.yaml`**

```yaml
App\Entity\HsVersionMapping:

    list:
        - id
        - oldHsCode
        - newHsCode
        - oldVersion
        - newVersion
        - changeType

    detail:
        - id
        - oldHsCode
        - newHsCode
        - oldVersion
        - newVersion
        - changeType
        - createdAt
        - updatedAt
```

- [ ] **Step 7: Register in `config/services.yaml`**

Add after `App\Service\HsRestrictionService`:

```yaml
                App\Service\HsVersionMappingService: '@App\Service\HsVersionMappingService'
```

- [ ] **Step 8: Commit**

```
git add src/Entity/HsVersionMapping.php src/Repository/HsVersionMappingRepository.php src/Service/HsVersionMappingService.php migrations/mysql/Version20260624100000.php migrations/sqlite/Version20260624100000.php config/serializer_groups/HsVersionMapping.yaml config/services.yaml
git commit -m "feat: add HsVersionMapping entity, repository, service, migrations, and serializer groups"
```

---

### Task 5: API Controllers

**Files:**
- Create: `src/Controller/Api/HsCodeController.php`
- Create: `src/Controller/Api/DutyRateController.php`
- Create: `src/Controller/Api/HsRestrictionController.php`
- Create: `src/Controller/Api/HsVersionMappingController.php`

**Context:** All controllers extend `CrudController` (in same namespace) and use the four CRUD action traits. The `CrudController` resolves the service/repository from `app.auto_service_locator` by matching the controller class name (e.g., `HsCodeController` → `HsCodeService`). The three custom routes on `HsCodeController` (`/search`, `/browse/{parentId}`, `/calculate-duty`) must be controller-class methods, not trait methods. Symfony's router gives priority to literal route segments over parameterized ones, so `/search` will not be swallowed by the trait's `/{id}` route — but place custom methods at the top of the class as a convention. The `DutyRateRepository` is injected as an action-method parameter via Symfony autowiring (same pattern as `PortController` injects `PortRepository`).

- [ ] **Step 1: Create `src/Controller/Api/HsCodeController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\HsCode;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use App\Repository\DutyRateRepository;
use App\Repository\HsCodeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hs-code')]
#[IsGranted('ROLE_USER')]
class HsCodeController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;

    #[Route('/search', methods: ['GET'])]
    public function search(Request $request, HsCodeRepository $repo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        $limit = min((int) $request->query->get('limit', 20), 100);

        if (strlen($q) < 1) {
            return $this->json(['list' => []]);
        }

        $results = array_map(fn($h) => [
            'id'          => $h->getId(),
            'code'        => $h->getCode(),
            'description' => $h->getDescription(),
        ], $repo->createQueryBuilder('h')
            ->where('h.code LIKE :q OR h.description LIKE :q')
            ->setParameter('q', '%' . $q . '%')
            ->andWhere('h.isActive = true')
            ->orderBy('h.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()->getResult());

        return $this->json(['list' => $results]);
    }

    #[Route('/browse/{parentId}', methods: ['GET'])]
    public function browse(int $parentId, HsCodeRepository $repo): JsonResponse
    {
        $parent = $parentId > 0 ? $repo->find($parentId) : null;

        $qb = $repo->createQueryBuilder('h')
            ->andWhere('h.isActive = true')
            ->orderBy('h.code', 'ASC');

        if ($parentId > 0) {
            $qb->where('h.parent = :parent')->setParameter('parent', $parent);
        } else {
            $qb->where('h.parent IS NULL');
        }

        $results = array_map(fn($h) => [
            'id'          => $h->getId(),
            'code'        => $h->getCode(),
            'description' => $h->getDescription(),
            'level'       => $h->getLevel(),
            'digits'      => $h->getDigits(),
        ], $qb->getQuery()->getResult());

        return $this->json([
            'list'   => $results,
            'parent' => $parent ? ['id' => $parent->getId(), 'code' => $parent->getCode(), 'description' => $parent->getDescription()] : null,
        ]);
    }

    #[Route('/calculate-duty', methods: ['POST'])]
    public function calculateDuty(Request $request, HsCodeRepository $hsRepo, DutyRateRepository $dutyRepo): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $hsCodeId     = (int) ($body['hsCodeId'] ?? 0);
        $importCountry = $body['importCountry'] ?? null;
        $exportCountry = $body['exportCountry'] ?? null;
        $customsValue  = (float) ($body['customsValue'] ?? 0);

        if (!$hsCodeId) {
            return $this->json(['error' => 'hsCodeId is required.'], Response::HTTP_BAD_REQUEST);
        }

        $hsCode = $hsRepo->find($hsCodeId);
        if (!$hsCode) {
            return $this->json(['error' => 'HS code not found.'], Response::HTTP_NOT_FOUND);
        }

        $qb = $dutyRepo->createQueryBuilder('d')->where('d.hsCode = :hs')->setParameter('hs', $hsCode);
        if ($importCountry) {
            $qb->andWhere('d.importCountry = :ic OR d.importCountry IS NULL')->setParameter('ic', $importCountry);
        }
        if ($exportCountry) {
            $qb->andWhere('d.exportCountry = :ec OR d.exportCountry IS NULL')->setParameter('ec', $exportCountry);
        }

        $breakdown = array_map(function ($rate) use ($customsValue) {
            $dutyAmt   = $rate->getDutyRate()   !== null ? round((float) $rate->getDutyRate()   / 100 * $customsValue, 2) : null;
            $vatAmt    = $rate->getVatRate()    !== null ? round((float) $rate->getVatRate()    / 100 * ($customsValue + ($dutyAmt ?? 0)), 2) : null;
            $exciseAmt = $rate->getExciseRate() !== null ? round((float) $rate->getExciseRate() / 100 * $customsValue, 2) : null;
            return [
                'rateType'      => $rate->getRateType(),
                'ftaName'       => $rate->getFtaName(),
                'importCountry' => $rate->getImportCountry(),
                'exportCountry' => $rate->getExportCountry(),
                'dutyRate'      => $rate->getDutyRate(),
                'vatRate'       => $rate->getVatRate(),
                'exciseRate'    => $rate->getExciseRate(),
                'dutyAmount'    => $dutyAmt,
                'vatAmount'     => $vatAmt,
                'exciseAmount'  => $exciseAmt,
                'totalAmount'   => round(($dutyAmt ?? 0) + ($vatAmt ?? 0) + ($exciseAmt ?? 0), 2),
            ];
        }, $qb->getQuery()->getResult());

        return $this->json([
            'hsCode'        => ['id' => $hsCode->getId(), 'code' => $hsCode->getCode(), 'description' => $hsCode->getDescription()],
            'customsValue'  => $customsValue,
            'importCountry' => $importCountry,
            'exportCountry' => $exportCountry,
            'breakdown'     => $breakdown,
        ]);
    }
}
```

- [ ] **Step 2: Create `src/Controller/Api/DutyRateController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\DutyRate;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/duty-rate')]
#[IsGranted('ROLE_USER')]
class DutyRateController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
}
```

- [ ] **Step 3: Create `src/Controller/Api/HsRestrictionController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\HsRestriction;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hs-restriction')]
#[IsGranted('ROLE_USER')]
class HsRestrictionController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
}
```

- [ ] **Step 4: Create `src/Controller/Api/HsVersionMappingController.php`**

```php
<?php
namespace App\Controller\Api;

use App\Entity\HsVersionMapping;
use App\Misc\Traits\Controller\DeleteActionTrait;
use App\Misc\Traits\Controller\GetActionTrait;
use App\Misc\Traits\Controller\PostActionTrait;
use App\Misc\Traits\Controller\PutActionTrait;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hs-version-mapping')]
#[IsGranted('ROLE_USER')]
class HsVersionMappingController extends CrudController
{
    use GetActionTrait;
    use PostActionTrait;
    use PutActionTrait;
    use DeleteActionTrait;
}
```

- [ ] **Step 5: Commit**

```
git add src/Controller/Api/HsCodeController.php src/Controller/Api/DutyRateController.php src/Controller/Api/HsRestrictionController.php src/Controller/Api/HsVersionMappingController.php
git commit -m "feat: add API controllers for HsCode, DutyRate, HsRestriction, HsVersionMapping"
```

---

### Task 6: BO HsCode (service, configs, view, page)

**Files (all in `d:\Projects\make-cargo-client-bo`):**
- Create: `src/services/library/HsCodeService.js`
- Create: `src/config/forms/library/HsCode.js`
- Create: `src/config/tables/library/HsCode.js`
- Create: `src/views/library/HsCodeForm.vue`
- Create: `src/pages/library/hs-code.vue`

**Context:** The BO project is at `d:\Projects\make-cargo-client-bo`. Services live in `src/services/library/`. Pattern: `PackageTypeService.js` — `$api()` is a global fetch helper. The `AppForm` component accepts a `layout` function returning a 3D array (rows → columns → field objects). The `AppTable` component accepts `headers`, `buttons`, `filterConfigs`, `apiService`. Pages use `definePage({ meta: {...} })` for route metadata. Route names are derived from the file path: `src/pages/library/hs-code.vue` → route name `library-hs-code`. Import `ref` and `computed` are auto-imported (no explicit `import` needed in `<script setup>`).

- [ ] **Step 1: Create `src/services/library/HsCodeService.js`**

```js
import CommonService from '@/services/CommonService'

const BASE_URI = 'hs-code'

export default {
  list(params = '') {
    return $api(`${BASE_URI}?${params}`)
  },
  get(id) {
    return $api(`${BASE_URI}/${id}`)
  },
  add(entity) {
    return $api(BASE_URI, { method: 'POST', body: CommonService.formData(entity), loading: true })
  },
  update(entity) {
    return $api(BASE_URI, { method: 'PUT', body: CommonService.formData(entity), loading: true })
  },
  delete(id) {
    return $api(`${BASE_URI}/${id}`, { method: 'DELETE', loading: true })
  },
  search(q, limit = 20) {
    return $api(`${BASE_URI}/search?q=${encodeURIComponent(q)}&limit=${limit}`)
  },
  browse(parentId = 0) {
    return $api(`${BASE_URI}/browse/${parentId}`)
  },
}
```

- [ ] **Step 2: Create `src/config/forms/library/HsCode.js`**

```js
import CommonService from '@/services/CommonService'

export const makeDefaultEntity = async () => ({
  code: '',
  description: '',
  level: null,
  digits: null,
  countryCode: null,
  hsVersion: null,
  isActive: true,
  effectiveFrom: null,
  effectiveTo: null,
  parent: null,
})

export const layout = () => {
  const { required } = CommonService.rules()
  return [
    [
      [{ name: 'code', text: $gettext('HS Code'), rules: [required], columnSpan: 2 }],
      [{ name: 'description', text: $gettext('Description'), rules: [required], columnSpan: 4 }],
    ],
    [
      [{ name: 'level', text: $gettext('Level'), type: 'number', columnSpan: 1 }],
      [{ name: 'digits', text: $gettext('Digits'), type: 'number', columnSpan: 1 }],
      [{ name: 'countryCode', text: $gettext('Country Code'), columnSpan: 1 }],
      [{ name: 'hsVersion', text: $gettext('HS Version'), columnSpan: 1 }],
    ],
    [
      [{ name: 'effectiveFrom', text: $gettext('Effective From'), type: 'date', columnSpan: 2 }],
      [{ name: 'effectiveTo', text: $gettext('Effective To'), type: 'date', columnSpan: 2 }],
    ],
    [
      [{ name: 'isActive', text: $gettext('Active'), type: 'checkbox', columnSpan: 2 }],
    ],
  ]
}
```

- [ ] **Step 3: Create `src/config/tables/library/HsCode.js`**

```js
export const filterConfigs = () => [
  { title: $gettext('HS Code'), value: 'code', type: 'text' },
  { title: $gettext('Description'), value: 'description', type: 'text' },
  { title: $gettext('Country Code'), value: 'countryCode', type: 'text' },
  { title: $gettext('HS Version'), value: 'hsVersion', type: 'text' },
  {
    title: $gettext('Active'),
    value: 'isActive',
    type: 'select',
    items: [{ title: $gettext('Yes'), value: 1 }, { title: $gettext('No'), value: 0 }],
  },
]

export const headers = () => [
  { key: 'code', text: $gettext('Code') },
  { key: 'description', text: $gettext('Description') },
  { key: 'level', text: $gettext('Level') },
  { key: 'countryCode', text: $gettext('Country') },
  { key: 'hsVersion', text: $gettext('Version') },
  { key: 'isActive', text: $gettext('Active'), renderObject(item) { return item.isActive ? $gettext('Yes') : $gettext('No') } },
  { key: 'id', text: $gettext('Action'), sortable: false, renderSlot: 'action', bodyClass: 'px-0', headerClass: 'text-end pe-4' },
]
```

- [ ] **Step 4: Create `src/views/library/HsCodeForm.vue`**

```vue
<script setup>
import { layout, makeDefaultEntity } from '@/config/forms/library/HsCode'
import EntityService from '@/services/library/HsCodeService'

const form = ref(null)
function setEntity(entity = null) { form.value.setEntity(entity) }
defineExpose({ setEntity })
</script>
<template>
  <AppForm
    :layout="layout"
    :entityName="$gettext('HS Code')"
    :makeDefaultEntity="makeDefaultEntity"
    :service="EntityService"
    ref="form"
    :width="'900px'"
    @entitySubmitted="$emit('entitySubmitted')"
  />
</template>
```

- [ ] **Step 5: Create `src/pages/library/hs-code.vue`**

```vue
<script setup>
import { filterConfigs, headers } from '@/config/tables/library/HsCode'
import EntityService from '@/services/library/HsCodeService'
import HsCodeForm from '@/views/library/HsCodeForm.vue'

definePage({ meta: { action: 'GET', subject: 'HsCode' } })

const table = ref(null)
const form = ref(null)
const buttons = computed(() => [{ text: $gettext('Add HS Code'), func: form.value?.setEntity }])

async function editEntity(id) {
  const entity = await EntityService.get(id)
  form.value.setEntity(entity)
}
</script>
<template>
  <AppTable
    :headers="headers()"
    :buttons="buttons"
    :filterConfigs="filterConfigs"
    :apiService="EntityService"
    ref="table"
    :pageTitle="$gettext('HS Codes')"
  >
    <template #action="{ item }">
      <v-btn @click="editEntity(item.id)" :title="$gettext('edit')" class="grey--text mx-0" variant="text" size="x-small">
        <VIcon icon="tabler-pencil" size="18"/>
      </v-btn>
      <SubmitBtn
        @click="table.handleDelete(item, $refs['delete-' + item.id])"
        :title="$gettext('delete')"
        class="grey--text mx-0 ml-n2" variant="text"
        :autoQueue="false"
        :ref="'delete-' + item.id"
        size="x-small"
      >
        <VIcon icon="tabler-trash" size="18"/>
      </SubmitBtn>
    </template>
  </AppTable>
  <HsCodeForm ref="form" @entitySubmitted="$refs.table.fetchData()" />
</template>
```

- [ ] **Step 6: Commit (run from `d:\Projects\make-cargo-client-bo`)**

```
git add src/services/library/HsCodeService.js src/config/forms/library/HsCode.js src/config/tables/library/HsCode.js src/views/library/HsCodeForm.vue src/pages/library/hs-code.vue
git commit -m "feat: add BO HS code service, form config, table config, view, and page"
```

---

### Task 7: BO DutyRate (service, configs, view, page)

**Files (all in `d:\Projects\make-cargo-client-bo`):**
- Create: `src/services/library/DutyRateService.js`
- Create: `src/config/forms/library/DutyRate.js`
- Create: `src/config/tables/library/DutyRate.js`
- Create: `src/views/library/DutyRateForm.vue`
- Create: `src/pages/library/duty-rate.vue`

**Context:** Same BO patterns as Task 6. The `hsCode` field in the form needs to send just an integer ID (`hsCode: 42`) — the API's standard CRUD traits use `DoctrineEntityDenormalizer` to resolve FK IDs to entity references automatically. In the table, `item.hsCode` is the serialized `HsCode` object (with `code` and `id` properties) per the `DutyRate` serializer group. Use `renderObject` with optional chaining: `item.hsCode?.code ?? '—'`.

- [ ] **Step 1: Create `src/services/library/DutyRateService.js`**

```js
import CommonService from '@/services/CommonService'

const BASE_URI = 'duty-rate'

export default {
  list(params = '') { return $api(`${BASE_URI}?${params}`) },
  get(id) { return $api(`${BASE_URI}/${id}`) },
  add(entity) { return $api(BASE_URI, { method: 'POST', body: CommonService.formData(entity), loading: true }) },
  update(entity) { return $api(BASE_URI, { method: 'PUT', body: CommonService.formData(entity), loading: true }) },
  delete(id) { return $api(`${BASE_URI}/${id}`, { method: 'DELETE', loading: true }) },
}
```

- [ ] **Step 2: Create `src/config/forms/library/DutyRate.js`**

```js
import CommonService from '@/services/CommonService'

export const makeDefaultEntity = async () => ({
  hsCode: null,
  importCountry: null,
  exportCountry: null,
  rateType: null,
  ftaName: null,
  dutyRate: null,
  vatRate: null,
  exciseRate: null,
  effectiveFrom: null,
  effectiveTo: null,
})

export const layout = () => {
  const { required } = CommonService.rules()
  return [
    [
      [{ name: 'hsCode', text: $gettext('HS Code ID'), rules: [required], type: 'number', columnSpan: 2 }],
      [{ name: 'rateType', text: $gettext('Rate Type'), columnSpan: 2, type: 'select', items: [{ title: 'MFN', value: 'MFN' }, { title: 'FTA', value: 'FTA' }, { title: $gettext('Preferential'), value: 'PREFERENTIAL' }] }],
    ],
    [
      [{ name: 'importCountry', text: $gettext('Import Country'), columnSpan: 2 }],
      [{ name: 'exportCountry', text: $gettext('Export Country'), columnSpan: 2 }],
      [{ name: 'ftaName', text: $gettext('FTA Name'), columnSpan: 4 }],
    ],
    [
      [{ name: 'dutyRate', text: $gettext('Duty Rate (%)'), type: 'number', appendInner: '%', columnSpan: 2 }],
      [{ name: 'vatRate', text: $gettext('VAT Rate (%)'), type: 'number', appendInner: '%', columnSpan: 2 }],
      [{ name: 'exciseRate', text: $gettext('Excise Rate (%)'), type: 'number', appendInner: '%', columnSpan: 2 }],
    ],
    [
      [{ name: 'effectiveFrom', text: $gettext('Effective From'), type: 'date', columnSpan: 2 }],
      [{ name: 'effectiveTo', text: $gettext('Effective To'), type: 'date', columnSpan: 2 }],
    ],
  ]
}
```

- [ ] **Step 3: Create `src/config/tables/library/DutyRate.js`**

```js
export const filterConfigs = () => [
  { title: $gettext('Import Country'), value: 'importCountry', type: 'text' },
  { title: $gettext('Export Country'), value: 'exportCountry', type: 'text' },
  { title: $gettext('Rate Type'), value: 'rateType', type: 'text' },
  { title: $gettext('FTA Name'), value: 'ftaName', type: 'text' },
]

export const headers = () => [
  { key: 'hsCode', text: $gettext('HS Code'), renderObject(item) { return item.hsCode?.code ?? '—' } },
  { key: 'importCountry', text: $gettext('Import Country') },
  { key: 'exportCountry', text: $gettext('Export Country') },
  { key: 'rateType', text: $gettext('Rate Type') },
  { key: 'dutyRate', text: $gettext('Duty (%)') },
  { key: 'vatRate', text: $gettext('VAT (%)') },
  { key: 'exciseRate', text: $gettext('Excise (%)') },
  { key: 'id', text: $gettext('Action'), sortable: false, renderSlot: 'action', bodyClass: 'px-0', headerClass: 'text-end pe-4' },
]
```

- [ ] **Step 4: Create `src/views/library/DutyRateForm.vue`**

```vue
<script setup>
import { layout, makeDefaultEntity } from '@/config/forms/library/DutyRate'
import EntityService from '@/services/library/DutyRateService'

const form = ref(null)
function setEntity(entity = null) { form.value.setEntity(entity) }
defineExpose({ setEntity })
</script>
<template>
  <AppForm
    :layout="layout"
    :entityName="$gettext('Duty Rate')"
    :makeDefaultEntity="makeDefaultEntity"
    :service="EntityService"
    ref="form"
    :width="'900px'"
    @entitySubmitted="$emit('entitySubmitted')"
  />
</template>
```

- [ ] **Step 5: Create `src/pages/library/duty-rate.vue`**

```vue
<script setup>
import { filterConfigs, headers } from '@/config/tables/library/DutyRate'
import EntityService from '@/services/library/DutyRateService'
import DutyRateForm from '@/views/library/DutyRateForm.vue'

definePage({ meta: { action: 'GET', subject: 'DutyRate' } })

const table = ref(null)
const form = ref(null)
const buttons = computed(() => [{ text: $gettext('Add Duty Rate'), func: form.value?.setEntity }])

async function editEntity(id) {
  const entity = await EntityService.get(id)
  form.value.setEntity(entity)
}
</script>
<template>
  <AppTable
    :headers="headers()"
    :buttons="buttons"
    :filterConfigs="filterConfigs"
    :apiService="EntityService"
    ref="table"
    :pageTitle="$gettext('Duty Rates')"
  >
    <template #action="{ item }">
      <v-btn @click="editEntity(item.id)" :title="$gettext('edit')" class="grey--text mx-0" variant="text" size="x-small">
        <VIcon icon="tabler-pencil" size="18"/>
      </v-btn>
      <SubmitBtn
        @click="table.handleDelete(item, $refs['delete-' + item.id])"
        :title="$gettext('delete')"
        class="grey--text mx-0 ml-n2" variant="text"
        :autoQueue="false"
        :ref="'delete-' + item.id"
        size="x-small"
      >
        <VIcon icon="tabler-trash" size="18"/>
      </SubmitBtn>
    </template>
  </AppTable>
  <DutyRateForm ref="form" @entitySubmitted="$refs.table.fetchData()" />
</template>
```

- [ ] **Step 6: Commit (run from `d:\Projects\make-cargo-client-bo`)**

```
git add src/services/library/DutyRateService.js src/config/forms/library/DutyRate.js src/config/tables/library/DutyRate.js src/views/library/DutyRateForm.vue src/pages/library/duty-rate.vue
git commit -m "feat: add BO duty rate service, form config, table config, view, and page"
```

---

### Task 8: BO HsRestriction (service, configs, view, page)

**Files (all in `d:\Projects\make-cargo-client-bo`):**
- Create: `src/services/library/HsRestrictionService.js`
- Create: `src/config/forms/library/HsRestriction.js`
- Create: `src/config/tables/library/HsRestriction.js`
- Create: `src/views/library/HsRestrictionForm.vue`
- Create: `src/pages/library/hs-restriction.vue`

**Context:** Same patterns as Tasks 6 and 7. The `restrictionType` field is a select with three values: PROHIBITED, LICENCE_REQUIRED, QUOTA. The `hsCode` field sends an integer ID. In the table, `item.hsCode?.code ?? '—'` accesses the serialized nested object.

- [ ] **Step 1: Create `src/services/library/HsRestrictionService.js`**

```js
import CommonService from '@/services/CommonService'

const BASE_URI = 'hs-restriction'

export default {
  list(params = '') { return $api(`${BASE_URI}?${params}`) },
  get(id) { return $api(`${BASE_URI}/${id}`) },
  add(entity) { return $api(BASE_URI, { method: 'POST', body: CommonService.formData(entity), loading: true }) },
  update(entity) { return $api(BASE_URI, { method: 'PUT', body: CommonService.formData(entity), loading: true }) },
  delete(id) { return $api(`${BASE_URI}/${id}`, { method: 'DELETE', loading: true }) },
}
```

- [ ] **Step 2: Create `src/config/forms/library/HsRestriction.js`**

```js
import CommonService from '@/services/CommonService'

export const makeDefaultEntity = async () => ({
  hsCode: null,
  countryCode: null,
  restrictionType: null,
  authority: null,
  licenceType: null,
  effectiveFrom: null,
  effectiveTo: null,
})

export const layout = () => {
  const { required } = CommonService.rules()
  return [
    [
      [{ name: 'hsCode', text: $gettext('HS Code ID'), rules: [required], type: 'number', columnSpan: 2 }],
      [{ name: 'countryCode', text: $gettext('Country Code'), columnSpan: 2 }],
      [{
        name: 'restrictionType',
        text: $gettext('Restriction Type'),
        columnSpan: 3,
        type: 'select',
        items: [
          { title: $gettext('Prohibited'), value: 'PROHIBITED' },
          { title: $gettext('Licence Required'), value: 'LICENCE_REQUIRED' },
          { title: $gettext('Quota'), value: 'QUOTA' },
        ],
      }],
    ],
    [
      [{ name: 'authority', text: $gettext('Authority'), columnSpan: 4 }],
      [{ name: 'licenceType', text: $gettext('Licence Type'), columnSpan: 3 }],
    ],
    [
      [{ name: 'effectiveFrom', text: $gettext('Effective From'), type: 'date', columnSpan: 2 }],
      [{ name: 'effectiveTo', text: $gettext('Effective To'), type: 'date', columnSpan: 2 }],
    ],
  ]
}
```

- [ ] **Step 3: Create `src/config/tables/library/HsRestriction.js`**

```js
export const filterConfigs = () => [
  { title: $gettext('Country Code'), value: 'countryCode', type: 'text' },
  { title: $gettext('Restriction Type'), value: 'restrictionType', type: 'text' },
  { title: $gettext('Authority'), value: 'authority', type: 'text' },
]

export const headers = () => [
  { key: 'hsCode', text: $gettext('HS Code'), renderObject(item) { return item.hsCode?.code ?? '—' } },
  { key: 'countryCode', text: $gettext('Country') },
  { key: 'restrictionType', text: $gettext('Restriction Type') },
  { key: 'authority', text: $gettext('Authority') },
  { key: 'licenceType', text: $gettext('Licence Type') },
  { key: 'id', text: $gettext('Action'), sortable: false, renderSlot: 'action', bodyClass: 'px-0', headerClass: 'text-end pe-4' },
]
```

- [ ] **Step 4: Create `src/views/library/HsRestrictionForm.vue`**

```vue
<script setup>
import { layout, makeDefaultEntity } from '@/config/forms/library/HsRestriction'
import EntityService from '@/services/library/HsRestrictionService'

const form = ref(null)
function setEntity(entity = null) { form.value.setEntity(entity) }
defineExpose({ setEntity })
</script>
<template>
  <AppForm
    :layout="layout"
    :entityName="$gettext('HS Restriction')"
    :makeDefaultEntity="makeDefaultEntity"
    :service="EntityService"
    ref="form"
    :width="'900px'"
    @entitySubmitted="$emit('entitySubmitted')"
  />
</template>
```

- [ ] **Step 5: Create `src/pages/library/hs-restriction.vue`**

```vue
<script setup>
import { filterConfigs, headers } from '@/config/tables/library/HsRestriction'
import EntityService from '@/services/library/HsRestrictionService'
import HsRestrictionForm from '@/views/library/HsRestrictionForm.vue'

definePage({ meta: { action: 'GET', subject: 'HsRestriction' } })

const table = ref(null)
const form = ref(null)
const buttons = computed(() => [{ text: $gettext('Add Restriction'), func: form.value?.setEntity }])

async function editEntity(id) {
  const entity = await EntityService.get(id)
  form.value.setEntity(entity)
}
</script>
<template>
  <AppTable
    :headers="headers()"
    :buttons="buttons"
    :filterConfigs="filterConfigs"
    :apiService="EntityService"
    ref="table"
    :pageTitle="$gettext('HS Restrictions')"
  >
    <template #action="{ item }">
      <v-btn @click="editEntity(item.id)" :title="$gettext('edit')" class="grey--text mx-0" variant="text" size="x-small">
        <VIcon icon="tabler-pencil" size="18"/>
      </v-btn>
      <SubmitBtn
        @click="table.handleDelete(item, $refs['delete-' + item.id])"
        :title="$gettext('delete')"
        class="grey--text mx-0 ml-n2" variant="text"
        :autoQueue="false"
        :ref="'delete-' + item.id"
        size="x-small"
      >
        <VIcon icon="tabler-trash" size="18"/>
      </SubmitBtn>
    </template>
  </AppTable>
  <HsRestrictionForm ref="form" @entitySubmitted="$refs.table.fetchData()" />
</template>
```

- [ ] **Step 6: Commit (run from `d:\Projects\make-cargo-client-bo`)**

```
git add src/services/library/HsRestrictionService.js src/config/forms/library/HsRestriction.js src/config/tables/library/HsRestriction.js src/views/library/HsRestrictionForm.vue src/pages/library/hs-restriction.vue
git commit -m "feat: add BO HS restriction service, form config, table config, view, and page"
```

---

### Task 9: BO Navigation + Docs Guide

**Files:**
- Modify: `src/config/navigation/index.js` (in `d:\Projects\make-cargo-client-bo`)
- Create: `docs/guides/hs-codes.md` (in `d:\Projects\make-cargo-client`)

**Context:** The navigation file is at `d:\Projects\make-cargo-client-bo\src\config\navigation\index.js`. The Library section's `children` array currently ends with the `Incoterms` entry (around line 256). Add the three new entries after it, inside the same `children` array — before the closing `],` of the Library block. Route names: `library-hs-code`, `library-duty-rate`, `library-hs-restriction` (derived from file paths by unplugin-pages: directory separator + dashes in filename = dashes in route name).

- [ ] **Step 1: Add nav entries to Library section in `src/config/navigation/index.js`**

Find the Incoterms entry block:
```js
      {
        title: $gettext('Incoterms'),
        to: { name: 'library-incoterm' },
        subject: 'Incoterm',
        action: 'GET'
      }
```

Replace it with:
```js
      {
        title: $gettext('Incoterms'),
        to: { name: 'library-incoterm' },
        subject: 'Incoterm',
        action: 'GET'
      },
      {
        title: $gettext('HS Codes'),
        to: { name: 'library-hs-code' },
        subject: 'HsCode',
        action: 'GET'
      },
      {
        title: $gettext('Duty Rates'),
        to: { name: 'library-duty-rate' },
        subject: 'DutyRate',
        action: 'GET'
      },
      {
        title: $gettext('HS Restrictions'),
        to: { name: 'library-hs-restriction' },
        subject: 'HsRestriction',
        action: 'GET'
      }
```

- [ ] **Step 2: Create `docs/guides/hs-codes.md`**

```markdown
# HS Code & Tariff Classification Guide

This guide covers the HS Code (Harmonized System) feature in the client API and BO.

---

## Architecture Overview

Four entities manage HS code and tariff data:

| Entity | Table | Purpose |
|--------|-------|---------|
| `HsCode` | `hs_code` | Master code hierarchy (self-referential parent) |
| `DutyRate` | `duty_rate` | Import/export duty, VAT, excise rates per code + country |
| `HsRestriction` | `hs_restriction` | Trade restrictions and licence requirements |
| `HsVersionMapping` | `hs_version_mapping` | Cross-version code mappings (e.g., HS2017 → HS2022) |

All entities use the `BaseRepository` / `BaseService` / `CrudController` pattern.
`DutyRate`, `HsRestriction`, and `HsVersionMapping` use `EntityDateTimeAbleTrait` for `created_date` / `updated_date` audit columns.

---

## HsCode Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | Auto-generated |
| `code` | `code` | VARCHAR(10) | HS code string e.g. "01011010" |
| `description` | `description` | VARCHAR(500) | Product description |
| `level` | `level` | INT | Hierarchy level (2, 4, 6, 8, 10 digits) |
| `digits` | `digits` | INT | Number of digits in the code |
| `countryCode` | `country_code` | VARCHAR(2) | ISO country code; null = universal |
| `hsVersion` | `hs_version` | VARCHAR(10) | Schedule version e.g. "2022" |
| `isActive` | `is_active` | BOOLEAN | Default true |
| `effectiveFrom` | `effective_from` | DATE | nullable |
| `effectiveTo` | `effective_to` | DATE | nullable |
| `parent` | `parent_id` | INT FK → hs_code | Self-referential; ON DELETE SET NULL |

**Migration:** `Version20260624070000` (MySQL + SQLite)

---

## DutyRate Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | |
| `hsCode` | `hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `importCountry` | `import_country` | VARCHAR(2) | nullable |
| `exportCountry` | `export_country` | VARCHAR(2) | nullable |
| `rateType` | `rate_type` | VARCHAR(50) | MFN / FTA / PREFERENTIAL |
| `ftaName` | `fta_name` | VARCHAR(100) | FTA agreement name |
| `dutyRate` | `duty_rate` | DECIMAL(10,4) | PHP `?string` |
| `vatRate` | `vat_rate` | DECIMAL(10,4) | PHP `?string` |
| `exciseRate` | `excise_rate` | DECIMAL(10,4) | PHP `?string` |
| `effectiveFrom` | `effective_from` | DATE | nullable |
| `effectiveTo` | `effective_to` | DATE | nullable |

**Migration:** `Version20260624080000`

---

## HsRestriction Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | |
| `hsCode` | `hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `countryCode` | `country_code` | VARCHAR(2) | nullable |
| `restrictionType` | `restriction_type` | VARCHAR(50) | PROHIBITED / LICENCE_REQUIRED / QUOTA |
| `authority` | `authority` | VARCHAR(255) | Issuing authority name |
| `licenceType` | `licence_type` | VARCHAR(100) | nullable |
| `effectiveFrom` | `effective_from` | DATE | nullable |
| `effectiveTo` | `effective_to` | DATE | nullable |

**Migration:** `Version20260624090000`

---

## HsVersionMapping Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | |
| `oldHsCode` | `old_hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `newHsCode` | `new_hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `oldVersion` | `old_version` | VARCHAR(10) | e.g. "2017" |
| `newVersion` | `new_version` | VARCHAR(10) | e.g. "2022" |
| `changeType` | `change_type` | VARCHAR(50) | SPLIT / MERGE / RECLASSIFY |

**Migration:** `Version20260624100000`

---

## API Endpoints

### HsCode (`/hs-code`)

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/hs-code` | List with filter params |
| GET | `/hs-code/{id}` | Single record |
| POST | `/hs-code` | Create |
| PUT | `/hs-code` | Update |
| DELETE | `/hs-code/{id}` | Delete |
| GET | `/hs-code/search?q=...&limit=20` | Typeahead search by code/description |
| GET | `/hs-code/browse/{parentId}` | Browse children (parentId=0 for root) |
| POST | `/hs-code/calculate-duty` | Calculate duty breakdown |

### Calculate Duty

**Request:**
```json
{
  "hsCodeId": 42,
  "importCountry": "VN",
  "exportCountry": "US",
  "customsValue": 10000.00
}
```

**Response:**
```json
{
  "hsCode": { "id": 42, "code": "01011010", "description": "..." },
  "customsValue": 10000.00,
  "importCountry": "VN",
  "exportCountry": "US",
  "breakdown": [
    {
      "rateType": "MFN",
      "ftaName": null,
      "dutyRate": "5.0000",
      "vatRate": "10.0000",
      "exciseRate": null,
      "dutyAmount": 500.00,
      "vatAmount": 1050.00,
      "exciseAmount": null,
      "totalAmount": 1550.00
    }
  ]
}
```

VAT is applied to `customsValue + dutyAmount`. Excise is applied to `customsValue` only.

### DutyRate, HsRestriction, HsVersionMapping

Standard CRUD at `/duty-rate`, `/hs-restriction`, `/hs-version-mapping`.

---

## BO Pages

| Page | Route | File |
|------|-------|------|
| HS Codes | `/library/hs-code` | `src/pages/library/hs-code.vue` |
| Duty Rates | `/library/duty-rate` | `src/pages/library/duty-rate.vue` |
| HS Restrictions | `/library/hs-restriction` | `src/pages/library/hs-restriction.vue` |

All pages appear under **Library** in the sidebar. Each has a filterable table and a slide-in form for create/edit.

---

## Notes

- DECIMAL fields (`dutyRate`, `vatRate`, `exciseRate`) are PHP `?string` due to Doctrine's DECIMAL → string mapping. Cast with `(float)` before arithmetic.
- The `hsCode` field in DutyRate/HsRestriction forms sends an integer ID; the API's `DoctrineEntityDenormalizer` resolves it to an `HsCode` entity automatically.
- The `browse` endpoint returns root-level codes when `parentId=0`, or children of a given parent — useful for building a hierarchical tree picker in the BO.
```

- [ ] **Step 3: Commit both repos**

In `d:\Projects\make-cargo-client-bo`:
```
git add src/config/navigation/index.js
git commit -m "feat: add HS code, duty rate, restriction nav entries under Library"
```

In `d:\Projects\make-cargo-client`:
```
git add docs/guides/hs-codes.md
git commit -m "docs: add hs-codes guide"
```

---

## Self-Review

**Spec coverage:**
- ✅ `HsCode` entity with self-referential parent, all fields, int PK
- ✅ `DutyRate` entity with DECIMAL rates mapped to PHP `?string`
- ✅ `HsRestriction` entity with restriction types
- ✅ `HsVersionMapping` entity with dual FK to HsCode
- ✅ MySQL + SQLite migrations for all 4 tables (migration IDs: 070000–100000)
- ✅ `BaseRepository` subclass for all 4
- ✅ `BaseService` subclass for all 4, all registered in `app.auto_service_locator`
- ✅ Serializer group YAMLs for all 4 entities
- ✅ `HsCodeController` with `search`, `browse`, `calculate-duty` custom routes plus CRUD traits
- ✅ `DutyRateController`, `HsRestrictionController`, `HsVersionMappingController` (CRUD traits)
- ✅ BO services for HsCode, DutyRate, HsRestriction
- ✅ BO form configs, table configs, form views, list pages for all three
- ✅ Navigation entries added to Library section
- ✅ `docs/guides/hs-codes.md`

**Placeholder scan:** None found — all code blocks are complete.

**Type consistency:**
- DECIMAL property types are `?string` throughout entities and method signatures ✅
- `getCreatedAt()` / `getUpdatedAt()` come from `EntityDateTimeAbleTrait`; referenced in serializer YAMLs as `createdAt` / `updatedAt` ✅
- `calculate-duty` uses `(float)` cast on DECIMAL strings before arithmetic ✅
- BO table `renderObject` uses `?.` optional chaining on nested `hsCode` object ✅
- `HsVersionMapping` uses explicit `name:` in both `#[ORM\JoinColumn]` to disambiguate the two FKs ✅
