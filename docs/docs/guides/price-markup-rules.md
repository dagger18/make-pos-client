# Price Markup Rules — Setup Guide

## Overview

Price Markup Rules define how sell rates are automatically adjusted from buy rates during quote generation. Rules are grouped into **Pricing Tiers** (e.g. Gold, Silver, Standard). Each client is assigned to a tier, and the matching rules apply when the system generates sell rates for that client's quotes.

---

## Concepts

### Pricing Tier

A named group of rules. Examples: "Gold Client", "Standard", "Agent Discount".

Clients are linked to a tier via the **Pricing Level** field on the client record.

### Rule Types

| Type | Description | Required Fields |
|------|-------------|-----------------|
| `PERCENTAGE` | Add X% to the buy rate to produce the sell rate | `value` (percent) |
| `FLAT` | Add a flat currency amount on top of the buy rate | `value`, `currency` |
| `MIN_MARGIN` | Ensure the total margin for the quote is never below X | `value`, `currency` |

### Scope Fields

| Field | Description |
|-------|-------------|
| `chargeCategory` | Restrict rule to one charge category (`FR`, `LC`, `SV`, `CT`) — leave blank for all categories |
| `chargeCode` | Further restrict to a specific charge code (e.g. `THC`, `BAF`) — leave blank for all codes |

Rules within a tier are additive: all matching rules are applied in the order they appear.

---

## API Endpoints

All operations go through `/price-markup`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/price-markup` | List all pricing tiers |
| GET | `/price-markup/{id}` | Get a single tier with rules |
| POST | `/price-markup` | Create a tier |
| PUT | `/price-markup/{id}` | Update name and/or rules |
| DELETE | `/price-markup/{id}` | Delete a tier |

### Request body (POST / PUT)

```json
{
  "name": "Gold Client",
  "rules": [
    {
      "ruleType": "PERCENTAGE",
      "chargeCategory": "FR",
      "chargeCode": null,
      "value": 8.0,
      "currency": null
    },
    {
      "ruleType": "FLAT",
      "chargeCategory": "LC",
      "chargeCode": "THC",
      "value": 50.0,
      "currency": "USD"
    },
    {
      "ruleType": "MIN_MARGIN",
      "chargeCategory": null,
      "chargeCode": null,
      "value": 150.0,
      "currency": "USD"
    }
  ]
}
```

### Response (list / get)

```json
{
  "id": 1,
  "name": "Gold Client",
  "rules": [...]
}
```

---

## Migration

Run after deploying the updated code:

```bash
# MySQL
php bin/console doctrine:migrations:execute 'DoctrineMigrations\Version20260626000000' --up

# SQLite (test environment)
php bin/console doctrine:migrations:execute 'SqlEngineMigrations\Version20260626000000' --up --em=sqlite
```

---

## BO Administration

Navigate to **Rates → Markup Rules** in the back-office.

### Managing Tiers

The admin table shows all configured pricing tiers with a rule count badge.

**Actions per row:**
- **pencil** — open the editor dialog to rename the tier or edit its rules
- **trash** — delete the tier (clients assigned to this tier will no longer have a matched rule set)

### Creating a Tier

1. Click **New Tier**
2. Enter a name (e.g. "Gold Client", "Agent Net-Net")
3. The dialog opens with one default `PERCENTAGE` rule — configure it or add more
4. Click **Save**

### Configuring Rules

Each tier contains an ordered list of rules. Inside the editor dialog:

1. **Type** — select `Percentage (%)`, `Flat Amount`, or `Minimum Margin`
2. **Charge Category** — scope to a category or leave as "All Categories"
3. **Charge Code** — optionally restrict to one charge code (e.g. `THC`)
4. **Value** — the numeric amount (percentage for `%`, currency amount for `Flat`/`Min Margin`)
5. **Currency** — required for `Flat Amount` and `Minimum Margin`; leave blank for `Percentage`

The **Preview** section at the bottom of the dialog shows a human-readable summary of each rule.

### Assigning a Tier to a Client

1. Open the client record
2. Go to the **General** tab
3. Set the **Pricing Level** field to the desired tier
4. Save

---

## Rule Examples

| Scenario | Rule |
|----------|------|
| Add 10% to all freight charges | `PERCENTAGE`, Category: `FR`, Value: `10` |
| Add $50 flat to every THC charge | `FLAT`, Code: `THC`, Value: `50`, Currency: `USD` |
| Minimum $200 margin per quote | `MIN_MARGIN`, Value: `200`, Currency: `USD` |
| 5% markup on all charges (no scope) | `PERCENTAGE`, Category: All, Value: `5` |
