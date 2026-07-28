# Tax & VAT — Setup & Operations Guide

## Overview

The Tax/VAT module adds complete tax compliance to the freight-forwarder platform:
- **Tax Rule Library** — country/category/service-specific tax rates with specificity matching
- **Partner Tax Exemptions** — per-partner exemption certificates (zero-rated exports, diplomatic, etc.)
- **Partner Tax Registrations** — GST/VAT registration numbers per country
- **Charge Item Tax Detail** — `taxCode`, `taxRate`, `isZeroRated`, `isExempt`, `isReverseCharge` on every charge line
- **Withholding Tax** — rate, amount, and reference on each invoice
- **VAT Report** — period-based output/input tax breakdown and net payable

## Architecture

```
TaxRule (library)
  └─ countryCode + optional chargeCategory + serviceType + customerType
  └─ taxType + taxCode + taxRate (decimal)
  └─ isReverseCharge / isZeroRated / isExempt flags
  └─ effectiveFrom / effectiveTo

CustomerTaxExemption (per partner)
  └─ partner (Partner FK) + exemptionType + countryCode
  └─ validFrom / validTo + documentUrl
  └─ verifiedBy (User nullable) + verifiedAt

PartnerTaxRegistration (per partner)
  └─ partner (Partner FK) + countryCode + taxType
  └─ registrationNo + isPrimary + effectiveFrom / effectiveTo

ChargeItem additions
  └─ taxCode, taxRate, isZeroRated, isExempt, isReverseCharge

EbitNote additions
  └─ withholdingTaxRate, withholdingTaxAmount, withholdingTaxRef

VatReportRepository (DBAL)
  └─ getOutputTax(from, to) → AR invoices grouped by period+taxCode
  └─ getInputTax(from, to)  → AP invoices grouped by period+taxCode
  └─ getWithholdingTax(from, to) → WHT on invoices
```

## Tax Rule Specificity Matching

When looking up which tax rule applies to a charge line, use `GET /tax-rule/lookup`:

```
GET /api/tax-rule/lookup?countryCode=VN&chargeCategory=Freight&serviceType=OCEAN&customerType=Corporate&date=2026-01-15
```

The system scores rules by how many optional fields they match (chargeCategory = 4 pts, serviceType = 2 pts, customerType = 1 pt) and returns the highest-scoring valid rule. A rule with all three specific fields beats a general rule with all three null.

## Tax Types

| Value | Meaning |
|-------|---------|
| `VAT` | Value Added Tax |
| `GST` | Goods & Services Tax |
| `WHT` | Withholding Tax |
| `EXEMPT` | Exempt category marker |

## Charge Category / Service Type Values

These are free-form strings matched exactly. Recommended conventions:

| Field | Examples |
|-------|---------|
| `chargeCategory` | `Freight`, `Local`, `Customs`, `Service` |
| `serviceType` | `OCEAN`, `AIR`, `ROAD`, `COURIER` |
| `customerType` | `Corporate`, `Individual`, `Diplomatic`, `Export` |

## API Endpoints

### Tax Rules

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/tax-rule` | GET | List all |
| `GET /api/tax-rule/{id}` | GET | Get one |
| `GET /api/tax-rule/lookup` | GET | Find most specific match |
| `POST /api/tax-rule` | POST | Create |
| `PUT /api/tax-rule/{id}` | PUT | Update |
| `DELETE /api/tax-rule/{id}` | DELETE | Delete |

**POST/PUT body:**
```json
{
  "countryCode": "VN",
  "chargeCategory": "Freight",
  "serviceType": "OCEAN",
  "customerType": null,
  "taxType": "VAT",
  "taxCode": "VAT-10",
  "taxRate": 0.10,
  "isReverseCharge": false,
  "isZeroRated": false,
  "isExempt": false,
  "description": "Vietnam VAT 10% on ocean freight",
  "effectiveFrom": "2026-01-01",
  "effectiveTo": null
}
```

### Customer Tax Exemptions

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/partner/{id}/tax-exemption` | GET | List exemptions for partner |
| `POST /api/partner/{id}/tax-exemption` | POST | Create exemption |
| `PUT /api/tax-exemption/{id}` | PUT | Update |
| `DELETE /api/tax-exemption/{id}` | DELETE | Delete |

**POST/PUT body:**
```json
{
  "exemptionType": "ZERO_RATED_EXPORT",
  "countryCode": "VN",
  "exemptionRef": "CERT-2026-001",
  "validFrom": "2026-01-01",
  "validTo": null,
  "documentUrl": "https://example.com/cert.pdf"
}
```

### Partner Tax Registrations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/partner/{id}/tax-registration` | GET | List registrations |
| `POST /api/partner/{id}/tax-registration` | POST | Create |
| `PUT /api/tax-registration/{id}` | PUT | Update |
| `DELETE /api/tax-registration/{id}` | DELETE | Delete |

**POST/PUT body:**
```json
{
  "countryCode": "VN",
  "taxType": "VAT",
  "registrationNo": "0123456789",
  "isPrimary": true,
  "effectiveFrom": "2026-01-01",
  "effectiveTo": null
}
```

### VAT Report

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/report/vat?from=&to=` | GET | VAT report for period |

**Response:**
```json
{
  "outputTax": [
    { "tax_period": "2026-01", "tax_code": "VAT-10", "tax_rate": "0.1000", "taxable_amount": "10000.00", "tax_amount": "1000.00", "invoice_count": 5 }
  ],
  "inputTax": [...],
  "withholdingTax": [...],
  "netVatPayable": 650.00
}
```

## Database Tables

### `tax_rule`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `country_code` | CHAR(2) | ISO 3166-1 alpha-2 |
| `charge_category` | VARCHAR(16) | null = applies to all |
| `service_type` | VARCHAR(16) | null = applies to all |
| `customer_type` | VARCHAR(32) | null = applies to all |
| `tax_type` | VARCHAR(16) | VAT, GST, WHT, EXEMPT |
| `tax_code` | VARCHAR(16) | e.g. VAT-10, GST-0 |
| `tax_rate` | NUMERIC(6,4) | decimal e.g. 0.1000 = 10% |
| `is_reverse_charge` | BOOL | |
| `is_zero_rated` | BOOL | |
| `is_exempt` | BOOL | |
| `description` | VARCHAR(128) | optional |
| `effective_from` | DATE | |
| `effective_to` | DATE | null = open-ended |

### `customer_tax_exemption`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `partner_id` | INT FK | `partner.id` ON DELETE CASCADE |
| `exemption_type` | VARCHAR(32) | e.g. ZERO_RATED_EXPORT, DIPLOMATIC |
| `country_code` | CHAR(2) | |
| `exemption_ref` | VARCHAR(64) | Certificate number |
| `valid_from` | DATE | |
| `valid_to` | DATE | null = open-ended |
| `document_url` | TEXT | Link to uploaded certificate |
| `verified_by_id` | INT FK | `user.id` ON DELETE SET NULL |
| `verified_at` | DATE | |

### `partner_tax_registration`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `partner_id` | INT FK | `partner.id` ON DELETE CASCADE |
| `country_code` | CHAR(2) | |
| `tax_type` | VARCHAR(16) | VAT, GST, WHT |
| `registration_no` | VARCHAR(64) | |
| `is_primary` | BOOL | |
| `effective_from` | DATE | |
| `effective_to` | DATE | null = open-ended |

### `charge_item` additions

| Column | Type | Notes |
|--------|------|-------|
| `tax_code` | VARCHAR(16) | null = no tax |
| `tax_rate` | NUMERIC(6,4) | 0.0000 default |
| `is_zero_rated` | BOOL | |
| `is_exempt` | BOOL | |
| `is_reverse_charge` | BOOL | |

### `ebit_note` additions

| Column | Type | Notes |
|--------|------|-------|
| `withholding_tax_rate` | NUMERIC(6,4) | null = no WHT |
| `withholding_tax_amount` | NUMERIC(20,6) | amount withheld |
| `withholding_tax_ref` | VARCHAR(64) | WHT certificate number |

## VAT Report Logic

The report query uses `note_date` (not `created_date`) to determine the tax period, matching typical tax authority requirements. Only invoices with status `S` (Sent), `A` (Active), or `D` (Done) are included — pending invoices are excluded.

- **Output Tax**: AR invoices (`type = 'ID'`) with non-exempt charge lines
- **Input Tax**: AP invoices (`type = 'IC'`) with non-exempt charge lines
- **Withholding Tax**: Any invoice with `withholding_tax_amount` set

## Files Created / Modified

### Client API (`make-cargo-client`)

| File | What |
|------|------|
| `migrations/mysql/Version20260624290000.php` | New — `tax_rule` table |
| `migrations/sqlite/Version20260624290000.php` | New — SQLite |
| `migrations/mysql/Version20260624300000.php` | New — `customer_tax_exemption` table |
| `migrations/sqlite/Version20260624300000.php` | New — SQLite |
| `migrations/mysql/Version20260624310000.php` | New — `partner_tax_registration` table |
| `migrations/sqlite/Version20260624310000.php` | New — SQLite |
| `migrations/mysql/Version20260624320000.php` | New — ALTER charge_item (5 columns) |
| `migrations/sqlite/Version20260624320000.php` | New — SQLite |
| `migrations/mysql/Version20260624330000.php` | New — ALTER ebit_note (3 columns) |
| `migrations/sqlite/Version20260624330000.php` | New — SQLite |
| `src/Entity/TaxRule.php` | New |
| `src/Repository/TaxRuleRepository.php` | New |
| `src/Entity/CustomerTaxExemption.php` | New |
| `src/Repository/CustomerTaxExemptionRepository.php` | New |
| `src/Entity/PartnerTaxRegistration.php` | New |
| `src/Repository/PartnerTaxRegistrationRepository.php` | New |
| `src/Entity/ChargeItem.php` | Modified — 5 tax fields |
| `src/Entity/EbitNote.php` | Modified — 3 withholding fields |
| `src/Controller/Api/TaxRuleController.php` | New |
| `src/Controller/Api/CustomerTaxExemptionController.php` | New |
| `src/Controller/Api/PartnerTaxRegistrationController.php` | New |
| `src/Repository/VatReportRepository.php` | New |
| `src/Controller/Api/VatReportController.php` | New |
| `config/services.yaml` | Modified — VatReportRepository registered |

### Client BO (`make-cargo-client-bo`)

| File | What |
|------|------|
| `src/services/TaxRuleService.js` | New |
| `src/services/CustomerTaxExemptionService.js` | New |
| `src/services/PartnerTaxRegistrationService.js` | New |
| `src/services/VatReportService.js` | New |
| `src/pages/library/tax-rule.vue` | New |
| `src/pages/library/tax-exemption.vue` | New |
| `src/pages/library/tax-registration.vue` | New |
| `src/pages/report/vat-report.vue` | New |
| `src/config/navigation/index.js` | Modified — 4 new nav entries |
