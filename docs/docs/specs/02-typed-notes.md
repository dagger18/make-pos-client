# Feature 2: Typed Notes (ShipmentNote)

## Overview

Replaces the single `note` TEXT field on `Shipment` with a proper `ShipmentNote` entity supporting multiple notes per shipment, typed by audience (INTERNAL / CUSTOMER / AGENT / SYSTEM), with a pin feature and visibility control. This matches how CargoWise eAdaptor, Descartes, and Magaya implement job notes — a threaded, auditable message board attached to the file.

---

## Data Model

```sql
CREATE TABLE shipment_note (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id INT           NOT NULL REFERENCES shipment(id) ON DELETE CASCADE,
  note_type   VARCHAR(16)   NOT NULL,   -- INTERNAL | CUSTOMER | AGENT | SYSTEM
  body        LONGTEXT      NOT NULL,
  is_pinned   TINYINT(1)    NOT NULL DEFAULT 0,
  visible_to  VARCHAR(16)   NOT NULL DEFAULT 'INTERNAL',  -- INTERNAL | CUSTOMER | ALL
  created_by  INT           REFERENCES user(id) ON DELETE SET NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_note_shipment ON shipment_note (shipment_id, created_at DESC);
```

### NoteType enum

| Value | Who writes it | Who sees it |
|---|---|---|
| `INTERNAL` | Ops team | Internal users only |
| `CUSTOMER` | Ops team | Visible in customer portal / emails |
| `AGENT` | Ops team | Shared with overseas agent |
| `SYSTEM` | System (automated) | Everyone — status changes, task completions |

### VisibleTo enum (reuse or extend existing)

| Value | Meaning |
|---|---|
| `INTERNAL` | Internal users only |
| `CUSTOMER` | Shared with shipper/consignee |
| `ALL` | All parties including agent |

---

## Business Rules

1. Only the author or a manager can delete a note. System-generated notes (`SYSTEM` type) cannot be deleted.
2. Pinned notes appear at the top of the list regardless of created_at order. Max 3 pinned notes per shipment.
3. `SYSTEM` notes are auto-created by the application on status transitions, hold/unhold events, and milestone recordings. They are never created via the API directly.
4. `body` must be non-empty (min 1 char after trim).
5. When the single `Shipment.note` field is migrated, existing non-null values become one `INTERNAL` note authored by the system with `created_at = shipment.created_at`.

---

## API

```
GET    /shipment/{id}/notes           — list all notes, ordered: pinned first, then by created_at DESC
POST   /shipment/{id}/notes           — create note
PATCH  /shipment/{id}/notes/{noteId}  — update body or is_pinned (author only)
DELETE /shipment/{id}/notes/{noteId}  — soft delete (author or manager)
```

### POST body

```json
{
  "note_type": "INTERNAL",
  "body": "Carrier has confirmed space. ETD confirmed 15 Jul.",
  "is_pinned": false,
  "visible_to": "INTERNAL"
}
```

### Serializer groups

- `shipment_note:list` → id, noteType, body, isPinned, visibleTo, createdBy (name), createdAt
- `shipment_note:write` → noteType, body, isPinned, visibleTo

---

## BO UI

### Notes tab in ShipmentDetail

- Full-width tab (replaces the single textarea currently in the info tab).
- Notes rendered as a card-per-note feed, newest at top (pinned float to top).
- Each card shows: type badge (colour-coded chip), body text, author name, relative timestamp.
- Type badge colours: INTERNAL=grey, CUSTOMER=blue, AGENT=orange, SYSTEM=purple.
- **Compose area** at the top: textarea + type selector + visible_to selector + Pin toggle + Submit.
- **Pin icon** on each card — click to toggle pin (author or manager).
- **Delete icon** on hoverable cards — confirmation dialog before delete.
- SYSTEM notes are rendered with a robot icon and no delete option.

### Chip colours

```
INTERNAL  → grey  (default)
CUSTOMER  → blue
AGENT     → orange / amber
SYSTEM    → purple (auto-generated)
```

---

## Migration

### Schema

```sql
-- MySQL
CREATE TABLE shipment_note (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id INT NOT NULL,
  note_type   VARCHAR(16) NOT NULL DEFAULT 'INTERNAL',
  body        LONGTEXT NOT NULL,
  is_pinned   TINYINT(1) NOT NULL DEFAULT 0,
  visible_to  VARCHAR(16) NOT NULL DEFAULT 'INTERNAL',
  created_by  INT DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_note_shipment (shipment_id, created_at),
  CONSTRAINT FK_note_shipment FOREIGN KEY (shipment_id) REFERENCES shipment(id) ON DELETE CASCADE,
  CONSTRAINT FK_note_user FOREIGN KEY (created_by) REFERENCES user(id) ON DELETE SET NULL
);

-- Migrate existing Shipment.note values
INSERT INTO shipment_note (shipment_id, note_type, body, visible_to, created_at)
SELECT id, 'INTERNAL', note, 'INTERNAL', created_at
FROM shipment
WHERE note IS NOT NULL AND TRIM(note) != '';

-- Drop old column (separate migration after verification)
-- ALTER TABLE shipment DROP COLUMN note;
```

---

## Reference: Industry Patterns

- **CargoWise One** has a `Notes` section on every job with note types: Internal Memo, Customer Note, Agent Note. Notes are append-only with a timestamp and username on every entry.
- **Magaya** has `Comments` with a visibility toggle (Internal / Customer-facing). Pinning is not built-in but frequently requested.
- **Descartes** uses a `Remarks` system with INTERNAL / EXTERNAL classification. System events (status changes) auto-append system remarks.
- **Flexport** shows a "shipment feed" that mixes operational updates, document uploads, and internal notes in a single timeline, filterable by type.
