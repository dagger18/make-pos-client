# Freight Forwarder SaaS — Customer Portal

## 1. What the Customer Portal Is

The customer portal is a self-service web interface for shippers, consignees, and importers to track their shipments, download documents, view invoices, and request quotes — without contacting the forwarder directly.

It is not a separate system. It is a restricted view into the same data, filtered by the customer's own jobs and controlled by the same permission model.

---

## 2. What Customers Can See and Do

| Feature | What customers can do |
|---|---|
| Shipment tracking | View active and historical jobs, milestone timeline, ETA |
| Document download | Download HBL, HAWB, arrival notice, invoice, D/O |
| Invoice view | See outstanding and paid invoices, download PDF |
| Quote request | Submit a freight enquiry form — creates a draft quote in the system |
| Booking request | Request a booking for an accepted quote |
| Reporting | Volume by month, spend by mode, on-time delivery rate |

---

## 3. Portal User Model

Portal users are not the same as internal `app_user` records. They are external contacts linked to a customer organisation.

```sql
CREATE TABLE portal_user (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  contact_id        UUID          NOT NULL REFERENCES contact(id),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),
  email             VARCHAR(128)  UNIQUE NOT NULL,
  password_hash     TEXT          NOT NULL,
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  role              VARCHAR(16)   NOT NULL DEFAULT 'VIEWER',  -- VIEWER / REQUESTER / APPROVER
  last_login_at     TIMESTAMPTZ,
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

### Portal roles

| Role | Can see | Can do |
|---|---|---|
| `VIEWER` | All jobs for their organisation | Download documents, view invoices |
| `REQUESTER` | All jobs + quote form | Submit quote requests, request bookings |
| `APPROVER` | All jobs + financials | Approve quotes, access full invoice history |

---

## 4. Data Filtering — What the Customer Sees

Every query in the portal is filtered by `organisation_id`. The customer can only see jobs where their organisation appears as a party.

```sql
-- Portal: list shipments for a customer organisation
SELECT
  s.shipment_id,
  s.transport_mode,
  s.service_type,
  s.direction,
  s.status,
  s.sub_status,
  s.etd,
  s.eta,
  l_pol.name   AS origin,
  l_pod.name   AS destination,
  -- Latest milestone
  (SELECT mt.customer_label
   FROM milestone m
   JOIN milestone_master mt ON m.milestone_code = mt.code
   WHERE m.job_id = s.id AND m.actual_date IS NOT NULL
     AND mt.is_customer_visible = true
   ORDER BY m.actual_date DESC LIMIT 1) AS latest_status,
  -- Container count
  COUNT(c.id)  AS container_count
FROM shipment s
JOIN job_party jp  ON jp.job_id = s.id AND jp.organisation_id = :portal_org_id
JOIN location l_pol ON s.pol_code = l_pol.code
JOIN location l_pod ON s.pod_code = l_pod.code
LEFT JOIN container c ON c.job_id = s.id
WHERE s.status NOT IN ('CANCELLED')
GROUP BY s.id, l_pol.name, l_pod.name
ORDER BY s.etd DESC;
```

---

## 5. Customer-Visible Tracking Timeline

The milestone timeline shown to customers uses plain-language labels, not internal codes.

```sql
-- In the milestone_master table, add customer-facing fields
ALTER TABLE milestone_master ADD COLUMN customer_label      VARCHAR(128);
ALTER TABLE milestone_master ADD COLUMN is_customer_visible BOOLEAN NOT NULL DEFAULT false;

-- Example values
UPDATE milestone_master SET customer_label = 'Booking confirmed', is_customer_visible = true WHERE code = 'CARGO_BOOKED';
UPDATE milestone_master SET customer_label = 'Cargo received at port', is_customer_visible = true WHERE code = 'GATE_IN';
UPDATE milestone_master SET customer_label = 'Cargo loaded on vessel', is_customer_visible = true WHERE code = 'ON_BOARD';
UPDATE milestone_master SET customer_label = 'Vessel departed', is_customer_visible = true WHERE code = 'VESSEL_DEPARTED';
UPDATE milestone_master SET customer_label = 'Vessel arrived at destination', is_customer_visible = true WHERE code = 'VESSEL_ARRIVED';
UPDATE milestone_master SET customer_label = 'Customs cleared', is_customer_visible = true WHERE code = 'CUSTOMS_RELEASED';
UPDATE milestone_master SET customer_label = 'Cargo delivered', is_customer_visible = true WHERE code = 'DELIVERED';
```

Internal milestones like `VGM_SUBMITTED`, `SI_SUBMITTED`, `EMPTY_RELEASED` are not shown to customers.

---

## 6. Document Access Control

Customers can only download documents tagged as customer-accessible.

```sql
-- In job_document table
ALTER TABLE job_document ADD COLUMN is_customer_accessible BOOLEAN NOT NULL DEFAULT false;

-- Documents accessible to customers
UPDATE job_document SET is_customer_accessible = true
WHERE doc_type IN ('HBL', 'HAWB', 'ARRIVAL_NOTICE', 'DO', 'AR_INVOICE', 'PACKING_LIST', 'COO');

-- Internal-only
-- MBL, AP_BILL, COST_SHEET, CUSTOMS_ENTRY (internal filing copy) — not accessible
```

---

## 7. Quote Request Form

The portal quote request form creates a draft quote in the internal system and notifies the sales team.

```sql
CREATE TABLE portal_quote_request (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  portal_user_id    UUID          NOT NULL REFERENCES portal_user(id),
  organisation_id   UUID          NOT NULL REFERENCES organisation(id),
  transport_mode    VARCHAR(8),
  service_type      VARCHAR(16),
  origin            VARCHAR(128),
  destination       VARCHAR(128),
  cargo_description TEXT,
  weight_kg         NUMERIC(12,2),
  volume_cbm        NUMERIC(10,4),
  container_type    VARCHAR(8),
  incoterm          VARCHAR(8),
  cargo_ready_date  DATE,
  special_requirements TEXT,
  status            VARCHAR(16)   NOT NULL DEFAULT 'RECEIVED',   -- RECEIVED / IN_PROGRESS / QUOTED / CLOSED
  quote_id          UUID          REFERENCES quote(id),           -- linked when quote is created
  assigned_to       UUID          REFERENCES app_user(id),
  created_at        TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

When submitted, the system notifies the responsible sales rep or branch inbox and creates a draft quote pre-populated with the request data.

---

## 8. Invoice Payment Integration

The portal can show outstanding invoices and link to a payment gateway for direct online payment.

```sql
CREATE TABLE portal_payment_attempt (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  invoice_id        UUID          NOT NULL REFERENCES invoice(id),
  portal_user_id    UUID          NOT NULL REFERENCES portal_user(id),
  amount            NUMERIC(20,6) NOT NULL,
  currency          CHAR(3)       NOT NULL,
  gateway           VARCHAR(32)   NOT NULL,   -- STRIPE / PAYPAL / VNPAY / MOMO
  gateway_ref       VARCHAR(128),
  status            VARCHAR(16)   NOT NULL,   -- INITIATED / SUCCESS / FAILED / CANCELLED
  initiated_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),
  completed_at      TIMESTAMPTZ
);
```

On successful payment, the gateway posts a webhook to the system, which records an `ar_payment` record and updates the invoice status.

---

## 9. Security Considerations

- Portal users authenticate separately from internal users — different login endpoint, separate session tokens
- All portal API endpoints are prefixed `/portal/` and protected by portal-only middleware
- Rate limiting on login attempts (max 5 per 15 minutes per IP)
- Document downloads generate a time-limited signed URL (expires in 15 minutes) — never expose the raw storage URL
- All portal actions are logged in the `job_activity` table with `source = 'PORTAL'`
- Two-factor authentication (TOTP) available for APPROVER role

---

## 10. Golden Rules

1. **The portal is a restricted view — not a separate database.** All data comes from the same tables, filtered by `organisation_id`. There is no portal-specific data store.
2. **Customers never see internal cost data.** Buy rates, margin, cost sheet, and AP bills are never exposed — not even accidentally through API responses.
3. **Milestone labels shown to customers are in plain language.** Internal codes like `VGM_SUBMITTED` mean nothing to a shipper. Map to human-readable labels in the `milestone_master` table.
4. **Document downloads use signed URLs with short expiry.** Never expose permanent storage URLs in the portal.
5. **Portal quote requests are requests, not bookings.** A portal submission creates a draft quote that requires internal review before being sent to the customer. The portal does not create confirmed bookings automatically.
