# Profit Centers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up the existing `Department` entity as the profit center — add a department-grouped P&L report endpoint, surface `payableAt`/`departmentName` in the cost sheet, add opt-in `visibleTo` charge filtering, build the BO report page, and mark the feature complete.

**Architecture:** No new entities — `Department` (branch + direction + name) already IS the profit center. Both `ChargeItem` and `QuotePrice` already carry `department`, `payableAt`, and `visibleTo` fields. The missing pieces are raw-SQL report methods on `PnlRepository` (consistent with the existing pattern), a new `?myDept=1` filter option on the cost sheet endpoint, a new BO report page, and two cosmetic columns added to `ShipmentCostSheet.vue`.

**Tech Stack:** PHP 8.1 / Symfony, Doctrine DBAL (raw SQL via `Connection::fetchAllAssociative`), Vue 3 / Vuetify 3, `$api` (ofetch)

---

## File Map

| File | Action |
|------|--------|
| `src/Module/Finance/Repository/PnlRepository.php` | Add `getDepartmentPnl()`, extend `getJobCostSheet()` |
| `src/Module/Finance/Controller/PnlController.php` | Add `departmentPnl()` route, extend `costSheet()` for `?myDept=1` |
| `src/Module/Finance/Repository/ChargeItemRepository.php` | Add `findByShipmentFiltered()` |
| `src/services/PnlService.js` (BO) | Add `departmentPnl()` method |
| `src/pages/report/department-pnl.vue` (BO) | Create new report page |
| `src/views/shipment/ShipmentCostSheet.vue` (BO) | Add `payableAt` + `departmentName` columns |
| `docs/guides/profit-centers.md` | Create setup guide |
| `docs/saas/feature-matrix.md` | Update Profit Centers row to ✅ |

---

## Task 1: PnlRepository — getDepartmentPnl + extended getJobCostSheet

**Files:**
- Modify: `src/Module/Finance/Repository/PnlRepository.php`

- [ ] **Step 1: Replace the full file with the extended version**

```php
<?php
namespace App\Module\Finance\Repository;

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
              AND s.status = 'CO'
              AND DATE(s.completed_date) BETWEEN :from AND :to
            GROUP BY s.branch_id, b.name
            ORDER BY net_profit DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $dateFrom, 'to' => $dateTo]);
    }

    public function getDepartmentPnl(string $dateFrom, string $dateTo): array
    {
        $sql = "
            SELECT
                d.id                                                                              AS department_id,
                d.name                                                                            AS department_name,
                COALESCE(b.name, 'No Branch')                                                    AS branch_name,
                COALESCE(d.direction, '')                                                         AS direction,
                COUNT(DISTINCT en.shipment_id)                                                    AS jobs_count,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)    AS revenue_base,
                SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)    AS cost_base,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)  AS gross_profit,
                ROUND(
                    (SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
                     - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END))
                    / NULLIF(SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END), 0) * 100,
                    2
                )                                                                                 AS margin_pct
            FROM charge_item ci
            JOIN ebit_note en  ON ci.ebit_note_id = en.id
            JOIN shipment s    ON en.shipment_id = s.id
            JOIN department d  ON ci.department_id = d.id
            LEFT JOIN branch b ON d.branch_id = b.id
            WHERE en.type IN ('ID', 'IC')
              AND s.status = 'CO'
              AND DATE(s.completed_date) BETWEEN :from AND :to
              AND ci.department_id IS NOT NULL
            GROUP BY d.id, d.name, b.name, d.direction
            ORDER BY gross_profit DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $dateFrom, 'to' => $dateTo]);
    }

    /**
     * @param int        $shipmentId
     * @param array|null $visibleToFilter null = no filter; [] = only NULL/ALL; ['ORIGIN'] = ORIGIN+ALL+NULL
     */
    public function getJobCostSheet(int $shipmentId, ?array $visibleToFilter = null): array
    {
        $params = ['shipmentId' => $shipmentId];
        $filterClause = '';

        if ($visibleToFilter !== null) {
            if (empty($visibleToFilter)) {
                $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL')";
            } else {
                $parts = [];
                foreach (array_unique($visibleToFilter) as $i => $val) {
                    $key = "dir_{$i}";
                    $params[$key] = $val;
                    $parts[] = ":{$key}";
                }
                $inList = implode(', ', $parts);
                $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL' OR ci.visible_to IN ({$inList}))";
            }
        }

        $sql = "
            SELECT
                ci.charge_type_name                                                              AS chargeType,
                ci.charge_name                                                                   AS chargeName,
                COALESCE(ci.payable_at, '')                                                      AS payableAt,
                COALESCE(d.name, '')                                                             AS departmentName,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)   AS sellBase,
                SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)   AS buyBase,
                SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
                - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS marginBase
            FROM charge_item ci
            JOIN ebit_note en ON ci.ebit_note_id = en.id
            LEFT JOIN department d ON ci.department_id = d.id
            WHERE en.shipment_id = :shipmentId
              AND en.type IN ('ID', 'IC')
              {$filterClause}
            GROUP BY ci.charge_type_name, ci.charge_name, ci.payable_at, d.name
            ORDER BY ABS(marginBase) DESC
        ";
        $lines = $this->connection->fetchAllAssociative($sql, $params);

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

- [ ] **Step 2: Verify the file saved correctly**

```bash
php bin/console debug:router | grep profit
```
Expected: existing routes listed (no error means Symfony can still boot).

- [ ] **Step 3: Commit**

```bash
git add src/Module/Finance/Repository/PnlRepository.php
git commit -m "feat: add getDepartmentPnl and extend getJobCostSheet with payableAt filter"
```

---

## Task 2: PnlController — new department route + myDept cost sheet

**Files:**
- Modify: `src/Module/Finance/Controller/PnlController.php`

- [ ] **Step 1: Replace the full file**

```php
<?php
namespace App\Module\Finance\Controller;

use App\Module\Core\Entity\User;
use App\Module\Finance\Repository\PnlRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report')]
#[IsGranted('ROLE_USER')]
#[AppModule('finance')]
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

    #[Route('/profit-loss/department', methods: ['GET'])]
    public function departmentPnl(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getDepartmentPnl($from, $to));
    }

    #[Route('/cost-sheet/{shipmentId}', methods: ['GET'])]
    public function costSheet(int $shipmentId, Request $request): JsonResponse
    {
        $visibleToFilter = null;

        if ($request->query->getBoolean('myDept')) {
            /** @var User $user */
            $user  = $this->getUser();
            $roles = $user->getRoles();

            $isPrivileged = (bool) array_intersect(['ROLE_MANAGER', 'ROLE_ADMIN'], $roles);

            if (!$isPrivileged) {
                $allowed = [];
                $showAll = false;

                foreach ($user->getDepartments() as $dept) {
                    $dir = $dept->getDirection();
                    if ($dir === null) {
                        $showAll = true;
                        break;
                    }
                    $mapped = match ($dir->value) {
                        'EXP' => 'ORIGIN',
                        'IMP' => 'DESTINATION',
                        default => null,
                    };
                    if ($mapped === null) {
                        $showAll = true;
                        break;
                    }
                    $allowed[] = $mapped;
                }

                if (!$showAll) {
                    $visibleToFilter = array_unique($allowed);
                }
            }
        }

        return $this->json($this->repo->getJobCostSheet($shipmentId, $visibleToFilter));
    }
}
```

- [ ] **Step 2: Verify routing**

```bash
php bin/console debug:router | grep profit-loss
```
Expected output includes two lines:
```
api_report_profit-loss         GET   /api/report/profit-loss
api_report_profit-loss_department  GET   /api/report/profit-loss/department
```

- [ ] **Step 3: Smoke-test the new endpoint with curl (adjust host/token)**

```bash
curl -s "http://localhost:8000/api/report/profit-loss/department?from=2026-01-01&to=2026-12-31" \
  -H "X-W-Auth: /Token Email=\"admin@example.com\", Token=\"<your-token\">/"
```
Expected: JSON array (may be empty if no completed shipments with department-allocated charge items).

- [ ] **Step 4: Commit**

```bash
git add src/Module/Finance/Controller/PnlController.php
git commit -m "feat: add department P&L endpoint and myDept cost sheet filter"
```

---

## Task 3: ChargeItemRepository — findByShipmentFiltered

**Files:**
- Modify: `src/Module/Finance/Repository/ChargeItemRepository.php`

- [ ] **Step 1: Replace the full file**

```php
<?php

namespace App\Module\Finance\Repository;

use App\Module\Core\Entity\User;
use App\Module\Core\Repository\BaseRepository;

class ChargeItemRepository extends BaseRepository
{
    /**
     * Returns charge item rows for a shipment, filtered by the user's department visibleTo mapping.
     * null = no filter (admin/manager).
     * Mapping: EXP dept → ORIGIN, IMP dept → DESTINATION, others → show all.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByShipmentFiltered(int $shipmentId, User $user): array
    {
        $roles       = $user->getRoles();
        $isPrivileged = (bool) array_intersect(['ROLE_MANAGER', 'ROLE_ADMIN'], $roles);

        $params = ['shipmentId' => $shipmentId];
        $filterClause = '';

        if (!$isPrivileged) {
            $allowed = [];
            $showAll = false;

            foreach ($user->getDepartments() as $dept) {
                $dir = $dept->getDirection();
                if ($dir === null) {
                    $showAll = true;
                    break;
                }
                $mapped = match ($dir->value) {
                    'EXP' => 'ORIGIN',
                    'IMP' => 'DESTINATION',
                    default => null,
                };
                if ($mapped === null) {
                    $showAll = true;
                    break;
                }
                $allowed[] = $mapped;
            }

            if (!$showAll) {
                if (empty($allowed)) {
                    $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL')";
                } else {
                    $parts = [];
                    foreach (array_unique($allowed) as $i => $val) {
                        $key           = "dir_{$i}";
                        $params[$key]  = $val;
                        $parts[]       = ":{$key}";
                    }
                    $inList       = implode(', ', $parts);
                    $filterClause = "AND (ci.visible_to IS NULL OR ci.visible_to = 'ALL' OR ci.visible_to IN ({$inList}))";
                }
            }
        }

        $sql = "
            SELECT ci.*
            FROM charge_item ci
            JOIN ebit_note en ON ci.ebit_note_id = en.id
            WHERE en.shipment_id = :shipmentId
              AND en.type IN ('ID', 'IC')
              {$filterClause}
            ORDER BY ci.id
        ";

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params);
    }
}
```

- [ ] **Step 2: Verify Symfony can boot**

```bash
php bin/console cache:clear
```
Expected: `Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Step 3: Commit**

```bash
git add src/Module/Finance/Repository/ChargeItemRepository.php
git commit -m "feat: add findByShipmentFiltered to ChargeItemRepository"
```

---

## Task 4: PnlService.js — add departmentPnl

**Files:**
- Modify: `src/services/PnlService.js` (in `make-cargo-client-bo`)

- [ ] **Step 1: Replace the full file**

```js
export default {
  periodPnl(from, to)        { return $api(`report/profit-loss?from=${from}&to=${to}`) },
  departmentPnl(from, to)    { return $api(`report/profit-loss/department?from=${from}&to=${to}`) },
  costSheet(shipmentId)      { return $api(`report/cost-sheet/${shipmentId}`) },
  accountingClose(shipId)    { return $api(`report/accounting-close/${shipId}`, { method: 'POST' }) },
}
```

- [ ] **Step 2: Commit**

```bash
git add src/services/PnlService.js
git commit -m "feat: add departmentPnl method to PnlService"
```

---

## Task 5: department-pnl.vue — new report page

**Files:**
- Create: `src/pages/report/department-pnl.vue` (in `make-cargo-client-bo`)

- [ ] **Step 1: Create the file**

```vue
<script setup>
import PnlService from '@/services/PnlService'

definePage({ meta: { action: 'GET', subject: 'EbitNote' } })

const rows    = ref([])
const loading = ref(false)
const dateFrom = ref(new Date().toISOString().slice(0, 8) + '01')
const dateTo   = ref(new Date().toISOString().slice(0, 10))

const fmt = v => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const directionColor = {
  EXP: 'primary',
  IMP: 'info',
  XTD: 'warning',
  DOM: 'secondary',
  TSH: 'success',
}

const totals = computed(() => ({
  jobs:    rows.value.reduce((s, r) => s + +r.jobs_count, 0),
  revenue: rows.value.reduce((s, r) => s + +r.revenue_base, 0),
  cost:    rows.value.reduce((s, r) => s + +r.cost_base, 0),
  profit:  rows.value.reduce((s, r) => s + +r.gross_profit, 0),
}))

async function load() {
  loading.value = true
  rows.value = await PnlService.departmentPnl(dateFrom.value, dateTo.value)
  loading.value = false
}

onMounted(load)
</script>

<template>
  <VContainer fluid class="px-0">
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
            <th>Department</th>
            <th>Branch</th>
            <th>Direction</th>
            <th class="text-right">Jobs</th>
            <th class="text-right">Revenue</th>
            <th class="text-right">Cost</th>
            <th class="text-right">Gross Profit</th>
            <th class="text-right">Margin %</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="text-center pa-4">
              <VProgressCircular indeterminate size="24" />
            </td>
          </tr>
          <tr v-else-if="!rows.length">
            <td colspan="8" class="text-center text-medium-emphasis pa-4">
              No data for selected period. Make sure completed shipments have charge items assigned to departments.
            </td>
          </tr>
          <tr v-for="r in rows" :key="r.department_id">
            <td class="font-weight-medium">{{ r.department_name }}</td>
            <td>{{ r.branch_name }}</td>
            <td>
              <VChip
                v-if="r.direction"
                :color="directionColor[r.direction] ?? 'default'"
                size="small"
                label
              >
                {{ r.direction }}
              </VChip>
            </td>
            <td class="text-right">{{ r.jobs_count }}</td>
            <td class="text-right">{{ fmt(r.revenue_base) }}</td>
            <td class="text-right">{{ fmt(r.cost_base) }}</td>
            <td class="text-right" :class="+r.gross_profit >= 0 ? 'text-success' : 'text-error'">
              {{ fmt(r.gross_profit) }}
            </td>
            <td class="text-right">{{ r.margin_pct }}%</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr class="font-weight-bold bg-surface">
            <td colspan="3">TOTAL</td>
            <td class="text-right">{{ totals.jobs }}</td>
            <td class="text-right">{{ fmt(totals.revenue) }}</td>
            <td class="text-right">{{ fmt(totals.cost) }}</td>
            <td class="text-right" :class="totals.profit >= 0 ? 'text-success' : 'text-error'">
              {{ fmt(totals.profit) }}
            </td>
            <td></td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Verify the route is auto-discovered**

The project uses `unplugin-vue-router` which auto-generates routes from `src/pages/`. The route will be `/report/department-pnl`. Open the BO dev server and navigate to `/report/department-pnl` — the page should render with date pickers and a "Run Report" button.

- [ ] **Step 3: Commit**

```bash
git add src/pages/report/department-pnl.vue
git commit -m "feat: add department P&L report page"
```

---

## Task 6: ShipmentCostSheet.vue — payableAt + departmentName badges

**Files:**
- Modify: `src/views/shipment/ShipmentCostSheet.vue` (in `make-cargo-client-bo`)

- [ ] **Step 1: Replace the full file**

```vue
<script setup>
import PnlService from '@/services/PnlService'

const props = defineProps({ shipment: { type: Object, required: true } })
const data    = ref(null)
const loading = ref(false)
const closing = ref(false)
const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const payableAtColor = { ORIGIN: 'primary', DESTINATION: 'warning', BOTH: 'secondary' }

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

    <VTable v-if="data">
      <thead>
        <tr>
          <th>Charge Type</th>
          <th>Charge Name</th>
          <th>Dept</th>
          <th>Payable At</th>
          <th class="text-right">Sell (base)</th>
          <th class="text-right">Buy (base)</th>
          <th class="text-right">Margin</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="7" class="text-center pa-4">
            <VProgressCircular indeterminate size="24" />
          </td>
        </tr>
        <tr v-for="(line, i) in data.lines" :key="i">
          <td>{{ line.chargeType }}</td>
          <td>{{ line.chargeName }}</td>
          <td>
            <VChip v-if="line.departmentName" size="x-small" label color="default">
              {{ line.departmentName }}
            </VChip>
          </td>
          <td>
            <VChip
              v-if="line.payableAt"
              size="x-small"
              label
              :color="payableAtColor[line.payableAt] ?? 'default'"
            >
              {{ line.payableAt }}
            </VChip>
          </td>
          <td class="text-right">{{ fmt(line.sellBase) }}</td>
          <td class="text-right">{{ fmt(line.buyBase) }}</td>
          <td class="text-right" :class="+line.marginBase >= 0 ? 'text-success' : 'text-error'">
            {{ fmt(line.marginBase) }}
          </td>
        </tr>
      </tbody>
    </VTable>

    <div v-else-if="loading" class="text-center pa-4">
      <VProgressCircular indeterminate />
    </div>
  </div>
</template>
```

- [ ] **Step 2: Open a shipment cost sheet in the browser**

Navigate to any shipment → Cost Sheet tab. Verify:
- Table now shows "Dept" and "Payable At" columns
- For charge items without department/payableAt set, those cells are empty (no chip)
- Totals (Revenue, Cost, Gross Profit, Margin) still calculate correctly

- [ ] **Step 3: Commit**

```bash
git add src/views/shipment/ShipmentCostSheet.vue
git commit -m "feat: add payableAt and department badges to ShipmentCostSheet"
```

---

## Task 7: profit-centers.md guide

**Files:**
- Create: `docs/guides/profit-centers.md` (in `make-cargo-client`)

- [ ] **Step 1: Create the file**

```markdown
# Profit Centers

## Overview

Profit centers in this system are **Departments** — each Department represents a `branch + direction` combination (e.g. "HCM Export", "HAN Import"). No separate profit center entity is needed.

Every charge item (on quotes and invoices) can be assigned to a Department. This assignment is the P&L attribution — it determines which department's revenue and cost the charge contributes to.

---

## Setup

### 1. Create Departments

Go to **Settings → Departments** and create one department per branch/direction combination you want to track separately.

| Name | Branch | Direction |
|------|--------|-----------|
| HCM Export | Ho Chi Minh City | EXP |
| HCM Import | Ho Chi Minh City | IMP |
| HAN Export | Hanoi | EXP |
| HAN Import | Hanoi | IMP |

If a department handles all directions (e.g. a small branch), leave Direction blank.

### 2. Assign departments to users

Go to **Settings → Users** and assign each user to their department(s). A user assigned to "HCM Export" will have their cost sheet filtered to export-side charges when they request `?myDept=1`.

---

## Assigning Charges to Departments

When creating a **Debit Note** (revenue) or **Credit Note** (cost) on a shipment, each charge item line has three optional attribution fields:

| Field | Purpose |
|-------|---------|
| **Department** | Which department's P&L this charge contributes to |
| **Payable At** | Which side of the shipment raises the invoice (`ORIGIN` / `DESTINATION` / `BOTH`) |
| **Visible To** | Which department's users can see this charge line (`ORIGIN` / `DESTINATION` / `ALL`) |

**Typical setup by direction:**

| Direction | Dept | Payable At | Visible To |
|-----------|------|-----------|-----------|
| Export charge | HCM Export | ORIGIN | ORIGIN |
| Import charge | HAN Import | DESTINATION | DESTINATION |
| DDP destination charge billed to shipper | HAN Import | ORIGIN | DESTINATION |
| Cross-trade charge | Coordinating dept | BOTH | ALL |

---

## Department P&L Report

Navigate to **Reports → Department P&L**.

Select a date range and click **Run Report**. The report shows, for each department:

- **Jobs** — number of distinct completed shipments with charge items assigned to this department
- **Revenue** — sum of all sell (debit) charge items assigned to this department, in base currency
- **Cost** — sum of all buy (credit) charge items assigned to this department, in base currency
- **Gross Profit** — Revenue minus Cost
- **Margin %** — Gross Profit / Revenue × 100

Only **completed shipments** (`status = CO`) within the date range are included.

Charge items with no department assigned are excluded from this report (they appear in the branch-level P&L report instead).

---

## Cost Sheet — My Department View

The shipment cost sheet at **Shipment → Cost Sheet** shows all charge items. Two new columns are now visible:

- **Dept** — the department assigned to each charge line
- **Payable At** — the billing side (`ORIGIN` / `DESTINATION` / `BOTH`)

To see only the charge lines your department is responsible for, add `?myDept=1` to the cost sheet API call:

```
GET /api/report/cost-sheet/{shipmentId}?myDept=1
```

With this flag:
- **ROLE_ADMIN / ROLE_MANAGER** — no filter applied, all lines returned
- **EXP department users** — only lines where `visible_to IN ('ORIGIN', 'ALL')` or `visible_to IS NULL`
- **IMP department users** — only lines where `visible_to IN ('DESTINATION', 'ALL')` or `visible_to IS NULL`
- **Other direction users (XTD, DOM, TSH)** — all lines returned

---

## Unallocated Charges

Charge items without a department assignment are:
- Visible in the cost sheet (always shown)
- Included in the branch-level P&L report (`GET /api/report/profit-loss`)
- **Excluded** from the department P&L report

This is intentional — unallocated charges are shared overhead that hasn't been attributed to a profit center yet.
```

- [ ] **Step 2: Commit**

```bash
git add docs/guides/profit-centers.md
git commit -m "docs: add profit-centers setup guide"
```

---

## Task 8: Feature matrix — mark Profit Centers complete

**Files:**
- Modify: `docs/saas/feature-matrix.md`

- [ ] **Step 1: Find and replace the Profit Centers row**

The current row looks like:
```
| **core**         | Profit Centers                   | Formal branch+direction P&L attribution with `payable_at` / `visible_to` charge routing          | Demo     | [profit-centers.md](freight-forwarder-saas-profit-centers.md)                   | ❌   | ❌   |      |                                                                                                                                                                                                           |
```

Replace it with:
```
| **core**         | Profit Centers                   | Formal branch+direction P&L attribution with `payable_at` / `visible_to` charge routing          | Demo     | [profit-centers.md](freight-forwarder-saas-profit-centers.md)                   | ✅   | ✅   | ✅    | Department entity = profit center (branch+direction); getDepartmentPnl report; payableAt+dept badges in cost sheet; visibleTo opt-in filter via ?myDept=1 |
```

- [ ] **Step 2: Commit**

```bash
git add docs/saas/feature-matrix.md
git commit -m "docs: mark Profit Centers as complete in feature matrix"
```

---

## Self-Review

**Spec coverage:**
- ✅ `getDepartmentPnl()` — Task 1
- ✅ `GET /report/profit-loss/department` — Task 2
- ✅ `getJobCostSheet()` extended with `payableAt` + `departmentName` — Task 1
- ✅ `ChargeItemRepository::findByShipmentFiltered()` — Task 3
- ✅ `GET /report/cost-sheet/{id}?myDept=1` — Task 2
- ✅ `PnlService.departmentPnl()` — Task 4
- ✅ `department-pnl.vue` page — Task 5
- ✅ `ShipmentCostSheet.vue` badges — Task 6
- ✅ `docs/guides/profit-centers.md` — Task 7
- ✅ Feature matrix updated — Task 8

**Placeholder scan:** None found. All code blocks are complete.

**Type consistency:**
- `getJobCostSheet(int $shipmentId, ?array $visibleToFilter = null)` — called in Task 2 with this exact signature ✅
- `getDepartmentPnl(string $dateFrom, string $dateTo)` — called in Task 2 with these params ✅
- `PnlService.departmentPnl(from, to)` — used in Task 5's `load()` ✅
- `data.lines[i].payableAt` / `data.lines[i].departmentName` — returned by Task 1's SQL, consumed in Task 6 ✅
