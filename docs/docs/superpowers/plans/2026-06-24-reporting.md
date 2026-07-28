# Reporting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add KPI dashboard, customer profitability, top-lanes, exception, and operational dashboard reports to both the client API and client BO.

**Architecture:** Two new read-only DBAL repositories (`KpiRepository`, `ReportRepository`) feed two new API controllers (`KpiController`, `ReportAnalyticsController`). The BO gets a new service (`ReportAnalyticsService`) and five new/replaced pages: dashboard (operational), kpi, customer-profitability, top-lanes, exception.

**Tech Stack:** Symfony 7 / PHP 8.2 (Doctrine DBAL raw SQL, MySQL); Vue 3 + Vuetify 3 (BO). No new DB tables — all queries use existing data.

---

## File Map

### Client API (`d:\Projects\make-cargo-client`)
| File | Action |
|------|--------|
| `src/Repository/KpiRepository.php` | Create |
| `src/Repository/ReportRepository.php` | Create |
| `src/Controller/Api/KpiController.php` | Create |
| `src/Controller/Api/ReportAnalyticsController.php` | Create |

### Client BO (`d:\Projects\make-cargo-client-bo`)
| File | Action |
|------|--------|
| `src/services/ReportAnalyticsService.js` | Create |
| `src/pages/dashboard.vue` | Replace content |
| `src/pages/report/kpi.vue` | Create |
| `src/pages/report/customer-profitability.vue` | Create |
| `src/pages/report/top-lanes.vue` | Create |
| `src/pages/report/exception.vue` | Create |
| `src/config/navigation/index.js` | Modify — add 4 nav items |
| `docs/guides/reporting.md` (in client API repo) | Create |

---

### Task 1: KpiRepository

**Files:**
- Create: `src/Repository/KpiRepository.php`

- [ ] **Step 1: Create the file**

```php
<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;

class KpiRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getOnTimeRate(string $from, string $to): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN sm.actual_date <= sm.planned_date THEN 1 ELSE 0 END) AS on_time,
                COUNT(*) - SUM(CASE WHEN sm.actual_date <= sm.planned_date THEN 1 ELSE 0 END) AS delayed
            FROM shipment_milestone sm
            WHERE sm.milestone_code IN ('VESSEL_DEPARTED', 'FLIGHT_DEPARTED')
              AND sm.planned_date IS NOT NULL
              AND sm.actual_date IS NOT NULL
              AND DATE(sm.actual_date) BETWEEN :from AND :to
        ";
        $row   = $this->connection->fetchAssociative($sql, ['from' => $from, 'to' => $to]);
        $total = (int) ($row['total'] ?? 0);
        $on    = (int) ($row['on_time'] ?? 0);
        return [
            'total'       => $total,
            'on_time'     => $on,
            'delayed'     => (int) ($row['delayed'] ?? 0),
            'on_time_pct' => $total > 0 ? round($on / $total * 100, 1) : 0.0,
        ];
    }

    public function getConversionRate(string $from, string $to): array
    {
        $sql = "
            SELECT
                SUBSTR(q.created_date, 1, 7)                                          AS month,
                COUNT(*)                                                               AS total,
                SUM(CASE WHEN q.status = 'B' THEN 1 ELSE 0 END)                      AS converted,
                SUM(CASE WHEN q.status = 'R' THEN 1 ELSE 0 END)                      AS rejected
            FROM quote q
            WHERE DATE(q.created_date) BETWEEN :from AND :to
            GROUP BY SUBSTR(q.created_date, 1, 7)
            ORDER BY month ASC
        ";
        $rows = $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
        return array_map(fn($r) => [
            ...$r,
            'conversion_pct' => (int) $r['total'] > 0
                ? round((int) $r['converted'] / (int) $r['total'] * 100, 1)
                : 0.0,
        ], $rows);
    }

    public function getOperatorProductivity(string $from, string $to): array
    {
        $sql = "
            SELECT
                COALESCE(u.first_name, '')                                                            AS first_name,
                COALESCE(u.last_name, '')                                                             AS last_name,
                COUNT(DISTINCT s.id)                                                                  AS jobs_count,
                COALESCE(SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS revenue_base,
                COALESCE(SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS cost_base,
                COALESCE(
                    SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                    - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END),
                    0
                )                                                                                     AS profit_base
            FROM shipment s
            LEFT JOIN user u ON u.id = s.account_manager_id
            LEFT JOIN ebit_note en ON en.shipment_id = s.id AND en.type IN ('ID', 'IC')
            WHERE s.status = 'CO'
              AND DATE(s.updated_at) BETWEEN :from AND :to
            GROUP BY s.account_manager_id, u.first_name, u.last_name
            ORDER BY jobs_count DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getDso(string $from, string $to): float
    {
        $sql = "
            SELECT COALESCE(AVG(DATEDIFF(rpt.created_date, en.created_date)), 0) AS avg_dso
            FROM ebit_note en
            JOIN ebit_note rpt ON rpt.parent_note_id = en.id AND rpt.type = 'RPT'
            WHERE en.type = 'ID'
              AND en.status != 'D'
              AND DATE(rpt.created_date) BETWEEN :from AND :to
        ";
        $result = $this->connection->fetchOne($sql, ['from' => $from, 'to' => $to]);
        return $result !== false ? round((float) $result, 1) : 0.0;
    }
}
```

- [ ] **Step 2: Verify Symfony can auto-wire it**

```bash
cd d:\Projects\make-cargo-client
php bin/console debug:container App\Repository\KpiRepository
```
Expected: shows `App\Repository\KpiRepository` with `autowired: yes`.

- [ ] **Step 3: Commit**

```bash
git add src/Repository/KpiRepository.php
git commit -m "feat: add KpiRepository with on-time rate, conversion, productivity, DSO queries"
```

---

### Task 2: ReportRepository

**Files:**
- Create: `src/Repository/ReportRepository.php`

- [ ] **Step 1: Create the file**

```php
<?php
namespace App\Repository;

use Doctrine\DBAL\Connection;

class ReportRepository
{
    public function __construct(private readonly Connection $connection) {}

    public function getCustomerProfitability(string $from, string $to): array
    {
        $sql = "
            SELECT
                COALESCE(c.name, 'Unknown')                                                           AS client_name,
                COUNT(DISTINCT s.id)                                                                  AS jobs_count,
                COALESCE(SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS revenue_base,
                COALESCE(SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS cost_base,
                COALESCE(
                    SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                    - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END),
                    0
                )                                                                                     AS profit_base
            FROM ebit_note en
            JOIN shipment s ON s.id = en.shipment_id
            LEFT JOIN client c ON c.id = en.collect_from_id
            WHERE en.type IN ('ID', 'IC')
              AND s.status = 'CO'
              AND DATE(s.updated_at) BETWEEN :from AND :to
            GROUP BY en.collect_from_id, c.name
            ORDER BY profit_base DESC
        ";
        $rows = $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
        return array_map(fn($r) => [
            ...$r,
            'margin_pct' => (float) $r['revenue_base'] > 0
                ? round((float) $r['profit_base'] / (float) $r['revenue_base'] * 100, 1)
                : 0.0,
        ], $rows);
    }

    public function getTopLanes(string $from, string $to, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "
            SELECT
                COALESCE(po.name, '')                                                                  AS origin,
                COALESCE(pd.name, '')                                                                  AS destination,
                COUNT(DISTINCT s.id)                                                                   AS shipments,
                COALESCE(SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END), 0) AS revenue_base,
                COALESCE(
                    SUM(CASE WHEN en.type='ID' THEN en.amount_amount / en.amount_rate ELSE 0 END)
                    - SUM(CASE WHEN en.type='IC' THEN en.amount_amount / en.amount_rate ELSE 0 END),
                    0
                )                                                                                      AS profit_base
            FROM shipment s
            JOIN booking b ON b.id = s.booking_id
            LEFT JOIN port po ON po.id = b.port_loading_id
            LEFT JOIN port pd ON pd.id = b.port_discharge_id
            LEFT JOIN ebit_note en ON en.shipment_id = s.id AND en.type IN ('ID', 'IC')
            WHERE s.status = 'CO'
              AND DATE(s.updated_at) BETWEEN :from AND :to
              AND b.port_loading_id IS NOT NULL
              AND b.port_discharge_id IS NOT NULL
            GROUP BY b.port_loading_id, b.port_discharge_id, po.name, pd.name
            ORDER BY shipments DESC
            LIMIT " . $limit . "
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getExceptions(string $from, string $to): array
    {
        $sql = "
            SELECT
                s.code                                    AS shipment_code,
                sm.milestone_code,
                sm.planned_date,
                sm.actual_date,
                sm.exception_hours,
                sm.remarks,
                COALESCE(u.first_name, '')               AS operator_first_name,
                COALESCE(u.last_name, '')                AS operator_last_name,
                COALESCE(po.name, '')                    AS origin,
                COALESCE(pd.name, '')                    AS destination
            FROM shipment_milestone sm
            JOIN shipment s ON s.id = sm.shipment_id
            LEFT JOIN booking b ON b.id = s.booking_id
            LEFT JOIN port po ON po.id = b.port_loading_id
            LEFT JOIN port pd ON pd.id = b.port_discharge_id
            LEFT JOIN user u ON u.id = s.account_manager_id
            WHERE sm.is_exception = 1
              AND DATE(sm.created_at) BETWEEN :from AND :to
            ORDER BY sm.created_at DESC
        ";
        return $this->connection->fetchAllAssociative($sql, ['from' => $from, 'to' => $to]);
    }

    public function getOperationalDashboard(): array
    {
        $sql = "
            SELECT
                s.id,
                s.code,
                s.status,
                COALESCE(po.name, '')                    AS origin,
                COALESCE(pd.name, '')                    AS destination,
                b.etd,
                b.eta,
                COALESCE(u.first_name, '')               AS operator_first_name,
                COALESCE(u.last_name, '')                AS operator_last_name,
                (
                    SELECT COUNT(*)
                    FROM shipment_milestone sm2
                    WHERE sm2.shipment_id = s.id AND sm2.is_exception = 1
                )                                        AS exception_count
            FROM shipment s
            LEFT JOIN booking b ON b.id = s.booking_id
            LEFT JOIN port po ON po.id = b.port_loading_id
            LEFT JOIN port pd ON pd.id = b.port_discharge_id
            LEFT JOIN user u ON u.id = s.account_manager_id
            WHERE s.status IN ('PE', 'AC')
            ORDER BY b.etd ASC
        ";
        $shipments = $this->connection->fetchAllAssociative($sql);
        return [
            'active_count'    => count($shipments),
            'exception_count' => (int) array_sum(array_column($shipments, 'exception_count')),
            'shipments'       => $shipments,
        ];
    }
}
```

- [ ] **Step 2: Verify autowiring**

```bash
php bin/console debug:container App\Repository\ReportRepository
```
Expected: shows service with `autowired: yes`.

- [ ] **Step 3: Commit**

```bash
git add src/Repository/ReportRepository.php
git commit -m "feat: add ReportRepository with customer profitability, top lanes, exceptions, dashboard queries"
```

---

### Task 3: KpiController

**Files:**
- Create: `src/Controller/Api/KpiController.php`

- [ ] **Step 1: Create the controller**

```php
<?php
namespace App\Controller\Api;

use App\Repository\KpiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/kpi')]
#[IsGranted('ROLE_USER')]
class KpiController extends AbstractController
{
    public function __construct(private readonly KpiRepository $repo) {}

    #[Route('/on-time-rate', methods: ['GET'])]
    public function onTimeRate(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getOnTimeRate($from, $to));
    }

    #[Route('/conversion-rate', methods: ['GET'])]
    public function conversionRate(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getConversionRate($from, $to));
    }

    #[Route('/operator-productivity', methods: ['GET'])]
    public function operatorProductivity(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getOperatorProductivity($from, $to));
    }

    #[Route('/dso', methods: ['GET'])]
    public function dso(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json(['avg_dso' => $this->repo->getDso($from, $to)]);
    }
}
```

- [ ] **Step 2: Verify routes are registered**

```bash
php bin/console debug:router | grep "report/kpi"
```
Expected output (4 lines):
```
 app_kpicontroller_ontimerate      ANY    ANY    ANY  /api/report/kpi/on-time-rate
 app_kpicontroller_conversionrate  ANY    ANY    ANY  /api/report/kpi/conversion-rate
 app_kpicontroller_operatorproductivity ANY ANY ANY  /api/report/kpi/operator-productivity
 app_kpicontroller_dso             ANY    ANY    ANY  /api/report/kpi/dso
```

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/KpiController.php
git commit -m "feat: add KpiController with on-time rate, conversion, productivity, DSO endpoints"
```

---

### Task 4: ReportAnalyticsController

**Files:**
- Create: `src/Controller/Api/ReportAnalyticsController.php`

- [ ] **Step 1: Create the controller**

```php
<?php
namespace App\Controller\Api;

use App\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/report/analytics')]
#[IsGranted('ROLE_USER')]
class ReportAnalyticsController extends AbstractController
{
    public function __construct(private readonly ReportRepository $repo) {}

    #[Route('/customer-profitability', methods: ['GET'])]
    public function customerProfitability(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getCustomerProfitability($from, $to));
    }

    #[Route('/top-lanes', methods: ['GET'])]
    public function topLanes(Request $request): JsonResponse
    {
        $from  = $request->query->get('from', date('Y-m-01'));
        $to    = $request->query->get('to', date('Y-m-d'));
        $limit = (int) $request->query->get('limit', 20);
        return $this->json($this->repo->getTopLanes($from, $to, $limit));
    }

    #[Route('/exceptions', methods: ['GET'])]
    public function exceptions(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to   = $request->query->get('to', date('Y-m-d'));
        return $this->json($this->repo->getExceptions($from, $to));
    }

    #[Route('/operational-dashboard', methods: ['GET'])]
    public function operationalDashboard(): JsonResponse
    {
        return $this->json($this->repo->getOperationalDashboard());
    }
}
```

- [ ] **Step 2: Verify routes**

```bash
php bin/console debug:router | grep "report/analytics"
```
Expected: 4 routes for customer-profitability, top-lanes, exceptions, operational-dashboard.

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/ReportAnalyticsController.php
git commit -m "feat: add ReportAnalyticsController with profitability, top-lanes, exceptions, dashboard endpoints"
```

---

### Task 5: BO — ReportAnalyticsService

Working directory: `d:\Projects\make-cargo-client-bo`

**Files:**
- Create: `src/services/ReportAnalyticsService.js`

- [ ] **Step 1: Create the service**

```js
const BASE = 'report'
export default {
  onTimeRate (from, to)           { return $api(`${BASE}/kpi/on-time-rate?from=${from}&to=${to}`) },
  conversionRate (from, to)       { return $api(`${BASE}/kpi/conversion-rate?from=${from}&to=${to}`) },
  operatorProductivity (from, to) { return $api(`${BASE}/kpi/operator-productivity?from=${from}&to=${to}`) },
  dso (from, to)                  { return $api(`${BASE}/kpi/dso?from=${from}&to=${to}`) },
  customerProfitability (from, to){ return $api(`${BASE}/analytics/customer-profitability?from=${from}&to=${to}`) },
  topLanes (from, to, limit = 20) { return $api(`${BASE}/analytics/top-lanes?from=${from}&to=${to}&limit=${limit}`) },
  exceptions (from, to)           { return $api(`${BASE}/analytics/exceptions?from=${from}&to=${to}`) },
  operationalDashboard ()         { return $api(`${BASE}/analytics/operational-dashboard`) },
}
```

- [ ] **Step 2: Verify the file exists**

```bash
ls src/services/ReportAnalyticsService.js
```
Expected: file listed.

- [ ] **Step 3: Commit**

```bash
git add src/services/ReportAnalyticsService.js
git commit -m "feat: add ReportAnalyticsService with all analytics API wrappers"
```

---

### Task 6: BO — dashboard.vue (Operational Dashboard)

Working directory: `d:\Projects\make-cargo-client-bo`

**Files:**
- Modify: `src/pages/dashboard.vue` (currently shows `<UnderMaintenance />`)

- [ ] **Step 1: Replace dashboard.vue content**

```vue
<script setup>
import ReportAnalyticsService from '@/services/ReportAnalyticsService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const data = ref(null)
const loading = ref(false)
async function load() {
  loading.value = true
  data.value = await ReportAnalyticsService.operationalDashboard()
  loading.value = false
}
onMounted(load)
const formatDate = (d) => d ? new Date(d).toLocaleDateString() : '—'
</script>
<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Operational Dashboard</h4></VCol>
      <VCol cols="auto">
        <VBtn variant="tonal" icon="tabler-refresh" :loading="loading" @click="load" />
      </VCol>
    </VRow>
    <VProgressCircular v-if="loading && !data" indeterminate class="mt-8 d-block mx-auto" />
    <template v-if="data">
      <VRow class="mb-6">
        <VCol cols="12" sm="6" md="3">
          <VCard>
            <VCardText>
              <div class="text-caption text-medium-emphasis mb-1">Active Shipments</div>
              <div class="text-h4 font-weight-bold">{{ data.active_count }}</div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol cols="12" sm="6" md="3">
          <VCard>
            <VCardText>
              <div class="text-caption text-medium-emphasis mb-1">Open Exceptions</div>
              <div class="text-h4 font-weight-bold" :class="data.exception_count > 0 ? 'text-error' : 'text-success'">
                {{ data.exception_count }}
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
      <VCard>
        <VTable>
          <thead>
            <tr>
              <th>Job #</th>
              <th>Status</th>
              <th>Origin</th>
              <th>Destination</th>
              <th>ETD</th>
              <th>ETA</th>
              <th>Operator</th>
              <th class="text-right">Exceptions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="8" class="text-center pa-4">
                <VProgressCircular indeterminate size="24" />
              </td>
            </tr>
            <tr v-else-if="!data?.shipments?.length">
              <td colspan="8" class="text-center text-medium-emphasis pa-4">No active shipments.</td>
            </tr>
            <tr v-for="s in data.shipments" :key="s.id">
              <td>
                <RouterLink :to="{ name: 'shipment-id-tab1?tab2?', params: { id: s.code, tab1: 'info', tab2: 'order' } }">
                  {{ s.code }}
                </RouterLink>
              </td>
              <td>
                <VChip size="small" :color="s.status === 'AC' ? 'success' : 'warning'">{{ s.status }}</VChip>
              </td>
              <td>{{ s.origin || '—' }}</td>
              <td>{{ s.destination || '—' }}</td>
              <td>{{ formatDate(s.etd) }}</td>
              <td>{{ formatDate(s.eta) }}</td>
              <td>{{ s.operator_first_name }} {{ s.operator_last_name }}</td>
              <td class="text-right">
                <VChip v-if="+s.exception_count > 0" color="error" size="small">{{ s.exception_count }}</VChip>
                <span v-else class="text-disabled">—</span>
              </td>
            </tr>
          </tbody>
        </VTable>
      </VCard>
    </template>
  </VContainer>
</template>
```

- [ ] **Step 2: Verify build compiles**

```bash
npm run build 2>&1 | tail -5
```
Expected: no errors, `✓ built in ...`

- [ ] **Step 3: Commit**

```bash
git add src/pages/dashboard.vue
git commit -m "feat: replace placeholder dashboard with operational shipment dashboard"
```

---

### Task 7: BO — kpi.vue

Working directory: `d:\Projects\make-cargo-client-bo`

**Files:**
- Create: `src/pages/report/kpi.vue`

- [ ] **Step 1: Create the file**

```vue
<script setup>
import ReportAnalyticsService from '@/services/ReportAnalyticsService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const loading = ref(false)
const dateFrom = ref(new Date().toISOString().slice(0, 8) + '01')
const dateTo   = ref(new Date().toISOString().slice(0, 10))
const onTime = ref(null)
const conversion = ref([])
const productivity = ref([])
const dso = ref(null)
const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

async function load() {
  loading.value = true
  const [ot, cv, pr, d] = await Promise.all([
    ReportAnalyticsService.onTimeRate(dateFrom.value, dateTo.value),
    ReportAnalyticsService.conversionRate(dateFrom.value, dateTo.value),
    ReportAnalyticsService.operatorProductivity(dateFrom.value, dateTo.value),
    ReportAnalyticsService.dso(dateFrom.value, dateTo.value),
  ])
  onTime.value      = ot
  conversion.value  = cv
  productivity.value = pr
  dso.value         = d
  loading.value     = false
}
onMounted(load)
</script>
<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">KPI Dashboard</h4></VCol>
    </VRow>
    <VRow class="mb-4">
      <VCol cols="12" sm="3">
        <VTextField v-model="dateFrom" type="date" label="From" density="compact" hide-details />
      </VCol>
      <VCol cols="12" sm="3">
        <VTextField v-model="dateTo" type="date" label="To" density="compact" hide-details />
      </VCol>
      <VCol cols="auto">
        <VBtn color="primary" :loading="loading" @click="load">Run</VBtn>
      </VCol>
    </VRow>

    <!-- KPI Summary Cards -->
    <VRow class="mb-6">
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis mb-1">On-Time Rate</div>
            <div
              class="text-h4 font-weight-bold"
              :class="!onTime ? '' : onTime.on_time_pct >= 90 ? 'text-success' : onTime.on_time_pct >= 70 ? 'text-warning' : 'text-error'"
            >
              {{ onTime ? onTime.on_time_pct + '%' : '—' }}
            </div>
            <div class="text-caption text-medium-emphasis" v-if="onTime">
              {{ onTime.on_time }} on-time / {{ onTime.total }} total
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" sm="6" md="3">
        <VCard>
          <VCardText>
            <div class="text-caption text-medium-emphasis mb-1">DSO (Avg Days to Collect)</div>
            <div
              class="text-h4 font-weight-bold"
              :class="!dso ? '' : dso.avg_dso <= 30 ? 'text-success' : dso.avg_dso <= 60 ? 'text-warning' : 'text-error'"
            >
              {{ dso ? dso.avg_dso + ' days' : '—' }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Quote Conversion Rate by Month -->
    <VCard class="mb-6">
      <VCardTitle class="text-subtitle-1 font-weight-semibold pa-4">Quote Conversion by Month</VCardTitle>
      <VTable>
        <thead>
          <tr>
            <th>Month</th>
            <th class="text-right">Total Quotes</th>
            <th class="text-right">Converted (Booked)</th>
            <th class="text-right">Rejected</th>
            <th class="text-right">Conversion %</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="text-center pa-4"><VProgressCircular indeterminate size="24" /></td>
          </tr>
          <tr v-else-if="!conversion.length">
            <td colspan="5" class="text-center text-medium-emphasis pa-4">No data for selected period.</td>
          </tr>
          <tr v-for="r in conversion" :key="r.month">
            <td class="font-weight-medium">{{ r.month }}</td>
            <td class="text-right">{{ r.total }}</td>
            <td class="text-right text-success">{{ r.converted }}</td>
            <td class="text-right text-error">{{ r.rejected }}</td>
            <td class="text-right font-weight-bold">{{ r.conversion_pct }}%</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <!-- Operator Productivity -->
    <VCard>
      <VCardTitle class="text-subtitle-1 font-weight-semibold pa-4">Operator Productivity</VCardTitle>
      <VTable>
        <thead>
          <tr>
            <th>Operator</th>
            <th class="text-right">Jobs Closed</th>
            <th class="text-right">Revenue</th>
            <th class="text-right">Cost</th>
            <th class="text-right">Profit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="5" class="text-center pa-4"><VProgressCircular indeterminate size="24" /></td>
          </tr>
          <tr v-else-if="!productivity.length">
            <td colspan="5" class="text-center text-medium-emphasis pa-4">No completed jobs in this period.</td>
          </tr>
          <tr v-for="r in productivity" :key="r.first_name + r.last_name">
            <td class="font-weight-medium">{{ r.first_name }} {{ r.last_name }}</td>
            <td class="text-right">{{ r.jobs_count }}</td>
            <td class="text-right">{{ fmt(r.revenue_base) }}</td>
            <td class="text-right">{{ fmt(r.cost_base) }}</td>
            <td class="text-right font-weight-bold" :class="+r.profit_base >= 0 ? 'text-success' : 'text-error'">
              {{ fmt(r.profit_base) }}
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Verify build**

```bash
npm run build 2>&1 | tail -5
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/pages/report/kpi.vue
git commit -m "feat: add KPI dashboard page with on-time rate, DSO, conversion, and productivity"
```

---

### Task 8: BO — customer-profitability.vue

Working directory: `d:\Projects\make-cargo-client-bo`

**Files:**
- Create: `src/pages/report/customer-profitability.vue`

- [ ] **Step 1: Create the file**

```vue
<script setup>
import ReportAnalyticsService from '@/services/ReportAnalyticsService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const rows = ref([])
const loading = ref(false)
const dateFrom = ref(new Date().toISOString().slice(0, 8) + '01')
const dateTo   = ref(new Date().toISOString().slice(0, 10))
const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const totals = computed(() => ({
  jobs:    rows.value.reduce((s, r) => s + +r.jobs_count, 0),
  revenue: rows.value.reduce((s, r) => s + +r.revenue_base, 0),
  cost:    rows.value.reduce((s, r) => s + +r.cost_base, 0),
  profit:  rows.value.reduce((s, r) => s + +r.profit_base, 0),
}))
async function load() {
  loading.value = true
  rows.value = await ReportAnalyticsService.customerProfitability(dateFrom.value, dateTo.value)
  loading.value = false
}
onMounted(load)
</script>
<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Customer Profitability</h4></VCol>
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
            <th>Client</th>
            <th class="text-right">Jobs</th>
            <th class="text-right">Revenue</th>
            <th class="text-right">Cost</th>
            <th class="text-right">Profit</th>
            <th class="text-right">Margin %</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center pa-4"><VProgressCircular indeterminate size="24" /></td>
          </tr>
          <tr v-else-if="!rows.length">
            <td colspan="6" class="text-center text-medium-emphasis pa-4">No data for selected period.</td>
          </tr>
          <tr v-for="r in rows" :key="r.client_name">
            <td class="font-weight-medium">{{ r.client_name }}</td>
            <td class="text-right">{{ r.jobs_count }}</td>
            <td class="text-right">{{ fmt(r.revenue_base) }}</td>
            <td class="text-right">{{ fmt(r.cost_base) }}</td>
            <td class="text-right" :class="+r.profit_base >= 0 ? 'text-success' : 'text-error'">{{ fmt(r.profit_base) }}</td>
            <td class="text-right">{{ r.margin_pct }}%</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr class="font-weight-bold bg-surface">
            <td>TOTAL</td>
            <td class="text-right">{{ totals.jobs }}</td>
            <td class="text-right">{{ fmt(totals.revenue) }}</td>
            <td class="text-right">{{ fmt(totals.cost) }}</td>
            <td class="text-right" :class="totals.profit >= 0 ? 'text-success' : 'text-error'">{{ fmt(totals.profit) }}</td>
            <td></td>
          </tr>
        </tfoot>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Verify build**

```bash
npm run build 2>&1 | tail -5
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/pages/report/customer-profitability.vue
git commit -m "feat: add customer profitability report page"
```

---

### Task 9: BO — top-lanes.vue

Working directory: `d:\Projects\make-cargo-client-bo`

**Files:**
- Create: `src/pages/report/top-lanes.vue`

- [ ] **Step 1: Create the file**

```vue
<script setup>
import ReportAnalyticsService from '@/services/ReportAnalyticsService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const rows = ref([])
const loading = ref(false)
const dateFrom = ref(new Date().toISOString().slice(0, 8) + '01')
const dateTo   = ref(new Date().toISOString().slice(0, 10))
const fmt = (v) => Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
async function load() {
  loading.value = true
  rows.value = await ReportAnalyticsService.topLanes(dateFrom.value, dateTo.value)
  loading.value = false
}
onMounted(load)
</script>
<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Top Lanes</h4></VCol>
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
            <th>#</th>
            <th>Origin</th>
            <th>Destination</th>
            <th class="text-right">Shipments</th>
            <th class="text-right">Revenue</th>
            <th class="text-right">Profit</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="6" class="text-center pa-4"><VProgressCircular indeterminate size="24" /></td>
          </tr>
          <tr v-else-if="!rows.length">
            <td colspan="6" class="text-center text-medium-emphasis pa-4">No completed shipments for selected period.</td>
          </tr>
          <tr v-for="(r, i) in rows" :key="i">
            <td class="text-medium-emphasis">{{ i + 1 }}</td>
            <td>{{ r.origin || '—' }}</td>
            <td>{{ r.destination || '—' }}</td>
            <td class="text-right font-weight-bold">{{ r.shipments }}</td>
            <td class="text-right">{{ fmt(r.revenue_base) }}</td>
            <td class="text-right" :class="+r.profit_base >= 0 ? 'text-success' : 'text-error'">{{ fmt(r.profit_base) }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Verify build**

```bash
npm run build 2>&1 | tail -5
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/pages/report/top-lanes.vue
git commit -m "feat: add top lanes report page"
```

---

### Task 10: BO — exception.vue

Working directory: `d:\Projects\make-cargo-client-bo`

**Files:**
- Create: `src/pages/report/exception.vue`

- [ ] **Step 1: Create the file**

```vue
<script setup>
import ReportAnalyticsService from '@/services/ReportAnalyticsService'
definePage({ meta: { action: 'GET', subject: 'EbitNote' } })
const rows = ref([])
const loading = ref(false)
const dateFrom = ref(new Date().toISOString().slice(0, 8) + '01')
const dateTo   = ref(new Date().toISOString().slice(0, 10))
const formatDate = (d) => d ? new Date(d).toLocaleString() : '—'
async function load() {
  loading.value = true
  rows.value = await ReportAnalyticsService.exceptions(dateFrom.value, dateTo.value)
  loading.value = false
}
onMounted(load)
</script>
<template>
  <VContainer fluid>
    <VRow align="center" class="mb-4">
      <VCol><h4 class="text-h5 font-weight-bold">Exception Report</h4></VCol>
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
            <th>Job #</th>
            <th>Milestone</th>
            <th>Origin</th>
            <th>Destination</th>
            <th>Planned</th>
            <th>Actual</th>
            <th class="text-right">Delay (hrs)</th>
            <th>Operator</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="text-center pa-4"><VProgressCircular indeterminate size="24" /></td>
          </tr>
          <tr v-else-if="!rows.length">
            <td colspan="8" class="text-center text-medium-emphasis pa-4">No exceptions for selected period.</td>
          </tr>
          <tr v-for="(r, i) in rows" :key="i">
            <td>
              <RouterLink
                :to="{ name: 'shipment-id-tab1?tab2?', params: { id: r.shipment_code, tab1: 'info', tab2: 'order' } }"
              >
                {{ r.shipment_code }}
              </RouterLink>
            </td>
            <td>{{ r.milestone_code }}</td>
            <td>{{ r.origin || '—' }}</td>
            <td>{{ r.destination || '—' }}</td>
            <td>{{ formatDate(r.planned_date) }}</td>
            <td>{{ formatDate(r.actual_date) }}</td>
            <td class="text-right text-error font-weight-bold">{{ r.exception_hours ?? '—' }}</td>
            <td>{{ r.operator_first_name }} {{ r.operator_last_name }}</td>
          </tr>
        </tbody>
      </VTable>
    </VCard>
  </VContainer>
</template>
```

- [ ] **Step 2: Verify build**

```bash
npm run build 2>&1 | tail -5
```
Expected: no errors.

- [ ] **Step 3: Commit**

```bash
git add src/pages/report/exception.vue
git commit -m "feat: add exception report page"
```

---

### Task 11: BO — Navigation Updates

Working directory: `d:\Projects\make-cargo-client-bo`

**Files:**
- Modify: `src/config/navigation/index.js`

- [ ] **Step 1: Add 4 items inside the Reports children array**

Find the `Reports` section (starts with `title: 'Reports'`). Inside the `children` array, after the existing `Period P&L` entry (last existing child), add:

```js
      {
        title: $gettext('KPI Dashboard'),
        to: { name: 'report-kpi' },
        subject: 'EbitNote',
        action: 'GET',
      },
      {
        title: $gettext('Customer Profitability'),
        to: { name: 'report-customer-profitability' },
        subject: 'EbitNote',
        action: 'GET',
      },
      {
        title: $gettext('Top Lanes'),
        to: { name: 'report-top-lanes' },
        subject: 'EbitNote',
        action: 'GET',
      },
      {
        title: $gettext('Exceptions'),
        to: { name: 'report-exception' },
        subject: 'EbitNote',
        action: 'GET',
      },
```

The updated `children` array in the Reports section should look like:

```js
    children: [
      { title: $gettext('Dataset'), to: { name: 'report-dataset' }, subject: 'Dataset', action: 'Manage' },
      { title: $gettext('Shipment Report'), to: { name: 'report-shipment' }, subject: 'Report', action: 'Manage' },
      { title: $gettext('Staff Report'), to: { name: 'report-staff' }, subject: 'Report', action: 'Manage' },
      { title: $gettext('Charge Report'), to: { name: 'report-charge' }, subject: 'Report', action: 'Manage' },
      { title: $gettext('AR Ageing'), to: { name: 'accounting-ageing-ar' }, subject: 'EbitNote', action: 'GET' },
      { title: $gettext('AP Ageing'), to: { name: 'accounting-ageing-ap' }, subject: 'EbitNote', action: 'GET' },
      { title: $gettext('Period P&L'), to: { name: 'accounting-pnl-period' }, subject: 'EbitNote', action: 'GET' },
      { title: $gettext('KPI Dashboard'), to: { name: 'report-kpi' }, subject: 'EbitNote', action: 'GET' },
      { title: $gettext('Customer Profitability'), to: { name: 'report-customer-profitability' }, subject: 'EbitNote', action: 'GET' },
      { title: $gettext('Top Lanes'), to: { name: 'report-top-lanes' }, subject: 'EbitNote', action: 'GET' },
      { title: $gettext('Exceptions'), to: { name: 'report-exception' }, subject: 'EbitNote', action: 'GET' },
    ]
```

- [ ] **Step 2: Verify build**

```bash
npm run build 2>&1 | tail -5
```
Expected: no errors.

- [ ] **Step 3: Commit in BO**

```bash
git add src/config/navigation/index.js
git commit -m "feat: add KPI, profitability, top-lanes, and exception links to Reports nav"
```

---

### Task 12: Documentation

Working directory: `d:\Projects\make-cargo-client`

**Files:**
- Create: `docs/guides/reporting.md`

- [ ] **Step 1: Create the guide**

```markdown
# Reporting — Setup & Operations Guide

## Overview

The reporting module provides operational and financial analytics across KPIs, customer
profitability, top trade lanes, milestone exceptions, and a live operational dashboard.
All reports use read-only DBAL raw SQL against existing tables — no additional migrations required.

## Architecture

```
KpiRepository (DBAL raw SQL)
  └─ getOnTimeRate(from, to)          → {total, on_time, delayed, on_time_pct}
  └─ getConversionRate(from, to)      → [{month, total, converted, rejected, conversion_pct}]
  └─ getOperatorProductivity(from, to)→ [{first_name, last_name, jobs_count, revenue_base, cost_base, profit_base}]
  └─ getDso(from, to)                 → float (avg days)

ReportRepository (DBAL raw SQL)
  └─ getCustomerProfitability(from, to) → [{client_name, jobs_count, revenue_base, cost_base, profit_base, margin_pct}]
  └─ getTopLanes(from, to, limit)       → [{origin, destination, shipments, revenue_base, profit_base}]
  └─ getExceptions(from, to)            → [{shipment_code, milestone_code, planned_date, actual_date, exception_hours, ...}]
  └─ getOperationalDashboard()          → {active_count, exception_count, shipments: [...]}
```

## API Endpoints

All endpoints require `ROLE_USER`.

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/report/kpi/on-time-rate` | GET | Shipment on-time delivery rate |
| `GET /api/report/kpi/conversion-rate` | GET | Quote-to-booking conversion by month |
| `GET /api/report/kpi/operator-productivity` | GET | Closed jobs + revenue per account manager |
| `GET /api/report/kpi/dso` | GET | Average days sales outstanding |
| `GET /api/report/analytics/customer-profitability` | GET | Revenue/cost/profit by client |
| `GET /api/report/analytics/top-lanes` | GET | Top origin-destination pairs by volume |
| `GET /api/report/analytics/exceptions` | GET | Milestone exceptions list |
| `GET /api/report/analytics/operational-dashboard` | GET | Live active shipment summary |

### Query Parameters

All date-range endpoints accept:
- `from` (YYYY-MM-DD, default: first day of current month)
- `to` (YYYY-MM-DD, default: today)

`/top-lanes` additionally accepts:
- `limit` (integer, default: 20, max: 100)

### Example: On-Time Rate

```
GET /api/report/kpi/on-time-rate?from=2026-01-01&to=2026-06-30

{
  "total": 142,
  "on_time": 118,
  "delayed": 24,
  "on_time_pct": 83.1
}
```

### Example: Operational Dashboard

```
GET /api/report/analytics/operational-dashboard

{
  "active_count": 23,
  "exception_count": 3,
  "shipments": [
    {
      "id": 101,
      "code": "SHP-2026-0101",
      "status": "AC",
      "origin": "Shanghai",
      "destination": "Los Angeles",
      "etd": "2026-06-25T00:00:00",
      "eta": "2026-07-15T00:00:00",
      "operator_first_name": "John",
      "operator_last_name": "Smith",
      "exception_count": 1
    }
  ]
}
```

## Back-Office Pages

| Page | Route | Description |
|------|-------|-------------|
| Dashboard | `/` | Operational dashboard — active shipments, ETD/ETA, exceptions |
| KPI Dashboard | `/report/kpi` | On-time rate, DSO, conversion by month, operator productivity |
| Customer Profitability | `/report/customer-profitability` | Revenue / cost / margin per client |
| Top Lanes | `/report/top-lanes` | Top 20 origin-destination pairs by shipment count |
| Exception Report | `/report/exception` | All milestone exceptions with delay hours |

All pages except Dashboard have a date-range picker and "Run Report" button.
The Dashboard auto-loads on mount and has a manual refresh button.

## Metric Definitions

| Metric | Definition |
|--------|-----------|
| **On-Time Rate** | % of VESSEL_DEPARTED / FLIGHT_DEPARTED milestones where `actual_date ≤ planned_date` |
| **Conversion Rate** | % of quotes with status `B` (Booked) out of all quotes created in the period |
| **Operator Productivity** | Completed shipments (`status = CO`) grouped by account manager, using `updated_at` as close date |
| **DSO** | `AVG(DATEDIFF(receipt.created_date, invoice.created_date))` for AR invoices (type=ID) linked to receipts (type=RPT) via `parent_note_id` |
| **Customer Profitability** | `SUM(ID amount) - SUM(IC amount)` per client on completed shipments, base currency |
| **Top Lanes** | Completed shipments grouped by `(port_loading_id, port_discharge_id)` |
| **Exceptions** | Shipment milestones where `is_exception = true` (auto-set when `actual_date > planned_date`) |

## Files Created

### Client API (`make-cargo-client`)

| File | What it does |
|------|-------------|
| `src/Repository/KpiRepository.php` | KPI queries (on-time, conversion, productivity, DSO) |
| `src/Repository/ReportRepository.php` | Analytics queries (profitability, top lanes, exceptions, dashboard) |
| `src/Controller/Api/KpiController.php` | 4 GET endpoints under `/report/kpi/` |
| `src/Controller/Api/ReportAnalyticsController.php` | 4 GET endpoints under `/report/analytics/` |

### Client BO (`make-cargo-client-bo`)

| File | What it does |
|------|-------------|
| `src/services/ReportAnalyticsService.js` | All 8 API call wrappers |
| `src/pages/dashboard.vue` | Replaced placeholder — live operational shipment table |
| `src/pages/report/kpi.vue` | KPI dashboard page |
| `src/pages/report/customer-profitability.vue` | Customer profitability table |
| `src/pages/report/top-lanes.vue` | Top lanes table |
| `src/pages/report/exception.vue` | Exceptions table |
| `src/config/navigation/index.js` | Added 4 nav items under Reports |
```

- [ ] **Step 2: Commit the guide**

```bash
git add docs/guides/reporting.md
git commit -m "docs: add reporting setup and operations guide"
```

---

## Self-Review

**Spec coverage:**
- On-time rate → Task 1 (getOnTimeRate) + Task 3 + Task 7 ✓
- Quote conversion rate → Task 1 (getConversionRate) + Task 3 + Task 7 ✓
- Operator productivity → Task 1 (getOperatorProductivity) + Task 3 + Task 7 ✓
- DSO → Task 1 (getDso) + Task 3 + Task 7 ✓
- Customer profitability → Task 2 + Task 4 + Task 8 ✓
- Top lanes → Task 2 + Task 4 + Task 9 ✓
- Exception report → Task 2 + Task 4 + Task 10 ✓
- Operational dashboard → Task 2 + Task 4 + Task 6 ✓
- Navigation → Task 11 ✓
- Guide → Task 12 ✓

**No placeholders found.** All code is complete.

**Type consistency:** `ReportAnalyticsService.js` method names match the Vue page import calls exactly.
