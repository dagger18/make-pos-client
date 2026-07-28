# Feature 7: Task System (ShipmentTask)

## Overview

A structured operational checklist attached to each shipment. Tasks tell the operator exactly what needs to be done before the next milestone can be recorded. Mandatory tasks with a `milestone_gate` field block that milestone from being marked actual until the task is completed. Task lists are auto-generated at shipment creation based on `transport_mode × direction` templates.

Reference: CargoWise task management, Magaya task list, Descartes workflow tasks.

---

## Data Model

```sql
CREATE TABLE shipment_task (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id     INT           NOT NULL REFERENCES shipment(id) ON DELETE CASCADE,
  title           VARCHAR(128)  NOT NULL,
  description     TEXT          DEFAULT NULL,
  task_type       VARCHAR(32)   NOT NULL DEFAULT 'OTHER',   -- see TaskType enum
  assigned_to     INT           DEFAULT NULL REFERENCES user(id) ON DELETE SET NULL,
  due_date        DATETIME      DEFAULT NULL,
  completed_at    DATETIME      DEFAULT NULL,
  completed_by    INT           DEFAULT NULL REFERENCES user(id) ON DELETE SET NULL,
  is_mandatory    TINYINT(1)    NOT NULL DEFAULT 0,
  milestone_gate  VARCHAR(32)   DEFAULT NULL,   -- MilestoneCode value; blocks milestone until done
  sort_order      INT           NOT NULL DEFAULT 0,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_task_shipment (shipment_id),
  INDEX idx_task_gate     (shipment_id, milestone_gate)
);
```

### TaskType enum

| Code | Description |
|---|---|
| `DOCUMENT` | Collect, issue, or submit a document |
| `BOOKING` | Booking-related action (confirm space, submit SI, VGM) |
| `CUSTOMS` | Customs filing or release |
| `INVOICE` | Issue AR invoice, match AP bill |
| `FOLLOW_UP` | Communication / chasing |
| `TRANSPORT` | Arrange inland haulage, empty release |
| `OTHER` | Free-form task |

---

## Auto-generated Task Templates

When a shipment is created, the system generates a standard task list from the template matching `transport_mode` + `direction`.

### Ocean FCL Export template

| # | Title | Type | Mandatory | Milestone gate |
|---|---|---|---|---|
| 1 | Confirm booking with carrier | BOOKING | Yes | `CARGO_BOOKED` |
| 2 | Collect packing list from shipper | DOCUMENT | Yes | `SI_SUBMITTED` |
| 3 | Collect commercial invoice from shipper | DOCUMENT | Yes | `SI_SUBMITTED` |
| 4 | Submit shipping instruction to carrier | BOOKING | Yes | `SI_SUBMITTED` |
| 5 | Obtain and submit VGM | BOOKING | Yes | `VGM_SUBMITTED` |
| 6 | Issue House Bill of Lading | DOCUMENT | Yes | `ON_BOARD` |
| 7 | File export customs declaration | CUSTOMS | Yes | `ON_BOARD` |
| 8 | Send pre-alert to overseas agent | FOLLOW_UP | Yes | `VESSEL_DEPARTED` |
| 9 | Issue AR invoice to shipper | INVOICE | Yes | `POD_RECEIVED` |

### Ocean FCL Import template

| # | Title | Type | Mandatory | Milestone gate |
|---|---|---|---|---|
| 1 | Request original BL from overseas agent | FOLLOW_UP | Yes | `VESSEL_ARRIVED` |
| 2 | File import customs entry | CUSTOMS | Yes | `CUSTOMS_RELEASED` |
| 3 | Arrange customs clearance | CUSTOMS | Yes | `CUSTOMS_RELEASED` |
| 4 | Arrange inland delivery | TRANSPORT | No | `DELIVERED` |
| 5 | Obtain signed POD from consignee | DOCUMENT | Yes | `POD_RECEIVED` |
| 6 | Issue AR invoice to consignee | INVOICE | Yes | `POD_RECEIVED` |

### Air Export template

| # | Title | Type | Mandatory | Milestone gate |
|---|---|---|---|---|
| 1 | Book space with airline | BOOKING | Yes | `CARGO_BOOKED` |
| 2 | Collect DG declaration (if DG) | DOCUMENT | Conditional | `CARGO_ACCEPTED` |
| 3 | Prepare House Air Waybill | DOCUMENT | Yes | `CARGO_ACCEPTED` |
| 4 | File export customs declaration | CUSTOMS | Yes | `FLIGHT_DEPARTED` |
| 5 | Send flight pre-alert to agent | FOLLOW_UP | Yes | `FLIGHT_DEPARTED` |
| 6 | Issue AR invoice | INVOICE | Yes | `POD_RECEIVED` |

### Road Export template

| # | Title | Type | Mandatory | Milestone gate |
|---|---|---|---|---|
| 1 | Arrange origin pickup | TRANSPORT | Yes | `PICKUP_COMPLETED` |
| 2 | Prepare CMR Waybill | DOCUMENT | Yes | `PICKUP_COMPLETED` |
| 3 | File export customs declaration | CUSTOMS | Yes | `BORDER_CROSSED` |
| 4 | Confirm delivery with consignee | FOLLOW_UP | Yes | `DELIVERED` |
| 5 | Collect signed CMR from driver | DOCUMENT | Yes | `POD_RECEIVED` |

---

## Business Rules

1. When a task with `milestone_gate = 'X'` is incomplete and the operator tries to set `actual_date` on milestone `X`, the API returns 422 with a list of blocking tasks.
2. Only mandatory tasks (`is_mandatory = true`) block milestone recording. Optional tasks can be left open.
3. A task is completed by setting `completed_at` (timestamp). `completed_by` is auto-set to the current user.
4. A completed task can be re-opened by clearing `completed_at`. Re-opening a gated task also clears the milestone's `actual_date` if it was previously recorded (to maintain consistency).
5. Operators can add ad-hoc tasks beyond the auto-generated list at any time.
6. `due_date` on auto-generated tasks is calculated from Booking dates: SI cutoff → task 4 due, ETD → task 6 due, etc. Rules are defined in the template.
7. Overdue tasks (`due_date < now AND completed_at IS NULL`) trigger a notification to `assigned_to` and `accountManager`.

---

## API

```
GET    /shipment/{id}/tasks               — list all tasks (ordered by sort_order)
POST   /shipment/{id}/tasks               — create ad-hoc task
PATCH  /shipment/{id}/tasks/{taskId}      — update task (title, due_date, assigned_to, completed_at)
DELETE /shipment/{id}/tasks/{taskId}      — remove non-mandatory task (mandatory tasks cannot be deleted)
POST   /shipment/{id}/tasks/{taskId}/complete   — mark complete (sets completed_at = now, completed_by = current user)
POST   /shipment/{id}/tasks/{taskId}/reopen     — reopen (clears completed_at, completed_by)
```

### POST body (create ad-hoc)

```json
{
  "title": "Follow up with carrier for ETD confirmation",
  "description": "Call agent at least 48h before planned ETD",
  "taskType": "FOLLOW_UP",
  "assignedTo": 5,
  "dueDate": "2026-07-10T17:00:00Z",
  "isMandatory": false,
  "milestoneGate": null
}
```

### GET response (task list)

```json
[
  {
    "id": 1,
    "title": "Confirm booking with carrier",
    "taskType": "BOOKING",
    "assignedTo": { "id": 5, "name": "Tran Thi B" },
    "dueDate": "2026-07-05T17:00:00Z",
    "completedAt": "2026-07-04T10:23:00Z",
    "completedBy": { "id": 5, "name": "Tran Thi B" },
    "isMandatory": true,
    "milestoneGate": "CARGO_BOOKED",
    "isOverdue": false
  },
  {
    "id": 2,
    "title": "Collect packing list from shipper",
    "taskType": "DOCUMENT",
    "assignedTo": { "id": 5, "name": "Tran Thi B" },
    "dueDate": "2026-07-12T17:00:00Z",
    "completedAt": null,
    "completedBy": null,
    "isMandatory": true,
    "milestoneGate": "SI_SUBMITTED",
    "isOverdue": false
  }
]
```

---

## BO UI

### Tasks tab in ShipmentDetail

**Layout:** Grouped by `milestone_gate` heading, then ungrouped tasks at the bottom.

Each task row:
- Checkbox (click = complete / reopen)
- Title (inline editable)
- Type chip
- Assigned to avatar + name
- Due date (editable)
- Status chip: Pending / Completed / Overdue

**Milestone gate group header:**
- Shows the gate milestone name (e.g. "Before: ON_BOARD")
- Shows a lock icon if the gate is blocked (mandatory tasks incomplete)
- Shows a green check when all mandatory tasks in this gate are done

**Toolbar:**
- "Add Task" button → inline row insertion
- Filter: All / Pending / Overdue / Completed
- Assign to me (sets assigned_to = current user on all unassigned tasks)

**Task progress widget** (shown at top):
- `X / Y tasks completed (Z mandatory remaining)`

---

## Migration

```sql
-- MySQL
CREATE TABLE shipment_task (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id    INT NOT NULL,
  title          VARCHAR(128) NOT NULL,
  description    TEXT DEFAULT NULL,
  task_type      VARCHAR(32) NOT NULL DEFAULT 'OTHER',
  assigned_to    INT DEFAULT NULL,
  due_date       DATETIME DEFAULT NULL,
  completed_at   DATETIME DEFAULT NULL,
  completed_by   INT DEFAULT NULL,
  is_mandatory   TINYINT(1) NOT NULL DEFAULT 0,
  milestone_gate VARCHAR(32) DEFAULT NULL,
  sort_order     INT NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_task_shipment (shipment_id),
  INDEX idx_task_gate     (shipment_id, milestone_gate),
  CONSTRAINT FK_task_shipment    FOREIGN KEY (shipment_id) REFERENCES shipment(id) ON DELETE CASCADE,
  CONSTRAINT FK_task_assigned    FOREIGN KEY (assigned_to) REFERENCES user(id) ON DELETE SET NULL,
  CONSTRAINT FK_task_completed   FOREIGN KEY (completed_by) REFERENCES user(id) ON DELETE SET NULL
);

-- SQLite
CREATE TABLE shipment_task (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  shipment_id    INTEGER NOT NULL,
  title          TEXT NOT NULL,
  description    TEXT DEFAULT NULL,
  task_type      TEXT NOT NULL DEFAULT 'OTHER',
  assigned_to    INTEGER DEFAULT NULL,
  due_date       DATETIME DEFAULT NULL,
  completed_at   DATETIME DEFAULT NULL,
  completed_by   INTEGER DEFAULT NULL,
  is_mandatory   INTEGER NOT NULL DEFAULT 0,
  milestone_gate TEXT DEFAULT NULL,
  sort_order     INTEGER NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME DEFAULT NULL
);
```

---

## Reference: Industry Patterns

- **CargoWise One** auto-generates a workflow task list from the job type. Each task has a due date, assignee, and a "gate" that prevents the next workflow step. Tasks appear in the operator's personal task dashboard across all jobs.
- **Magaya** has a checklist panel on each shipment with configurable task templates per mode. Uncompleted tasks show as warnings on the shipment header.
- **Descartes** uses a "Job Workflow" system where tasks are linked to milestones in a dependency graph. Completing a task can auto-trigger the next task's creation.
- **Flexport** gives customers visibility into a simplified version of the task list (showing only customer-facing items like "Submit packing list by Jul 12") via their customer portal.
