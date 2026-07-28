# Module Tiers

Set `ENABLED_MODULES` in your `.env` file (or server environment) to control which features are available. `core` is always active regardless of this value.

## Tiers

### Demo
```dotenv
ENABLED_MODULES=core,quote,operations,finance
```
Covers: shipment management, quoting, basic invoicing.

### Pro
```dotenv
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting
```
Covers: full accounting, VAT/HS code, carrier tracking & scoring, CRM, reporting dashboards.

### Business
```dotenv
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting,integration
```
Covers: everything in Pro plus customer portal and EDI/API integration.

## Available Modules

| Module | What it covers |
|---|---|
| `core` | Users, branches, config, media — always on |
| `quote` | Rate engine, quote lifecycle, incoterms, free time agreements |
| `operations` | Shipments, bookings, consols, documents, dangerous goods |
| `finance` | AR/AP invoicing, GL, journal entries, P&L, credit control, tax groups |
| `tax` | VAT handling, HS codes, duty rates, customs filing reports |
| `carrier` | Carriers, vessel schedules, container tracking, performance scores |
| `crm` | Clients, partners, contacts, agent network |
| `notification` | Email/SMS/in-app alerts and notification rules |
| `reporting` | KPI dashboards, revenue analytics, datasets |
| `integration` | Customer portal, EDI/API integrations |

## Adding a controller to a module

1. Add `use App\Misc\Attribute\AppModule;` to the controller's use-statement block.
2. Add `#[AppModule('module_name')]` on the line immediately before `class ControllerName`.
3. Add the module name to `ENABLED_MODULES` in the relevant tier's env configuration.
4. Run `php bin/console cache:clear` to rebuild the DI container.

## How it works

At container build time (`cache:warmup`), a compiler pass reads `ENABLED_MODULES` and removes any service whose class is tagged with a disabled module's `#[AppModule]`. As a result:

- Disabled-module controllers are absent from the DI container.
- Their routes still exist in the router but cannot resolve (the controller service is gone).
- A `ModuleGuardListener` on `KernelEvents::CONTROLLER` returns HTTP 403 as a safety net for cached containers during the warm-up window.

All database entities remain registered in Doctrine regardless of module state, so the schema is identical across all tiers — no migration changes are needed when upgrading a client's tier.
