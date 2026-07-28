# Freight Forwarder SaaS — Cargo Insurance Module

## 1. What the Cargo Insurance Module Does

Many freight forwarders offer cargo insurance as an ancillary service — either as an agent of an insurer (selling policies on their behalf) or under an open cover policy (insuring all cargo automatically and claiming from the insurer when needed). The module handles certificate issuance, premium calculation, claims management, and insurer settlement.

---

## 2. Insurance Programme Types

| Type | Description | Typical arrangement |
|---|---|---|
| **Open cover** | A blanket policy covering all shipments automatically up to a limit | Forwarder holds the policy, declares shipments, invoices customers |
| **Specific voyage** | Per-shipment policy arranged on request | Forwarder acts as broker, insurer issues the certificate |
| **Customer own cover** | Customer has their own policy — forwarder is not involved | No action required; note "Customer's own insurance" on BL |
| **Liability only** | Forwarder insures their own liability, not the cargo value | Covers legal liability — not cargo value |

---

## 3. Open Cover Policy Table

```sql
CREATE TABLE insurance_policy (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  policy_number     VARCHAR(64)   UNIQUE NOT NULL,
  insurer_id        UUID          NOT NULL REFERENCES organisation(id),
  policy_type       VARCHAR(32)   NOT NULL,   -- OPEN_COVER / SPECIFIC_VOYAGE / LIABILITY
  policy_holder_id  UUID          NOT NULL REFERENCES organisation(id),  -- your company
  coverage_scope    VARCHAR(32)   NOT NULL,   -- ALL_RISK / NAMED_PERILS / TOTAL_LOSS_ONLY
  max_per_shipment  NUMERIC(20,6) NOT NULL,   -- maximum insured value per single shipment
  max_per_conveyance NUMERIC(20,6),           -- maximum on one vessel/aircraft
  annual_limit      NUMERIC(20,6),
  currency          CHAR(3)       NOT NULL,
  premium_basis     VARCHAR(16)   NOT NULL,   -- PCT_VALUE / FLAT_RATE / PER_UNIT
  premium_rate      NUMERIC(8,6)  NOT NULL,   -- e.g. 0.0005 = 0.05% of cargo value
  min_premium       NUMERIC(20,6),            -- minimum premium per certificate
  deductible        NUMERIC(20,6),
  modes_covered     TEXT[]        NOT NULL,   -- {OCN, AIR, RD, RAL}
  effective_from    DATE          NOT NULL,
  expiry_date       DATE          NOT NULL,
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 4. Insurance Certificate

Each shipment that requires coverage generates an insurance certificate.

```sql
CREATE TABLE insurance_certificate (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  certificate_number VARCHAR(64)  UNIQUE NOT NULL,
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  policy_id         UUID          NOT NULL REFERENCES insurance_policy(id),

  -- Insured parties
  insured_name      VARCHAR(255)  NOT NULL,   -- shipper or consignee per Incoterm
  beneficiary_name  VARCHAR(255),             -- usually the consignee (or bank for LC)

  -- Cargo
  goods_description TEXT          NOT NULL,
  packing           VARCHAR(128),             -- "50 cartons" / "2 FCL 40HC"
  marks_numbers     TEXT,
  hs_code           VARCHAR(12),

  -- Route
  vessel_or_flight  VARCHAR(128),
  voyage_or_flight_no VARCHAR(32),
  pol_name          VARCHAR(128)  NOT NULL,
  pod_name          VARCHAR(128)  NOT NULL,
  etd               DATE,

  -- Value and premium
  cargo_value       NUMERIC(20,6) NOT NULL,   -- insured value (usually CIF + 10%)
  value_currency    CHAR(3)       NOT NULL,
  insured_amount    NUMERIC(20,6) NOT NULL,   -- = cargo_value × coverage_multiplier (e.g. 1.10)
  premium_amount    NUMERIC(20,6) NOT NULL,
  premium_currency  CHAR(3)       NOT NULL,
  coverage_scope    VARCHAR(32)   NOT NULL,   -- ALL_RISK / NAMED_PERILS

  -- Status
  status            VARCHAR(16)   NOT NULL DEFAULT 'ISSUED',  -- ISSUED / CANCELLED / CLAIMED
  issue_date        DATE          NOT NULL,
  issued_by         UUID          REFERENCES app_user(id),

  -- Revenue
  is_invoiced       BOOLEAN       NOT NULL DEFAULT false,
  invoice_id        UUID          REFERENCES invoice(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

---

## 5. Premium Calculation

```python
def calculate_premium(
    cargo_value: float,
    currency: str,
    policy_id: str,
    transport_mode: str,
    goods_description: str
) -> dict:
    policy = fetch_insurance_policy(policy_id)

    # Standard insured amount = CIF value + 10% (industry convention)
    insured_amount = cargo_value * 1.10

    if policy.premium_basis == 'PCT_VALUE':
        premium = insured_amount * policy.premium_rate
    elif policy.premium_basis == 'FLAT_RATE':
        premium = policy.premium_rate
    else:
        premium = 0

    # Apply minimum premium
    premium = max(premium, policy.min_premium or 0)

    return {
        "cargo_value":     cargo_value,
        "insured_amount":  insured_amount,
        "premium_rate":    policy.premium_rate,
        "premium_amount":  round(premium, 2),
        "currency":        currency
    }
```

The premium is added as a SELL charge line on the job with `charge_code = 'INSURANCE'`.

---

## 6. Cargo Claims

When cargo is lost or damaged, a claim is filed against the insurance policy.

```sql
CREATE TABLE insurance_claim (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  certificate_id    UUID          NOT NULL REFERENCES insurance_certificate(id),
  job_id            UUID          NOT NULL REFERENCES shipment(id),
  claim_number      VARCHAR(64)   UNIQUE NOT NULL,
  claim_type        VARCHAR(32)   NOT NULL,   -- TOTAL_LOSS / PARTIAL_LOSS / DAMAGE / THEFT / DELAY
  incident_date     DATE          NOT NULL,
  incident_location VARCHAR(128),
  description       TEXT          NOT NULL,
  claimed_amount    NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  status            VARCHAR(16)   NOT NULL DEFAULT 'FILED',
  -- FILED / SURVEYOR_APPOINTED / UNDER_ASSESSMENT / APPROVED / REJECTED / SETTLED / WITHDRAWN
  surveyor_id       UUID          REFERENCES organisation(id),
  surveyor_ref      VARCHAR(64),
  approved_amount   NUMERIC(20,6),
  deductible_applied NUMERIC(20,6),
  net_settlement    NUMERIC(20,6),
  settled_date      DATE,
  rejection_reason  TEXT,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Claims workflow

```
Cargo damaged on delivery
        ↓
Operator creates insurance claim on the certificate
        ↓
System generates claim number and notifies insurer
        ↓
Surveyor appointed (by insurer)
        ↓
Surveyor inspection — damage report submitted
        ↓
Insurer assessment:
  APPROVED → settlement amount confirmed
  REJECTED → reason recorded, operator notified
        ↓
Settlement paid:
  Insurer pays forwarder → forwarder passes to customer
  OR insurer pays customer directly
        ↓
Claim closed, certificate status = CLAIMED
```

---

## 7. Declaration to Insurer (Open Cover)

Under an open cover policy, the forwarder must periodically declare all shipments covered to the insurer. This is typically done monthly.

```sql
CREATE TABLE insurance_declaration (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  policy_id         UUID          NOT NULL REFERENCES insurance_policy(id),
  declaration_ref   VARCHAR(64)   UNIQUE NOT NULL,
  period_from       DATE          NOT NULL,
  period_to         DATE          NOT NULL,
  certificate_count INT           NOT NULL,
  total_insured_value NUMERIC(20,6) NOT NULL,
  total_premium     NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  status            VARCHAR(16)   NOT NULL DEFAULT 'DRAFT',  -- DRAFT / SUBMITTED / ACKNOWLEDGED
  submitted_at      TIMESTAMPTZ,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);

-- Certificates included in this declaration
CREATE TABLE insurance_declaration_line (
  declaration_id    UUID    NOT NULL REFERENCES insurance_declaration(id),
  certificate_id    UUID    NOT NULL REFERENCES insurance_certificate(id),
  PRIMARY KEY (declaration_id, certificate_id)
);
```

---

## 8. Golden Rules

1. **Insured amount is cargo value + 10%, not the invoice value.** The standard industry practice is CIF value × 1.10. This covers profit on the goods. Always apply this uplift unless the customer specifies otherwise.
2. **The certificate is a legal document.** It is evidence of insurance cover and may be required for Letters of Credit. Generate it as a formatted PDF using the document generation system.
3. **Claims must be filed promptly.** Most policies require notification of a claim within 3–7 days of the incident. The system should alert the operator if a damaged-delivery milestone is recorded without a corresponding claim being filed.
4. **Open cover declarations must be submitted on time.** Late or missing declarations can void coverage. Automate the monthly declaration generation and submission.
5. **Insurance revenue is a separate charge line.** The premium charged to the customer is `SELL` revenue. The premium paid to the insurer is `BUY` cost. Both appear on the job cost sheet with `charge_code = 'INSURANCE'`.
