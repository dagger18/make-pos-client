# Accounting Phase 2: General Ledger — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a double-entry general ledger — Chart of Accounts, JournalEntry, JournalLine — and a service that auto-posts balanced journal entries whenever an EbitNote transitions to Active/Done status.

**Architecture:** Two new entities (`ChartOfAccount`, `JournalEntry`+`JournalLine`) backed by their own tables. `JournalPostingService` maps EbitNote type → GL accounts using `chargeType` on ChargeItems. Posting is triggered from `EbitNoteController` existing POST/PUT actions. A seeding migration inserts the 20 standard freight accounts. A new `JournalEntryController` exposes read-only GL queries. BO gets a settings page (CoA) and an accounting page (journal list).

**Tech Stack:** PHP 8.1+, Doctrine ORM, Symfony 6, Vue 3 Composition API, Vuetify 3

**Prerequisite:** Phase 1 must be complete (EbitNote has new fields; VarianceStatus enum exists).

---

## Repo paths

- API: `d:\Projects\make-cargo-client`
- BO:  `d:\Projects\make-cargo-client-bo`

## GL account mapping (chargeType → account codes)

| chargeType value | Revenue account | COGS account |
|---|---|---|
| `FREIGHT` or `freight` | 4100 | 5100 |
| `LOCAL` or `local` | 4120 | 5120 |
| `CUSTOMS` or `customs` | 4130 | 5130 |
| `SERVICE` or `service` | 4140 | 5140 |
| _(anything else)_ | 4100 | 5100 |

AR account = 1100, AP account = 2100, Cash = 1200, FX Gain/Loss = 6900.

---

## Task 1: ChartOfAccount entity

**Files:**
- Create: `src/Entity/ChartOfAccount.php`
- Create: `src/Repository/ChartOfAccountRepository.php`

- [ ] **Step 1: Create entity**

```php
<?php
// src/Entity/ChartOfAccount.php
namespace App\Entity;

use App\Repository\ChartOfAccountRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChartOfAccountRepository::class)]
class ChartOfAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 128)]
    private string $name = '';

    #[ORM\Column(length: 16)]
    private string $accountType = ''; // ASSET, LIABILITY, REVENUE, COST, OTHER

    #[ORM\Column]
    private bool $isActive = true;

    public function getId(): ?int { return $this->id; }

    public function getCode(): string { return $this->code; }
    public function setCode(string $c): static { $this->code = $c; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $n): static { $this->name = $n; return $this; }

    public function getAccountType(): string { return $this->accountType; }
    public function setAccountType(string $t): static { $this->accountType = $t; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }
}
```

- [ ] **Step 2: Create repository**

```php
<?php
// src/Repository/ChartOfAccountRepository.php
namespace App\Repository;

use App\Entity\ChartOfAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChartOfAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChartOfAccount::class);
    }

    public function findByCode(string $code): ?ChartOfAccount
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function save(ChartOfAccount $account): void
    {
        $em = $this->getEntityManager();
        $em->persist($account);
        $em->flush();
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Entity/ChartOfAccount.php src/Repository/ChartOfAccountRepository.php
git commit -m "feat(gl): add ChartOfAccount entity and repository"
```

---

## Task 2: JournalEntry and JournalLine entities

**Files:**
- Create: `src/Entity/JournalEntry.php`
- Create: `src/Entity/JournalLine.php`
- Create: `src/Repository/JournalEntryRepository.php`

- [ ] **Step 1: Create JournalEntry**

```php
<?php
// src/Entity/JournalEntry.php
namespace App\Entity;

use App\Repository\JournalEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JournalEntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class JournalEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $journalNumber = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?EbitNote $ebitNote = null;

    #[ORM\Column(length: 32)]
    private string $sourceType = ''; // AR_INVOICE, AP_BILL, AR_PAYMENT, AP_PAYMENT, CREDIT_NOTE, MANUAL

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $entryDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isPosted = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $postedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $postedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'journalEntry', targetEntity: JournalLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void { $this->createdAt = new \DateTime(); }

    public function getId(): ?int { return $this->id; }

    public function getJournalNumber(): string { return $this->journalNumber; }
    public function setJournalNumber(string $n): static { $this->journalNumber = $n; return $this; }

    public function getEbitNote(): ?EbitNote { return $this->ebitNote; }
    public function setEbitNote(?EbitNote $e): static { $this->ebitNote = $e; return $this; }

    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $t): static { $this->sourceType = $t; return $this; }

    public function getEntryDate(): ?\DateTimeInterface { return $this->entryDate; }
    public function setEntryDate(\DateTimeInterface $d): static { $this->entryDate = $d; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function isPosted(): bool { return $this->isPosted; }
    public function setIsPosted(bool $v): static { $this->isPosted = $v; return $this; }

    public function getPostedAt(): ?\DateTimeInterface { return $this->postedAt; }
    public function setPostedAt(?\DateTimeInterface $d): static { $this->postedAt = $d; return $this; }

    public function getPostedBy(): ?User { return $this->postedBy; }
    public function setPostedBy(?User $u): static { $this->postedBy = $u; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    /** @return Collection<int, JournalLine> */
    public function getLines(): Collection { return $this->lines; }

    public function addLine(JournalLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setJournalEntry($this);
        }
        return $this;
    }
}
```

- [ ] **Step 2: Create JournalLine**

```php
<?php
// src/Entity/JournalLine.php
namespace App\Entity;

use App\Repository\JournalLineRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: JournalLineRepository::class)]
class JournalLine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private ?JournalEntry $journalEntry = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?ChartOfAccount $account = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $debit = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $credit = 0;

    #[ORM\Column(length: 8)]
    private string $currency = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $baseDebit = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4)]
    private float $baseCredit = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    private float $fxRate = 1;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int { return $this->id; }

    public function getJournalEntry(): ?JournalEntry { return $this->journalEntry; }
    public function setJournalEntry(?JournalEntry $e): static { $this->journalEntry = $e; return $this; }

    public function getAccount(): ?ChartOfAccount { return $this->account; }
    public function setAccount(?ChartOfAccount $a): static { $this->account = $a; return $this; }

    public function getDebit(): float { return $this->debit; }
    public function setDebit(float $v): static { $this->debit = $v; return $this; }

    public function getCredit(): float { return $this->credit; }
    public function setCredit(float $v): static { $this->credit = $v; return $this; }

    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $c): static { $this->currency = $c; return $this; }

    public function getBaseDebit(): float { return $this->baseDebit; }
    public function setBaseDebit(float $v): static { $this->baseDebit = $v; return $this; }

    public function getBaseCredit(): float { return $this->baseCredit; }
    public function setBaseCredit(float $v): static { $this->baseCredit = $v; return $this; }

    public function getFxRate(): float { return $this->fxRate; }
    public function setFxRate(float $v): static { $this->fxRate = $v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }
}
```

- [ ] **Step 3: Create JournalEntryRepository**

```php
<?php
// src/Repository/JournalEntryRepository.php
namespace App\Repository;

use App\Entity\JournalEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class JournalEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalEntry::class);
    }

    public function findByEbitNote(int $ebitNoteId): array
    {
        return $this->createQueryBuilder('j')
            ->andWhere('j.ebitNote = :id')
            ->setParameter('id', $ebitNoteId)
            ->orderBy('j.entryDate', 'DESC')
            ->getQuery()->getResult();
    }

    public function save(JournalEntry $entry): void
    {
        $em = $this->getEntityManager();
        $em->persist($entry);
        $em->flush();
    }
}
```

- [ ] **Step 4: Create JournalLineRepository stub**

```php
<?php
// src/Repository/JournalLineRepository.php
namespace App\Repository;

use App\Entity\JournalLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class JournalLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalLine::class);
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Entity/JournalEntry.php src/Entity/JournalLine.php src/Repository/JournalEntryRepository.php src/Repository/JournalLineRepository.php
git commit -m "feat(gl): add JournalEntry, JournalLine entities and repositories"
```

---

## Task 3: Migration — GL tables + seed default accounts

**Files:**
- Create: `migrations/mysql/Version20260622160000.php`
- Create: `migrations/sqlite/Version20260622160000.php`

- [ ] **Step 1: Create MySQL migration**

```php
<?php
// migrations/mysql/Version20260622160000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting Phase 2: chart_of_account, journal_entry, journal_line tables + seed accounts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE chart_of_account (
                id INT AUTO_INCREMENT NOT NULL,
                code VARCHAR(16) NOT NULL,
                name VARCHAR(128) NOT NULL,
                account_type VARCHAR(16) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE INDEX UNIQ_COA_CODE (code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB
        ");

        $this->addSql("
            CREATE TABLE journal_entry (
                id INT AUTO_INCREMENT NOT NULL,
                journal_number VARCHAR(64) NOT NULL,
                ebit_note_id INT DEFAULT NULL,
                source_type VARCHAR(32) NOT NULL,
                entry_date DATE NOT NULL,
                description LONGTEXT DEFAULT NULL,
                is_posted TINYINT(1) NOT NULL DEFAULT 0,
                posted_at DATETIME DEFAULT NULL,
                posted_by_id INT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_JE_NUMBER (journal_number),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB
        ");

        $this->addSql("ALTER TABLE journal_entry
            ADD CONSTRAINT FK_JE_EBIT_NOTE FOREIGN KEY (ebit_note_id) REFERENCES ebit_note(id) ON DELETE SET NULL,
            ADD CONSTRAINT FK_JE_POSTED_BY FOREIGN KEY (posted_by_id)  REFERENCES user(id) ON DELETE SET NULL
        ");

        $this->addSql("
            CREATE TABLE journal_line (
                id INT AUTO_INCREMENT NOT NULL,
                journal_entry_id INT NOT NULL,
                account_id INT NOT NULL,
                debit DECIMAL(15,4) NOT NULL DEFAULT 0,
                credit DECIMAL(15,4) NOT NULL DEFAULT 0,
                currency VARCHAR(8) NOT NULL,
                base_debit DECIMAL(15,4) NOT NULL DEFAULT 0,
                base_credit DECIMAL(15,4) NOT NULL DEFAULT 0,
                fx_rate DECIMAL(15,6) NOT NULL DEFAULT 1,
                description LONGTEXT DEFAULT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB
        ");

        $this->addSql("ALTER TABLE journal_line
            ADD CONSTRAINT FK_JL_ENTRY   FOREIGN KEY (journal_entry_id) REFERENCES journal_entry(id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_JL_ACCOUNT FOREIGN KEY (account_id)       REFERENCES chart_of_account(id)
        ");

        // Seed standard freight-forwarder chart of accounts
        $this->addSql("INSERT INTO chart_of_account (code, name, account_type) VALUES
            ('1100', 'Accounts Receivable',        'ASSET'),
            ('1110', 'AR - Ocean Freight',         'ASSET'),
            ('1120', 'AR - Air Freight',           'ASSET'),
            ('1130', 'AR - Local Charges',         'ASSET'),
            ('1140', 'AR - Customs Charges',       'ASSET'),
            ('1200', 'Cash and Bank',              'ASSET'),
            ('2100', 'Accounts Payable',           'LIABILITY'),
            ('2110', 'AP - Carriers',              'LIABILITY'),
            ('2120', 'AP - Overseas Agents',       'LIABILITY'),
            ('2130', 'AP - Customs Brokers',       'LIABILITY'),
            ('2140', 'AP - Truckers',              'LIABILITY'),
            ('4100', 'Revenue - Ocean Freight',    'REVENUE'),
            ('4110', 'Revenue - Air Freight',      'REVENUE'),
            ('4120', 'Revenue - Local Charges',    'REVENUE'),
            ('4130', 'Revenue - Customs Charges',  'REVENUE'),
            ('4140', 'Revenue - Service Charges',  'REVENUE'),
            ('5100', 'COGS - Ocean Freight',       'COST'),
            ('5120', 'COGS - Local Charges',       'COST'),
            ('5130', 'COGS - Customs / Duty',      'COST'),
            ('5140', 'COGS - Service Charges',     'COST'),
            ('6900', 'FX Gain / Loss',             'OTHER')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE journal_line");
        $this->addSql("DROP TABLE journal_entry");
        $this->addSql("DROP TABLE chart_of_account");
    }
}
```

- [ ] **Step 2: Create SQLite migration**

```php
<?php
// migrations/sqlite/Version20260622160000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting Phase 2: GL tables + seed accounts (SQLite)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE chart_of_account (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                code VARCHAR(16) NOT NULL,
                name VARCHAR(128) NOT NULL,
                account_type VARCHAR(16) NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                CONSTRAINT UNIQ_COA_CODE UNIQUE (code)
            )
        ");

        $this->addSql("
            CREATE TABLE journal_entry (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                journal_number VARCHAR(64) NOT NULL,
                ebit_note_id INTEGER DEFAULT NULL,
                source_type VARCHAR(32) NOT NULL,
                entry_date DATE NOT NULL,
                description TEXT DEFAULT NULL,
                is_posted INTEGER NOT NULL DEFAULT 0,
                posted_at DATETIME DEFAULT NULL,
                posted_by_id INTEGER DEFAULT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT UNIQ_JE_NUMBER UNIQUE (journal_number)
            )
        ");

        $this->addSql("
            CREATE TABLE journal_line (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                journal_entry_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                debit DECIMAL(15,4) NOT NULL DEFAULT 0,
                credit DECIMAL(15,4) NOT NULL DEFAULT 0,
                currency VARCHAR(8) NOT NULL,
                base_debit DECIMAL(15,4) NOT NULL DEFAULT 0,
                base_credit DECIMAL(15,4) NOT NULL DEFAULT 0,
                fx_rate DECIMAL(15,6) NOT NULL DEFAULT 1,
                description TEXT DEFAULT NULL
            )
        ");

        $this->addSql("INSERT INTO chart_of_account (code, name, account_type) VALUES
            ('1100','Accounts Receivable','ASSET'),('1200','Cash and Bank','ASSET'),
            ('2100','Accounts Payable','LIABILITY'),
            ('4100','Revenue - Ocean Freight','REVENUE'),('4120','Revenue - Local Charges','REVENUE'),
            ('4130','Revenue - Customs Charges','REVENUE'),('4140','Revenue - Service Charges','REVENUE'),
            ('5100','COGS - Ocean Freight','COST'),('5120','COGS - Local Charges','COST'),
            ('5130','COGS - Customs / Duty','COST'),('5140','COGS - Service Charges','COST'),
            ('6900','FX Gain / Loss','OTHER')
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP TABLE journal_line");
        $this->addSql("DROP TABLE journal_entry");
        $this->addSql("DROP TABLE chart_of_account");
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add migrations/mysql/Version20260622160000.php migrations/sqlite/Version20260622160000.php
git commit -m "feat(gl): migration for chart_of_account, journal_entry, journal_line + seed accounts"
```

---

## Task 4: JournalPostingService

**Files:**
- Create: `src/Service/JournalPostingService.php`

Rules:
- Only posts if no existing `JournalEntry` for this `ebitNote` + `sourceType` (idempotent).
- All base amounts calculated as `amount->getAmount() / amount->getRate()`.
- Journal number format: `JNL-{YYYYMM}-{padded_id}` (generated after persist).

- [ ] **Step 1: Create service**

```php
<?php
// src/Service/JournalPostingService.php
namespace App\Service;

use App\Entity\EbitNote;
use App\Entity\JournalEntry;
use App\Entity\JournalLine;
use App\Misc\Enum\EbitNoteType;
use App\Repository\ChartOfAccountRepository;
use App\Repository\JournalEntryRepository;

class JournalPostingService
{
    private const CHARGE_TYPE_MAP = [
        'freight' => ['revenue' => '4100', 'cogs' => '5100'],
        'FREIGHT' => ['revenue' => '4100', 'cogs' => '5100'],
        'local'   => ['revenue' => '4120', 'cogs' => '5120'],
        'LOCAL'   => ['revenue' => '4120', 'cogs' => '5120'],
        'customs' => ['revenue' => '4130', 'cogs' => '5130'],
        'CUSTOMS' => ['revenue' => '4130', 'cogs' => '5130'],
        'service' => ['revenue' => '4140', 'cogs' => '5140'],
        'SERVICE' => ['revenue' => '4140', 'cogs' => '5140'],
    ];

    public function __construct(
        private readonly JournalEntryRepository  $journalRepo,
        private readonly ChartOfAccountRepository $coaRepo,
    ) {}

    private function baseAmount(EbitNote $note): float
    {
        $money = $note->getAmount();
        if (!$money || !$money->getRate() || $money->getRate() == 0) return 0;
        return round($money->getAmount() / $money->getRate(), 4);
    }

    private function account(string $code): ?\App\Entity\ChartOfAccount
    {
        return $this->coaRepo->findByCode($code);
    }

    private function line(JournalEntry $je, string $code, float $debit, float $credit, string $currency, float $rate, ?string $desc = null): void
    {
        $acc = $this->account($code);
        if (!$acc) return;
        $baseDebit  = $debit  > 0 ? round($debit  / $rate, 4) : 0;
        $baseCredit = $credit > 0 ? round($credit / $rate, 4) : 0;
        $l = (new JournalLine())
            ->setAccount($acc)
            ->setDebit($debit)->setCredit($credit)
            ->setCurrency($currency)
            ->setBaseDebit($baseDebit)->setBaseCredit($baseCredit)
            ->setFxRate($rate)
            ->setDescription($desc);
        $je->addLine($l);
    }

    private function entry(EbitNote $note, string $sourceType): JournalEntry
    {
        $je = new JournalEntry();
        $je->setEbitNote($note)
           ->setSourceType($sourceType)
           ->setEntryDate(new \DateTime())
           ->setIsPosted(true)
           ->setPostedAt(new \DateTime());
        $this->journalRepo->save($je);
        $je->setJournalNumber('JNL-' . (new \DateTime())->format('Ym') . '-' . str_pad((string)$je->getId(), 5, '0', STR_PAD_LEFT));
        $this->journalRepo->save($je);
        return $je;
    }

    public function postArInvoice(EbitNote $note): void
    {
        $currency = $note->getCurrency() ?? 'USD';
        $rate     = $note->getAmount()?->getRate() ?? 1;
        $je       = $this->entry($note, 'AR_INVOICE');
        $totalBase = 0;

        foreach ($note->getChargeItems() as $item) {
            $itemAmt  = $item->getAmount()?->getAmount() ?? 0;
            $itemRate = $item->getAmount()?->getRate() ?? $rate;
            $itemBase = $itemRate > 0 ? round($itemAmt / $itemRate, 4) : 0;
            $chargeType = $item->getChargeType() ?? 'FREIGHT';
            $revenueCode = self::CHARGE_TYPE_MAP[$chargeType]['revenue'] ?? '4100';
            $this->line($je, $revenueCode, 0, $itemBase, $currency, $itemRate, $item->getChargeName());
            $totalBase += $itemBase;
        }

        // DR Accounts Receivable
        $this->line($je, '1100', $totalBase, 0, $currency, $rate, 'AR - ' . $note->getCode());
    }

    public function postApBill(EbitNote $note): void
    {
        $currency = $note->getCurrency() ?? 'USD';
        $rate     = $note->getAmount()?->getRate() ?? 1;
        $je       = $this->entry($note, 'AP_BILL');
        $totalBase = 0;

        foreach ($note->getChargeItems() as $item) {
            $itemAmt  = $item->getAmount()?->getAmount() ?? 0;
            $itemRate = $item->getAmount()?->getRate() ?? $rate;
            $itemBase = $itemRate > 0 ? round($itemAmt / $itemRate, 4) : 0;
            $chargeType = $item->getChargeType() ?? 'FREIGHT';
            $cogsCode = self::CHARGE_TYPE_MAP[$chargeType]['cogs'] ?? '5100';
            $this->line($je, $cogsCode, $itemBase, 0, $currency, $itemRate, $item->getChargeName());
            $totalBase += $itemBase;
        }

        // CR Accounts Payable
        $this->line($je, '2100', 0, $totalBase, $currency, $rate, 'AP - ' . $note->getCode());
    }

    public function postArPayment(EbitNote $receipt): void
    {
        $parent   = $receipt->getParentNote();
        $currency = $receipt->getCurrency() ?? 'USD';
        $rate     = $receipt->getAmount()?->getRate() ?? 1;
        $paidBase = $this->baseAmount($receipt);
        $invBase  = $parent ? $this->baseAmount($parent) : $paidBase;
        $fxGL     = round($paidBase - $invBase, 4);
        $je       = $this->entry($receipt, 'AR_PAYMENT');

        // DR Cash
        $this->line($je, '1200', $paidBase, 0, $currency, $rate, 'Receipt ' . $receipt->getCode());
        // CR AR
        $this->line($je, '1100', 0, $invBase, $currency, $parent?->getAmount()?->getRate() ?? $rate);
        // FX Gain/Loss if applicable
        if (abs($fxGL) > 0.001) {
            if ($fxGL > 0) {
                $this->line($je, '6900', 0, $fxGL, $currency, 1, 'FX Gain on ' . $receipt->getCode());
            } else {
                $this->line($je, '6900', abs($fxGL), 0, $currency, 1, 'FX Loss on ' . $receipt->getCode());
            }
        }
    }

    public function postApPayment(EbitNote $payment): void
    {
        $parent   = $payment->getParentNote();
        $currency = $payment->getCurrency() ?? 'USD';
        $rate     = $payment->getAmount()?->getRate() ?? 1;
        $paidBase = $this->baseAmount($payment);
        $billBase = $parent ? $this->baseAmount($parent) : $paidBase;
        $fxGL     = round($billBase - $paidBase, 4);
        $je       = $this->entry($payment, 'AP_PAYMENT');

        // DR AP
        $this->line($je, '2100', $billBase, 0, $currency, $parent?->getAmount()?->getRate() ?? $rate, 'AP ' . $payment->getCode());
        // CR Cash
        $this->line($je, '1200', 0, $paidBase, $currency, $rate);
        // FX Gain/Loss
        if (abs($fxGL) > 0.001) {
            if ($fxGL > 0) {
                $this->line($je, '6900', 0, $fxGL, $currency, 1, 'FX Gain on ' . $payment->getCode());
            } else {
                $this->line($je, '6900', abs($fxGL), 0, $currency, 1, 'FX Loss on ' . $payment->getCode());
            }
        }
    }

    public function postCreditNote(EbitNote $cn): void
    {
        $currency = $cn->getCurrency() ?? 'USD';
        $rate     = $cn->getAmount()?->getRate() ?? 1;
        $base     = $this->baseAmount($cn);
        $je       = $this->entry($cn, 'CREDIT_NOTE');

        // DR Revenue (reverse), CR AR
        $this->line($je, '4100', $base, 0, $currency, $rate, 'CN ' . $cn->getCode());
        $this->line($je, '1100', 0, $base, $currency, $rate);
    }
}
```

- [ ] **Step 2: Register in services.yaml**

In `config/services.yaml`, in the `app.auto_service_locator` arguments:

```yaml
                App\Service\JournalPostingService: '@App\Service\JournalPostingService'
```

- [ ] **Step 3: Commit**

```bash
git add src/Service/JournalPostingService.php config/services.yaml
git commit -m "feat(gl): JournalPostingService auto-posts balanced journal entries per EbitNote type"
```

---

## Task 5: Hook JournalPostingService into EbitNoteController

**Files:**
- Modify: `src/Controller/Api/EbitNoteController.php`

- [ ] **Step 1: Inject JournalPostingService**

Add to constructor:

```php
    protected JournalPostingService $journalPostingService,
```

Add import: `use App\Service\JournalPostingService;`

- [ ] **Step 2: Hook into POST action**

In the `POST` method, before `return $this->json(...)`, add:

```php
        $this->postJournalIfNeeded($entity);
```

- [ ] **Step 3: Add private helper method**

```php
    private function postJournalIfNeeded(\App\Entity\EbitNote $note): void
    {
        match ($note->getType()) {
            EbitNoteType::InvoiceDebit  => $this->journalPostingService->postArInvoice($note),
            EbitNoteType::InvoiceCredit => $this->journalPostingService->postApBill($note),
            EbitNoteType::RecordReceipt => $this->journalPostingService->postArPayment($note),
            EbitNoteType::RecordPayment => $this->journalPostingService->postApPayment($note),
            EbitNoteType::Credit        => $this->journalPostingService->postCreditNote($note),
            default => null,
        };
    }
```

Also call `$this->postJournalIfNeeded($entity)` in `markPaidInvoiceDebit` when status is set to `Done` and `RecordReceipt` is created.

- [ ] **Step 4: Commit**

```bash
git add src/Controller/Api/EbitNoteController.php
git commit -m "feat(gl): auto-post journal entries on EbitNote create via JournalPostingService"
```

---

## Task 6: ChartOfAccountController + JournalEntryController

**Files:**
- Create: `src/Controller/Api/ChartOfAccountController.php`
- Create: `src/Controller/Api/JournalEntryController.php`

- [ ] **Step 1: Create ChartOfAccountController**

```php
<?php
// src/Controller/Api/ChartOfAccountController.php
namespace App\Controller\Api;

use App\Entity\ChartOfAccount;
use App\Repository\ChartOfAccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/chart-of-account')]
#[IsGranted('ROLE_USER')]
class ChartOfAccountController extends AbstractController
{
    public function __construct(private readonly ChartOfAccountRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $accounts = $this->repo->findBy([], ['code' => 'ASC']);
        return $this->json(array_map(fn($a) => [
            'id'          => $a->getId(),
            'code'        => $a->getCode(),
            'name'        => $a->getName(),
            'accountType' => $a->getAccountType(),
            'isActive'    => $a->isActive(),
        ], $accounts));
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $account = $this->repo->find($id);
        if (!$account) throw $this->createNotFoundException();
        $body = json_decode($request->getContent(), true) ?? [];
        if (isset($body['name'])) $account->setName($body['name']);
        if (isset($body['isActive'])) $account->setIsActive((bool) $body['isActive']);
        $this->repo->save($account);
        return $this->json(['id' => $account->getId(), 'code' => $account->getCode(), 'name' => $account->getName()]);
    }
}
```

- [ ] **Step 2: Create JournalEntryController**

```php
<?php
// src/Controller/Api/JournalEntryController.php
namespace App\Controller\Api;

use App\Repository\JournalEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/journal')]
#[IsGranted('ROLE_USER')]
class JournalEntryController extends AbstractController
{
    public function __construct(private readonly JournalEntryRepository $repo) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $ebitNoteId = $request->query->get('ebitNoteId');
        if ($ebitNoteId) {
            $entries = $this->repo->findByEbitNote((int) $ebitNoteId);
        } else {
            $entries = $this->repo->findBy([], ['entryDate' => 'DESC'], 100);
        }
        return $this->json(array_map(fn($e) => $this->serialize($e), $entries));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $entry = $this->repo->find($id);
        if (!$entry) throw $this->createNotFoundException();
        return $this->json($this->serialize($entry, true));
    }

    private function serialize(\App\Entity\JournalEntry $e, bool $withLines = false): array
    {
        $data = [
            'id'            => $e->getId(),
            'journalNumber' => $e->getJournalNumber(),
            'sourceType'    => $e->getSourceType(),
            'entryDate'     => $e->getEntryDate()?->format('Y-m-d'),
            'description'   => $e->getDescription(),
            'isPosted'      => $e->isPosted(),
            'ebitNoteId'    => $e->getEbitNote()?->getId(),
            'ebitNoteCode'  => $e->getEbitNote()?->getCode(),
        ];
        if ($withLines) {
            $data['lines'] = array_map(fn($l) => [
                'account'     => $l->getAccount()?->getCode() . ' ' . $l->getAccount()?->getName(),
                'accountCode' => $l->getAccount()?->getCode(),
                'debit'       => $l->getDebit(),
                'credit'      => $l->getCredit(),
                'baseDebit'   => $l->getBaseDebit(),
                'baseCredit'  => $l->getBaseCredit(),
                'currency'    => $l->getCurrency(),
                'fxRate'      => $l->getFxRate(),
                'description' => $l->getDescription(),
            ], $e->getLines()->toArray());
        }
        return $data;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/ChartOfAccountController.php src/Controller/Api/JournalEntryController.php
git commit -m "feat(gl): ChartOfAccountController and JournalEntryController"
```

---

## Task 7: BO — services and pages

**Files:**
- Create: `src/services/ChartOfAccountService.js`
- Create: `src/services/JournalService.js`
- Create: `src/pages/accounting/journal.vue`
- Modify: `src/config/navigation/index.js` (in BO repo — add GL items)

- [ ] **Step 1: Create ChartOfAccountService.js**

```js
// src/services/ChartOfAccountService.js
export default {
  list() { return $api('chart-of-account') },
  update(id, data) { return $api(`chart-of-account/${id}`, { method: 'PUT', body: data }) },
}
```

- [ ] **Step 2: Create JournalService.js**

```js
// src/services/JournalService.js
export default {
  list(params = '') { return $api(`journal?${params}`) },
  get(id)           { return $api(`journal/${id}`) },
  byEbitNote(id)    { return $api(`journal?ebitNoteId=${id}`) },
}
```

- [ ] **Step 3: Create journal.vue page**

```vue
<!-- src/pages/accounting/journal.vue -->
<script setup>
import JournalService from '@/services/JournalService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const entries = ref([])
const selected = ref(null)
const loading = ref(false)
const TYPE_LABELS = { AR_INVOICE: 'AR Invoice', AP_BILL: 'AP Bill', AR_PAYMENT: 'AR Receipt', AP_PAYMENT: 'AP Payment', CREDIT_NOTE: 'Credit Note', MANUAL: 'Manual' }

async function load() {
  loading.value = true
  entries.value = await JournalService.list()
  loading.value = false
}

async function openDetail(entry) {
  selected.value = await JournalService.get(entry.id)
}

onMounted(load)
</script>

<template>
  <VContainer fluid>
    <h4 class="text-h5 font-weight-bold mb-4">Journal Entries</h4>
    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Journal #</th>
            <th>Type</th>
            <th>Date</th>
            <th>EbitNote</th>
            <th>Posted</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="6" class="text-center pa-4"><VProgressCircular indeterminate size="24" /></td></tr>
          <tr v-for="e in entries" :key="e.id">
            <td class="font-weight-medium">{{ e.journalNumber }}</td>
            <td><VChip size="small" label>{{ TYPE_LABELS[e.sourceType] ?? e.sourceType }}</VChip></td>
            <td>{{ e.entryDate }}</td>
            <td>{{ e.ebitNoteCode ?? '—' }}</td>
            <td><VIcon :icon="e.isPosted ? 'tabler-circle-check' : 'tabler-clock'" :color="e.isPosted ? 'success' : 'warning'" size="16" /></td>
            <td>
              <VBtn size="x-small" variant="text" @click="openDetail(e)"><VIcon icon="tabler-eye" size="16" /></VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </VContainer>

  <VDialog v-model="!!selected" max-width="700" @update:model-value="v => { if (!v) selected = null }">
    <VCard v-if="selected" :title="selected.journalNumber">
      <VCardText>
        <VTable density="compact">
          <thead><tr><th>Account</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Ccy</th><th>Description</th></tr></thead>
          <tbody>
            <tr v-for="(l, i) in selected.lines" :key="i">
              <td>{{ l.account }}</td>
              <td class="text-right">{{ l.debit > 0 ? Number(l.debit).toLocaleString() : '' }}</td>
              <td class="text-right">{{ l.credit > 0 ? Number(l.credit).toLocaleString() : '' }}</td>
              <td>{{ l.currency }}</td>
              <td>{{ l.description }}</td>
            </tr>
          </tbody>
        </VTable>
      </VCardText>
      <VCardActions class="justify-end"><VBtn @click="selected = null">Close</VBtn></VCardActions>
    </VCard>
  </VDialog>
</template>
```

- [ ] **Step 4: Add GL items to navigation**

In `src/config/navigation/index.js`, inside the existing Accounting section or after the `accounting` route item, find the Reports section and before it add (or merge into Accounting if it's a children array):

```js
  {
    title: $gettext('General Ledger'),
    icon: { icon: 'tabler-book-2' },
    children: [
      { title: $gettext('Journal Entries'), to: { name: 'accounting-journal' }, subject: 'EbitNote', action: 'GET' },
      { title: $gettext('Chart of Accounts'), to: { name: 'setting-chart-of-accounts' }, subject: 'Config', action: 'GET' },
    ]
  },
```

- [ ] **Step 5: Commit**

```bash
git add src/services/ChartOfAccountService.js src/services/JournalService.js src/pages/accounting/journal.vue src/config/navigation/index.js
git commit -m "feat(gl): BO journal entries page, GL navigation items, ChartOfAccount and Journal services"
```
