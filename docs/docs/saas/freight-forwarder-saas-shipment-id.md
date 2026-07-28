# Freight Forwarder SaaS — Shipment ID Design and Configurable Template System

## 1. Why Shipment ID Design Matters

A badly designed shipment ID causes:
- Support headaches when operators cannot tell jobs apart at a glance
- Data entry errors when IDs are too long, too similar, or too cryptic
- Painful log debugging with opaque UUIDs and no business context
- Inability to adapt as the business grows (new branches, new modes, new directions)

A well-designed ID is human-readable, machine-validatable, scoped correctly, and — critically — **the format itself must be changeable without touching code.**

---

## 2. Two Schools of Thought

### Opaque sequential IDs
Pure numbers or random strings. No meaning encoded.

```
SHP-000042317
```

Simple, no coupling between ID and business logic. But operators reading hundreds of job references per day get zero context from the ID itself.

### Structured / smart IDs
Meaning encoded in segments.

```
HCM-EXP-OCN-202604-00123
```

Operators can read branch, direction, and mode at a glance. Almost universally preferred in freight SaaS. The risk: if any segment is wrong at creation time (e.g. cargo re-routed to a different branch), the ID is permanently misleading. Design accordingly — see the UUID primary key rule below.

---

## 3. Recommended Static Format (Baseline)

```
{BRANCH}-{DIRECTION}-{MODE}-{YEARMONTH}-{SEQUENCE}

Example:  HCM-EXP-OCN-202604-00123
```

### Segment definitions

| Segment | Length | Charset | Example values |
|---|---|---|---|
| `BRANCH` | 2–4 chars | A–Z | HCM, HAN, SIN, BKK, SHA |
| `DIRECTION` | 3 chars | Fixed set | EXP, IMP, XTD, DOM, TSH |
| `MODE` | 2–3 chars | Fixed set | OCN, AIR, RD, RAL, COU, MMD |
| `YEARMONTH` | 6 digits | 0–9 | 202604, 202512 |
| `SEQUENCE` | 5 digits | 0–9 (zero-padded) | 00001, 09999, 99999 |

### Direction codes

| Code | Meaning |
|---|---|
| `EXP` | Export |
| `IMP` | Import |
| `XTD` | Cross-trade |
| `DOM` | Domestic |
| `TSH` | Transshipment |

### Mode codes

| Code | Meaning |
|---|---|
| `OCN` | Ocean (FCL or LCL) |
| `AIR` | Air freight |
| `RD` | Road |
| `RAL` | Rail |
| `COU` | Courier |
| `MMD` | Multimodal |

### Validation regex (static format)

```regex
^[A-Z]{2,4}-(?:EXP|IMP|XTD|DOM|TSH)-(?:OCN|AIR|RD|RAL|COU|MMD)-\d{6}-\d{5}$
```

Breaking it down:

```
^                              start of string
[A-Z]{2,4}                    branch: 2–4 uppercase letters
-                              separator
(?:EXP|IMP|XTD|DOM|TSH)       direction: exact values only
-                              separator
(?:OCN|AIR|RD|RAL|COU|MMD)    mode: exact values only
-                              separator
\d{6}                          year+month: exactly 6 digits
-                              separator
\d{5}                          sequence: exactly 5 digits
$                              end of string
```

Valid examples:
```
HCM-EXP-OCN-202604-00001   ✓
SIN-IMP-AIR-202604-00042   ✓
HAN-XTD-OCN-202512-99999   ✓
SHA-DOM-RD-202601-00001    ✓
```

Invalid examples:
```
hcm-EXP-OCN-202604-00001    ✗  lowercase branch
HCM-EXPORT-OCN-202604-00001 ✗  direction too long
HCM-EXP-OCN-2604-00001      ✗  year+month only 4 digits
HCM-EXP-OCN-202604-001      ✗  sequence too short
HCM-EXP-SEA-202604-00001    ✗  SEA is not a valid mode code
```

---

## 4. The Configurable Template System

Rather than hardcoding the format in application logic, the ID format is stored as a **template record in the database**. When the business needs to change the format — new separator, different segment order, longer sequence, date format change — a finance admin updates the template record. No code deployment needed.

### Core concept: token-based templates

The template is a string of named tokens separated by a configurable separator:

```
{BRANCH}-{DIRECTION}-{MODE}-{YEARMONTH}-{SEQ5}
```

Each token maps to a resolver function in the application. The database stores the template string. The application parses the template, resolves each token against the current shipment's attributes, and concatenates the result.

---

## 5. Database Schema

### `id_format_template` — the template registry

```sql
CREATE TABLE id_format_template (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  name            VARCHAR(64)   NOT NULL,           -- "Standard 2026", "Air Only Format"
  template        VARCHAR(255)  NOT NULL,           -- "{BRANCH}-{DIR}-{MODE}-{YYYYMM}-{SEQ5}"
  separator       CHAR(1)       NOT NULL DEFAULT '-',
  scope_type      VARCHAR(16)   NOT NULL DEFAULT 'GLOBAL',  -- GLOBAL / BRANCH / MODE / DIRECTION
  scope_value     VARCHAR(16),                      -- e.g. 'AIR' if scope_type = MODE
  is_active       BOOLEAN       NOT NULL DEFAULT true,
  effective_from  DATE          NOT NULL,           -- when this format takes effect
  effective_to    DATE,                             -- null = open-ended (current format)
  created_by      UUID          REFERENCES app_user(id),
  created_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),
  notes           TEXT                              -- reason for change, audit trail
);

CREATE INDEX idx_ift_active ON id_format_template (scope_type, scope_value, effective_from DESC)
  WHERE is_active = true;
```

### `id_format_token` — the token library

```sql
CREATE TABLE id_format_token (
  token_key       VARCHAR(32)   PRIMARY KEY,   -- the string inside {}: BRANCH, SEQ5, YYYYMM
  description     VARCHAR(128)  NOT NULL,
  output_example  VARCHAR(32)   NOT NULL,      -- what it produces: "HCM", "00001", "202604"
  is_fixed_length BOOLEAN       NOT NULL,
  fixed_length    SMALLINT,                    -- null if variable length
  resolver_fn     VARCHAR(64)   NOT NULL       -- name of the server-side function that resolves it
);
```

Seed data:

| token_key | description | output_example | resolver_fn |
|---|---|---|---|
| `BRANCH` | Branch/station IATA code | HCM | `resolve_branch` |
| `DIR` | Direction code (3 chars) | EXP | `resolve_direction` |
| `DIR2` | Direction code (2 chars) | EX | `resolve_direction_short` |
| `MODE` | Transport mode code | OCN | `resolve_mode` |
| `YYYYMM` | Year and month (6 digits) | 202604 | `resolve_yearmonth` |
| `YYYY` | Year only (4 digits) | 2026 | `resolve_year` |
| `YY` | Year short (2 digits) | 26 | `resolve_year_short` |
| `MM` | Month only (2 digits) | 04 | `resolve_month` |
| `SEQ3` | Sequence, 3-digit zero-padded | 042 | `resolve_sequence(3)` |
| `SEQ4` | Sequence, 4-digit zero-padded | 0042 | `resolve_sequence(4)` |
| `SEQ5` | Sequence, 5-digit zero-padded | 00042 | `resolve_sequence(5)` |
| `SEQ6` | Sequence, 6-digit zero-padded | 000042 | `resolve_sequence(6)` |
| `RAND4` | 4-char random alphanumeric suffix | X7K2 | `resolve_random(4)` |
| `COMPANY` | Company short code | FWD | `resolve_company_code` |
| `CUST` | Customer short code (if set) | IKEA | `resolve_customer_code` |

### `id_sequence_counter` — atomic sequence generation

```sql
CREATE TABLE id_sequence_counter (
  id              UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  template_id     UUID          NOT NULL REFERENCES id_format_template(id),
  scope_key       VARCHAR(128)  NOT NULL,   -- hashed combination of branch+dir+mode+period
  period          VARCHAR(16)   NOT NULL,   -- "202604" or "2026" depending on template's date token
  last_seq        BIGINT        NOT NULL DEFAULT 0,
  updated_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),

  UNIQUE (template_id, scope_key, period)
);
```

`scope_key` is a normalised string of whichever segments are included in the template that affect sequence uniqueness. For example, if the template includes `{BRANCH}`, `{DIR}`, and `{MODE}`, the scope_key would be `HCM|EXP|OCN`. This ensures sequences are always unique within their natural scope.

### `shipment` — stores both the ID and the template used

```sql
ALTER TABLE shipment ADD COLUMN shipment_id        VARCHAR(64)  UNIQUE NOT NULL;
ALTER TABLE shipment ADD COLUMN id_format_id       UUID         REFERENCES id_format_template(id);
ALTER TABLE shipment ADD COLUMN id_generated_at    TIMESTAMPTZ  NOT NULL DEFAULT now();
```

`id_format_id` records which template generated this ID — critical for historical audit, and for knowing how to parse an old ID years later.

---

## 6. Token Resolution Logic (Application Layer)

```python
import re
from datetime import date

TOKENS = re.compile(r'\{([A-Z0-9]+)\}')

def generate_shipment_id(template: str, context: dict) -> str:
    """
    template: "{BRANCH}-{DIR}-{MODE}-{YYYYMM}-{SEQ5}"
    context:  { branch, direction, mode, customer_code, company_code, date }
    """
    tokens = TOKENS.findall(template)
    values = {}

    for token in tokens:
        values[token] = resolve_token(token, context)

    result = template
    for token, value in values.items():
        result = result.replace(f'{{{token}}}', value)

    return result


def resolve_token(token: str, ctx: dict) -> str:
    today = ctx.get('date', date.today())

    resolvers = {
        'BRANCH':  lambda: ctx['branch'].upper(),
        'DIR':     lambda: ctx['direction'][:3].upper(),
        'DIR2':    lambda: ctx['direction'][:2].upper(),
        'MODE':    lambda: ctx['mode'][:3].upper(),
        'YYYYMM':  lambda: today.strftime('%Y%m'),
        'YYYY':    lambda: today.strftime('%Y'),
        'YY':      lambda: today.strftime('%y'),
        'MM':      lambda: today.strftime('%m'),
        'SEQ3':    lambda: get_next_sequence(ctx, width=3),
        'SEQ4':    lambda: get_next_sequence(ctx, width=4),
        'SEQ5':    lambda: get_next_sequence(ctx, width=5),
        'SEQ6':    lambda: get_next_sequence(ctx, width=6),
        'RAND4':   lambda: generate_random(4),
        'COMPANY': lambda: ctx['company_code'].upper(),
        'CUST':    lambda: (ctx.get('customer_code') or 'GEN').upper(),
    }

    if token not in resolvers:
        raise ValueError(f"Unknown token: {token}")

    return resolvers[token]()


def get_next_sequence(ctx: dict, width: int) -> str:
    """Atomically increment the counter for this scope+period."""
    scope_key = build_scope_key(ctx)
    period    = ctx.get('date', date.today()).strftime('%Y%m')

    # Single atomic SQL: INSERT ... ON CONFLICT DO UPDATE ... RETURNING last_seq
    seq = db.execute("""
        INSERT INTO id_sequence_counter (template_id, scope_key, period, last_seq)
        VALUES (:template_id, :scope_key, :period, 1)
        ON CONFLICT (template_id, scope_key, period)
        DO UPDATE SET
          last_seq   = id_sequence_counter.last_seq + 1,
          updated_at = now()
        RETURNING last_seq
    """, template_id=ctx['template_id'], scope_key=scope_key, period=period).scalar()

    if seq > (10 ** width) - 1:
        raise SequenceExhaustedError(
            f"Sequence exhausted for scope {scope_key} period {period} at width {width}"
        )

    return str(seq).zfill(width)


def build_scope_key(ctx: dict) -> str:
    """Normalise scope segments into a consistent key."""
    parts = [
        ctx.get('branch', ''),
        ctx.get('direction', ''),
        ctx.get('mode', ''),
    ]
    return '|'.join(p.upper() for p in parts if p)
```

---

## 7. Template Lookup — Which Template Applies?

When a new shipment is created, the system must find the correct active template. Priority order:

```
1. Most specific scope first:
   scope_type = MODE     AND scope_value = shipment.mode
   scope_type = DIRECTION AND scope_value = shipment.direction
   scope_type = BRANCH   AND scope_value = shipment.branch
   scope_type = GLOBAL

2. Within same scope_type, prefer:
   effective_from <= today  (most recent effective_from wins)
   effective_to IS NULL OR effective_to >= today
```

```sql
SELECT *
FROM id_format_template
WHERE is_active = true
  AND effective_from <= CURRENT_DATE
  AND (effective_to IS NULL OR effective_to >= CURRENT_DATE)
  AND (
    (scope_type = 'MODE'      AND scope_value = :mode)      OR
    (scope_type = 'DIRECTION' AND scope_value = :direction)  OR
    (scope_type = 'BRANCH'    AND scope_value = :branch)     OR
    (scope_type = 'GLOBAL')
  )
ORDER BY
  CASE scope_type
    WHEN 'MODE'      THEN 1
    WHEN 'DIRECTION' THEN 2
    WHEN 'BRANCH'    THEN 3
    WHEN 'GLOBAL'    THEN 4
  END,
  effective_from DESC
LIMIT 1;
```

This means you can have a global default format, override it for air freight (`scope_type = MODE, scope_value = AIR`), and further override it for a specific branch (`scope_type = BRANCH, scope_value = HAN`). All coexist without conflict.

---

## 8. Changing the Format — The Safe Workflow

Never change an existing template record. Instead, **close the old one and create a new one**. This preserves the exact format that generated every historical ID.

```sql
-- Step 1: Close the current active template
UPDATE id_format_template
SET effective_to = '2026-06-30'
WHERE is_active = true
  AND scope_type = 'GLOBAL'
  AND effective_to IS NULL;

-- Step 2: Insert the new template, effective from the next day
INSERT INTO id_format_template (
  name, template, separator, scope_type,
  effective_from, effective_to, notes
) VALUES (
  'Global format v2 — longer sequence',
  '{BRANCH}-{DIR}-{MODE}-{YYYY}{MM}-{SEQ6}',
  '-',
  'GLOBAL',
  '2026-07-01',
  NULL,
  'Sequence expanded to 6 digits due to HCM volume growth'
);
```

From July 1, all new shipments get `HCM-EXP-OCN-202607-000001`. All shipments created before July 1 keep their original 5-digit format. Both are valid — each is linked to the template that generated it via `id_format_id` on the shipment record.

---

## 9. Dynamic Regex Generation

Because the format is configurable, the validation regex must also be generated dynamically from the active template — not hardcoded.

```python
TOKEN_PATTERNS = {
    'BRANCH':  r'[A-Z]{2,4}',
    'DIR':     r'(?:EXP|IMP|XTD|DOM|TSH)',
    'DIR2':    r'(?:EX|IM|XT|DO|TS)',
    'MODE':    r'(?:OCN|AIR|RD|RAL|COU|MMD)',
    'YYYYMM':  r'\d{6}',
    'YYYY':    r'\d{4}',
    'YY':      r'\d{2}',
    'MM':      r'\d{2}',
    'SEQ3':    r'\d{3}',
    'SEQ4':    r'\d{4}',
    'SEQ5':    r'\d{5}',
    'SEQ6':    r'\d{6}',
    'RAND4':   r'[A-Z0-9]{4}',
    'COMPANY': r'[A-Z]{2,8}',
    'CUST':    r'[A-Z]{2,8}',
}

def build_regex_from_template(template: str, separator: str = '-') -> str:
    """
    '{BRANCH}-{DIR}-{MODE}-{YYYYMM}-{SEQ5}'
    →  '^[A-Z]{2,4}-(?:EXP|IMP|XTD|DOM|TSH)-(?:OCN|AIR|RD|RAL|COU|MMD)-\\d{6}-\\d{5}$'
    """
    escaped_sep = re.escape(separator)
    pattern = re.sub(
        r'\{([A-Z0-9]+)\}',
        lambda m: TOKEN_PATTERNS.get(m.group(1), r'[A-Z0-9]+'),
        template
    )
    return f'^{pattern}$'


def validate_shipment_id(shipment_id: str, template: str, separator: str = '-') -> bool:
    regex = build_regex_from_template(template, separator)
    return bool(re.fullmatch(regex, shipment_id))
```

Usage:

```python
template = '{BRANCH}-{DIR}-{MODE}-{YYYYMM}-{SEQ5}'
regex    = build_regex_from_template(template)
# → '^[A-Z]{2,4}-(?:EXP|IMP|XTD|DOM|TSH)-(?:OCN|AIR|RD|RAL|COU|MMD)-\d{6}-\d{5}$'

validate_shipment_id('HCM-EXP-OCN-202604-00123', template)  # True
validate_shipment_id('HCM-EXP-OCN-202604-123',   template)  # False — SEQ too short
```

To validate a historical ID, look up its `id_format_id`, fetch that template from the database, and generate the regex from it — not the current active template.

---

## 10. Quote ID vs. Job ID

Quotes that do not convert to jobs should be distinguishable at a glance. Common pattern: replace the `{DIR}` segment with a fixed `QTE` prefix, using the same template structure otherwise.

```
Quote:     HCM-QTE-OCN-202604-00045
Job:       HCM-EXP-OCN-202604-00023
```

In the template system, quotes use a separate template with `scope_type = 'QUOTE'` and the `DIR` segment replaced with a literal `QTE`:

```
Quote template:  {BRANCH}-QTE-{MODE}-{YYYYMM}-{SEQ4}
Job template:    {BRANCH}-{DIR}-{MODE}-{YYYYMM}-{SEQ5}
```

Alternatively, use a `PREFIX` token that resolves to `QTE` for quote entities and is omitted (or resolves to `DIR`) for job entities — both controlled by the template string.

---

## 11. The UUID Primary Key Rule

**Never use the shipment ID as the database primary key.** Use a UUID as the internal PK. The shipment ID is a `UNIQUE` business key in a separate column.

```sql
CREATE TABLE shipment (
  id          UUID        PRIMARY KEY DEFAULT gen_random_uuid(),  -- internal PK, never changes
  shipment_id VARCHAR(64) UNIQUE NOT NULL,                         -- human-facing, format-dependent
  ...
);
```

Why this matters: if a shipment is mis-assigned to the wrong branch at creation (e.g. HAN instead of HCM), the ID says `HAN-EXP-OCN-...` forever. With UUID as PK, you can implement a correction workflow that issues a new shipment_id (linked to an amendment record) without cascading FK changes across invoices, charge lines, milestones, documents, and every other related table. The internal UUID stays the same; only the display ID changes.

---

## 12. Database Check Constraint

Store the current active regex in the template record and enforce it at the database level as a belt-and-suspenders guard:

```sql
-- Add a computed/stored regex column to the template
ALTER TABLE id_format_template
ADD COLUMN validation_regex VARCHAR(512) GENERATED ALWAYS AS (
  -- populated by application on insert, not truly generated by DB
  -- use a trigger or application layer instead in most RDBMSs
  NULL
) STORED;

-- Or: enforce via a trigger that validates against the active template
CREATE OR REPLACE FUNCTION validate_shipment_id_format()
RETURNS TRIGGER AS $$
DECLARE
  active_regex TEXT;
BEGIN
  SELECT t.validation_regex INTO active_regex
  FROM id_format_template t
  WHERE t.id = NEW.id_format_id;

  IF active_regex IS NOT NULL AND NEW.shipment_id !~ active_regex THEN
    RAISE EXCEPTION 'Shipment ID % does not match template regex %',
      NEW.shipment_id, active_regex;
  END IF;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validate_shipment_id
BEFORE INSERT ON shipment
FOR EACH ROW EXECUTE FUNCTION validate_shipment_id_format();
```

---

## 13. Template Examples

Several common formats you may want to configure:

| Use case | Template | Example output |
|---|---|---|
| Standard (recommended) | `{BRANCH}-{DIR}-{MODE}-{YYYYMM}-{SEQ5}` | `HCM-EXP-OCN-202604-00123` |
| Year only (lower volume) | `{BRANCH}-{DIR}-{MODE}-{YYYY}-{SEQ4}` | `HCM-EXP-OCN-2026-0123` |
| No mode (simple ops) | `{BRANCH}-{DIR}-{YYYYMM}-{SEQ5}` | `HCM-EXP-202604-00123` |
| With company prefix | `{COMPANY}-{BRANCH}-{DIR}-{YYYYMM}-{SEQ5}` | `FWD-HCM-EXP-202604-00123` |
| Air freight specific | `{BRANCH}-AIR-{YYYYMM}-{SEQ6}` | `HCM-AIR-202604-000123` |
| High-volume branch | `{BRANCH}-{DIR}-{MODE}-{YYYYMM}-{SEQ6}` | `HCM-EXP-OCN-202604-000123` |
| Quote format | `{BRANCH}-QTE-{MODE}-{YYYYMM}-{SEQ4}` | `HCM-QTE-OCN-202604-0045` |
| Customer-tagged | `{CUST}-{BRANCH}-{DIR}-{YYYYMM}-{SEQ5}` | `IKEA-HCM-EXP-202604-00001` |
| Short / internal | `{BRANCH}{YY}{MM}-{SEQ5}` | `HCM2604-00123` |

---

## 14. Summary: The Golden Rules

1. **Store the format in the database, not in code.** A template record is the single source of truth for the ID structure.
2. **Never edit an existing template.** Close it with `effective_to`, create a new one with `effective_from`. The history is permanent.
3. **Link every shipment to the template that generated it.** Enables correct historical parsing and validation years later.
4. **Generate sequences atomically.** Use `INSERT ... ON CONFLICT DO UPDATE ... RETURNING` — never `MAX() + 1`.
5. **UUID is the primary key. Shipment ID is a business key.** These are two different columns with two different purposes.
6. **Generate the validation regex from the template, not hardcoded.** Both the application and the database trigger use the same derived regex.
7. **Scope templates for flexibility.** A global default + mode-specific override + branch-specific override can all coexist, resolved by priority order.
8. **Plan for sequence exhaustion.** With 5 digits you have 99,999 per scope per month. For high-volume branches, use `SEQ6` from the start or design the scope key to split further (e.g. include week number).
