# Cargo Insurance — Setup & Operations Guide

## Overview

The Cargo Insurance module lets freight forwarders manage open-cover and voyage-specific insurance on behalf of their customers. It covers:

- **Insurance Policy** library (open cover & specific voyage)
- **Insurance Certificate** issuance per shipment with automatic premium calculation
- **Insurance Claim** filing and status tracking against a certificate
- **Insurance Declaration** — monthly declarations submitted to the insurer under open-cover policies

---

## Architecture

```
InsurancePolicy (library)
  └─ insurer (Provider), policyType, coverageScope
  └─ premiumBasis (PCT_VALUE / FLAT_RATE) + premiumRate
  └─ maxPerShipment, modesCovered, effectiveFrom / expiryDate

InsuranceCertificate (per shipment)
  └─ links to Shipment (CASCADE delete)
  └─ links to InsurancePolicy (RESTRICT)
  └─ cargoValue → insuredAmount (×1.10) → premiumAmount (via PremiumCalculationService)
  └─ status: ISSUED | CANCELLED | CLAIMED

InsuranceClaim (per certificate)
  └─ links to InsuranceCertificate (RESTRICT)
  └─ claimType: TOTAL_LOSS | PARTIAL_LOSS | DAMAGE | THEFT | DELAY
  └─ workflow: FILED → SURVEYOR_APPOINTED → UNDER_ASSESSMENT → APPROVED / REJECTED → SETTLED
  └─ approvedAmount, deductibleApplied, netSettlement

InsuranceDeclaration (monthly batch)
  └─ links to InsurancePolicy
  └─ groups all certificates issued within a period
  └─ status: DRAFT → SUBMITTED → ACKNOWLEDGED
  └─ InsuranceDeclarationLine — junction to individual certificates

PremiumCalculationService
  └─ insuredAmount = cargoValue × 1.10
  └─ PCT_VALUE:  premium = insuredAmount × premiumRate
  └─ FLAT_RATE:  premium = premiumRate (fixed amount)
  └─ Applies minPremium floor
```

---

## Premium Calculation

Insured amount is always **cargo value × 1.10** (industry CIF+10% convention).

| Basis | Formula | Example |
|---|---|---|
| `PCT_VALUE` | `insuredAmount × premiumRate` | 110,000 × 0.0005 = 55.00 |
| `FLAT_RATE` | `premiumRate` (fixed per cert) | 25.00 per certificate |

Minimum premium is applied after the rate-based calculation if `minPremium` is set.

The `POST /insurance/certificate/calculate-premium` endpoint returns a preview before saving:

```json
// POST /api/insurance/certificate/calculate-premium
{ "policyId": 1, "cargoValue": 100000 }

// Response
{
  "cargoValue": 100000,
  "insuredAmount": 110000,
  "premiumRate": 0.0005,
  "premiumAmount": 55.00,
  "currency": "USD"
}
```

---

## API Endpoints

### Insurance Policies

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/insurance/policy` | GET | List all (add `?active=1` for active only) |
| `GET /api/insurance/policy/{id}` | GET | Get one |
| `POST /api/insurance/policy` | POST | Create |
| `PUT /api/insurance/policy/{id}` | PUT | Update |
| `DELETE /api/insurance/policy/{id}` | DELETE | Delete |

**POST/PUT body:**
```json
{
  "policyNumber": "OC-2026-001",
  "insurerId": 12,
  "policyType": "OPEN_COVER",
  "coverageScope": "ALL_RISK",
  "maxPerShipment": 500000,
  "maxPerConveyance": 2000000,
  "annualLimit": 10000000,
  "currency": "USD",
  "premiumBasis": "PCT_VALUE",
  "premiumRate": 0.0005,
  "minPremium": 25,
  "deductible": 500,
  "modesCovered": ["OCN", "AIR"],
  "effectiveFrom": "2026-01-01",
  "expiryDate": "2026-12-31",
  "isActive": true
}
```

### Insurance Certificates

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/insurance/certificate` | GET | List all (add `?shipmentId=X` for per-shipment) |
| `GET /api/insurance/certificate/{id}` | GET | Get one |
| `POST /api/insurance/certificate/calculate-premium` | POST | Preview premium |
| `POST /api/insurance/certificate` | POST | Issue certificate |
| `PUT /api/insurance/certificate/{id}` | PUT | Update (while ISSUED) |
| `POST /api/insurance/certificate/{id}/cancel` | POST | Cancel certificate |
| `DELETE /api/insurance/certificate/{id}` | DELETE | Delete |

### Insurance Claims

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/insurance/claim` | GET | List all (add `?certificateId=X`) |
| `GET /api/insurance/claim/{id}` | GET | Get one |
| `POST /api/insurance/claim` | POST | File a claim |
| `PUT /api/insurance/claim/{id}` | PUT | Update status / settlement |
| `DELETE /api/insurance/claim/{id}` | DELETE | Delete |

**Claim status progression:**
```
FILED → SURVEYOR_APPOINTED → UNDER_ASSESSMENT → APPROVED → SETTLED
                                              ↘ REJECTED
```

### Insurance Declarations

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /api/insurance/declaration` | GET | List all |
| `POST /api/insurance/declaration` | POST | Generate declaration for a period |
| `POST /api/insurance/declaration/{id}/submit` | POST | Mark as submitted to insurer |
| `POST /api/insurance/declaration/{id}/acknowledge` | POST | Mark as acknowledged by insurer |
| `DELETE /api/insurance/declaration/{id}` | DELETE | Delete (DRAFT only) |

**POST /api/insurance/declaration body:**
```json
{
  "policyId": 1,
  "periodFrom": "2026-06-01",
  "periodTo": "2026-06-30",
  "currency": "USD"
}
```
All certificates issued under `policyId` within the period are automatically included.

---

## Back-Office Features

### Library → Insurance Policies

- Lists all insurance policies with insurer, type, coverage scope, premium basis/rate, modes covered, validity dates, active status
- **New Policy** dialog — full configuration including modes covered (multi-select), premium rate, limits
- Edit / Delete buttons per row

### Shipment Detail → Insurance Tab

Each shipment has an **Insurance** tab (shield icon) for managing certificates on that shipment.

- **Issue Certificate** — opens dialog; select the policy → cargo value → premium is auto-calculated (insuredAmount and premiumAmount populated automatically via `/calculate-premium`)
- Shows: Certificate #, Policy, Insured Name, Cargo Value, Insured Amount, Premium, Issue Date, Status
- **Edit** (pencil) — update while status is `ISSUED`
- **File Claim** (alert icon) — opens claim dialog directly from the certificate row; prefills certificateId and currency
- **Cancel Certificate** (×) — sets status to `CANCELLED`; confirmation dialog
- **Delete** (trash) — removes the certificate record

### Reports → Insurance Declarations

- Lists all declarations with policy, period, certificate count, totals, status
- **New Declaration** — choose policy + date range; system auto-includes all certificates for that period
- **Submit** button — moves `DRAFT` → `SUBMITTED` (records submittedAt timestamp)
- **Acknowledge** button — moves `SUBMITTED` → `ACKNOWLEDGED`
- Delete (DRAFT only)

---

## Database Tables

### `insurance_policy`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `insurer_id` | INT FK | `partner.id` ON DELETE SET NULL |
| `policy_number` | VARCHAR(64) | Unique |
| `policy_type` | VARCHAR(32) | `OPEN_COVER`, `SPECIFIC_VOYAGE`, `LIABILITY` |
| `coverage_scope` | VARCHAR(32) | `ALL_RISK`, `NAMED_PERILS`, `TOTAL_LOSS_ONLY` |
| `max_per_shipment` | NUMERIC(20,6) | |
| `max_per_conveyance` | NUMERIC(20,6) | nullable |
| `annual_limit` | NUMERIC(20,6) | nullable |
| `currency` | VARCHAR(3) | |
| `premium_basis` | VARCHAR(16) | `PCT_VALUE`, `FLAT_RATE` |
| `premium_rate` | NUMERIC(8,6) | Rate or flat amount |
| `min_premium` | NUMERIC(20,6) | nullable |
| `deductible` | NUMERIC(20,6) | nullable |
| `modes_covered` | JSON | `["OCN","AIR"]` |
| `effective_from` | DATE | |
| `expiry_date` | DATE | |
| `is_active` | BOOL | |

### `insurance_certificate`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `shipment_id` | INT FK | `shipment.id` ON DELETE CASCADE |
| `policy_id` | INT FK | `insurance_policy.id` ON DELETE RESTRICT |
| `issued_by_id` | INT FK | `user.id` ON DELETE SET NULL |
| `certificate_number` | VARCHAR(64) | Unique, auto-generated `CERT-{YYYY}-{seq}` |
| `insured_name` | VARCHAR(255) | |
| `beneficiary_name` | VARCHAR(255) | nullable |
| `goods_description` | TEXT | |
| `cargo_value` | NUMERIC(20,6) | CIF value |
| `value_currency` | VARCHAR(3) | |
| `insured_amount` | NUMERIC(20,6) | cargo_value × 1.10 |
| `premium_amount` | NUMERIC(20,6) | Calculated |
| `premium_currency` | VARCHAR(3) | |
| `coverage_scope` | VARCHAR(32) | |
| `status` | VARCHAR(16) | `ISSUED`, `CANCELLED`, `CLAIMED` |
| `issue_date` | DATE | |
| `is_invoiced` | BOOL | Set when added to invoice |

### `insurance_claim`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `certificate_id` | INT FK | `insurance_certificate.id` ON DELETE RESTRICT |
| `shipment_id` | INT FK | `shipment.id` ON DELETE CASCADE |
| `surveyor_id` | INT FK | `partner.id` ON DELETE SET NULL |
| `claim_number` | VARCHAR(64) | Unique, auto-generated `CLM-{YYYY}-{seq}` |
| `claim_type` | VARCHAR(32) | `TOTAL_LOSS`, `PARTIAL_LOSS`, `DAMAGE`, `THEFT`, `DELAY` |
| `status` | VARCHAR(32) | See workflow above |
| `claimed_amount` | NUMERIC(20,6) | |
| `approved_amount` | NUMERIC(20,6) | nullable |
| `deductible_applied` | NUMERIC(20,6) | nullable |
| `net_settlement` | NUMERIC(20,6) | nullable |
| `settled_date` | DATE | nullable |

### `insurance_declaration` / `insurance_declaration_line`

| Column | Type | Notes |
|--------|------|-------|
| `id` | INT PK | |
| `policy_id` | INT FK | `insurance_policy.id` |
| `declaration_ref` | VARCHAR(64) | Unique, auto-generated `DCL-{policyId}-{YYYYMM}-{seq}` |
| `period_from` / `period_to` | DATE | Reporting window |
| `certificate_count` | INT | Total certs included |
| `total_insured_value` | NUMERIC(20,6) | |
| `total_premium` | NUMERIC(20,6) | |
| `status` | VARCHAR(16) | `DRAFT`, `SUBMITTED`, `ACKNOWLEDGED` |
| `submitted_at` | DATETIME | nullable |

`insurance_declaration_line` is a composite PK junction: `(declaration_id, certificate_id)`.

---

## Files Created / Modified

### Client API (`make-cargo-client`)

| File | What |
|------|------|
| `migrations/mysql/Version20260626010000.php` | New — `insurance_policy` table |
| `migrations/sqlite/Version20260626010000.php` | New — SQLite |
| `migrations/mysql/Version20260626020000.php` | New — `insurance_certificate` table |
| `migrations/sqlite/Version20260626020000.php` | New — SQLite |
| `migrations/mysql/Version20260626030000.php` | New — `insurance_claim` table |
| `migrations/sqlite/Version20260626030000.php` | New — SQLite |
| `migrations/mysql/Version20260626040000.php` | New — `insurance_declaration` + `_line` tables |
| `migrations/sqlite/Version20260626040000.php` | New — SQLite |
| `src/Module/Insurance/Entity/InsurancePolicy.php` | New |
| `src/Module/Insurance/Entity/InsuranceCertificate.php` | New |
| `src/Module/Insurance/Entity/InsuranceClaim.php` | New |
| `src/Module/Insurance/Entity/InsuranceDeclaration.php` | New |
| `src/Module/Insurance/Entity/InsuranceDeclarationLine.php` | New |
| `src/Module/Insurance/Repository/InsurancePolicyRepository.php` | New |
| `src/Module/Insurance/Repository/InsuranceCertificateRepository.php` | New |
| `src/Module/Insurance/Repository/InsuranceClaimRepository.php` | New |
| `src/Module/Insurance/Repository/InsuranceDeclarationRepository.php` | New |
| `src/Module/Insurance/Service/PremiumCalculationService.php` | New |
| `src/Module/Insurance/Controller/InsurancePolicyController.php` | New |
| `src/Module/Insurance/Controller/InsuranceCertificateController.php` | New |
| `src/Module/Insurance/Controller/InsuranceClaimController.php` | New |
| `src/Module/Insurance/Controller/InsuranceDeclarationController.php` | New |

### Client BO (`make-cargo-client-bo`)

| File | What |
|------|------|
| `src/services/InsuranceService.js` | New |
| `src/pages/library/insurance-policy.vue` | New — policy management page |
| `src/pages/report/insurance-declaration.vue` | New — declaration management page |
| `src/views/shipment/InsuranceCertificatePanel.vue` | New — per-shipment certificates + claim filing |
| `src/views/shipment/ShipmentDetail.vue` | Modified — added Insurance tab |
| `src/config/navigation/index.js` | Modified — added Library + Reports nav entries |
