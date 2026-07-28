# Freight Forwarder SaaS — Carrier Rate Import

## 1. Why Rate Import Matters

Freight forwarders receive rate cards from dozens of carriers, co-loaders, and airline GSAs — typically as Excel files, PDFs, or increasingly via API. Without an import pipeline, every rate must be manually entered into the rate card tables, which is time-consuming and error-prone, and means rates are often not updated when they change.

A rate import system turns rate maintenance from a weekly manual task into a near-automated process.

---

## 2. Import Sources

| Source | Format | Frequency | Automation |
|---|---|---|---|
| Carrier Excel rate sheet | .xlsx | Weekly / monthly | Semi-automated (template parser) |
| Carrier API rate feed | JSON / REST | Real-time | Fully automated |
| Ocean aggregator (WebCargo / Inttra) | API | Real-time | Fully automated |
| Air GSA / airline | Excel / PDF | Weekly | Semi-automated |
| Co-loader (LCL) | Excel | Monthly | Semi-automated |
| Internal pricing team | Excel upload | Ad hoc | Semi-automated |

---

## 3. Rate Import Job Table

Every import attempt is tracked as a job — providing an audit trail and enabling rollback.

```sql
CREATE TABLE rate_import_job (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  import_source     VARCHAR(32)   NOT NULL,   -- EXCEL / CARRIER_API / WEBCARGO / INTTRA / MANUAL
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  transport_mode    VARCHAR(8)    NOT NULL,
  file_name         VARCHAR(255),
  file_url          TEXT,
  status            VARCHAR(16)   NOT NULL DEFAULT 'PENDING',
  -- PENDING / PARSING / VALIDATING / PREVIEW / APPROVED / IMPORTING / COMPLETED / FAILED / ROLLED_BACK

  -- Counts
  total_rows        INT           NOT NULL DEFAULT 0,
  rows_imported     INT           NOT NULL DEFAULT 0,
  rows_skipped      INT           NOT NULL DEFAULT 0,
  rows_errored      INT           NOT NULL DEFAULT 0,

  -- Rate card coverage
  effective_date    DATE,
  expiry_date       DATE,
  currency          CHAR(3),

  -- Approval
  requires_approval BOOLEAN       NOT NULL DEFAULT true,
  approved_by       UUID          REFERENCES app_user(id),
  approved_at       TIMESTAMPTZ,

  -- Rollback
  can_rollback      BOOLEAN       NOT NULL DEFAULT true,
  rolled_back_by    UUID          REFERENCES app_user(id),
  rolled_back_at    TIMESTAMPTZ,

  -- Audit
  uploaded_by       UUID          REFERENCES app_user(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now(),
  completed_at      TIMESTAMPTZ,
  error_log         JSONB
);
```

---

## 4. Excel Rate Sheet Parser

Most carrier Excel rate sheets follow similar structures, but each carrier formats them differently. The parser is template-driven — a mapping configuration tells it where to find each field.

```sql
CREATE TABLE rate_sheet_template (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  carrier_id        UUID          NOT NULL REFERENCES organisation(id),
  transport_mode    VARCHAR(8)    NOT NULL,
  template_name     VARCHAR(128)  NOT NULL,
  sheet_name        VARCHAR(64),              -- which Excel tab to read
  header_row        SMALLINT      NOT NULL DEFAULT 1,
  data_start_row    SMALLINT      NOT NULL DEFAULT 2,
  column_mapping    JSONB         NOT NULL,   -- field name → column letter or index
  date_format       VARCHAR(32)   DEFAULT 'DD/MM/YYYY',
  is_active         BOOLEAN       NOT NULL DEFAULT true
);
```

### Column mapping example (JSONB)

```json
{
  "pol":           {"col": "A", "type": "port_code"},
  "pod":           {"col": "B", "type": "port_code"},
  "effective_date":{"col": "C", "type": "date"},
  "expiry_date":   {"col": "D", "type": "date"},
  "currency":      {"col": "E", "type": "string"},
  "20GP":          {"col": "F", "type": "decimal"},
  "40GP":          {"col": "G", "type": "decimal"},
  "40HC":          {"col": "H", "type": "decimal"},
  "40RF":          {"col": "I", "type": "decimal", "optional": true},
  "transit_days":  {"col": "J", "type": "integer", "optional": true},
  "via":           {"col": "K", "type": "port_code", "optional": true}
}
```

### Parser pipeline

```python
def parse_rate_sheet(import_job_id: str, file_path: str, template_id: str) -> list[dict]:
    template = fetch_template(template_id)
    workbook = openpyxl.load_workbook(file_path, data_only=True)
    sheet    = workbook[template.sheet_name or workbook.active.title]

    rows = []
    errors = []

    for row_num in range(template.data_start_row, sheet.max_row + 1):
        try:
            row_data = extract_row(sheet, row_num, template.column_mapping)
            if is_empty_row(row_data):
                continue

            validated = validate_rate_row(row_data, template)
            rows.append(validated)

        except ValidationError as e:
            errors.append({"row": row_num, "error": str(e), "data": row_data})

    update_import_job(import_job_id,
                      total_rows=len(rows) + len(errors),
                      rows_errored=len(errors),
                      error_log=errors)
    return rows
```

---

## 5. Validation Rules

Before any rate is inserted into the rate card tables, it passes through validation:

| Check | Rule |
|---|---|
| Port code valid | `pol_code` and `pod_code` exist in `location` table |
| Date range valid | `effective_date < expiry_date` |
| Rate positive | All rate values > 0 |
| Currency valid | Currency code exists in `currency` table |
| Duplicate check | No existing active rate card for same pol/pod/carrier/container_type/effective_date |
| Rate sanity | Rate is within ±50% of previous rate for same lane (flag if outside, do not reject) |

---

## 6. Preview Before Import

Before rates are applied to the database, the operator sees a preview showing:
- How many new rate cards will be created
- Which existing rate cards will be expired (superseded)
- Any rates that differ significantly from the current active rate (sanity flag)
- Validation errors that prevented specific rows from being parsed

Only after the operator reviews and approves the preview does the import proceed.

```sql
CREATE TABLE rate_import_preview (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  import_job_id     UUID          NOT NULL REFERENCES rate_import_job(id),
  row_number        INT           NOT NULL,
  pol_code          VARCHAR(10),
  pod_code          VARCHAR(10),
  container_type    VARCHAR(8),
  new_rate          NUMERIC(20,6),
  current_rate      NUMERIC(20,6),            -- existing active rate for comparison
  change_pct        NUMERIC(8,4),             -- percentage change from current
  is_sanity_flagged BOOLEAN       NOT NULL DEFAULT false,
  action            VARCHAR(16)   NOT NULL,   -- NEW / UPDATE / EXPIRE / SKIP / ERROR
  error_message     TEXT
);
```

---

## 7. Import Execution

After approval, the importer runs atomically:

```python
def execute_rate_import(import_job_id: str) -> None:
    preview_rows = fetch_approved_preview_rows(import_job_id)
    import_job   = fetch_import_job(import_job_id)

    with db.transaction():
        for row in preview_rows:
            if row.action == 'ERROR':
                continue

            if row.action in ('NEW', 'UPDATE'):
                # Expire any existing active rate card for this lane
                db.execute("""
                    UPDATE rate_card SET expiry_date = :new_effective_date - 1
                    WHERE pol_code     = :pol
                      AND pod_code     = :pod
                      AND carrier_id   = :carrier_id
                      AND transport_mode = :mode
                      AND customer_id IS NULL
                      AND expiry_date IS NULL
                """, **row)

                # Insert new rate card
                rate_card_id = insert_rate_card(row, import_job_id)

                # Insert rate card lines
                for container_type in ['20GP', '40GP', '40HC', '40RF', '45HC']:
                    if row.get(container_type):
                        insert_rate_card_line(rate_card_id, container_type, row[container_type])

        update_import_job(import_job_id, status='COMPLETED',
                         rows_imported=len([r for r in preview_rows if r.action != 'ERROR']))
```

---

## 8. Rollback

If a rate import causes problems (wrong rates applied to quotes), it can be rolled back within a configurable window (default: 48 hours).

```python
def rollback_rate_import(import_job_id: str, rolled_back_by: str) -> None:
    import_job = fetch_import_job(import_job_id)

    if not import_job.can_rollback:
        raise RollbackNotAllowedError("Rollback window has expired")

    with db.transaction():
        # Delete all rate cards created by this import
        db.execute("""
            DELETE FROM rate_card_line WHERE rate_card_id IN (
              SELECT id FROM rate_card WHERE import_job_id = ?
            )
        """, import_job_id)

        db.execute("DELETE FROM rate_card WHERE import_job_id = ?", import_job_id)

        # Restore expiry dates that were set by this import
        # (requires storing previous expiry date in the import preview)
        restore_expired_rate_cards(import_job_id)

        update_import_job(import_job_id, status='ROLLED_BACK',
                         rolled_back_by=rolled_back_by)
```

---

## 9. Surcharge Import

Surcharges (BAF, FSC, GRI, PSS) are imported separately from base rates, since they change on a different schedule. The same pipeline applies but targets the `surcharge` table rather than `rate_card_line`.

```
Carrier announces new BAF effective next month
        ↓
Finance team downloads BAF table from carrier website
        ↓
Uploads to surcharge import with template: MAERSK_BAF_TEMPLATE
        ↓
System previews: 47 lanes affected, average increase 8%
        ↓
Approved → all BAF surcharge records updated for next month
```

---

## 10. Golden Rules

1. **Preview before import is mandatory.** Never write rates directly to the database without an operator review step. A wrong rate applied to thousands of quotes causes serious revenue impact.
2. **Imports are audited.** Every import job records who uploaded, who approved, and what changed. Rollback is possible within the configured window.
3. **New rate cards never overwrite old ones.** The old rate card is expired (its `expiry_date` is set to the day before the new effective date). The old rate is preserved in history.
4. **Sanity flags are warnings, not blocks.** A rate that moves more than 50% from the previous is flagged for review — but it can still be imported if the operator confirms it is correct.
5. **Surcharges have their own import pipeline.** Base rates and surcharges change at different frequencies and must be managed separately.
