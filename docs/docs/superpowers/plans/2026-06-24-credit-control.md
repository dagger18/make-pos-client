# Credit Control Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement high and medium credit control features: automated exposure calculation, PASS/WARN/REQUIRE_APPROVAL/HARD_BLOCK decision engine, nightly status auto-escalation, shipment hold propagation, overdue notification rules, and BO credit visibility (utilisation bar, history tab, quote approval modal, shipment hold badge).

**Architecture:** `CreditCheckService` queries `AgeingRepository` for a client's live AR outstanding, computes utilisation against `Client.creditLimit`, and returns a structured decision. A nightly `UpdateClientCreditStatusCommand` auto-escalates ON_HOLD (>30d overdue) and BLOCKED (>90d). A Doctrine `postUpdate` listener on `Client` propagates credit holds to active shipments. `CreditLimitHistory` records every status/limit change for audit. The BO calls a new `/client/{id}/credit-check` endpoint and enforces the decision gate in quote creation.

**Tech Stack:** Symfony 7 / PHP 8.2, Doctrine ORM, Symfony Console, Vue 3 + Vuetify 3. Dual migrations: `migrations/mysql/` (namespace `DoctrineMigrations`) and `migrations/sqlite/` (namespace `SqlEngineMigrations`). Last existing migration: `Version20260624240000`. New migrations start at `Version20260624250000`.

---

## File Map

**API (`d:\Projects\make-cargo-client`):**

| File | Action |
|------|--------|
| `src/Entity/CreditLimitHistory.php` | Create |
| `src/Repository/CreditLimitHistoryRepository.php` | Create |
| `migrations/mysql/Version20260624250000.php` | Create — `credit_limit_history` table |
| `migrations/sqlite/Version20260624250000.php` | Create — same for SQLite |
| `src/Repository/AgeingRepository.php` | Modify — add `getClientExposure()` + `getClientsWithOverdueData()` |
| `src/Service/CreditCheckService.php` | Create |
| `config/services.yaml` | Modify — register `CreditCheckService` + `CreditLimitHistoryRepository` wait, auto-wired; just register CreditCheckService in locator |
| `src/Controller/Api/ClientController.php` | Modify — add `creditCheck()` + `creditHistory()` endpoints |
| `src/Command/UpdateClientCreditStatusCommand.php` | Create |
| `src/EventListener/ClientCreditListener.php` | Create |
| `migrations/mysql/Version20260624260000.php` | Create — seed overdue Day 1/14/30/60 notification rules |
| `migrations/sqlite/Version20260624260000.php` | Create — same for SQLite |

**BO (`d:\Projects\make-cargo-client-bo`):**

| File | Action |
|------|--------|
| `src/services/ClientService.js` | Modify — add `getCreditCheck()` + `getCreditHistory()` |
| `src/views/client/ClientGeneral.vue` | Modify — utilisation bar + available credit |
| `src/views/client/ClientCreditHistory.vue` | Create — history list component |
| `src/views/client/ClientDetail.vue` (or equivalent tab host) | Modify — add Credit History tab |
| `src/config/forms/quote/Quote.js` | Modify — credit check before submit |
| `src/views/quote/QuoteForm.vue` (or wherever quote is submitted) | Modify — approval modal |
| `src/config/tables/shipment/Shipment.js` | Modify — on-hold badge column |
| `docs/guides/credit-control.md` | Create |

---

## Task 1: CreditLimitHistory Entity + Repository + Migrations

**Files:**
- Create: `src/Entity/CreditLimitHistory.php`
- Create: `src/Repository/CreditLimitHistoryRepository.php`
- Create: `migrations/mysql/Version20260624250000.php`
- Create: `migrations/sqlite/Version20260624250000.php`

- [ ] **Step 1: Create the entity**

```php
<?php
// src/Entity/CreditLimitHistory.php
namespace App\Entity;

use App\Misc\Enum\CreditStatus;
use App\Misc\Traits\EntityDateTimeAbleTrait;
use App\Repository\CreditLimitHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreditLimitHistoryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CreditLimitHistory
{
    use EntityDateTimeAbleTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $changedBy = null;

    #[ORM\Column(length: 32)]
    private string $changeType = 'STATUS_CHANGE'; // STATUS_CHANGE | LIMIT_CHANGE | AUTO_ESCALATION

    #[ORM\Column(length: 16, nullable: true, enumType: CreditStatus::class)]
    private ?CreditStatus $oldStatus = null;

    #[ORM\Column(length: 16, nullable: true, enumType: CreditStatus::class)]
    private ?CreditStatus $newStatus = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?float $oldLimitAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?float $newLimitAmount = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    public function getId(): ?int { return $this->id; }

    public function getClient(): ?Client { return $this->client; }
    public function setClient(?Client $client): static { $this->client = $client; return $this; }

    public function getChangedBy(): ?User { return $this->changedBy; }
    public function setChangedBy(?User $changedBy): static { $this->changedBy = $changedBy; return $this; }

    public function getChangeType(): string { return $this->changeType; }
    public function setChangeType(string $changeType): static { $this->changeType = $changeType; return $this; }

    public function getOldStatus(): ?CreditStatus { return $this->oldStatus; }
    public function setOldStatus(?CreditStatus $oldStatus): static { $this->oldStatus = $oldStatus; return $this; }

    public function getNewStatus(): ?CreditStatus { return $this->newStatus; }
    public function setNewStatus(?CreditStatus $newStatus): static { $this->newStatus = $newStatus; return $this; }

    public function getOldLimitAmount(): ?float { return $this->oldLimitAmount; }
    public function setOldLimitAmount(?float $oldLimitAmount): static { $this->oldLimitAmount = $oldLimitAmount; return $this; }

    public function getNewLimitAmount(): ?float { return $this->newLimitAmount; }
    public function setNewLimitAmount(?float $newLimitAmount): static { $this->newLimitAmount = $newLimitAmount; return $this; }

    public function getCurrency(): ?string { return $this->currency; }
    public function setCurrency(?string $currency): static { $this->currency = $currency; return $this; }

    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): static { $this->reason = $reason; return $this; }
}
```

- [ ] **Step 2: Create the repository**

```php
<?php
// src/Repository/CreditLimitHistoryRepository.php
namespace App\Repository;

use App\Entity\CreditLimitHistory;
use Symfony\Component\HttpFoundation\Request;

class CreditLimitHistoryRepository extends BaseRepository
{
    public function findForClient(int $clientId): array
    {
        return $this->createQueryBuilder('h')
            ->where('h.client = :clientId')
            ->setParameter('clientId', $clientId)
            ->leftJoin('h.changedBy', 'u')
            ->addSelect('u')
            ->orderBy('h.createdDate', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    public function save(CreditLimitHistory $entity, ?Request $request = null): CreditLimitHistory
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
        return $entity;
    }
}
```

- [ ] **Step 3: Create MySQL migration**

```php
<?php
// migrations/mysql/Version20260624250000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624250000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create credit_limit_history table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE credit_limit_history (
            id INT AUTO_INCREMENT NOT NULL,
            client_id INT NOT NULL,
            changed_by_id INT DEFAULT NULL,
            change_type VARCHAR(32) NOT NULL,
            old_status VARCHAR(16) DEFAULT NULL,
            new_status VARCHAR(16) DEFAULT NULL,
            old_limit_amount NUMERIC(15,4) DEFAULT NULL,
            new_limit_amount NUMERIC(15,4) DEFAULT NULL,
            currency VARCHAR(8) DEFAULT NULL,
            reason LONGTEXT DEFAULT NULL,
            created_date DATETIME DEFAULT NULL,
            updated_date DATETIME DEFAULT NULL,
            INDEX IDX_clh_client (client_id),
            INDEX IDX_clh_changed_by (changed_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE credit_limit_history ADD CONSTRAINT FK_clh_client FOREIGN KEY (client_id) REFERENCES partner (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE credit_limit_history ADD CONSTRAINT FK_clh_changed_by FOREIGN KEY (changed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credit_limit_history');
    }
}
```

- [ ] **Step 4: Create SQLite migration**

```php
<?php
// migrations/sqlite/Version20260624250000.php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624250000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create credit_limit_history table'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE credit_limit_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            client_id INTEGER NOT NULL,
            changed_by_id INTEGER DEFAULT NULL,
            change_type VARCHAR(32) NOT NULL,
            old_status VARCHAR(16) DEFAULT NULL,
            new_status VARCHAR(16) DEFAULT NULL,
            old_limit_amount NUMERIC(15,4) DEFAULT NULL,
            new_limit_amount NUMERIC(15,4) DEFAULT NULL,
            currency VARCHAR(8) DEFAULT NULL,
            reason CLOB DEFAULT NULL,
            created_date DATETIME DEFAULT NULL,
            updated_date DATETIME DEFAULT NULL
        )');
        $this->addSql('CREATE INDEX IDX_clh_client ON credit_limit_history (client_id)');
        $this->addSql('CREATE INDEX IDX_clh_changed_by ON credit_limit_history (changed_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE credit_limit_history');
    }
}
```

- [ ] **Step 5: Run migrations and verify**

```
php bin/console doctrine:migrations:migrate --no-interaction
```

Expected: both `Version20260624250000` applied. Verify with:
```
php bin/console doctrine:migrations:status
```

- [ ] **Step 6: Commit**

```bash
git add src/Entity/CreditLimitHistory.php src/Repository/CreditLimitHistoryRepository.php migrations/mysql/Version20260624250000.php migrations/sqlite/Version20260624250000.php
git commit -m "feat: add CreditLimitHistory entity, repository, and migrations"
```

---

## Task 2: AgeingRepository — Client Exposure Methods

**Files:**
- Modify: `src/Repository/AgeingRepository.php`

- [ ] **Step 1: Add `getClientExposure()` method**

Append to the class body (before closing `}`):

```php
public function getClientExposure(int $clientId, string $currency): float
{
    $sql = "
        SELECT COALESCE(
            SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0),
            0
        ) AS outstanding
        FROM ebit_note en
        LEFT JOIN (
            SELECT parent_note_id, SUM(amount_amount) AS paid_amount
            FROM ebit_note
            WHERE type = 'RPT'
            GROUP BY parent_note_id
        ) paid ON paid.parent_note_id = en.id
        WHERE en.type = 'ID'
          AND en.status != 'D'
          AND en.collect_from_id = :clientId
          AND en.currency = :currency
    ";
    $result = $this->connection->fetchOne($sql, ['clientId' => $clientId, 'currency' => $currency]);
    return $result !== false ? (float) $result : 0.0;
}
```

- [ ] **Step 2: Add `getClientsWithOverdueData()` method**

```php
public function getClientsWithOverdueData(): array
{
    $sql = "
        SELECT
            en.collect_from_id AS client_id,
            en.currency,
            MAX(DATEDIFF(CURDATE(), en.due_date)) AS max_days_overdue,
            SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0) AS outstanding
        FROM ebit_note en
        LEFT JOIN (
            SELECT parent_note_id, SUM(amount_amount) AS paid_amount
            FROM ebit_note
            WHERE type = 'RPT'
            GROUP BY parent_note_id
        ) paid ON paid.parent_note_id = en.id
        WHERE en.type = 'ID'
          AND en.status != 'D'
          AND DATEDIFF(CURDATE(), en.due_date) > 0
        GROUP BY en.collect_from_id, en.currency
        HAVING outstanding > 0
        ORDER BY max_days_overdue DESC
    ";
    return $this->connection->fetchAllAssociative($sql);
}
```

- [ ] **Step 3: Verify syntax — clear cache**

```
php bin/console cache:clear
```

No errors expected.

- [ ] **Step 4: Commit**

```bash
git add src/Repository/AgeingRepository.php
git commit -m "feat: add client exposure and overdue data queries to AgeingRepository"
```

---

## Task 3: CreditCheckService + services.yaml Registration

**Files:**
- Create: `src/Service/CreditCheckService.php`
- Create: `src/Repository/CreditLimitHistoryRepository.php` (already created in Task 1)
- Modify: `config/services.yaml`

- [ ] **Step 1: Create CreditCheckService**

```php
<?php
// src/Service/CreditCheckService.php
namespace App\Service;

use App\Entity\Client;
use App\Entity\CreditLimitHistory;
use App\Entity\User;
use App\Misc\Enum\CreditStatus;
use App\Repository\AgeingRepository;
use App\Repository\CreditLimitHistoryRepository;

class CreditCheckService
{
    public function __construct(
        private readonly AgeingRepository            $ageingRepository,
        private readonly CreditLimitHistoryRepository $historyRepository,
    ) {}

    /**
     * Returns decision array:
     * [
     *   decision: PASS | WARN | REQUIRE_APPROVAL | HARD_BLOCK,
     *   reason:   string,
     *   exposure: float|null,
     *   limit:    float|null,
     *   currency: string|null,
     *   utilisation: float|null,   (0-100+)
     *   available:   float|null,
     * ]
     */
    public function check(Client $client): array
    {
        if (in_array($client->getCreditStatus(), [CreditStatus::Blocked, CreditStatus::Blacklisted])) {
            return [
                'decision'    => 'HARD_BLOCK',
                'reason'      => 'Client credit status is ' . $client->getCreditStatus()->value,
                'exposure'    => null,
                'limit'       => null,
                'currency'    => null,
                'utilisation' => null,
                'available'   => null,
            ];
        }

        $limitMoney = $client->getCreditLimit();
        $limitAmount = $limitMoney?->getAmount();
        $currency = $limitMoney?->getCurrency();

        if ($limitAmount === null || $limitAmount <= 0 || $currency === null) {
            return [
                'decision'    => 'PASS',
                'reason'      => 'No credit limit configured',
                'exposure'    => null,
                'limit'       => null,
                'currency'    => null,
                'utilisation' => null,
                'available'   => null,
            ];
        }

        $exposure = $this->ageingRepository->getClientExposure($client->getId(), $currency);
        $utilisation = ($exposure / $limitAmount) * 100;
        $available = $limitAmount - $exposure;

        $decision = match(true) {
            $utilisation > 100 => 'REQUIRE_APPROVAL',
            $utilisation >= 80 => 'WARN',
            default            => 'PASS',
        };

        return [
            'decision'    => $decision,
            'reason'      => match($decision) {
                'REQUIRE_APPROVAL' => 'Outstanding exposure exceeds credit limit',
                'WARN'             => 'Outstanding exposure is above 80% of credit limit',
                default            => 'Within credit limit',
            },
            'exposure'    => $exposure,
            'limit'       => $limitAmount,
            'currency'    => $currency,
            'utilisation' => round($utilisation, 2),
            'available'   => $available,
        ];
    }

    public function recordHistory(
        Client      $client,
        ?User       $changedBy,
        string      $changeType,
        ?CreditStatus $oldStatus,
        ?CreditStatus $newStatus,
        ?float      $oldLimitAmount = null,
        ?float      $newLimitAmount = null,
        ?string     $currency = null,
        ?string     $reason = null,
    ): CreditLimitHistory {
        $history = new CreditLimitHistory();
        $history->setClient($client)
            ->setChangedBy($changedBy)
            ->setChangeType($changeType)
            ->setOldStatus($oldStatus)
            ->setNewStatus($newStatus)
            ->setOldLimitAmount($oldLimitAmount)
            ->setNewLimitAmount($newLimitAmount)
            ->setCurrency($currency)
            ->setReason($reason);
        return $this->historyRepository->save($history);
    }
}
```

- [ ] **Step 2: Register CreditCheckService in config/services.yaml**

In `config/services.yaml`, inside the `app.auto_service_locator` arguments block, add after the `InAppNotificationService` line:

```yaml
                App\Service\CreditCheckService: '@App\Service\CreditCheckService'
```

- [ ] **Step 3: Verify container compiles**

```
php bin/console cache:clear
```

No errors expected.

- [ ] **Step 4: Commit**

```bash
git add src/Service/CreditCheckService.php config/services.yaml
git commit -m "feat: add CreditCheckService with PASS/WARN/REQUIRE_APPROVAL/HARD_BLOCK decision logic"
```

---

## Task 4: ClientController — Credit Check + History Endpoints

**Files:**
- Modify: `src/Controller/Api/ClientController.php`

- [ ] **Step 1: Add use statements to ClientController**

At the top of `src/Controller/Api/ClientController.php`, add to the existing `use` block:

```php
use App\Repository\CreditLimitHistoryRepository;
use App\Service\CreditCheckService;
```

- [ ] **Step 2: Add `creditCheck()` endpoint**

Add this method after the existing `CHECK_DUPLICATES` method in `ClientController`:

```php
#[Route('/{id}/credit-check', methods: ['GET'])]
public function creditCheck(
    int $id,
    CreditCheckService $creditCheckService,
): JsonResponse {
    /** @var \App\Entity\Client|null $client */
    $client = $this->repository->find($id);
    if (!$client) {
        throw $this->createNotFoundException('Client not found');
    }
    return $this->json($creditCheckService->check($client));
}
```

- [ ] **Step 3: Add `creditHistory()` endpoint**

```php
#[Route('/{id}/credit-history', methods: ['GET'])]
public function creditHistory(
    int $id,
    CreditLimitHistoryRepository $historyRepository,
): JsonResponse {
    $entries = $historyRepository->findForClient($id);
    return $this->json(array_map(fn($h) => [
        'id'             => $h->getId(),
        'changeType'     => $h->getChangeType(),
        'oldStatus'      => $h->getOldStatus()?->value,
        'newStatus'      => $h->getNewStatus()?->value,
        'oldLimitAmount' => $h->getOldLimitAmount(),
        'newLimitAmount' => $h->getNewLimitAmount(),
        'currency'       => $h->getCurrency(),
        'reason'         => $h->getReason(),
        'changedBy'      => $h->getChangedBy() ? [
            'id'        => $h->getChangedBy()->getId(),
            'firstName' => $h->getChangedBy()->getFirstName(),
            'lastName'  => $h->getChangedBy()->getLastName(),
        ] : null,
        'createdDate'    => $h->getCreatedDate()?->format(\DateTimeInterface::ATOM),
    ], $entries));
}
```

- [ ] **Step 4: Test endpoints manually**

```
GET /api/client/1/credit-check
GET /api/client/1/credit-history
```

Expected: Both return 200 JSON responses without errors.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/Api/ClientController.php
git commit -m "feat: add credit-check and credit-history endpoints to ClientController"
```

---

## Task 5: UpdateClientCreditStatusCommand

**Files:**
- Create: `src/Command/UpdateClientCreditStatusCommand.php`

- [ ] **Step 1: Create the command**

```php
<?php
// src/Command/UpdateClientCreditStatusCommand.php
namespace App\Command;

use App\Entity\Client;
use App\Misc\Enum\CreditStatus;
use App\Repository\AgeingRepository;
use App\Repository\ClientRepository;
use App\Service\CreditCheckService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:credit-control:update-statuses',
    description: 'Auto-escalate client credit status based on overdue invoices (>30d ON_HOLD, >90d BLOCKED)',
)]
class UpdateClientCreditStatusCommand extends Command
{
    public function __construct(
        private readonly AgeingRepository      $ageingRepository,
        private readonly ClientRepository      $clientRepository,
        private readonly CreditCheckService    $creditCheckService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Credit Control: Auto Status Update');

        $overdueData = $this->ageingRepository->getClientsWithOverdueData();

        // Group by client_id — pick the worst (max days overdue) across currencies
        $clientWorst = [];
        foreach ($overdueData as $row) {
            $cid = (int) $row['client_id'];
            if (!isset($clientWorst[$cid]) || $row['max_days_overdue'] > $clientWorst[$cid]['max_days_overdue']) {
                $clientWorst[$cid] = $row;
            }
        }

        $updated = 0;
        foreach ($clientWorst as $clientId => $row) {
            /** @var Client|null $client */
            $client = $this->clientRepository->find($clientId);
            if (!$client) {
                continue;
            }

            $maxDays = (int) $row['max_days_overdue'];
            $currentStatus = $client->getCreditStatus();

            // Skip if already manually set to BLOCKED or BLACKLISTED
            if ($currentStatus === CreditStatus::Blacklisted) {
                continue;
            }

            $targetStatus = match(true) {
                $maxDays > 90 => CreditStatus::Blocked,
                $maxDays > 30 => CreditStatus::OnHold,
                default       => null,
            };

            if ($targetStatus === null || $targetStatus === $currentStatus) {
                continue;
            }

            $reason = "Auto-escalated: oldest overdue invoice is {$maxDays} days overdue";

            $this->creditCheckService->recordHistory(
                client:         $client,
                changedBy:      null,
                changeType:     'AUTO_ESCALATION',
                oldStatus:      $currentStatus,
                newStatus:      $targetStatus,
                oldLimitAmount: $client->getCreditLimit()?->getAmount(),
                newLimitAmount: $client->getCreditLimit()?->getAmount(),
                currency:       $client->getCreditLimit()?->getCurrency(),
                reason:         $reason,
            );

            $client->setCreditStatus($targetStatus);
            $client->setCreditHoldReason($reason);
            $this->em->flush();

            $io->writeln(sprintf(
                '  [%s] %s: %s → %s (%d days overdue)',
                $clientId,
                $client->getName(),
                $currentStatus->value,
                $targetStatus->value,
                $maxDays
            ));
            $updated++;
        }

        $io->success("Updated {$updated} client(s).");
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Verify command is registered**

```
php bin/console list app:credit-control
```

Expected: `app:credit-control:update-statuses` listed.

- [ ] **Step 3: Run against dev data**

```
php bin/console app:credit-control:update-statuses
```

Expected: Runs without exceptions. Reports N clients updated (0 if no overdue invoices in dev).

- [ ] **Step 4: Commit**

```bash
git add src/Command/UpdateClientCreditStatusCommand.php
git commit -m "feat: add nightly credit status auto-escalation command (ON_HOLD >30d, BLOCKED >90d)"
```

---

## Task 6: ClientCreditListener — Propagate Hold to Shipments

**Files:**
- Create: `src/EventListener/ClientCreditListener.php`

This listener fires after a `Client` is updated and, when `creditStatus` changes to `ON_HOLD` or `BLOCKED`, sets `isOnHold = true` on all active shipments belonging to that client. When status returns to `ACTIVE`, it clears the credit hold (only if `holdReason` starts with `CREDIT_HOLD:`).

- [ ] **Step 1: Add `findActiveByClient` to ShipmentRepository (or use existing EM)**

First, check if `ShipmentRepository` exists:

```
grep -r "class ShipmentRepository" src/Repository/
```

If it exists, add the method. If not, we'll use EntityManager directly in the listener. Add to `src/Repository/ShipmentRepository.php`:

```php
public function findActiveByClient(int $clientId): array
{
    return $this->createQueryBuilder('s')
        ->join('s.quote', 'q')
        ->join('q.client', 'c')
        ->where('c.id = :clientId')
        ->andWhere('s.isOnHold = false OR s.holdReason LIKE :creditPrefix')
        ->setParameter('clientId', $clientId)
        ->setParameter('creditPrefix', 'CREDIT_HOLD:%')
        ->getQuery()
        ->getResult();
}
```

- [ ] **Step 2: Create ClientCreditListener**

```php
<?php
// src/EventListener/ClientCreditListener.php
namespace App\EventListener;

use App\Entity\Client;
use App\Misc\Enum\CreditStatus;
use App\Repository\ShipmentRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postUpdate, entity: Client::class)]
class ClientCreditListener
{
    public function __construct(
        private readonly ShipmentRepository     $shipmentRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function postUpdate(Client $client, PostUpdateEventArgs $args): void
    {
        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($client);

        if (!isset($changeSet['creditStatus'])) {
            return;
        }

        [, $newStatus] = $changeSet['creditStatus'];

        // $newStatus may be the enum value string or enum instance depending on Doctrine version
        if (is_string($newStatus)) {
            $newStatus = CreditStatus::from($newStatus);
        }

        $holdStatuses = [CreditStatus::OnHold, CreditStatus::Blocked, CreditStatus::Blacklisted];

        if (in_array($newStatus, $holdStatuses)) {
            $this->flagShipmentsOnHold($client, $newStatus);
        } elseif ($newStatus === CreditStatus::Active) {
            $this->clearCreditHoldOnShipments($client);
        }
    }

    private function flagShipmentsOnHold(Client $client, CreditStatus $status): void
    {
        $reason = 'CREDIT_HOLD: Client credit status changed to ' . $status->value;
        $shipments = $this->shipmentRepository->findActiveByClient($client->getId());
        foreach ($shipments as $shipment) {
            $shipment->noLog = true;
            $shipment->setIsOnHold(true);
            $shipment->setHoldReason($reason);
        }
        if (!empty($shipments)) {
            $this->em->flush();
        }
    }

    private function clearCreditHoldOnShipments(Client $client): void
    {
        $shipments = $this->shipmentRepository->findActiveByClient($client->getId());
        foreach ($shipments as $shipment) {
            if (str_starts_with((string) $shipment->getHoldReason(), 'CREDIT_HOLD:')) {
                $shipment->noLog = true;
                $shipment->setIsOnHold(false);
                $shipment->setHoldReason(null);
            }
        }
        if (!empty($shipments)) {
            $this->em->flush();
        }
    }
}
```

- [ ] **Step 3: Verify container compiles (listener auto-registered via AsEntityListener)**

```
php bin/console cache:clear
php bin/console debug:event-dispatcher doctrine.orm.entity_listener
```

Expected: `ClientCreditListener` appears in the list.

- [ ] **Step 4: Commit**

```bash
git add src/EventListener/ClientCreditListener.php src/Repository/ShipmentRepository.php
git commit -m "feat: add ClientCreditListener to propagate credit holds to active shipments"
```

---

## Task 7: Additional Overdue Notification Rules Seed Migration

**Files:**
- Create: `migrations/mysql/Version20260624260000.php`
- Create: `migrations/sqlite/Version20260624260000.php`

Adding Day 1, 14, 30, 60 overdue escalation rules (Day 7 already seeded in `Version20260624240000`).

- [ ] **Step 1: Create MySQL migration**

```php
<?php
// migrations/mysql/Version20260624260000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624260000 extends AbstractMigration
{
    public function getDescription(): string { return 'Seed additional overdue escalation notification rules (Day 1, 14, 30, 60)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO notification_rule (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date) VALUES
('INVOICE_OVERDUE_1D','Invoice Overdue 1 Day','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":1}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','NORMAL',NOW()),
('INVOICE_OVERDUE_14D','Invoice Overdue 14 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":14}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','HIGH',NOW()),
('INVOICE_OVERDUE_30D','Invoice Overdue 30 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":30}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','URGENT',NOW()),
('INVOICE_OVERDUE_60D','Invoice Overdue 60 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":60}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','URGENT',NOW())
");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM notification_rule WHERE rule_key IN ('INVOICE_OVERDUE_1D','INVOICE_OVERDUE_14D','INVOICE_OVERDUE_30D','INVOICE_OVERDUE_60D')");
    }
}
```

- [ ] **Step 2: Create SQLite migration**

```php
<?php
// migrations/sqlite/Version20260624260000.php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624260000 extends AbstractMigration
{
    public function getDescription(): string { return 'Seed additional overdue escalation notification rules (Day 1, 14, 30, 60)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO notification_rule (rule_key, name, trigger_type, trigger_config, recipient_config, channels, template_key, is_active, scope_type, priority, created_date) VALUES
('INVOICE_OVERDUE_1D','Invoice Overdue 1 Day','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":1}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','NORMAL',datetime('now')),
('INVOICE_OVERDUE_14D','Invoice Overdue 14 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":14}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','HIGH',datetime('now')),
('INVOICE_OVERDUE_30D','Invoice Overdue 30 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":30}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','URGENT',datetime('now')),
('INVOICE_OVERDUE_60D','Invoice Overdue 60 Days','FINANCIAL','{\"event\":\"INVOICE_OVERDUE\",\"days_overdue\":60}','[{\"type\":\"JOB_OPERATOR\"}]','[\"IN_APP\",\"EMAIL\"]','email_invoice_overdue',1,'GLOBAL','URGENT',datetime('now'))
");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM notification_rule WHERE rule_key IN ('INVOICE_OVERDUE_1D','INVOICE_OVERDUE_14D','INVOICE_OVERDUE_30D','INVOICE_OVERDUE_60D')");
    }
}
```

- [ ] **Step 3: Run migration**

```
php bin/console doctrine:migrations:migrate --no-interaction
```

Expected: Both Version20260624260000 migrations applied.

- [ ] **Step 4: Commit**

```bash
git add migrations/mysql/Version20260624260000.php migrations/sqlite/Version20260624260000.php
git commit -m "feat: seed overdue escalation notification rules for Day 1, 14, 30, 60"
```

---

## Task 8: BO — ClientService.js Credit Methods

**Files:**
- Modify: `src/services/ClientService.js` (in `d:\Projects\make-cargo-client-bo`)

- [ ] **Step 1: Add credit methods to ClientService.js**

After the existing `checkDuplicates` method, add:

```js
getCreditCheck(id) {
  return $api(`client/${id}/credit-check`)
},

getCreditHistory(id) {
  return $api(`client/${id}/credit-history`)
},
```

- [ ] **Step 2: Verify no syntax errors**

```
npm run build 2>&1 | head -30
```

Expected: Builds without errors.

- [ ] **Step 3: Commit**

```bash
git add src/services/ClientService.js
git commit -m "feat: add getCreditCheck and getCreditHistory to ClientService"
```

---

## Task 9: BO — ClientGeneral.vue Utilisation + Available Credit

**Files:**
- Modify: `src/views/client/ClientGeneral.vue` (in `d:\Projects\make-cargo-client-bo`)

- [ ] **Step 1: Add creditCheck reactive data + fetch logic**

In the `<script setup>` section, add after the existing imports and after the `emit` definition:

```js
import ClientService from '@/services/ClientService'

const creditCheck = ref(null)

async function loadCreditCheck() {
  if (props.client?.id) {
    try {
      creditCheck.value = await ClientService.getCreditCheck(props.client.id)
    } catch (e) {
      creditCheck.value = null
    }
  }
}

onMounted(loadCreditCheck)
watch(() => props.client?.id, loadCreditCheck)

const utilisationColor = computed(() => {
  if (!creditCheck.value) return 'primary'
  const d = creditCheck.value.decision
  if (d === 'HARD_BLOCK') return 'error'
  if (d === 'REQUIRE_APPROVAL') return 'error'
  if (d === 'WARN') return 'warning'
  return 'success'
})
```

- [ ] **Step 2: Add utilisation UI to the credit section in the template**

Find the credit section in the template (where `printMoney(client.creditLimit)` is displayed). After the existing credit limit/period/status display, add:

```html
<!-- Credit Utilisation -->
<template v-if="creditCheck && creditCheck.limit">
  <VDivider class="my-2" />
  <div class="text-disabled text-uppercase text-sm mb-2">{{ $gettext('Credit Utilisation') }}</div>
  <div class="d-flex justify-space-between mb-1">
    <span class="text-sm">
      {{ creditCheck.currency }} {{ Number(creditCheck.exposure).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
      / {{ Number(creditCheck.limit).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
    </span>
    <span class="text-sm font-weight-bold" :class="`text-${utilisationColor}`">
      {{ creditCheck.utilisation }}%
    </span>
  </div>
  <VProgressLinear
    :model-value="Math.min(creditCheck.utilisation, 100)"
    :color="utilisationColor"
    rounded height="8"
    class="mb-2"
  />
  <div class="d-flex justify-space-between text-sm">
    <span class="text-disabled">{{ $gettext('Available') }}</span>
    <span :class="`text-${utilisationColor}`">
      {{ creditCheck.currency }} {{ Number(Math.max(creditCheck.available, 0)).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
    </span>
  </div>
  <VChip
    v-if="creditCheck.decision === 'HARD_BLOCK'"
    color="error" size="small" class="mt-2"
  >
    {{ $gettext('Credit Blocked') }}
  </VChip>
  <VChip
    v-else-if="creditCheck.decision === 'REQUIRE_APPROVAL'"
    color="error" variant="outlined" size="small" class="mt-2"
  >
    {{ $gettext('Over Limit — Requires Approval') }}
  </VChip>
  <VChip
    v-else-if="creditCheck.decision === 'WARN'"
    color="warning" size="small" class="mt-2"
  >
    {{ $gettext('Approaching Limit') }}
  </VChip>
</template>
```

- [ ] **Step 3: Verify in browser**

Navigate to any client detail page. The credit section should now show the utilisation bar and available credit. If the client has no credit limit configured, the section is hidden.

- [ ] **Step 4: Commit**

```bash
git add src/views/client/ClientGeneral.vue
git commit -m "feat: add credit utilisation bar and available credit to ClientGeneral"
```

---

## Task 10: BO — ClientCreditHistory.vue + Tab

**Files:**
- Create: `src/views/client/ClientCreditHistory.vue` (in `d:\Projects\make-cargo-client-bo`)
- Modify: client detail view that hosts tabs (find by searching for the other client tabs)

- [ ] **Step 1: Find the tab host file**

```bash
grep -r "ClientGeneral" src/views/client/ --include="*.vue" -l
```

This reveals the parent view that imports `ClientGeneral`. Identify the file that defines the tabs (e.g., `ClientView.vue` or `ClientDetail.vue`).

- [ ] **Step 2: Create ClientCreditHistory.vue**

```vue
<!-- src/views/client/ClientCreditHistory.vue -->
<script setup>
import ClientService from '@/services/ClientService'
import { printDateTime } from '@/services/CommonService'
import { findByValue as findCreditStatus } from '@/config/enums/CreditStatus'

const props = defineProps({
  clientId: { type: Number, required: true }
})

const history = ref([])
const loading = ref(false)

async function load() {
  if (!props.clientId) return
  loading.value = true
  try {
    history.value = await ClientService.getCreditHistory(props.clientId)
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(() => props.clientId, load)

const changeTypeLabel = {
  STATUS_CHANGE:    'Manual Status Change',
  LIMIT_CHANGE:     'Limit Change',
  AUTO_ESCALATION:  'Auto Escalation',
}
</script>
<template>
  <VCard>
    <VCardText>
      <div v-if="loading" class="text-center py-6">
        <v-progress-circular indeterminate :size="32" />
      </div>
      <div v-else-if="history.length === 0" class="text-center py-6 text-disabled">
        {{ $gettext('No credit history recorded yet.') }}
      </div>
      <VTimeline v-else side="end" density="compact" truncate-line="start">
        <VTimelineItem
          v-for="h in history"
          :key="h.id"
          :dot-color="h.newStatus === 'BLOCKED' || h.newStatus === 'BLACKLISTED' ? 'error' : h.newStatus === 'ON_HOLD' ? 'warning' : 'success'"
          size="small"
        >
          <template #opposite>
            <span class="text-xs text-disabled">
              {{ printDateTime(h.createdDate, 'DD/MM/YYYY HH:mm') }}
            </span>
          </template>
          <VCard variant="outlined" class="py-2 px-3">
            <div class="d-flex align-center gap-2 mb-1">
              <VChip size="x-small" :color="h.changeType === 'AUTO_ESCALATION' ? 'secondary' : 'primary'">
                {{ changeTypeLabel[h.changeType] ?? h.changeType }}
              </VChip>
              <span v-if="h.oldStatus && h.newStatus" class="text-sm">
                <strong>{{ h.oldStatus }}</strong>
                <VIcon size="12" icon="tabler-arrow-right" class="mx-1" />
                <strong>{{ h.newStatus }}</strong>
              </span>
            </div>
            <div v-if="h.oldLimitAmount !== null || h.newLimitAmount !== null" class="text-sm text-disabled">
              Limit: {{ h.oldLimitAmount ?? '—' }} → {{ h.newLimitAmount ?? '—' }} {{ h.currency }}
            </div>
            <div v-if="h.reason" class="text-sm mt-1">{{ h.reason }}</div>
            <div v-if="h.changedBy" class="text-xs text-disabled mt-1">
              By {{ h.changedBy.firstName }} {{ h.changedBy.lastName }}
            </div>
          </VCard>
        </VTimelineItem>
      </VTimeline>
    </VCardText>
  </VCard>
</template>
```

- [ ] **Step 3: Add Credit History tab to the tab host**

In the tab host file identified in Step 1, import the component and add a tab:

```js
import ClientCreditHistory from './ClientCreditHistory.vue'
```

In the tabs definition array, add:

```js
{ title: $gettext('Credit History'), value: 'creditHistory' }
```

In the tab panels, add:

```html
<VWindow v-model="activeTab">
  <!-- existing tabs ... -->
  <VWindowItem value="creditHistory">
    <ClientCreditHistory :client-id="client.id" />
  </VWindowItem>
</VWindow>
```

- [ ] **Step 4: Verify in browser**

Navigate to a client detail page. A "Credit History" tab should appear. Clicking it shows the timeline or empty state.

- [ ] **Step 5: Commit**

```bash
git add src/views/client/ClientCreditHistory.vue
git commit -m "feat: add ClientCreditHistory component and Credit History tab to client detail"
```

---

## Task 11: BO — Quote Creation Credit Check Modal

**Files:**
- Modify: `src/config/forms/quote/Quote.js` (in `d:\Projects\make-cargo-client-bo`)
- Modify: the Quote form/page component that handles the submit action

- [ ] **Step 1: Find the quote submit handler**

```bash
grep -r "QuoteService.add\|QuoteService\.add\|quote.*submit\|onSubmit" src/views/quote/ --include="*.vue" -l
```

This finds the component that calls the quote creation API.

- [ ] **Step 2: Add credit check before quote submit**

In the quote form component's `<script setup>`, add:

```js
import ClientService from '@/services/ClientService'

const creditCheckDialog = ref(false)
const creditCheckResult = ref(null)
const pendingSubmitCallback = ref(null)

async function checkCreditAndSubmit(entity, originalSubmit) {
  if (!entity.client?.id) {
    originalSubmit()
    return
  }

  const check = await ClientService.getCreditCheck(entity.client.id)
  creditCheckResult.value = check

  if (check.decision === 'HARD_BLOCK') {
    // Show error — do not proceed
    creditCheckDialog.value = true
    return
  }

  if (check.decision === 'REQUIRE_APPROVAL') {
    // Show approval modal — user must confirm
    creditCheckDialog.value = true
    pendingSubmitCallback.value = originalSubmit
    return
  }

  // PASS or WARN — proceed
  originalSubmit()
}

function confirmCreditOverride() {
  creditCheckDialog.value = false
  if (pendingSubmitCallback.value) {
    pendingSubmitCallback.value()
    pendingSubmitCallback.value = null
  }
}
```

- [ ] **Step 3: Add the credit check dialog to the quote form template**

After the existing form markup, add:

```html
<!-- Credit Check Dialog -->
<VDialog v-model="creditCheckDialog" max-width="480" persistent>
  <VCard>
    <VCardTitle class="d-flex align-center gap-2 pa-4">
      <VIcon
        :icon="creditCheckResult?.decision === 'HARD_BLOCK' ? 'tabler-lock' : 'tabler-alert-triangle'"
        :color="creditCheckResult?.decision === 'HARD_BLOCK' ? 'error' : 'warning'"
        size="28"
      />
      <span>
        {{ creditCheckResult?.decision === 'HARD_BLOCK' ? $gettext('Credit Blocked') : $gettext('Credit Limit Exceeded') }}
      </span>
    </VCardTitle>
    <VCardText>
      <p class="mb-3">{{ creditCheckResult?.reason }}</p>
      <template v-if="creditCheckResult?.limit">
        <VList density="compact" class="pa-0">
          <VListItem>
            <VListItemTitle>{{ $gettext('Outstanding Exposure') }}</VListItemTitle>
            <template #append>
              <strong>{{ creditCheckResult.currency }} {{ Number(creditCheckResult.exposure).toLocaleString() }}</strong>
            </template>
          </VListItem>
          <VListItem>
            <VListItemTitle>{{ $gettext('Credit Limit') }}</VListItemTitle>
            <template #append>
              <strong>{{ creditCheckResult.currency }} {{ Number(creditCheckResult.limit).toLocaleString() }}</strong>
            </template>
          </VListItem>
          <VListItem>
            <VListItemTitle>{{ $gettext('Utilisation') }}</VListItemTitle>
            <template #append>
              <strong class="text-error">{{ creditCheckResult.utilisation }}%</strong>
            </template>
          </VListItem>
        </VList>
      </template>
    </VCardText>
    <VCardActions class="justify-end pa-4 gap-2">
      <VBtn variant="tonal" @click="creditCheckDialog = false">
        {{ $gettext('Cancel') }}
      </VBtn>
      <VBtn
        v-if="creditCheckResult?.decision === 'REQUIRE_APPROVAL'"
        color="warning"
        @click="confirmCreditOverride"
      >
        {{ $gettext('Proceed Anyway') }}
      </VBtn>
    </VCardActions>
  </VCard>
</VDialog>
```

- [ ] **Step 4: Wire the credit check into the existing submit flow**

Locate where the quote form currently calls its submit handler (e.g. `form.value?.submit()` or similar). Wrap it so it goes through `checkCreditAndSubmit` first:

```js
// Replace: form.value?.submit()
// With:
async function handleSubmit() {
  await checkCreditAndSubmit(entity.value, () => form.value?.submit())
}
```

Then update the submit button's `@click` to call `handleSubmit` instead of directly submitting.

- [ ] **Step 5: Test in browser**

1. Open a quote creation form and select a client with creditStatus=BLOCKED → modal appears with no "Proceed" button.
2. Select a client over credit limit (if test data available) → modal appears with "Proceed Anyway" button.
3. Select a client within limit → quote proceeds normally.

- [ ] **Step 6: Commit**

```bash
git add src/config/forms/quote/Quote.js
git commit -m "feat: add credit check gate to quote creation with REQUIRE_APPROVAL modal and HARD_BLOCK prevention"
```

---

## Task 12: BO — Shipment Table On-Hold Badge

**Files:**
- Modify: `src/config/tables/shipment/Shipment.js` (in `d:\Projects\make-cargo-client-bo`)

- [ ] **Step 1: Add isOnHold to the shipment list serializer group**

Check that `isOnHold` is included in the shipment list response. Check `config/serializer_groups/Shipment.yaml`:

```
grep -n "isOnHold" config/serializer_groups/Shipment.yaml
```

If missing, add it to the `list` group in `config/serializer_groups/Shipment.yaml`:

```yaml
      - isOnHold
      - holdReason
```

Then clear API cache:
```
php bin/console cache:clear
```

- [ ] **Step 2: Add on-hold column to Shipment.js table config**

In `src/config/tables/shipment/Shipment.js`, add a column after the status column:

```js
{
  title: $gettext('Hold'),
  key: 'isOnHold',
  sortable: false,
  width: 80,
  render(item) {
    if (!item.isOnHold) return ''
    return `<span class="v-chip v-chip--density-compact v-chip--size-x-small bg-error text-white px-2 rounded-pill">ON HOLD</span>`
  }
},
```

If the table uses component-based rendering, use a slot approach instead. Locate how other chip columns are rendered in the file and match that pattern exactly.

- [ ] **Step 3: Verify in browser**

Navigate to the shipment list. The "Hold" column should appear. Any shipment with `isOnHold=true` shows the red "ON HOLD" badge.

- [ ] **Step 4: Commit**

```bash
git add src/config/tables/shipment/Shipment.js config/serializer_groups/Shipment.yaml
git commit -m "feat: add on-hold badge column to shipment table"
```

---

## Task 13: Documentation Guide

**Files:**
- Create: `docs/guides/credit-control.md` (in `d:\Projects\make-cargo-client-bo` or API repo as appropriate)

- [ ] **Step 1: Write the guide**

```markdown
# Credit Control — Setup & Operations Guide

## Overview

The credit control system monitors client AR exposure, enforces credit limits, auto-escalates
credit status, propagates holds to shipments, and surfaces utilisation in the back-office UI.

## Architecture

```
AgeingRepository (DBAL raw SQL)
    ↓ getClientExposure()
CreditCheckService
    ↓ check(Client) → decision: PASS | WARN | REQUIRE_APPROVAL | HARD_BLOCK
    ↓ recordHistory()
        → CreditLimitHistory (audit trail)

UpdateClientCreditStatusCommand (nightly cron)
    ↓ getClientsWithOverdueData() — max_days_overdue by client
    ↓ ON_HOLD when max_days_overdue > 30
    ↓ BLOCKED when max_days_overdue > 90

ClientCreditListener (Doctrine postUpdate on Client)
    ↓ ON_HOLD / BLOCKED → set isOnHold=true on active shipments (holdReason: CREDIT_HOLD:...)
    ↓ ACTIVE → clear CREDIT_HOLD: holds on shipments
```

## Credit Status Values

| Status | Description |
|--------|-------------|
| ACTIVE | Normal — all operations allowed |
| ON_HOLD | Soft hold — auto-escalated at >30 days overdue; new quotes require approval |
| BLOCKED | Hard block — auto-escalated at >90 days overdue; no new quotes/shipments |
| BLACKLISTED | Permanent block — manually set only; overrides auto-escalation |

## Credit Check Decision Logic

| Condition | Decision |
|-----------|----------|
| Status is BLOCKED or BLACKLISTED | HARD_BLOCK |
| No credit limit configured | PASS (unlimited) |
| Utilisation > 100% | REQUIRE_APPROVAL |
| Utilisation ≥ 80% | WARN |
| Utilisation < 80% | PASS |

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/client/{id}/credit-check` | GET | Live credit check result |
| `/api/client/{id}/credit-history` | GET | Audit history of status/limit changes |

### Credit Check Response

```json
{
  "decision": "WARN",
  "reason": "Outstanding exposure is above 80% of credit limit",
  "exposure": 84500.00,
  "limit": 100000.00,
  "currency": "USD",
  "utilisation": 84.5,
  "available": 15500.00
}
```

## Running the Nightly Command

```bash
# Run manually
php bin/console app:credit-control:update-statuses

# Cron (every night at 02:00)
0 2 * * * /path/to/project/bin/console app:credit-control:update-statuses >> /var/log/credit-control.log 2>&1
```

The command:
1. Queries AR invoices with `DATEDIFF(CURDATE(), due_date) > 0` grouped by client
2. Escalates to ON_HOLD if max overdue > 30 days (skips BLACKLISTED)
3. Escalates to BLOCKED if max overdue > 90 days
4. Records each change in `credit_limit_history` with `changeType = AUTO_ESCALATION`
5. Skips clients already at the target status

## Notification Rules for Overdue Invoices

Seeded by migrations `Version20260624240000` and `Version20260624260000`:

| Rule Key | Trigger | Priority |
|----------|---------|----------|
| INVOICE_OVERDUE_1D | 1 day overdue | NORMAL |
| INVOICE_OVERDUE_7D | 7 days overdue | HIGH |
| INVOICE_OVERDUE_14D | 14 days overdue | HIGH |
| INVOICE_OVERDUE_30D | 30 days overdue | URGENT |
| INVOICE_OVERDUE_60D | 60 days overdue | URGENT |

The `NotificationSchedulerCommand` (`app:notifications:scheduler`) fires these rules daily.
Add it to cron alongside the credit status command.

## Manual Credit Status Override (Back-Office)

On any client detail page → General tab → click the edit icon next to Credit Status.
Select a new status, optionally enter a hold reason, and save. This calls `PUT /api/client/{id}`
(which uses the existing PUT endpoint with `creditStatus`, `creditHoldReason`, `creditReviewedAt`).

**Note:** The `ClientCreditListener` fires on save — if you change status to ON_HOLD or BLOCKED,
all active shipments for this client will be flagged `isOnHold=true` immediately.

## Back-Office Features

### Client Detail → General Tab
- **Utilisation bar**: shows exposure/limit ratio with colour coding (green/yellow/red)
- **Available credit**: remaining credit in client currency
- **Credit status chip**: ACTIVE / ON_HOLD / BLOCKED / BLACKLISTED

### Client Detail → Credit History Tab
- Timeline of all status and limit changes
- Shows change type (manual vs auto-escalation), who changed it, and the reason

### Quote Creation
- Client dropdown shows `[Max Credit Exceeded]` in red for over-limit clients (existing behaviour)
- On submit: calls `/client/{id}/credit-check`
  - `HARD_BLOCK` → shows error dialog, blocks submission
  - `REQUIRE_APPROVAL` → shows warning dialog with "Proceed Anyway" option
  - `PASS` / `WARN` → proceeds normally (WARN is informational only)

### Shipment List
- **ON HOLD** chip in the Hold column for any shipment with `isOnHold=true`

## Database Tables

### `credit_limit_history`

| Column | Type | Notes |
|--------|------|-------|
| id | INT PK | Auto-increment |
| client_id | INT FK | References `partner.id` ON DELETE CASCADE |
| changed_by_id | INT FK | References `user.id` ON DELETE SET NULL; null for auto-escalation |
| change_type | VARCHAR(32) | STATUS_CHANGE / LIMIT_CHANGE / AUTO_ESCALATION |
| old_status | VARCHAR(16) | CreditStatus enum value or null |
| new_status | VARCHAR(16) | CreditStatus enum value or null |
| old_limit_amount | DECIMAL(15,4) | null if not a limit change |
| new_limit_amount | DECIMAL(15,4) | null if not a limit change |
| currency | VARCHAR(8) | Credit limit currency |
| reason | TEXT | Freeform reason / auto message |
| created_date | DATETIME | Set by EntityDateTimeAbleTrait |
```

- [ ] **Step 2: Commit the guide**

```bash
git add docs/guides/credit-control.md
git commit -m "docs: add credit control setup and operations guide"
```

---

## Self-Review

**Spec coverage:**
- ✅ CreditLimitHistory entity for audit — Task 1
- ✅ CreditCheckService PASS/WARN/REQUIRE_APPROVAL/HARD_BLOCK — Task 3
- ✅ Credit check API endpoint — Task 4
- ✅ Nightly auto-escalation (ON_HOLD >30d, BLOCKED >90d) — Task 5
- ✅ Shipment hold propagation listener — Task 6
- ✅ Overdue notification rules Day 1, 14, 30, 60 — Task 7
- ✅ BO utilisation bar + available credit — Task 9
- ✅ BO credit history tab — Task 10
- ✅ BO quote approval modal — Task 11
- ✅ BO shipment on-hold badge — Task 12
- ✅ Documentation guide — Task 13

**Type consistency:**
- `CreditCheckService.check(Client $client): array` used in Task 3 and Task 4 — consistent
- `CreditCheckService.recordHistory(...)` used in Tasks 3 and 5 — same signature throughout
- `AgeingRepository.getClientExposure(int $clientId, string $currency): float` used in Task 2 (definition) and Task 3 (CreditCheckService) — consistent
- `AgeingRepository.getClientsWithOverdueData(): array` used in Task 2 (definition) and Task 5 — consistent
- `CreditLimitHistoryRepository.findForClient(int $clientId): array` used in Task 1 (definition) and Task 4 — consistent
- `ClientService.getCreditCheck(id)` used in Tasks 8, 9, 11 — consistent
- Migration version numbers: 250000 (entity table), 260000 (notification rules seed) — no collisions
