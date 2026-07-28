# Profit Centers — Implementation Design

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Wire up the existing `Department` entity (branch + direction) as the profit center for P&L reporting, add `payableAt` grouping to the cost sheet display, and enforce opt-in `visibleTo` filtering on charge item queries.

**Architecture:** `Department` already IS the profit center — it has `branch`, `direction`, `name`, `isActive`. `ChargeItem` and `QuotePrice` already carry `department`, `payableAt`, and `visibleTo`. The missing pieces are: (1) a department-grouped P&L query + endpoint, (2) `payableAt` surfaced in the cost sheet view, (3) a `visibleTo`-filtered charge item repository method wired into an opt-in query param on the cost sheet endpoint.

**Tech Stack:** PHP/Symfony + Doctrine DBAL raw SQL (consistent with `PnlRepository` pattern), Vue 3 / Vuetify 3, `$api` via `ofetch`

---

## Scope

### What is built

| # | Layer | Deliverable |
|---|-------|-------------|
| 1 | API | `PnlRepository::getDepartmentPnl(from, to)` — GROUP BY department |
| 2 | API | `GET /api/report/profit-loss/department` endpoint |
| 3 | API | `PnlRepository::getJobCostSheet()` extended to include `payable_at` per line |
| 4 | API | `ChargeItemRepository::findByShipmentFiltered(shipmentId, user)` — opt-in `visibleTo` filter |
| 5 | API | `GET /api/report/cost-sheet/{id}` accepts `?myDept=1` to apply opt-in filter |
| 6 | BO  | `PnlService.departmentPnl(from, to)` |
| 7 | BO  | `src/pages/report/department-pnl.vue` — new report page |
| 8 | BO  | `ShipmentCostSheet.vue` — add `payableAt` badge column + `visibleTo` badge |
| 9 | Doc | `docs/guides/profit-centers.md` in client API repo |
| 10 | Doc | Feature matrix updated: Profit Centers → API ✅, BO ✅, Done ✅ |

### What is NOT built (out of scope)

- Changing EbitNote invoice auto-routing by `payableAt` (user chose visual hint only)
- Row-level security (MySQL/SQLite don't support PostgreSQL RLS)
- Separate `ProfitCenter` entity (Department already covers the spec's requirements)
- `user_profit_center_access` permission table (existing role system is sufficient)

---

## API Design

### 1. `PnlRepository::getDepartmentPnl(string $dateFrom, string $dateTo): array`

SQL joins `charge_item → ebit_note → shipment → department → branch`. Groups by `department.id`. Only includes charge items that have a `department_id` set (unallocated items are excluded from department P&L).

```sql
SELECT
    d.id                                                                         AS department_id,
    d.name                                                                       AS department_name,
    COALESCE(b.name, 'No Branch')                                                AS branch_name,
    COALESCE(d.direction, '')                                                    AS direction,
    COUNT(DISTINCT en.shipment_id)                                               AS jobs_count,
    SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS revenue_base,
    SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS cost_base,
    SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
    - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END) AS gross_profit,
    ROUND(
        (SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END)
         - SUM(CASE WHEN en.type='IC' THEN ci.amount_amount / ci.amount_rate ELSE 0 END))
        / NULLIF(SUM(CASE WHEN en.type='ID' THEN ci.amount_amount / ci.amount_rate ELSE 0 END), 0) * 100,
        2
    )                                                                            AS margin_pct
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
```

### 2. `GET /api/report/profit-loss/department`

Added to existing `PnlController`. Query params: `from` (default: first day of current month), `to` (default: today). Returns the array from `getDepartmentPnl`.

```php
#[Route('/profit-loss/department', methods: ['GET'])]
public function departmentPnl(Request $request): JsonResponse
{
    $from = $request->query->get('from', date('Y-m-01'));
    $to   = $request->query->get('to', date('Y-m-d'));
    return $this->json($this->repo->getDepartmentPnl($from, $to));
}
```

### 3. `PnlRepository::getJobCostSheet()` — add `payable_at`

Extend the SELECT and GROUP BY in the existing method to include `ci.payable_at`. The result shape gains a `payableAt` key per line. No other change to the method signature.

```sql
-- Add to SELECT:
ci.payable_at AS payableAt,
COALESCE(d.name, '') AS departmentName,

-- Add to GROUP BY:
ci.payable_at, d.name
```

The `lines` array in the response now contains `payableAt` and `departmentName` per row. The BO renders these as badges.

### 4. `ChargeItemRepository::findByShipmentFiltered(int $shipmentId, User $user): array`

A DQL query on `ChargeItem` that applies opt-in `visibleTo` filtering:

- Gets the user's department directions (e.g. `['EXP', 'IMP']`) from `$user->getDepartments()`
- If the user has ROLE_ADMIN or ROLE_MANAGER, no filter is applied (return all)
- Otherwise: `AND (ci.visibleTo IS NULL OR ci.visibleTo = 'ALL' OR ci.visibleTo IN (:directions))`

`directions` is the set of `direction->value` strings from the user's departments.

### 5. `GET /api/report/cost-sheet/{shipmentId}?myDept=1`

In `PnlController::costSheet()`, check for `?myDept=1`. When present, pass the current user to a new `getJobCostSheetFiltered(shipmentId, user)` variant that adds the `visibleTo` WHERE clause. When absent, use the existing unfiltered query (backward compatible).

---

## BO Design

### 6. `PnlService.departmentPnl(from, to)`

Add to `src/services/PnlService.js`:

```js
departmentPnl(from, to) {
  return $api(`report/profit-loss/department?from=${from}&to=${to}`)
},
```

### 7. `src/pages/report/department-pnl.vue`

New report page. Pattern: identical to `customer-profitability.vue` (date pickers, Run Report button, VTable). Columns:

| Column | Key | Notes |
|--------|-----|-------|
| Department | `department_name` | |
| Branch | `branch_name` | |
| Direction | `direction` | chip: EXP/IMP/XTD/DOM/TSH with colour |
| Jobs | `jobs_count` | right-aligned |
| Revenue | `revenue_base` | formatted number |
| Cost | `cost_base` | formatted number |
| Gross Profit | `gross_profit` | coloured: green if ≥ 0, red if < 0 |
| Margin % | `margin_pct` | `x%` |

Footer row sums Jobs, Revenue, Cost, Gross Profit.

`definePage` meta: `{ action: 'GET', subject: 'EbitNote' }` (same permission as other P&L reports).

### 8. `ShipmentCostSheet.vue` — payableAt + visibleTo badges

The cost sheet table already shows `chargeType`, `chargeName`, `sellBase`, `buyBase`, `marginBase`. Add two columns:

- **Payable At** — chip from `payableAt` value: `ORIGIN` (blue), `DESTINATION` (orange), `BOTH` (purple), empty if null
- **Dept** — text from `departmentName`, shown as a small grey chip, empty if null

The `visibleTo` field is not shown in the cost sheet (it's on individual charge items, not in the cost sheet SQL grouping). It is already visible when editing a charge item via the `ChargeItem.js` form.

No change to the data fetch call — the cost sheet endpoint now returns `payableAt` and `departmentName` per line automatically.

---

## Guide

`docs/guides/profit-centers.md` in the client API repo covers:

1. What a profit center is in this system (Department entity = branch + direction)
2. How to create departments and assign them to branches and directions
3. How to assign `department`, `payableAt`, `visibleTo` on charge items when creating a debit/credit note
4. How to read the Department P&L report
5. How the `visibleTo` opt-in filter works (`?myDept=1` on cost sheet)
6. The `payableAt` values and what each means for invoice routing context

---

## Feature Matrix Update

In `docs/saas/feature-matrix.md`, change the Profit Centers row:

```
| **core** | Profit Centers | ... | Demo | ... | ✅ | ✅ | ✅ | Department entity = profit center; getDepartmentPnl report; payableAt grouping in cost sheet; visibleTo opt-in filter |
```

---

## File Map

| File | Action |
|------|--------|
| `src/Module/Finance/Repository/PnlRepository.php` | Add `getDepartmentPnl()`, extend `getJobCostSheet()` |
| `src/Module/Finance/Controller/PnlController.php` | Add `departmentPnl()` route, extend `costSheet()` for `?myDept=1` |
| `src/Module/Finance/Repository/ChargeItemRepository.php` | Add `findByShipmentFiltered()` |
| `src/services/PnlService.js` (BO) | Add `departmentPnl()` |
| `src/pages/report/department-pnl.vue` (BO) | Create new page |
| `src/views/shipment/ShipmentCostSheet.vue` (BO) | Add payableAt + dept badge columns |
| `docs/guides/profit-centers.md` | Create guide |
| `docs/saas/feature-matrix.md` | Update Profit Centers row |
