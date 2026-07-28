# Module Activation System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement an `ENABLED_MODULES` env-var-driven module activation gate so that each deployment tier (Demo, Pro, Business) exposes only its licensed API controllers, with no code changes between deployments.

**Architecture:** A PHP 8.1 `#[AppModule]` attribute tags every API controller with its module name. A compiler pass inside `Kernel::process()` reads `ENABLED_MODULES` at container build time and removes disabled-module controller services from the DI container. A `ModuleGuardListener` on `KernelEvents::CONTROLLER` acts as a runtime safety net, returning HTTP 404 if a disabled controller somehow survives the build phase (e.g. during cache warm-up). All entities remain registered with Doctrine so the DB schema is identical across all tiers.

**Tech Stack:** PHP 8.1 attributes, Symfony 7 DI compiler pass (`Kernel::process()`), Symfony `KernelEvents::CONTROLLER`, `$_ENV`/`getenv()` for env var access.

**Design spec:** `docs/superpowers/specs/2026-06-25-module-architecture-design.md`

---

### Task 1: Create `#[AppModule]` attribute and `ModuleRegistry`

**Files:**
- Create: `src/Misc/Attribute/AppModule.php`
- Create: `src/Module/ModuleRegistry.php`

- [ ] **Step 1: Create the `AppModule` PHP attribute**

```php
<?php
// src/Misc/Attribute/AppModule.php
namespace App\Misc\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AppModule
{
    public function __construct(public readonly string $name) {}
}
```

- [ ] **Step 2: Create `ModuleRegistry`**

`ModuleRegistry` reads `ENABLED_MODULES` from the environment (comma-separated module names) and answers `isEnabled(string $module): bool`. The `core` module is always enabled regardless of the env var.

```php
<?php
// src/Module/ModuleRegistry.php
namespace App\Module;

class ModuleRegistry
{
    /** @var string[] */
    private array $enabled;

    public function __construct(string $enabledModules)
    {
        $names = array_filter(array_map('trim', explode(',', $enabledModules)));
        $this->enabled = array_merge(['core'], $names);
    }

    public function isEnabled(string $module): bool
    {
        return in_array($module, $this->enabled, true);
    }

    /** @return string[] */
    public function getEnabled(): array
    {
        return $this->enabled;
    }
}
```

- [ ] **Step 3: Wire `ModuleRegistry` in `config/services.yaml`**

Add after the existing `parameters:` block (before the `services:` key):

```yaml
# config/services.yaml  — add inside parameters:
parameters:
  # ... existing params ...
  app.enabled_modules: '%env(ENABLED_MODULES)%'
```

Add inside the `services:` block:

```yaml
    App\Module\ModuleRegistry:
        arguments:
            $enabledModules: '%app.enabled_modules%'
```

- [ ] **Step 4: Commit**

```bash
git add src/Misc/Attribute/AppModule.php src/Module/ModuleRegistry.php config/services.yaml
git commit -m "feat(modules): add AppModule attribute and ModuleRegistry"
```

---

### Task 2: Add compiler pass to `Kernel::process()`

The existing `Kernel::process()` already handles DBAL service types and serializer groups. We add module filtering at the **end** of that method so it runs after all services are registered.

**Files:**
- Modify: `src/Kernel.php`

- [ ] **Step 1: Add the module-filtering block to `Kernel::process()`**

Open `src/Kernel.php`. At the very end of the `process()` method body (after the existing serializer lines), add:

```php
    public function process(ContainerBuilder $container)
    {
        // ... existing DBAL and serializer code stays unchanged ...

        // Module activation: remove services for disabled modules at build time.
        $enabledRaw  = $_ENV['ENABLED_MODULES'] ?? getenv('ENABLED_MODULES') ?: '';
        $enabled     = array_merge(['core'], array_filter(array_map('trim', explode(',', $enabledRaw))));

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if (!$class || !class_exists($class)) {
                continue;
            }
            $ref  = new \ReflectionClass($class);
            $attrs = $ref->getAttributes(\App\Misc\Attribute\AppModule::class);
            if (empty($attrs)) {
                continue;
            }
            $moduleName = $attrs[0]->newInstance()->name;
            if (!in_array($moduleName, $enabled, true)) {
                $container->removeDefinition($id);
            }
        }
    }
```

The full updated `Kernel.php` looks like this (complete file — replace entirely):

```php
<?php

namespace App;

use App\Misc\Attribute\Log;
use App\Misc\Attribute\AppModule;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\Config\Resource\GlobResource;

class Kernel extends BaseKernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            Log::class,
            static function (
                ChildDefinition $definition,
                Log $attribute,
                \ReflectionMethod $reflector
            ): void {
                //dd($definition);
            }
        );
    }

    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('doctrine.dbal.connection_factory');

        foreach ($container->findTaggedServiceIds('app.doctrine.dbal.service_type') as $id => $_) {
            $definition->addMethodCall('registerServiceType', [new Reference($id)]);
        }

        $dir   = $container->getParameter('kernel.project_dir') . '/config/serializer_groups';
        $container->addResource(new GlobResource($dir, '/*.yaml', false));
        $files = glob($dir . '/*.yaml') ?: [];
        if ($files) {
            $container->getDefinition(\App\Serializer\GroupCentricYamlLoader::class)
                ->setArgument('$files', $files);

            $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');
            $loaders     = $chainLoader->getArgument(0);
            $loaders[]   = new Reference(\App\Serializer\GroupCentricYamlLoader::class);
            $chainLoader->setArgument(0, $loaders);
        }

        // Module activation: remove services belonging to disabled modules at build time.
        $enabledRaw = $_ENV['ENABLED_MODULES'] ?? getenv('ENABLED_MODULES') ?: '';
        $enabled    = array_merge(['core'], array_filter(array_map('trim', explode(',', $enabledRaw))));

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if (!$class || !class_exists($class)) {
                continue;
            }
            $ref   = new \ReflectionClass($class);
            $attrs = $ref->getAttributes(AppModule::class);
            if (empty($attrs)) {
                continue;
            }
            $moduleName = $attrs[0]->newInstance()->name;
            if (!in_array($moduleName, $enabled, true)) {
                $container->removeDefinition($id);
            }
        }
    }
}
```

- [ ] **Step 2: Verify the file is syntactically correct**

```bash
php -l src/Kernel.php
```

Expected: `No syntax errors detected in src/Kernel.php`

- [ ] **Step 3: Commit**

```bash
git add src/Kernel.php
git commit -m "feat(modules): remove disabled-module services in Kernel compiler pass"
```

---

### Task 3: Create `ModuleGuardListener` (runtime safety net)

This listener fires on every request **after** the controller is resolved. If the controller's class carries `#[AppModule('x')]` and module `x` is disabled, it returns a 404 JSON response immediately. This is a safety net for the cached-container window; the compiler pass handles the normal case.

**Files:**
- Create: `src/EventListener/ModuleGuardListener.php`

- [ ] **Step 1: Create the listener**

```php
<?php
// src/EventListener/ModuleGuardListener.php
namespace App\EventListener;

use App\Misc\Attribute\AppModule;
use App\Module\ModuleRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 10)]
class ModuleGuardListener
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function __invoke(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // Resolve the class from callable formats
        if (is_array($controller)) {
            $class = get_class($controller[0]);
        } elseif (is_object($controller)) {
            $class = get_class($controller);
        } else {
            return;
        }

        if (!class_exists($class)) {
            return;
        }

        $attrs = (new \ReflectionClass($class))->getAttributes(AppModule::class);
        if (empty($attrs)) {
            return;
        }

        $moduleName = $attrs[0]->newInstance()->name;
        if (!$this->registry->isEnabled($moduleName)) {
            $event->setController(static fn() => new JsonResponse(
                ['error' => "Module '{$moduleName}' is not available on this plan."],
                404
            ));
        }
    }
}
```

- [ ] **Step 2: Verify syntax**

```bash
php -l src/EventListener/ModuleGuardListener.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add src/EventListener/ModuleGuardListener.php
git commit -m "feat(modules): add ModuleGuardListener as runtime safety net"
```

---

### Task 4: Tag all API controllers with `#[AppModule]`

Add `use App\Misc\Attribute\AppModule;` and `#[AppModule('module_name')]` to every API controller class. The attribute goes on the **class**, one line above the `class` keyword (below any existing class-level attributes like `#[Route]` and `#[IsGranted]`).

**Files (all under `src/Controller/Api/`):**

Module assignments:

| Module | Controllers |
|---|---|
| `quote` | QuoteController, RateController, PriceMarkupController, CalculationTypeController, CustomChargeTypeController, IncotermController, FreeTimeAgreementController |
| `finance` | EbitNoteController, ChargeController, ChargeItemController, JournalEntryController, ChartOfAccountController, BankAccountController, InvoiceInfoController, ExchangeRateGroupController, CurrencyController, PaymentMethodController, AgeingController, PnlController, AccountingCloseController |
| `tax` | CustomerTaxExemptionController, PartnerTaxRegistrationController, HsCodeController, HsRestrictionController, HsVersionMappingController, DutyRateController, VatReportController |
| `operations` | ShipmentController, ShipmentActivityController, ShipmentDocumentController, ShipmentMilestoneController, ShipmentModeController, ShipmentNoteController, ShipmentPartyController, ShipmentTaskController, BookingController, InstructionController, ConsolidationController, DeliveryOrderController, ArrivalNoticeController, DangerousGoodsController |
| `carrier` | ProviderController, CarrierProfileController, CarrierEventMappingController, CarrierPerformanceController, CargoClaimController, VesselRollController, VesselSailingController, TrackingRequestController, TrackingWebhookController, ContainerDdController, FlightScheduleController |
| `crm` | ClientController, ContactController, AgentProfileController |
| `reporting` | DatasetController, KpiController, ReportAnalyticsController |
| `core` | UserController, UserGroupController, MyProfileController, BranchController, DepartmentController, ConfigController, MediaController, PageController, CrudController, TestController |

**Portal controllers (`src/Controller/Portal/`):** tag with `integration`.  
**Http controllers (`src/Controller/Http/`):** tag with `core`.

- [ ] **Step 1: Tag `quote` controllers**

For each file listed under `quote`, add these two lines:

After the last `use` statement before the class declaration:
```php
use App\Misc\Attribute\AppModule;
```

On the line immediately before `class QuoteController`:
```php
#[AppModule('quote')]
```

Example — `src/Controller/Api/QuoteController.php` class declaration becomes:

```php
use App\Misc\Attribute\AppModule;
// ...other use statements...

#[Route('/quote')]
#[IsGranted('ROLE_USER')]
#[AppModule('quote')]
class QuoteController extends AbstractController
```

Apply the same pattern to: `RateController`, `PriceMarkupController`, `CalculationTypeController`, `CustomChargeTypeController`, `IncotermController`, `FreeTimeAgreementController`.

- [ ] **Step 2: Tag `finance` controllers**

Apply `#[AppModule('finance')]` to: `EbitNoteController`, `ChargeController`, `ChargeItemController`, `JournalEntryController`, `ChartOfAccountController`, `BankAccountController`, `InvoiceInfoController`, `ExchangeRateGroupController`, `CurrencyController`, `PaymentMethodController`, `AgeingController`, `PnlController`, `AccountingCloseController`.

- [ ] **Step 3: Tag `tax` controllers**

Apply `#[AppModule('tax')]` to: `CustomerTaxExemptionController`, `PartnerTaxRegistrationController`, `HsCodeController`, `HsRestrictionController`, `HsVersionMappingController`, `DutyRateController`, `VatReportController`.

- [ ] **Step 4: Tag `operations` controllers**

Apply `#[AppModule('operations')]` to: `ShipmentController`, `ShipmentActivityController`, `ShipmentDocumentController`, `ShipmentMilestoneController`, `ShipmentModeController`, `ShipmentNoteController`, `ShipmentPartyController`, `ShipmentTaskController`, `BookingController`, `InstructionController`, `ConsolidationController`, `DeliveryOrderController`, `ArrivalNoticeController`, `DangerousGoodsController`.

- [ ] **Step 5: Tag `carrier` controllers**

Apply `#[AppModule('carrier')]` to: `ProviderController`, `CarrierProfileController`, `CarrierEventMappingController`, `CarrierPerformanceController`, `CargoClaimController`, `VesselRollController`, `VesselSailingController`, `TrackingRequestController`, `TrackingWebhookController`, `ContainerDdController`, `FlightScheduleController`.

- [ ] **Step 6: Tag `crm`, `reporting`, `core` controllers**

Apply `#[AppModule('crm')]` to: `ClientController`, `ContactController`, `AgentProfileController`.

Apply `#[AppModule('reporting')]` to: `DatasetController`, `KpiController`, `ReportAnalyticsController`.

Apply `#[AppModule('core')]` to: `UserController`, `UserGroupController`, `MyProfileController`, `BranchController`, `DepartmentController`, `ConfigController`, `MediaController`, `PageController`, `CrudController`, `TestController`.

Apply `#[AppModule('integration')]` to all controllers under `src/Controller/Portal/`.

Apply `#[AppModule('core')]` to all controllers under `src/Controller/Http/`.

- [ ] **Step 7: Verify no file was missed**

```bash
grep -rL "AppModule" src/Controller/Api/ src/Controller/Portal/ src/Controller/Http/
```

Expected: empty output (every controller has the attribute).

- [ ] **Step 8: Commit**

```bash
git add src/Controller/
git commit -m "feat(modules): tag all controllers with #[AppModule]"
```

---

### Task 5: Configure env vars and smoke-test

**Files:**
- Modify: `.env`
- Modify: `.env.test` (if it exists)

- [ ] **Step 1: Add `ENABLED_MODULES` to `.env`**

Open `.env`. Add after the last `APP_*` line:

```dotenv
###> module activation ###
# Comma-separated list of enabled modules.
# core is always enabled regardless of this value.
# Available: quote,operations,finance,tax,carrier,crm,notification,reporting,integration
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting,integration
###< module activation ###
```

- [ ] **Step 2: Add to `.env.test` (if present)**

```dotenv
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting,integration
```

- [ ] **Step 3: Clear the Symfony cache**

```bash
php bin/console cache:clear
```

Expected: `Cache for the "dev" environment (debug=true) was successfully cleared.`

- [ ] **Step 4: Verify the container builds cleanly**

```bash
php bin/console debug:container --show-hidden 2>&1 | tail -5
```

Expected: no errors, last lines show service count summary.

- [ ] **Step 5: Smoke-test — disable `carrier` module**

Edit `.env` temporarily:
```dotenv
ENABLED_MODULES=core,quote,operations,finance
```

Clear cache:
```bash
php bin/console cache:clear
```

Verify `CarrierPerformanceController` is gone from the container:
```bash
php bin/console debug:container App\\Controller\\Api\\CarrierPerformanceController 2>&1
```

Expected: `No services found matching "App\Controller\Api\CarrierPerformanceController"` (or similar "not found" message).

- [ ] **Step 6: Restore full module list**

Set `.env` back to:
```dotenv
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting,integration
```

```bash
php bin/console cache:clear
```

- [ ] **Step 7: Commit**

```bash
git add .env
git commit -m "feat(modules): add ENABLED_MODULES env var with full module list default"
```

---

### Task 6: Document the tier configuration

**Files:**
- Create: `docs/guides/module-tiers.md`

- [ ] **Step 1: Create the tier guide**

```markdown
# Module Tiers

Set `ENABLED_MODULES` in your `.env` file (or server environment) to control
which features are available. `core` is always active regardless of this value.

## Demo
```dotenv
ENABLED_MODULES=core,quote,operations,finance
```
Covers: shipment management, quoting, basic invoicing.

## Pro
```dotenv
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting
```
Covers: full accounting, VAT/HS code, carrier tracking & scoring, CRM, reporting dashboards.

## Business
```dotenv
ENABLED_MODULES=core,quote,operations,finance,tax,carrier,crm,notification,reporting,integration
```
Covers: everything in Pro plus customer portal and EDI/API integration.

## Available Modules

| Module        | What it covers |
|---------------|----------------|
| `core`        | Users, branches, config, media — always on |
| `quote`       | Rate engine, quote lifecycle, incoterms |
| `operations`  | Shipments, bookings, consols, documents, dangerous goods |
| `finance`     | AR/AP invoicing, GL, journal entries, P&L, credit control |
| `tax`         | VAT handling, HS codes, duty rates, customs filing reports |
| `carrier`     | Carriers, vessel schedules, container tracking, performance scores |
| `crm`         | Clients, partners, contacts, agent network |
| `notification`| Email/SMS/in-app alerts and notification rules |
| `reporting`   | KPI dashboards, revenue analytics, datasets |
| `integration` | Customer portal, EDI/API integrations |

## Adding a new module

1. Add `#[AppModule('my_module')]` to its controller classes.
2. Add `my_module` to `ENABLED_MODULES` where needed.
3. Update this table above.
```

- [ ] **Step 2: Commit**

```bash
git add docs/guides/module-tiers.md
git commit -m "docs: add module-tiers guide"
```
