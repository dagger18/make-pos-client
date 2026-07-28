# Accounting Phase 3: Reports — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build four read-only reports on top of existing EbitNote data — AR Ageing, AP Ageing, Period P&L by branch, and Job Cost Sheet — plus an accounting close workflow that locks EbitNotes and marks a job's accounting as closed.

**Architecture:** All reports are raw-SQL queries in new repository methods, exposed via two new controllers (`AgeingController`, `PnlController`). No new entities needed. BO gets four new Vue pages and the shipment detail gains a "Cost Sheet" tab. Accounting close adds one field to `Shipment` (`accountingClosedAt`) and one endpoint on `ShipmentController`.

**Tech Stack:** PHP 8.1+, Doctrine DBAL (raw SQL for aggregation), Symfony 6, Vue 3, Vuetify 3

**Prerequisites:** Phase 1 complete (EbitNote has `isLocked`). Shipment entity accessible.

---

## Repo paths

- API: `d:\Projects\make-cargo-client`
- BO:  `d:\Projects\make-cargo-client-bo`

## Key EbitNote → DB column mapping

| PHP | DB column |
|---|---|
| `type` | `type` (enum string: ID, IC, RPT, PMT, CN…) |
| `amount.amount` | `amount_amount` |
| `amount.rate` | `amount_rate` |
| `dueDate` | `due_date` |
| `collectFrom` | `collect_from_id` |
| `payTo` | `pay_to_id` |
| `status` | `status` (P/S/A/D) |
| `shipment` | `shipment_id` |

Base amount formula: `amount_amount / amount_rate`

Outstanding for ID = total ID amount − SUM of linked RPT amounts (same `parent_note_id`).

---

## Task 1: AR Ageing — API

**Files:**
- Create: `src/Controller/Api/AgeingController.php`
- Create: `src/Repository/AgeingRepository.php`

- [ ] **Step 1: Create AgeingRepository**

```php
<?php
// src/Repository/AgeingRepository.php
namespace App\Repository;

use Doctrine\DBAL\Connection;

class AgeingRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getArAgeing(): array
    {
        $sql = "
            SELECT
                COALESCE(p.name, 'Unknown') AS partner,
                en.currency,
                SUM(en.amount_amount)                                                        AS total_invoiced,
                COALESCE(SUM(paid.paid_amount), 0)                                           AS total_paid,
                SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0)                  AS outstanding,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) <= 0
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS current_not_due,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 1  AND 30
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_1_30,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 31 AND 60
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 61 AND 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) > 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_90plus
            FROM ebit_note en
            LEFT JOIN partner p ON p.id = en.collect_from_id
            LEFT JOIN (
                SELECT parent_note_id, SUM(amount_amount) AS paid_amount
                FROM ebit_note
                WHERE type = 'RPT'
                GROUP BY parent_note_id
            ) paid ON paid.parent_note_id = en.id
            WHERE en.type = 'ID'
              AND en.status != 'D'
            GROUP BY en.collect_from_id, p.name, en.currency
            HAVING outstanding > 0
            ORDER BY outstanding DESC
        ";
        return $this->connection->fetchAllAssociative($sql);
    }

    public function getApAgeing(): array
    {
        $sql = "
            SELECT
                COALESCE(p.name, 'Unknown') AS partner,
                en.currency,
                SUM(en.amount_amount)                                                        AS total_billed,
                COALESCE(SUM(paid.paid_amount), 0)                                           AS total_paid,
                SUM(en.amount_amount) - COALESCE(SUM(paid.paid_amount), 0)                  AS outstanding,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) <= 0
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS current_not_due,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 1  AND 30
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_1_30,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 31 AND 60
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_31_60,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) BETWEEN 61 AND 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_61_90,
                SUM(CASE WHEN DATEDIFF(CURDATE(), en.due_date) > 90
                         THEN en.amount_amount - COALESCE(paid.paid_amount, 0) ELSE 0 END)  AS overdue_90plus
            FROM ebit_note en
            LEFT JOIN partner p ON p.id = en.pay_to_id
            LEFT JOIN (
                SELECT parent_note_id, SUM(amount_amount) AS paid_amount
                FROM ebit_note
                WHERE type = 'PMT'
                GROUP BY parent_note_id
            ) paid ON paid.parent_note_id = en.id
            WHERE en.type = 'IC'
              AND en.status != 'D'
            GROUP BY en.pay_to_id, p.name, en.currency
            HAVING outstanding > 0
            ORDER BY outstanding DESC
        ";
        return $this->connection->fetchAllAssociative($sql);
    }
}
```

- [ ] **Step 2: Create AgeingController**

```php
<?php
// src/Controller/Api/AgeingController.php
namespace App\Controller\Api;

use App\Repository\AgeingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/ageing')]
#[IsGranted('ROLE_USER')]
class AgeingController extends AbstractController
{
    public function __construct(private readonly AgeingRepository $repo) {}

    #[Route('/ar', methods: ['GET'])]
    public function arAgeing(): JsonResponse
    {
        return $this->json($this->repo->getArAgeing());
    }

    #[Route('/ap', methods: ['GET'])]
    public function apAgeing(): JsonResponse
    {
        return $this->json($this->repo->getApAgeing());
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Repository/AgeingRepository.php src/Controller/Api/AgeingController.php
git commit -m "feat(reports): AR and AP ageing report API endpoints"
```

---

## Task 2: Period P&L + Job Cost Sheet — API

**Files:**
- Create: `src/Controller/Api/PnlController.php`
- Create: `src/Repository/PnlRepository.php`

- [ ] **Step 1: Create PnlRepository**

```php
<?php
// src/Repository/PnlRepository.php
namespace App\Repository;

use Doctrine\DBAL\Connection;

class PnlRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getPeriodPnl(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                COALESCE(b.name, 'No Branch')                                                           AS branch,
                COUNT(DISTINCT s.id)                                                                     AS jobs_count,
                SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)           AS revenue_base,
                SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END)           AS cost_base,
                SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END)         AS gross_profit,
                COALESCE(SUM(CASE WHEN en.type='RPT' THEN en.fx_gain_loss ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN en.type='PMT' THEN en.fx_gain_loss ELSE 0 END), 0)            AS fx_gain_loss,
                SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                + COALESCE(SUM(CASE WHEN en.type='RPT' THEN en.fx_gain_loss ELSE 0 END), 0)
                + COALESCE(SUM(CASE WHEN en.type='PMT' THEN en.fx_gain_loss ELSE 0 END), 0)            AS net_profit
            FROM ebit_note en
            JOIN shipment s  ON s.id = en.shipment_id
            LEFT JOIN branch b ON b.id = s.branch_id
            WHERE en.type IN ('ID', 'IC', 'RPT', 'PMT')
              AND s.status = 'COMPLETED'
              AND DATE(s.updated_at) BETWEEN :from AND :to
            GROUP BY s.branch_id, b.name
            ORDER BY net_profit DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $dateFrom, 'to' => $dateTo]);
    }

    public function getJobCostSheet(int $shipmentId): array
    {
        $sql = "
            SELECT
                ci.charge_type_name                                                          AS chargeType,
                ci.charge_name                                                               AS chargeName,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS sellBase,
                SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS buyBase,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS marginBase
            FROM charge_item ci
            JOIN ebit_note en ON ci.ebit_note_id = en.id
            WHERE en.shipment_id = :shipmentId
              AND en.type IN ('ID', 'IC')
            GROUP BY ci.charge_type_name, ci.charge_name
            ORDER BY ABS(marginBase) DESC
        ";
        $lines = $this->connection->fetchAllAssociative($sql, ['shipmentId' => $shipmentId]);

        $totalSell = array_sum(array_column($lines, 'sellBase'));
        $totalBuy  = array_sum(array_column($lines, 'buyBase'));
        $margin    = $totalSell - $totalBuy;
        $pct       = $totalSell > 0 ? round($margin / $totalSell * 100, 2) : 0;

        return [
            'lines'       => $lines,
            'totalSell'   => round($totalSell, 4),
            'totalBuy'    => round($totalBuy, 4),
            'grossProfit' => round($margin, 4),
            'marginPct'   => $pct,
        ];
    }
}
```

- [ ] **Step 2: Create PnlController**

```php
<?php
// src/Controller/Api/PnlController.php
namespace App\Controller\Api;

use App\Repository\PnlRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report')]
#[IsGranted('ROLE_USER')]
class PnlController extends AbstractController
{
    public function __construct(private readonly PnlRepository $repo) {}

    #[Route('/profit-loss', methods: ['GET'])]
    public function periodPnl(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getPeriodPnl($from, $to));
    }

    #[Route('/cost-sheet/{shipmentId}', methods: ['GET'])]
    public function costSheet(int $shipmentId): JsonResponse
    {
        return $this->json($this->repo->getJobCostSheet($shipmentId));
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Repository/PnlRepository.php src/Controller/Api/PnlController.php
git commit -m "feat(reports): Period P&L and Job Cost Sheet API endpoints"
```

---

## Task 3: Accounting Close — Shipment field + endpoint

**Files:**
- Modify: `src/Entity/Shipment.php`
- Create: `migrations/mysql/Version20260622170000.php`
- Create: `migrations/sqlite/Version20260622170000.php`
- Modify: `src/Controller/Api/ShipmentController.php` (add close endpoint) OR create a standalone route in PnlController

- [ ] **Step 1: Add accountingClosedAt to Shipment entity**

In `src/Entity/Shipment.php`, after `getParentJobId()` getter, add:

```php
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $accountingClosedAt = null;

    public function getAccountingClosedAt(): ?\DateTimeInterface { return $this->accountingClosedAt; }
    public function setAccountingClosedAt(?\DateTimeInterface $d): static { $this->accountingClosedAt = $d; return $this; }
```

- [ ] **Step 2: Create MySQL migration**

```php
<?php
// migrations/mysql/Version20260622170000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Accounting Phase 3: accounting_closed_at on shipment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shipment ADD COLUMN accounting_closed_at DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shipment DROP COLUMN accounting_closed_at");
    }
}
```

- [ ] **Step 3: Create SQLite migration**

```php
<?php
// migrations/sqlite/Version20260622170000.php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622170000 extends AbstractMigration
{
    public function getDescription(): string { return 'accounting_closed_at on shipment (SQLite)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE shipment ADD COLUMN accounting_closed_at DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 4: Add accounting-close endpoint to PnlController**

Add to `PnlController`:

```php
    use App\Repository\ShipmentRepository;
    use App\Repository\EbitNoteRepository;

    // Add to constructor:
    // private readonly ShipmentRepository $shipmentRepo,
    // private readonly EbitNoteRepository $ebitNoteRepo,

    #[Route('/accounting-close/{shipmentId}', methods: ['POST'])]
    public function accountingClose(int $shipmentId): JsonResponse
    {
        // Find shipment via DBAL to avoid circular deps
        $shipment = $this->shipmentRepo->find($shipmentId);
        if (!$shipment) {
            return $this->json(['error' => 'Shipment not found'], Response::HTTP_NOT_FOUND);
        }
        if ($shipment->getAccountingClosedAt()) {
            return $this->json(['error' => 'Already closed'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Lock all EbitNotes for this shipment
        $notes = $this->ebitNoteRepo->findBy(['shipment' => $shipment]);
        foreach ($notes as $note) {
            $note->setIsLocked(true);
            $this->ebitNoteRepo->save($note);
        }

        $shipment->setAccountingClosedAt(new \DateTime());
        $this->shipmentRepo->save($shipment);

        return $this->json(['accountingClosedAt' => $shipment->getAccountingClosedAt()->format('Y-m-d H:i:s')]);
    }
```

Update PnlController constructor to inject `ShipmentRepository` and `EbitNoteRepository`.

- [ ] **Step 5: Update ShipmentNormalizer to expose accountingClosedAt**

In `src/Serializer/ShipmentNormalizer.php`, after the `consolId`/`parentJobId` lines, add:

```php
      $data['accountingClosedAt'] = $object->getAccountingClosedAt()?->format('Y-m-d H:i:s');
```

- [ ] **Step 6: Commit**

```bash
git add src/Entity/Shipment.php migrations/mysql/Version20260622170000.php migrations/sqlite/Version20260622170000.php src/Controller/Api/PnlController.php src/Serializer/ShipmentNormalizer.php
git commit -m "feat(reports): accountingClosedAt field on Shipment + accounting-close endpoint"
```

---

## Task 4: BO — AgeingService.js and PnlService.js

**Files:**
- Create: `src/services/AgeingService.js`
- Create: `src/services/PnlService.js`

- [ ] **Step 1: Create AgeingService.js**

```js
// src/services/AgeingService.js
export default {
  arAgeing()  { return $api('report/ageing/ar') },
  apAgeing()  { return $api('report/ageing/ap') },
}
```

- [ ] **Step 2: Create PnlService.js**

```js
// src/services/PnlService.js
export default {
  periodPnl(from, to)       { return $api(`report/profit-loss?from=${from}&to=${to}`) },
  costSheet(shipmentId)     { return $api(`report/cost-sheet/${shipmentId}`) },
  accountingClose(shipId)   { return $api(`report/accounting-close/${shipId}`, { method: 'POST' }) },
}
```

- [ ] **Step 3: Commit**

```bash
git add src/services/AgeingService.js src/services/PnlService.js
git commit -m "feat(reports): BO AgeingService and PnlService"
```

---

## Task 5: BO — AR Ageing page

**Files:**
- Create: `src/pages/accounting/ageing-ar.vue`

- [ ] **Step 1: Create page**

```vue
<!-- src/pages/accounting/ageing-ar.vue -->
<script setup>
import AgeingService from '@/services/AgeingService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const rows = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  rows.value = await AgeingService.arAgeing()
  loading.value = false
}

const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const totals = computed(() => ({
  outstanding:    rows.value.reduce((s, r) => s + +r.outstanding, 0),
  current:        rows.value.reduce((s, r) => s + +r.current_not_due, 0),
  d1_30:          rows.value.reduce((s, r) => s + +r.overdue_1_30, 0),
  d31_60:         rows.value.reduce((s, r) => s + +r.overdue_31_60, 0),
  d61_90:         rows.value.reduce((s, r) => s + +r.overdue_61_90, 0),
  d90plus:        rows.value.reduce((s, r) => s + +r.overdue_90plus, 0),
}))

onMounted(load)
</script>

<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">AR Ageing Report</h4></VCol>
      <VCol cols="auto"><VBtn prepend-icon="tabler-refresh" @click="load" :loading="loading">Refresh</VBtn></VCol>
    </VRow>

    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Customer</th>
            <th>Ccy</th>
            <th class="text-right">Outstanding</th>
            <th class="text-right">Current</th>
            <th class="text-right">1-30 days</th>
            <th class="text-right">31-60 days</th>
            <th class="text-right">61-90 days</th>
            <th class="text-right">90+ days</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8" class="text-center pa-4"><VProgressCircular indeterminate size="24"/></td></tr>
          <tr v-for="r in rows" :key="r.partner + r.currency">
            <td>{{ r.partner }}</td>
            <td>{{ r.currency }}</td>
            <td class="text-right font-weight-medium">{{ fmt(r.outstanding) }}</td>
            <td class="text-right text-success">{{ fmt(r.current_not_due) }}</td>
            <td class="text-right" :class="{ 'text-warning': +r.overdue_1_30 > 0 }">{{ fmt(r.overdue_1_30) }}</td>
            <td class="text-right" :class="{ 'text-orange': +r.overdue_31_60 > 0 }">{{ fmt(r.overdue_31_60) }}</td>
            <td class="text-right" :class="{ 'text-error': +r.overdue_61_90 > 0 }">{{ fmt(r.overdue_61_90) }}</td>
            <td class="text-right text-error font-weight-bold">{{ fmt(r.overdue_90plus) }}</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr class="font-weight-bold">
            <td colspan="2">TOTAL</td>
            <td class="text-right">{{ fmt(totals.outstanding) }}</td>
            <td class="text-right">{{ fmt(totals.current) }}</td>
            <td class="text-right">{{ fmt(totals.d1_30) }}</td>
            <td class="text-right">{{ fmt(totals.d31_60) }}</td>
            <td class="text-right">{{ fmt(totals.d61_90) }}</td>
            <td class="text-right">{{ fmt(totals.d90plus) }}</td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add src/pages/accounting/ageing-ar.vue
git commit -m "feat(reports): AR ageing report page"
```

---

## Task 6: BO — AP Ageing page

**Files:**
- Create: `src/pages/accounting/ageing-ap.vue`

- [ ] **Step 1: Create page**

```vue
<!-- src/pages/accounting/ageing-ap.vue -->
<script setup>
import AgeingService from '@/services/AgeingService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const rows = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  rows.value = await AgeingService.apAgeing()
  loading.value = false
}

const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const totals = computed(() => ({
  outstanding: rows.value.reduce((s, r) => s + +r.outstanding, 0),
  current:     rows.value.reduce((s, r) => s + +r.current_not_due, 0),
  d1_30:       rows.value.reduce((s, r) => s + +r.overdue_1_30, 0),
  d31_60:      rows.value.reduce((s, r) => s + +r.overdue_31_60, 0),
  d61_90:      rows.value.reduce((s, r) => s + +r.overdue_61_90, 0),
  d90plus:     rows.value.reduce((s, r) => s + +r.overdue_90plus, 0),
}))

onMounted(load)
</script>

<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">AP Ageing Report</h4></VCol>
      <VCol cols="auto"><VBtn prepend-icon="tabler-refresh" @click="load" :loading="loading">Refresh</VBtn></VCol>
    </VRow>

    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Vendor</th>
            <th>Ccy</th>
            <th class="text-right">Outstanding</th>
            <th class="text-right">Current</th>
            <th class="text-right">1-30 days</th>
            <th class="text-right">31-60 days</th>
            <th class="text-right">61-90 days</th>
            <th class="text-right">90+ days</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8" class="text-center pa-4"><VProgressCircular indeterminate size="24"/></td></tr>
          <tr v-for="r in rows" :key="r.partner + r.currency">
            <td>{{ r.partner }}</td>
            <td>{{ r.currency }}</td>
            <td class="text-right font-weight-medium">{{ fmt(r.outstanding) }}</td>
            <td class="text-right text-success">{{ fmt(r.current_not_due) }}</td>
            <td class="text-right" :class="{ 'text-warning': +r.overdue_1_30 > 0 }">{{ fmt(r.overdue_1_30) }}</td>
            <td class="text-right" :class="{ 'text-orange': +r.overdue_31_60 > 0 }">{{ fmt(r.overdue_31_60) }}</td>
            <td class="text-right" :class="{ 'text-error': +r.overdue_61_90 > 0 }">{{ fmt(r.overdue_61_90) }}</td>
            <td class="text-right text-error font-weight-bold">{{ fmt(r.overdue_90plus) }}</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr class="font-weight-bold">
            <td colspan="2">TOTAL</td>
            <td class="text-right">{{ fmt(totals.outstanding) }}</td>
            <td class="text-right">{{ fmt(totals.current) }}</td>
            <td class="text-right">{{ fmt(totals.d1_30) }}</td>
            <td class="text-right">{{ fmt(totals.d31_60) }}</td>
            <td class="text-right">{{ fmt(totals.d61_90) }}</td>
            <td class="text-right">{{ fmt(totals.d90plus) }}</td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add src/pages/accounting/ageing-ap.vue
git commit -m "feat(reports): AP ageing report page"
```

---

## Task 7: BO — Period P&L page

**Files:**
- Create: `src/pages/accounting/pnl-period.vue`

- [ ] **Step 1: Create page**

```vue
<!-- src/pages/accounting/pnl-period.vue -->
<script setup>
import PnlService from '@/services/PnlService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const rows = ref([])
const loading = ref(false)
const dateFrom = ref(new Date().toISOString().slice(0, 8) + '01')
const dateTo   = ref(new Date().toISOString().slice(0, 10))
const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const totals = computed(() => ({
  revenue:     rows.value.reduce((s, r) => s + +r.revenue_base, 0),
  cost:        rows.value.reduce((s, r) => s + +r.cost_base, 0),
  gross:       rows.value.reduce((s, r) => s + +r.gross_profit, 0),
  fx:          rows.value.reduce((s, r) => s + +r.fx_gain_loss, 0),
  net:         rows.value.reduce((s, r) => s + +r.net_profit, 0),
}))
const marginPct = (r) => +r.revenue_base > 0 ? ((+r.gross_profit / +r.revenue_base) * 100).toFixed(1) + '%' : '—'

async function load() {
  loading.value = true
  rows.value = await PnlService.periodPnl(dateFrom.value, dateTo.value)
  loading.value = false
}
onMounted(load)
</script>

<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Period P&amp;L by Branch</h4></VCol>
    </VRow>
    <VRow class="mb-4">
      <VCol cols="12" sm="3">
        <VTextField v-model="dateFrom" type="date" label="From" density="compact" hide-details />
      </VCol>
      <VCol cols="12" sm="3">
        <VTextField v-model="dateTo" type="date" label="To" density="compact" hide-details />
      </VCol>
      <VCol cols="auto">
        <VBtn color="primary" :loading="loading" @click="load">Run Report</VBtn>
      </VCol>
    </VRow>

    <VCard>
      <VTable>
        <thead>
          <tr>
            <th>Branch</th>
            <th class="text-right">Jobs</th>
            <th class="text-right">Revenue</th>
            <th class="text-right">Cost</th>
            <th class="text-right">Gross Profit</th>
            <th class="text-right">Margin %</th>
            <th class="text-right">FX Gain/Loss</th>
            <th class="text-right">Net Profit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8" class="text-center pa-4"><VProgressCircular indeterminate size="24"/></td></tr>
          <tr v-else-if="!rows.length"><td colspan="8" class="text-center text-medium-emphasis pa-4">No data for selected period.</td></tr>
          <tr v-for="r in rows" :key="r.branch">
            <td class="font-weight-medium">{{ r.branch }}</td>
            <td class="text-right">{{ r.jobs_count }}</td>
            <td class="text-right">{{ fmt(r.revenue_base) }}</td>
            <td class="text-right">{{ fmt(r.cost_base) }}</td>
            <td class="text-right" :class="+r.gross_profit >= 0 ? 'text-success' : 'text-error'">{{ fmt(r.gross_profit) }}</td>
            <td class="text-right">{{ marginPct(r) }}</td>
            <td class="text-right" :class="+r.fx_gain_loss >= 0 ? 'text-success' : 'text-error'">{{ fmt(r.fx_gain_loss) }}</td>
            <td class="text-right font-weight-bold" :class="+r.net_profit >= 0 ? 'text-success' : 'text-error'">{{ fmt(r.net_profit) }}</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr class="font-weight-bold">
            <td>TOTAL</td>
            <td></td>
            <td class="text-right">{{ fmt(totals.revenue) }}</td>
            <td class="text-right">{{ fmt(totals.cost) }}</td>
            <td class="text-right">{{ fmt(totals.gross) }}</td>
            <td></td>
            <td class="text-right">{{ fmt(totals.fx) }}</td>
            <td class="text-right">{{ fmt(totals.net) }}</td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add src/pages/accounting/pnl-period.vue
git commit -m "feat(reports): Period P&L by branch report page"
```

---

## Task 8: BO — Job Cost Sheet in ShipmentDetail

**Files:**
- Create: `src/views/shipment/ShipmentCostSheet.vue`
- Modify: `src/views/shipment/ShipmentDetail.vue`

- [ ] **Step 1: Create ShipmentCostSheet.vue**

```vue
<!-- src/views/shipment/ShipmentCostSheet.vue -->
<script setup>
import PnlService from '@/services/PnlService'

const props = defineProps({ shipment: { type: Object, required: true } })
const data = ref(null)
const loading = ref(false)
const closing = ref(false)
const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

async function load() {
  if (!props.shipment?.id) return
  loading.value = true
  data.value = await PnlService.costSheet(props.shipment.id)
  loading.value = false
}

async function closeAccounting() {
  closing.value = true
  await PnlService.accountingClose(props.shipment.id)
  closing.value = false
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Summary chips -->
    <VRow class="mb-4" v-if="data">
      <VCol cols="auto">
        <VCard variant="tonal" color="primary" class="pa-3 text-center">
          <div class="text-caption text-medium-emphasis">Revenue</div>
          <div class="text-h6 font-weight-bold">{{ fmt(data.totalSell) }}</div>
        </VCard>
      </VCol>
      <VCol cols="auto">
        <VCard variant="tonal" color="error" class="pa-3 text-center">
          <div class="text-caption text-medium-emphasis">Cost</div>
          <div class="text-h6 font-weight-bold">{{ fmt(data.totalBuy) }}</div>
        </VCard>
      </VCol>
      <VCol cols="auto">
        <VCard variant="tonal" :color="data.grossProfit >= 0 ? 'success' : 'error'" class="pa-3 text-center">
          <div class="text-caption text-medium-emphasis">Gross Profit</div>
          <div class="text-h6 font-weight-bold">{{ fmt(data.grossProfit) }}</div>
        </VCard>
      </VCol>
      <VCol cols="auto">
        <VCard variant="tonal" :color="data.marginPct >= 0 ? 'success' : 'error'" class="pa-3 text-center">
          <div class="text-caption text-medium-emphasis">Margin</div>
          <div class="text-h6 font-weight-bold">{{ data.marginPct }}%</div>
        </VCard>
      </VCol>
      <VCol class="d-flex align-center justify-end">
        <VBtn
          v-if="!shipment.accountingClosedAt"
          color="success" variant="tonal"
          prepend-icon="tabler-lock"
          :loading="closing"
          @click="closeAccounting"
        >
          Close Accounting
        </VBtn>
        <VChip v-else color="success" label prepend-icon="tabler-lock">
          Accounting Closed {{ shipment.accountingClosedAt?.slice(0, 10) }}
        </VChip>
      </VCol>
    </VRow>

    <!-- Cost sheet table -->
    <VTable v-if="data">
      <thead>
        <tr>
          <th>Charge Type</th>
          <th>Charge Name</th>
          <th class="text-right">Sell (base)</th>
          <th class="text-right">Buy (base)</th>
          <th class="text-right">Margin</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading"><td colspan="5" class="text-center pa-4"><VProgressCircular indeterminate size="24"/></td></tr>
        <tr v-for="(line, i) in data.lines" :key="i">
          <td>{{ line.chargeType }}</td>
          <td>{{ line.chargeName }}</td>
          <td class="text-right">{{ fmt(line.sellBase) }}</td>
          <td class="text-right">{{ fmt(line.buyBase) }}</td>
          <td class="text-right" :class="+line.marginBase >= 0 ? 'text-success' : 'text-error'">
            {{ fmt(line.marginBase) }}
          </td>
        </tr>
      </tbody>
    </VTable>

    <div v-else-if="loading" class="text-center pa-4"><VProgressCircular indeterminate /></div>
  </div>
</template>
```

- [ ] **Step 2: Add Cost Sheet tab to ShipmentDetail.vue**

In `src/views/shipment/ShipmentDetail.vue`, in the `<script setup>` section, add:

```js
import ShipmentCostSheet from './ShipmentCostSheet.vue'
```

In the `tabs` computed array, add after the Tasks entry:

```js
      {
        value: 'cost-sheet',
        title: $gettext('Cost Sheet'),
        icon: 'tabler-report-money',
        component: ShipmentCostSheet,
      },
```

- [ ] **Step 3: Commit**

```bash
git add src/views/shipment/ShipmentCostSheet.vue src/views/shipment/ShipmentDetail.vue
git commit -m "feat(reports): Job Cost Sheet tab in ShipmentDetail + accounting close button"
```

---

## Task 9: BO — Navigation updates

**Files:**
- Modify: `src/config/navigation/index.js`

- [ ] **Step 1: Add report links to the existing Reports section**

In the `children` array of the Reports nav item, add:

```js
      {
        title: $gettext('AR Ageing'),
        to: { name: 'accounting-ageing-ar' },
        subject: 'EbitNote',
        action: 'GET',
      },
      {
        title: $gettext('AP Ageing'),
        to: { name: 'accounting-ageing-ap' },
        subject: 'EbitNote',
        action: 'GET',
      },
      {
        title: $gettext('Period P&L'),
        to: { name: 'accounting-pnl-period' },
        subject: 'EbitNote',
        action: 'GET',
      },
```

- [ ] **Step 2: Commit**

```bash
git add src/config/navigation/index.js
git commit -m "feat(reports): add AR/AP Ageing and Period P&L to navigation"
```

---

## Self-review checklist

- [x] AR Ageing: buckets by 0/1-30/31-60/61-90/90+ days, grouped by partner+currency, outstanding = invoiced − paid
- [x] AP Ageing: same structure for IC type notes with PMT deductions
- [x] Period P&L: revenue (ID), cost (IC), gross profit, FX gain/loss from RPT/PMT, net profit — grouped by branch, filtered by shipment.updated_at
- [x] Job Cost Sheet: sell vs buy per charge type/name, margin, totals
- [x] Accounting close: locks all EbitNotes for shipment, sets accountingClosedAt, exposed in ShipmentNormalizer
- [x] BO Cost Sheet: shows in ShipmentDetail tab with summary chips + accounting close button
- [x] Navigation: 3 new report links under Reports section
- [x] All migrations created for both MySQL and SQLite drivers
