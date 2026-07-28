# HS Code & Tariff Classification Guide

This guide covers the HS Code (Harmonized System) feature in the client API and BO.

---

## Architecture Overview

Four entities manage HS code and tariff data:

| Entity | Table | Purpose |
|--------|-------|---------|
| `HsCode` | `hs_code` | Master code hierarchy with self-referential parent |
| `DutyRate` | `duty_rate` | Import/export duty, VAT, excise rates per code + country pair |
| `HsRestriction` | `hs_restriction` | Trade restrictions and licence requirements |
| `HsVersionMapping` | `hs_version_mapping` | Cross-version code mappings (e.g., HS2017 → HS2022) |

All entities use the `BaseRepository` / `BaseService` / `CrudController` pattern.
`DutyRate`, `HsRestriction`, and `HsVersionMapping` use `EntityDateTimeAbleTrait` for `created_date` / `updated_date` audit columns.

---

## HsCode Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | Auto-generated |
| `code` | `code` | VARCHAR(10) | HS code string e.g. "01011010" |
| `description` | `description` | VARCHAR(500) | Product description |
| `level` | `level` | INT | Hierarchy level |
| `digits` | `digits` | INT | Number of digits in the code |
| `countryCode` | `country_code` | VARCHAR(2) | ISO country code; null = universal |
| `hsVersion` | `hs_version` | VARCHAR(10) | Schedule version e.g. "2022" |
| `isActive` | `is_active` | BOOLEAN | Default true |
| `effectiveFrom` | `effective_from` | DATE | nullable |
| `effectiveTo` | `effective_to` | DATE | nullable |
| `parent` | `parent_id` | INT FK → hs_code | Self-referential; ON DELETE SET NULL |

**Migration:** `Version20260624070000` (MySQL + SQLite)

---

## DutyRate Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | |
| `hsCode` | `hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `importCountry` | `import_country` | VARCHAR(2) | nullable |
| `exportCountry` | `export_country` | VARCHAR(2) | nullable |
| `rateType` | `rate_type` | VARCHAR(50) | MFN / FTA / PREFERENTIAL |
| `ftaName` | `fta_name` | VARCHAR(100) | FTA agreement name |
| `dutyRate` | `duty_rate` | DECIMAL(10,4) | PHP `?string` |
| `vatRate` | `vat_rate` | DECIMAL(10,4) | PHP `?string` |
| `exciseRate` | `excise_rate` | DECIMAL(10,4) | PHP `?string` |
| `effectiveFrom` | `effective_from` | DATE | nullable |
| `effectiveTo` | `effective_to` | DATE | nullable |

**Migration:** `Version20260624080000`

---

## HsRestriction Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | |
| `hsCode` | `hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `countryCode` | `country_code` | VARCHAR(2) | nullable |
| `restrictionType` | `restriction_type` | VARCHAR(50) | PROHIBITED / LICENCE_REQUIRED / QUOTA |
| `authority` | `authority` | VARCHAR(255) | Issuing authority name |
| `licenceType` | `licence_type` | VARCHAR(100) | nullable |
| `effectiveFrom` | `effective_from` | DATE | nullable |
| `effectiveTo` | `effective_to` | DATE | nullable |

**Migration:** `Version20260624090000`

---

## HsVersionMapping Entity

**Fields:**

| Field | DB Column | Type | Notes |
|-------|-----------|------|-------|
| `id` | `id` | INT PK | |
| `oldHsCode` | `old_hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `newHsCode` | `new_hs_code_id` | INT FK → hs_code | CASCADE DELETE |
| `oldVersion` | `old_version` | VARCHAR(10) | e.g. "2017" |
| `newVersion` | `new_version` | VARCHAR(10) | e.g. "2022" |
| `changeType` | `change_type` | VARCHAR(50) | SPLIT / MERGE / RECLASSIFY |

**Migration:** `Version20260624100000`

---

## API Endpoints

### HsCode (`/hs-code`)

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/hs-code` | List with filter params |
| GET | `/hs-code/{id}` | Single record |
| POST | `/hs-code` | Create |
| PUT | `/hs-code` | Update |
| DELETE | `/hs-code/{id}` | Delete |
| GET | `/hs-code/search?q=...&limit=20` | Typeahead search by code/description |
| GET | `/hs-code/browse/{parentId}` | Browse children (parentId=0 for root) |
| POST | `/hs-code/calculate-duty` | Calculate duty breakdown |

**Calculate Duty Request:**
```json
{
  "hsCodeId": 42,
  "importCountry": "VN",
  "exportCountry": "US",
  "customsValue": 10000.00
}
```

**Calculate Duty Response:**
```json
{
  "hsCode": { "id": 42, "code": "01011010", "description": "..." },
  "customsValue": 10000.00,
  "importCountry": "VN",
  "exportCountry": "US",
  "breakdown": [
    {
      "rateType": "MFN",
      "ftaName": null,
      "dutyRate": "5.0000",
      "vatRate": "10.0000",
      "exciseRate": null,
      "dutyAmount": 500.00,
      "vatAmount": 1050.00,
      "exciseAmount": null,
      "totalAmount": 1550.00
    }
  ]
}
```

VAT is applied to `customsValue + dutyAmount`. Excise is applied to `customsValue` only.

### DutyRate, HsRestriction, HsVersionMapping

Standard CRUD at `/duty-rate`, `/hs-restriction`, `/hs-version-mapping`.

---

## BO Pages

| Page | Route | File |
|------|-------|------|
| HS Codes | `/library/hs-code` | `src/pages/library/hs-code.vue` |
| Duty Rates | `/library/duty-rate` | `src/pages/library/duty-rate.vue` |
| HS Restrictions | `/library/hs-restriction` | `src/pages/library/hs-restriction.vue` |

All pages appear under **Library** in the sidebar navigation. Each has a filterable table and a slide-in form for create/edit.

---

## Notes

- DECIMAL fields (`dutyRate`, `vatRate`, `exciseRate`) are PHP `?string` due to Doctrine's DECIMAL → string mapping. Cast with `(float)` before arithmetic.
- The `hsCode` field in DutyRate/HsRestriction forms sends an integer ID; the API's `DoctrineEntityDenormalizer` resolves it to an `HsCode` entity automatically.
- The `browse` endpoint returns root-level codes when `parentId=0`, or children of a given parent — useful for building a hierarchical tree picker in the BO.
