# Rate Import Guide

The Rate Import feature allows operators to bulk-import carrier rate cards from Excel files.  
It follows a preview-then-approve workflow and supports rollback within 48 hours.

---

## Excel Template Format

Prepare a `.xlsx` file with headers in **row 1** and data from **row 2** onward.

| Column | Required | Description |
|---|---|---|
| `POL_CODE` | Yes | UN/LOCODE or IATA code of the origin port |
| `POD_CODE` | Yes | UN/LOCODE or IATA code of the destination port |
| `CHARGE_CODE` | Yes | Must match a `Charge.customCode` in the system |
| `CONTAINER_TYPE` | No | e.g. `20DC`, `40DC`, `40HC`, `45HC` — must match a `ContainerType` value |
| `BUYING_RATE` | No | Numeric buying rate |
| `SELLING_RATE` | No | Numeric selling rate |
| `TRANSIT_DAYS` | No | Integer transit time in days |

Column names are case-insensitive. Empty rows are skipped.

---

## API Endpoints

### List all import jobs

```
GET /rate-import
Authorization: Bearer <token>
```

Returns an array of `RateImportJob` objects in `list` serialization group.

---

### Upload and preview

```
POST /rate-import
Authorization: Bearer <token>
Content-Type: multipart/form-data

file          (required) .xlsx file
transportType (required) OCN | AIR | RD | RAL | COU | MMD
effectiveDate (required) YYYY-MM-DD — when new rates become active
expiryDate    (required) YYYY-MM-DD — when new rates expire (or far future for open-ended)
providerId    (optional) Provider ID to associate rates with a specific carrier
currency      (optional, default USD) ISO 4217 code applied to all rows
```

**Response:** `RateImportJob` object. `status` will be `PREVIEW`.

The `rows` field is NOT returned in the list response — fetch it with `GET /rate-import/{id}`.

---

### Get job with preview rows

```
GET /rate-import/{id}
Authorization: Bearer <token>
```

Returns the job object with a `rows` array. Each row has:

| Field | Values |
|---|---|
| `action` | `NEW` — lane not found, will create a new rate |
| | `UPDATE` — existing open-ended rate found, will be expired and replaced |
| | `ERROR` — validation failed (see `errorMessage`) |
| `isSanityFlagged` | `true` if the new buying rate differs from current by more than 50% |
| `changePct` | Percentage change from current buying rate |
| `errorMessage` | Reason for `ERROR` rows |

---

### Approve import

```
POST /rate-import/{id}/approve
Authorization: Bearer <token>
```

Only allowed when `status = PREVIEW`. On success:
- Existing open-ended rates for the same lane (pol/pod/provider/containerType/transportType) have their `validUntil` set to `effectiveDate - 1 day`
- New `Rate` records are created and linked to this import job
- `status` changes to `COMPLETED`

**Response:** updated `RateImportJob` object.

---

### Rollback import

```
POST /rate-import/{id}/rollback
Authorization: Bearer <token>
```

Only allowed when `status = COMPLETED` and `canRollback = true` (within 48 hours).  
On success:
- All `Rate` records created by this import are deleted
- Previously-expired rates have their `validUntil` restored to the pre-import value
- `status` changes to `ROLLED_BACK`

After the 48-hour window, `canRollback` is set to `false` and this endpoint returns `422`.

---

## Validation Rules

| Check | Behaviour |
|---|---|
| POL/POD code exists in Port table | Row is marked `ERROR` |
| BUYING_RATE or SELLING_RATE ≤ 0 | Row is marked `ERROR` |
| Rate changes > 50% from current | Row is marked as sanity flagged but still imported on approve |
| CHARGE_CODE not found at approve time | Row is skipped (`rowsSkipped` incremented) |

---

## Status Lifecycle

```
PENDING → PARSING → PREVIEW → IMPORTING → COMPLETED → ROLLED_BACK
                                         ↘ FAILED
```
