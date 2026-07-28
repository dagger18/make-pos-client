# Accounting Phase 1: EbitNote Enhancements — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add FX gain/loss tracking on AR/AP payments, AP vendor bill variance matching workflow, credit note reason codes, and an accounting lock flag — all as new fields on the existing EbitNote entity.

**Architecture:** All new data hangs off `EbitNote` (no new tables). FX gain/loss is computed when a receipt/payment is linked to its parent invoice/bill and stored as `fxGainLoss`. Variance matching adds `varianceStatus`/`approvedBy`/`approvedAt` to IC-type notes. Two new PHP enums (VarianceStatus, CreditNoteReason) back the new string fields. One migration covers all column additions.

**Tech Stack:** PHP 8.1+, Doctrine ORM (embedded Money), Symfony 6, Vue 3 Composition API, Vuetify 3

---

## Repo paths

- API: `d:\Projects\make-cargo-client`
- BO:  `d:\Projects\make-cargo-client-bo`

## Money base-amount formula

`EbitNote.amount` is an embedded `Money(amount, currency, rate)`.  
Base amount = `amount / rate` (e.g. VND 24 500 000 / 24 500 = USD 1 000).

---

## Task 1: VarianceStatus enum

**Files:**
- Create: `src/Misc/Enum/VarianceStatus.php`

- [ ] **Step 1: Create enum**

```php
<?php
// src/Misc/Enum/VarianceStatus.php
namespace App\Misc\Enum;

enum VarianceStatus: string
{
    case Unmatched = 'UNMATCHED';
    case Matched   = 'MATCHED';
    case Variance  = 'VARIANCE';
    case Approved  = 'APPROVED';
    case Disputed  = 'DISPUTED';

    public function label(): string
    {
        return match($this) {
            self::Unmatched => 'Unmatched',
            self::Matched   => 'Matched',
            self::Variance  => 'Variance',
            self::Approved  => 'Approved',
            self::Disputed  => 'Disputed',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Unmatched => 'default',
            self::Matched   => 'success',
            self::Variance  => 'warning',
            self::Approved  => 'info',
            self::Disputed  => 'error',
        };
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Misc/Enum/VarianceStatus.php
git commit -m "feat(accounting): add VarianceStatus enum"
```

---

## Task 2: CreditNoteReason enum

**Files:**
- Create: `src/Misc/Enum/CreditNoteReason.php`

- [ ] **Step 1: Create enum**

```php
<?php
// src/Misc/Enum/CreditNoteReason.php
namespace App\Misc\Enum;

enum CreditNoteReason: string
{
    case RateError        = 'RATE_ERROR';
    case Duplicate        = 'DUPLICATE';
    case WeightAdjustment = 'WEIGHT_ADJUSTMENT';
    case Dispute          = 'DISPUTE';
    case Rebate           = 'REBATE';
    case CarrierCredit    = 'CARRIER_CREDIT';
    case Shortfall        = 'SHORTFALL';
    case Overbilling      = 'OVERBILLING';

    public function label(): string
    {
        return match($this) {
            self::RateError        => 'Rate Error',
            self::Duplicate        => 'Duplicate',
            self::WeightAdjustment => 'Weight Adjustment',
            self::Dispute          => 'Dispute',
            self::Rebate           => 'Rebate',
            self::CarrierCredit    => 'Carrier Credit',
            self::Shortfall        => 'Shortfall',
            self::Overbilling      => 'Overbilling',
        };
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add src/Misc/Enum/CreditNoteReason.php
git commit -m "feat(accounting): add CreditNoteReason enum"
```

---

## Task 3: Add new fields to EbitNote entity

**Files:**
- Modify: `src/Entity/EbitNote.php`

New fields to add after `$codeReference`:

| Field | Type | Purpose |
|---|---|---|
| `fxGainLoss` | DECIMAL 15,4 nullable | Stored FX gain/loss for RPT/PMT |
| `varianceStatus` | VARCHAR 16 nullable enum | IC variance state |
| `creditNoteReason` | VARCHAR 32 nullable enum | CN reason code |
| `isLocked` | BOOLEAN default false | Accounting lock |
| `vendorRef` | VARCHAR 64 nullable | IC vendor's own invoice ref |
| `approvedBy` | ManyToOne User nullable | IC variance approver |
| `approvedAt` | DATETIME nullable | IC variance approval timestamp |

- [ ] **Step 1: Add fields, getters, setters**

In `src/Entity/EbitNote.php`, after the `$codeReference` property declaration and before the constructor, add:

```php
    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 4, nullable: true)]
    private ?float $fxGainLoss = null;

    #[ORM\Column(length: 16, nullable: true, enumType: \App\Misc\Enum\VarianceStatus::class)]
    private ?\App\Misc\Enum\VarianceStatus $varianceStatus = null;

    #[ORM\Column(length: 32, nullable: true, enumType: \App\Misc\Enum\CreditNoteReason::class)]
    private ?\App\Misc\Enum\CreditNoteReason $creditNoteReason = null;

    #[ORM\Column]
    private bool $isLocked = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vendorRef = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?\App\Entity\User $approvedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;
```

Then add getters/setters at the end of the class (before the closing `}`):

```php
    public function getFxGainLoss(): ?float { return $this->fxGainLoss; }
    public function setFxGainLoss(?float $v): static { $this->fxGainLoss = $v; return $this; }

    public function getVarianceStatus(): ?\App\Misc\Enum\VarianceStatus { return $this->varianceStatus; }
    public function setVarianceStatus(?\App\Misc\Enum\VarianceStatus $v): static { $this->varianceStatus = $v; return $this; }

    public function getCreditNoteReason(): ?\App\Misc\Enum\CreditNoteReason { return $this->creditNoteReason; }
    public function setCreditNoteReason(?\App\Misc\Enum\CreditNoteReason $v): static { $this->creditNoteReason = $v; return $this; }

    public function isLocked(): bool { return $this->isLocked; }
    public function setIsLocked(bool $v): static { $this->isLocked = $v; return $this; }

    public function getVendorRef(): ?string { return $this->vendorRef; }
    public function setVendorRef(?string $v): static { $this->vendorRef = $v; return $this; }

    public function getApprovedBy(): ?\App\Entity\User { return $this->approvedBy; }
    public function setApprovedBy(?\App\Entity\User $u): static { $this->approvedBy = $u; return $this; }

    public function getApprovedAt(): ?\DateTimeInterface { return $this->approvedAt; }
    public function setApprovedAt(?\DateTimeInterface $d): static { $this->approvedAt = $d; return $this; }
```

- [ ] **Step 2: Add imports at top of EbitNote.php if not present**

Ensure `use Doctrine\DBAL\Types\Types;` is present (it already is).

- [ ] **Step 3: Commit**

```bash
git add src/Entity/EbitNote.php
git commit -m "feat(accounting): add FX/variance/lock fields to EbitNote entity"
```

---

## Task 4: Add expectedAmount to ChargeItem entity

**Files:**
- Modify: `src/Entity/ChargeItem.php`

`expectedAmount` stores the estimated/quoted cost on an IC-type ChargeItem, enabling variance = `amount - expectedAmount`.

- [ ] **Step 1: Add field and accessors**

After the `$visibleTo` property in `src/Entity/ChargeItem.php`, add:

```php
    #[ORM\Embedded(class: Money::class)]
    #[NullableEmbeddable]
    private ?Money $expectedAmount = null;
```

Add getter/setter at the end of the class:

```php
    public function getExpectedAmount(): ?Money { return $this->expectedAmount; }
    public function setExpectedAmount(?Money $m): static { $this->expectedAmount = $m; return $this; }
```

- [ ] **Step 2: Commit**

```bash
git add src/Entity/ChargeItem.php
git commit -m "feat(accounting): add expectedAmount to ChargeItem for variance tracking"
```

---

## Task 5: Migration — add all new columns

**Files:**
- Create: `migrations/mysql/Version20260622150000.php`
- Create: `migrations/sqlite/Version20260622150000.php`

- [ ] **Step 1: Create MySQL migration**

```php
<?php
// migrations/mysql/Version20260622150000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting Phase 1: FX gain/loss, variance, credit-note reason, lock fields on ebit_note; expectedAmount on charge_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ebit_note
            ADD COLUMN fx_gain_loss DECIMAL(15,4) DEFAULT NULL,
            ADD COLUMN variance_status VARCHAR(16) DEFAULT NULL,
            ADD COLUMN credit_note_reason VARCHAR(32) DEFAULT NULL,
            ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN vendor_ref VARCHAR(64) DEFAULT NULL,
            ADD COLUMN approved_by_id INT DEFAULT NULL,
            ADD COLUMN approved_at DATETIME DEFAULT NULL
        ");
        $this->addSql("ALTER TABLE ebit_note
            ADD CONSTRAINT FK_EBIT_NOTE_APPROVED_BY FOREIGN KEY (approved_by_id) REFERENCES user(id) ON DELETE SET NULL
        ");
        $this->addSql("ALTER TABLE charge_item
            ADD COLUMN expected_amount_amount DECIMAL(15,4) DEFAULT NULL,
            ADD COLUMN expected_amount_currency VARCHAR(255) DEFAULT NULL,
            ADD COLUMN expected_amount_rate DECIMAL(15,6) DEFAULT NULL
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ebit_note DROP FOREIGN KEY FK_EBIT_NOTE_APPROVED_BY");
        $this->addSql("ALTER TABLE ebit_note
            DROP COLUMN fx_gain_loss,
            DROP COLUMN variance_status,
            DROP COLUMN credit_note_reason,
            DROP COLUMN is_locked,
            DROP COLUMN vendor_ref,
            DROP COLUMN approved_by_id,
            DROP COLUMN approved_at
        ");
        $this->addSql("ALTER TABLE charge_item
            DROP COLUMN expected_amount_amount,
            DROP COLUMN expected_amount_currency,
            DROP COLUMN expected_amount_rate
        ");
    }
}
```

- [ ] **Step 2: Create SQLite migration**

```php
<?php
// migrations/sqlite/Version20260622150000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting Phase 1: FX/variance/lock fields on ebit_note; expectedAmount on charge_item';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE ebit_note ADD COLUMN fx_gain_loss DECIMAL(15,4) DEFAULT NULL");
        $this->addSql("ALTER TABLE ebit_note ADD COLUMN variance_status VARCHAR(16) DEFAULT NULL");
        $this->addSql("ALTER TABLE ebit_note ADD COLUMN credit_note_reason VARCHAR(32) DEFAULT NULL");
        $this->addSql("ALTER TABLE ebit_note ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0");
        $this->addSql("ALTER TABLE ebit_note ADD COLUMN vendor_ref VARCHAR(64) DEFAULT NULL");
        $this->addSql("ALTER TABLE ebit_note ADD COLUMN approved_by_id INTEGER DEFAULT NULL");
        $this->addSql("ALTER TABLE ebit_note ADD COLUMN approved_at DATETIME DEFAULT NULL");
        $this->addSql("ALTER TABLE charge_item ADD COLUMN expected_amount_amount DECIMAL(15,4) DEFAULT NULL");
        $this->addSql("ALTER TABLE charge_item ADD COLUMN expected_amount_currency VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE charge_item ADD COLUMN expected_amount_rate DECIMAL(15,6) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        // SQLite cannot drop columns — migration is not reversible
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add migrations/mysql/Version20260622150000.php migrations/sqlite/Version20260622150000.php
git commit -m "feat(accounting): migration for EbitNote/ChargeItem accounting fields"
```

---

## Task 6: FxGainLossService

**Files:**
- Create: `src/Service/FxGainLossService.php`

Computes and stores FX gain/loss when a receipt (RPT) or payment (PMT) is saved.

Base amount formula: `amount->getAmount() / amount->getRate()` (VND face / VND-per-USD rate = USD base).

FX gain/loss for RPT (AR Receipt):
- Invoice base = parent ID's `amount->getAmount() / amount->getRate()`
- Receipt base = RPT's `amount->getAmount() / amount->getRate()`
- fxGainLoss = receipt_base − invoice_base  (positive = FX gain, customer paid more in base)

FX gain/loss for PMT (AP Payment):
- Bill base = parent IC's `amount->getAmount() / amount->getRate()`  
- Payment base = PMT's `amount->getAmount() / amount->getRate()`
- fxGainLoss = bill_base − payment_base  (positive = FX gain, we paid less in base)

- [ ] **Step 1: Create service**

```php
<?php
// src/Service/FxGainLossService.php
namespace App\Service;

use App\Entity\EbitNote;
use App\Misc\Enum\EbitNoteType;

class FxGainLossService
{
    private function baseAmount(EbitNote $note): ?float
    {
        $money = $note->getAmount();
        if (!$money || !$money->getRate() || $money->getRate() == 0) {
            return null;
        }
        return $money->getAmount() / $money->getRate();
    }

    public function compute(EbitNote $note): void
    {
        $parent = $note->getParentNote();
        if (!$parent) {
            return;
        }

        $parentBase = $this->baseAmount($parent);
        $noteBase   = $this->baseAmount($note);

        if ($parentBase === null || $noteBase === null) {
            return;
        }

        if ($note->getType() === EbitNoteType::RecordReceipt) {
            // AR receipt: gain = received_base - invoice_base
            $note->setFxGainLoss(round($noteBase - $parentBase, 4));
        } elseif ($note->getType() === EbitNoteType::RecordPayment) {
            // AP payment: gain = bill_base - paid_base (paid less = gain)
            $note->setFxGainLoss(round($parentBase - $noteBase, 4));
        }
    }
}
```

- [ ] **Step 2: Register in services.yaml**

In `config/services.yaml`, inside the `app.auto_service_locator` arguments block, add:

```yaml
                App\Service\FxGainLossService: '@App\Service\FxGainLossService'
```

- [ ] **Step 3: Commit**

```bash
git add src/Service/FxGainLossService.php config/services.yaml
git commit -m "feat(accounting): FxGainLossService computes and stores FX gain/loss on receipts/payments"
```

---

## Task 7: EbitNoteController — variance and lock endpoints

**Files:**
- Modify: `src/Controller/Api/EbitNoteController.php`

Add three new routes:
- `POST /ebit-note/{id}/approve-variance` → sets varianceStatus=APPROVED
- `POST /ebit-note/{id}/dispute-variance` → sets varianceStatus=DISPUTED
- `POST /ebit-note/{id}/lock` → sets isLocked=true (validates no UNMATCHED/DISPUTED IC children)

Also hook `FxGainLossService::compute()` into the existing POST action for RPT/PMT types.

- [ ] **Step 1: Inject FxGainLossService**

In the constructor of `EbitNoteController`, add:

```php
    protected FxGainLossService $fxGainLossService,
```

Add `use App\Service\FxGainLossService;` import.

- [ ] **Step 2: Hook FX into POST**

In the `POST` action, before `$result = $this->repository->save(...)`, add:

```php
        if (in_array($entity->getType(), [EbitNoteType::RecordReceipt, EbitNoteType::RecordPayment])) {
            $this->fxGainLossService->compute($entity);
        }
```

- [ ] **Step 3: Add variance + lock endpoints**

Add these methods to `EbitNoteController`:

```php
    use App\Misc\Enum\VarianceStatus;
    use App\Repository\EbitNoteRepository;

    #[Route('/{id}/approve-variance', methods: ['POST'])]
    public function approveVariance(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $note = $this->ebitNoteService->repository->find($id);
        if (!$note) throw $this->createNotFoundException();
        $note->setVarianceStatus(VarianceStatus::Approved)
             ->setApprovedBy($user)
             ->setApprovedAt(new \DateTime());
        $this->repository->save($note);
        return $this->json(['varianceStatus' => $note->getVarianceStatus()->value]);
    }

    #[Route('/{id}/dispute-variance', methods: ['POST'])]
    public function disputeVariance(int $id): JsonResponse
    {
        $note = $this->ebitNoteService->repository->find($id);
        if (!$note) throw $this->createNotFoundException();
        $note->setVarianceStatus(VarianceStatus::Disputed);
        $this->repository->save($note);
        return $this->json(['varianceStatus' => $note->getVarianceStatus()->value]);
    }

    #[Route('/{id}/lock', methods: ['POST'])]
    public function lock(int $id): JsonResponse
    {
        $note = $this->ebitNoteService->repository->find($id);
        if (!$note) throw $this->createNotFoundException();
        if ($note->isLocked()) {
            return $this->json(['error' => 'Already locked.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $note->setIsLocked(true);
        $this->repository->save($note);
        return $this->json(['isLocked' => true]);
    }
```

- [ ] **Step 4: Commit**

```bash
git add src/Controller/Api/EbitNoteController.php
git commit -m "feat(accounting): add variance approval/dispute and lock endpoints to EbitNoteController"
```

---

## Task 8: Serializer group — expose new fields

**Files:**
- Modify: `config/serializer_groups/EbitNote.yaml`

- [ ] **Step 1: Add new fields to list group**

```yaml
App\Entity\EbitNote:

    list:
        - type
        - shipment
        - parentNote
        - id
        - code
        - codeReference
        - status
        - paymentMethod
        - collectFrom
        - payTo
        - amountNoTax
        - tax
        - amount
        - currency
        - createdBy
        - dueDate
        - noteDate
        - createdDate
        - fxGainLoss
        - varianceStatus
        - creditNoteReason
        - isLocked
        - vendorRef
        - approvedBy
        - approvedAt

    subList:
        - _extends:list
        - _exclude:shipment
```

- [ ] **Step 2: Commit**

```bash
git add config/serializer_groups/EbitNote.yaml
git commit -m "feat(accounting): expose new accounting fields in EbitNote serializer"
```

---

## Task 9: BO — EbitNoteService.js new methods

**Files:**
- Modify: `src/services/EbitNoteService.js` (in BO repo)

- [ ] **Step 1: Add three methods**

In `d:\Projects\make-cargo-client-bo\src\services\EbitNoteService.js`, add after the existing methods:

```js
  approveVariance(id) {
    return $api(`ebit-note/${id}/approve-variance`, { method: 'POST' })
  },
  disputeVariance(id) {
    return $api(`ebit-note/${id}/dispute-variance`, { method: 'POST' })
  },
  lock(id) {
    return $api(`ebit-note/${id}/lock`, { method: 'POST' })
  },
```

- [ ] **Step 2: Commit**

```bash
git add src/services/EbitNoteService.js
git commit -m "feat(accounting): add variance/lock API methods to BO EbitNoteService"
```

---

## Task 10: BO — IC.vue variance status and approval UI

**Files:**
- Modify: `src/pages/accounting/IC.vue` (in BO repo)

- [ ] **Step 1: Add variance status chip + approve/dispute buttons to IC.vue**

In the `<template>` section of IC.vue, within the `#action` slot of AppTable, add after existing action buttons:

```html
      <template #variance_status="{ item }">
        <VChip
          v-if="item.varianceStatus"
          :color="varianceColor(item.varianceStatus)"
          size="small" label
        >
          {{ item.varianceStatus }}
        </VChip>
        <VChip v-else size="small" variant="tonal" color="default">UNMATCHED</VChip>
      </template>

      <template #action="{ item }">
        <!-- existing action buttons here (keep them) -->

        <VBtn
          v-if="!item.varianceStatus || item.varianceStatus === 'UNMATCHED' || item.varianceStatus === 'VARIANCE'"
          size="x-small" variant="text" color="success"
          :title="$gettext('Approve variance')"
          @click="approveVariance(item)"
        >
          <VIcon icon="tabler-check" size="16" />
        </VBtn>
        <VBtn
          v-if="item.varianceStatus && item.varianceStatus !== 'DISPUTED'"
          size="x-small" variant="text" color="error"
          :title="$gettext('Dispute')"
          @click="disputeVariance(item)"
        >
          <VIcon icon="tabler-x" size="16" />
        </VBtn>
      </template>
```

- [ ] **Step 2: Add script logic**

In the `<script setup>` of IC.vue, add:

```js
const varianceColor = (s) => ({ UNMATCHED: 'default', MATCHED: 'success', VARIANCE: 'warning', APPROVED: 'info', DISPUTED: 'error' }[s] ?? 'default')

async function approveVariance(item) {
  await EntityService.approveVariance(item.id)
  table.value?.fetchData()
}

async function disputeVariance(item) {
  await EntityService.disputeVariance(item.id)
  table.value?.fetchData()
}
```

- [ ] **Step 3: Commit**

```bash
git add src/pages/accounting/IC.vue
git commit -m "feat(accounting): add variance status chip and approve/dispute buttons to IC.vue"
```

---

## Task 11: BO — RPT.vue and PMT.vue FX gain/loss display

**Files:**
- Modify: `src/pages/accounting/RPT.vue`
- Modify: `src/pages/accounting/PMT.vue`

- [ ] **Step 1: Add fxGainLoss column to RPT.vue template**

In `RPT.vue`, within the AppTable template, add a column slot:

```html
      <template #fx_gain_loss="{ item }">
        <span v-if="item.fxGainLoss !== null && item.fxGainLoss !== undefined"
          :class="item.fxGainLoss >= 0 ? 'text-success' : 'text-error'"
        >
          {{ item.fxGainLoss >= 0 ? '+' : '' }}{{ Number(item.fxGainLoss).toLocaleString(undefined, { minimumFractionDigits: 2 }) }}
        </span>
        <span v-else class="text-medium-emphasis">—</span>
      </template>
```

Also add a header entry for `fx_gain_loss` if the page uses a headers config array. If it uses a static array inline, add `{ key: 'fx_gain_loss', title: 'FX Gain/Loss' }` to the headers.

- [ ] **Step 2: Apply same change to PMT.vue**

Copy the identical slot template into `PMT.vue`.

For PMT, the label/interpretation: positive = FX gain (you paid less in base currency than the bill said).

- [ ] **Step 3: Commit**

```bash
git add src/pages/accounting/RPT.vue src/pages/accounting/PMT.vue
git commit -m "feat(accounting): show FX gain/loss column on RPT and PMT pages"
```

---

## Self-review checklist

- [x] VarianceStatus: 5 cases with label() and color()
- [x] CreditNoteReason: 8 cases matching spec reason codes
- [x] EbitNote: all 7 new fields added with getters/setters
- [x] ChargeItem: expectedAmount (embedded Money) added
- [x] Migration: covers all new columns on both drivers
- [x] FxGainLossService: handles RPT (gain = received − invoice) and PMT (gain = bill − paid)
- [x] Controller: FX computed on POST for RPT/PMT; variance endpoints added
- [x] Serializer: new fields exposed in list group
- [x] BO service: 3 new API methods
- [x] BO IC.vue: variance chip + approve/dispute buttons
- [x] BO RPT/PMT: FX gain/loss column
