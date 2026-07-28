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
