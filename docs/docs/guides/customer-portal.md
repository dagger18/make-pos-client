# Customer Portal

This guide covers the customer-facing portal: how it is architected, how authentication works, what the API exposes, and how to provision portal users.

---

## 1. Architecture Overview

The customer portal gives external users (customers) a self-service view of their shipments, documents, invoices, and freight quote requests.

**Portal user vs internal user**

| Dimension | Internal user | Portal user |
|---|---|---|
| Entity | `User` | `PortalUser` |
| Firewall | `main` | `portal` |
| URL prefix | `/api/...` | `/portal/...` |
| Identity source | Staff accounts | Customer org accounts |

**Client entity**

A `Client` represents a customer organisation. Every `PortalUser` belongs to exactly one `Client`. All data returned through the portal is scoped to that `Client`.

**Symfony firewall**

The portal runs under a dedicated `portal` firewall, completely separate from the `main` (staff) firewall. The two firewalls do not share sessions or tokens.

---

## 2. PortalUser Entity

| Field | Type | Notes |
|---|---|---|
| `email` | string | Unique login identifier |
| `passwordHash` | string | Bcrypt hash |
| `isActive` | bool | Soft-disable without deleting |
| `role` | string | `VIEWER`, `REQUESTER`, or `APPROVER` |
| `lastLoginAt` | datetime\|null | Updated on each successful login |
| `createdAt` | datetime | Set on creation |
| `client` | Client (ManyToOne) | Required — the customer org |
| `contact` | Contact (ManyToOne) | Optional — links to a contact record |

**Roles**

| Role | Intent |
|---|---|
| `VIEWER` | Read-only access to shipments, documents, and invoices. This is the default. |
| `REQUESTER` | Everything in VIEWER plus the ability to submit freight quote requests. |
| `APPROVER` | Everything in REQUESTER; reserved for future approval workflows. |

---

## 3. Auth Flow

### Login

```
POST /portal/auth
Content-Type: application/json

{
  "email": "customer@example.com",
  "password": "secret"
}
```

Success response (`200`):

```json
{
  "token": "<opaque token string>",
  "email": "customer@example.com",
  "role": "VIEWER",
  "expiresAt": "2026-07-04T10:00:00+00:00"
}
```

Tokens expire after **10 days**.

### Authenticated requests

Pass the token in the `X-W-Auth` header on every subsequent request:

```
X-W-Auth: Token Email="customer@example.com", Token="<token>"
```

Both the email and the token value are required. The server looks up the token, validates it has not expired, and scopes the response to the matching `PortalUser`'s `Client`.

### Logout

`POST /portal/logout` deletes **all** active tokens for the authenticated user, not just the current one. This signs out all devices simultaneously.

---

## 4. API Endpoint Table

| Method | Path | Auth required | Description |
|---|---|---|---|
| POST | `/portal/auth` | No | Exchange credentials for a token |
| GET | `/portal/me` | Yes | Return the authenticated portal user's profile |
| POST | `/portal/logout` | Yes | Invalidate all tokens for the user |
| GET | `/portal/shipments` | Yes | List shipments belonging to the client |
| GET | `/portal/shipments/{id}` | Yes | Shipment detail including milestone timeline |
| GET | `/portal/documents` | Yes | List customer-accessible documents |
| GET | `/portal/documents/{id}/download-url` | Yes | Generate a signed download URL (15-min TTL) |
| GET | `/portal/documents/{id}/file` | No (signed URL) | Serve the file via a pre-signed URL |
| GET | `/portal/invoices` | Yes | List AR invoices for the client |
| GET | `/portal/quote-requests` | Yes | List the client's freight quote requests |
| POST | `/portal/quote-requests` | Yes | Submit a new freight quote request |
| GET | `/portal/quote-requests/{id}` | Yes | Quote request detail |

---

## 5. Shipment Filtering

The `Shipment` entity has no direct collection of parties on it. Filtering by client uses the `ShipmentParty` join entity with a `WITH` condition:

```dql
SELECT s FROM Shipment s
JOIN ShipmentParty sp WITH sp.shipment = s AND sp.client = :client
```

This means only shipments where the client appears as a party (in any role) are returned.

---

## 6. Milestone Visibility

Not all internal milestone codes are shown to customers. Each `MilestoneCode` case exposes two methods:

- `isCustomerVisible()` — returns `true` for milestones that are safe to expose externally.
- `customerLabel()` — returns a human-readable label suitable for the customer timeline (may differ from the internal code label).

Internal-only milestones (e.g. cost accruals, routing decisions) return `isCustomerVisible() = false` and are stripped from the portal shipment detail response before serialization.

---

## 7. Document Signed URL Flow

### `isCustomerAccessible` flag

Every `ShipmentDocument` has an `isCustomerAccessible` boolean (default `false`). Internal staff must explicitly set this flag to `true` to make a document visible in the portal. Documents where the flag is `false` are hidden from all portal listing and download endpoints.

### Generating a signed URL

`GET /portal/documents/{id}/download-url` (authenticated) returns a short-lived URL:

```
/portal/documents/{id}/file?expires={unix_timestamp}&sig={hmac}
```

- `expires` is a Unix timestamp 15 minutes in the future.
- `sig` is an HMAC-SHA256 of `"{id}:{expires}"` keyed with `APP_SECRET` (`kernel.secret`).

### Serving the file

`GET /portal/documents/{id}/file` is a **public** endpoint (no `X-W-Auth` header needed). The server recomputes the HMAC, checks it matches `sig`, and checks that `expires` has not passed. On success it streams or redirects to the file. This pattern allows the signed URL to be used directly in a browser `<a href>` or `<img src>` without exposing the portal token.

---

## 8. Invoice Filtering

Only **accounts-receivable invoices** are exposed in the portal:

- Included: `EbitNoteType::InvoiceDebit` (internal value `'ID'`).
- Excluded: `InvoiceCredit` and all other note types.

Buy-rate (cost) data is never returned through any portal endpoint. Only sell-side values visible to the client are included in the serialized response.

---

## 9. Quote Request Lifecycle

```
RECEIVED → IN_PROGRESS → QUOTED → CLOSED
```

| Status | Meaning |
|---|---|
| `RECEIVED` | Customer has submitted; awaiting staff pickup |
| `IN_PROGRESS` | Staff are working on the quote |
| `QUOTED` | A quote has been provided to the customer |
| `CLOSED` | Request resolved (booked, declined, or expired) |

Customers can create requests (if `REQUESTER` or `APPROVER` role) and read status, but cannot change the status themselves.

---

## 10. Creating a Portal User

There is **no self-registration**. Portal users are provisioned by internal staff only, via `PortalAuthService::createUser()`:

```php
$portalUser = $portalAuthService->createUser([
    'email'    => 'customer@example.com',
    'password' => 'initialPassword123',
    'role'     => 'VIEWER',
    'client'   => $clientEntity,
]);
```

The password is hashed immediately. The plain-text value is not stored. The calling code is responsible for communicating the initial password to the customer through a secure channel.

---

## 11. Client BO Portal Pages

The following front-end routes are part of the customer portal UI:

| Route | Purpose |
|---|---|
| `/portal/login` | Customer login page |
| `/portal/dashboard` | Welcome screen with recent shipments and navigation tiles |
| `/portal/shipments` | Full paginated shipment list |
| `/portal/shipments/{id}` | Shipment detail with milestone timeline |
| `/portal/documents` | Document list with download links |
| `/portal/invoices` | AR invoice list |
| `/portal/quote-request` | Freight quote request submission form and request history |

---

## 12. Required Environment Variables

No new environment variables are required. The portal HMAC signing reuses the existing `APP_SECRET` value (`kernel.secret`), which must already be set for any Symfony application.

---

## 13. Migrations

| Version | What it creates |
|---|---|
| `20260624140000` | `portal_user` table |
| `20260624150000` | `portal_token` table |
| `20260624160000` | `portal_quote_request` table |
| `20260624170000` | Adds `is_customer_accessible` to `shipment_document` |
| `20260624180000` | Adds `source` to `shipment_activity` |

Run all five migrations in order after deploying the feature:

```bash
php bin/console doctrine:migrations:migrate
```
