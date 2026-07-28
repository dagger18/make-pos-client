# Consolidation Missing Features Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the 6 missing consolidation features: serializer groups, entity capacity/cutoff fields, depart/arrive status transitions with milestone fan-out, cargo manifest PDF, and BO UI updates.

**Architecture:** ConsolidationController extends AbstractController (not CrudController) and serializes manually — no BaseService pattern is needed. The manifest PDF follows the same pattern as DangerousGoodsController: inject ProviderRepository + MediaService + LocaleSwitcher, render with generatePdf() / renderView(). Status transitions (depart/arrive) fan out milestone records to child shipments using ShipmentMilestoneRepository.

**Tech Stack:** PHP 8.2 / Symfony 6, Doctrine ORM, Twig PDF templates, Vue 3 + Vuetify (BO). Both MySQL and SQLite migrations required.

---

## Context for all tasks

- **API project root:** `d:\Projects\make-cargo-client`
- **BO project root:** `d:\Projects\make-cargo-client-bo`
- MySQL migration namespace: `SqlEngineMigrations`
- SQLite migration namespace: `SqlEngineMigrations`
- Latest migration version: `Version20260624050000` → new versions start at `Version20260624060000`
- Milestone fan-out: SEA → `VesselDeparted`/`VesselArrived`, AIR → `FlightDeparted`/`FlightArrived`, ROAD → no milestone fan-out
- The `generatePdf()` method signature: `$mediaService->generatePdf($entity, $data, $language)` where `$entity` is a typed first argument just for context — passing `$consol` is fine
- PDF preview uses `$localeSwitcher->setLocale($language)` + `$this->renderView(...)` + `$localeSwitcher->reset()`

---

## Task 1: Serializer Groups YAML

**Files:**
- Create: `config/serializer_groups/Consolidation.yaml`

This file follows the convention for all entities. Since ConsolidationController serializes manually (not via CrudController), this YAML is not actively read at runtime, but it documents the entity shape and is required by convention.

- [ ] **Step 1: Create serializer groups YAML**

Create `config/serializer_groups/Consolidation.yaml`:

```yaml
App\Entity\Consolidation:

    list:
        - id
        - code
        - transportMode
        - serviceType
        - status
        - pol
        - pod
        - etd
        - eta
        - vessel
        - flightNumber
        - carrier
        - branch
        - cfsCutoff
        - docCutoff
        - maxWeightKg
        - maxVolumeCbm
        - createdAt
        - updatedAt

    detail:
        - id
        - code
        - transportMode
        - serviceType
        - status
        - pol
        - pod
        - etd
        - eta
        - vessel
        - voyage
        - mblNumber
        - mawbNumber
        - flightNumber
        - containerNumber
        - uldNumber
        - apportionmentBasis
        - cfsCutoff
        - docCutoff
        - maxWeightKg
        - maxVolumeCbm
        - carrier
        - branch
        - createdBy
        - createdAt
        - updatedAt
```

- [ ] **Step 2: Commit**

```bash
cd d:\Projects\make-cargo-client
git add config/serializer_groups/Consolidation.yaml
git commit -m "feat(consolidation): add serializer groups YAML"
```

---

## Task 2: Entity Capacity/Cutoff Fields + Migrations

**Files:**
- Modify: `src/Entity/Consolidation.php`
- Create: `migrations/mysql/Version20260624060000.php`
- Create: `migrations/sqlite/Version20260624060000.php`

Add 4 new nullable fields to the entity:
- `cfsCutoff` — datetime nullable (CFS cargo cut-off date)
- `docCutoff` — datetime nullable (documentation cut-off date)
- `maxWeightKg` — float nullable (maximum weight capacity in kg)
- `maxVolumeCbm` — float nullable (maximum volume capacity in CBM)

- [ ] **Step 1: Add fields to Consolidation entity**

Edit `src/Entity/Consolidation.php`. After the `$uldNumber` field (line ~67) and before `$apportionmentBasis`, add:

```php
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $cfsCutoff = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $docCutoff = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $maxWeightKg = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $maxVolumeCbm = null;
```

Add getters/setters after the `getUldNumber`/`setUldNumber` pair (around line ~144):

```php
    public function getCfsCutoff(): ?\DateTimeInterface { return $this->cfsCutoff; }
    public function setCfsCutoff(?\DateTimeInterface $d): static { $this->cfsCutoff = $d; return $this; }

    public function getDocCutoff(): ?\DateTimeInterface { return $this->docCutoff; }
    public function setDocCutoff(?\DateTimeInterface $d): static { $this->docCutoff = $d; return $this; }

    public function getMaxWeightKg(): ?float { return $this->maxWeightKg; }
    public function setMaxWeightKg(?float $w): static { $this->maxWeightKg = $w; return $this; }

    public function getMaxVolumeCbm(): ?float { return $this->maxVolumeCbm; }
    public function setMaxVolumeCbm(?float $v): static { $this->maxVolumeCbm = $v; return $this; }
```

- [ ] **Step 2: Create MySQL migration**

Create `migrations/mysql/Version20260624060000.php`:

```php
<?php

declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consolidation: add cfsCutoff, docCutoff, maxWeightKg, maxVolumeCbm fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consolidation ADD COLUMN cfs_cutoff DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE consolidation ADD COLUMN doc_cutoff DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE consolidation ADD COLUMN max_weight_kg DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE consolidation ADD COLUMN max_volume_cbm DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consolidation DROP COLUMN cfs_cutoff');
        $this->addSql('ALTER TABLE consolidation DROP COLUMN doc_cutoff');
        $this->addSql('ALTER TABLE consolidation DROP COLUMN max_weight_kg');
        $this->addSql('ALTER TABLE consolidation DROP COLUMN max_volume_cbm');
    }
}
```

- [ ] **Step 3: Create SQLite migration**

Create `migrations/sqlite/Version20260624060000.php`:

```php
<?php

declare(strict_types=1);

namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Consolidation: add cfsCutoff, docCutoff, maxWeightKg, maxVolumeCbm fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consolidation ADD COLUMN cfs_cutoff DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE consolidation ADD COLUMN doc_cutoff DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE consolidation ADD COLUMN max_weight_kg REAL DEFAULT NULL');
        $this->addSql('ALTER TABLE consolidation ADD COLUMN max_volume_cbm REAL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consolidation DROP COLUMN cfs_cutoff');
        $this->addSql('ALTER TABLE consolidation DROP COLUMN doc_cutoff');
        $this->addSql('ALTER TABLE consolidation DROP COLUMN max_weight_kg');
        $this->addSql('ALTER TABLE consolidation DROP COLUMN max_volume_cbm');
    }
}
```

- [ ] **Step 4: Commit**

```bash
cd d:\Projects\make-cargo-client
git add src/Entity/Consolidation.php migrations/mysql/Version20260624060000.php migrations/sqlite/Version20260624060000.php
git commit -m "feat(consolidation): add cfsCutoff, docCutoff, maxWeightKg, maxVolumeCbm fields"
```

---

## Task 3: Depart/Arrive Endpoints + Controller Updates

**Files:**
- Modify: `src/Controller/Api/ConsolidationController.php`

Add:
1. `PATCH /{id}/depart` endpoint — validates CLOSED status, sets DEPARTED, fans out departure milestone to children
2. `PATCH /{id}/arrive` endpoint — validates DEPARTED status, sets ARRIVED, fans out arrival milestone to children
3. Handle new fields (`cfsCutoff`, `docCutoff`, `maxWeightKg`, `maxVolumeCbm`) in `hydrate()` and `serializeDetail()`
4. Inject `ShipmentMilestoneRepository` in constructor

The milestone fan-out creates a new `ShipmentMilestone` for each child shipment (upsert: update `actualDate` if record already exists, otherwise create). Source is set to `'CONSOL_AUTO'`.

Milestone codes by transport mode:
- SEA depart → `MilestoneCode::VesselDeparted`, arrive → `MilestoneCode::VesselArrived`
- AIR depart → `MilestoneCode::FlightDeparted`, arrive → `MilestoneCode::FlightArrived`
- ROAD → no milestone fan-out (just status change)

- [ ] **Step 1: Add ShipmentMilestoneRepository to constructor imports and constructor**

Open `src/Controller/Api/ConsolidationController.php`. Add to the use block:

```php
use App\Entity\ShipmentMilestone;
use App\Misc\Enum\MilestoneCode;
use App\Repository\ShipmentMilestoneRepository;
```

Update the constructor to add `ShipmentMilestoneRepository`:

```php
    public function __construct(
        private readonly ConsolidationRepository    $consolRepository,
        private readonly ShipmentRepository         $shipmentRepository,
        private readonly BranchRepository           $branchRepository,
        private readonly ClientRepository           $clientRepository,
        private readonly PortRepository             $portRepository,
        private readonly ShipmentMilestoneRepository $milestoneRepository,
    ) {}
```

- [ ] **Step 2: Add depart() and arrive() action methods**

Add these two methods after the `close()` method (after line 120):

```php
    #[Route('/{id}/depart', methods: ['PATCH'])]
    public function depart(int $id): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        if ($consol->getStatus() !== ConsolidationStatus::Closed) {
            return $this->json(['error' => 'Only a closed consolidation can be departed.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $consol->setStatus(ConsolidationStatus::Departed);
        $this->consolRepository->save($consol);

        $departureCode = match($consol->getTransportMode()) {
            'SEA'  => MilestoneCode::VesselDeparted,
            'AIR'  => MilestoneCode::FlightDeparted,
            default => null,
        };

        if ($departureCode !== null) {
            $children = $this->shipmentRepository->findBy(['consolId' => $id]);
            foreach ($children as $child) {
                $milestone = $this->milestoneRepository->findByShipmentAndCode($child->getId(), $departureCode)
                    ?? (new ShipmentMilestone())->setShipment($child)->setMilestoneCode($departureCode);
                if ($milestone->getActualDate() === null) {
                    $milestone->setActualDate(new \DateTime());
                    $milestone->setSource('CONSOL_AUTO');
                    $milestone->setUpdatedBy($this->getUser());
                    $milestone->recalculateException();
                    $this->milestoneRepository->save($milestone);
                }
            }
        }

        return $this->json($this->serializeDetail($consol));
    }

    #[Route('/{id}/arrive', methods: ['PATCH'])]
    public function arrive(int $id): JsonResponse
    {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        if ($consol->getStatus() !== ConsolidationStatus::Departed) {
            return $this->json(['error' => 'Only a departed consolidation can be marked as arrived.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $consol->setStatus(ConsolidationStatus::Arrived);
        $this->consolRepository->save($consol);

        $arrivalCode = match($consol->getTransportMode()) {
            'SEA'  => MilestoneCode::VesselArrived,
            'AIR'  => MilestoneCode::FlightArrived,
            default => null,
        };

        if ($arrivalCode !== null) {
            $children = $this->shipmentRepository->findBy(['consolId' => $id]);
            foreach ($children as $child) {
                $milestone = $this->milestoneRepository->findByShipmentAndCode($child->getId(), $arrivalCode)
                    ?? (new ShipmentMilestone())->setShipment($child)->setMilestoneCode($arrivalCode);
                if ($milestone->getActualDate() === null) {
                    $milestone->setActualDate(new \DateTime());
                    $milestone->setSource('CONSOL_AUTO');
                    $milestone->setUpdatedBy($this->getUser());
                    $milestone->recalculateException();
                    $this->milestoneRepository->save($milestone);
                }
            }
        }

        return $this->json($this->serializeDetail($consol));
    }
```

- [ ] **Step 3: Update hydrate() to handle new fields**

In the `hydrate()` method, add after the existing `eta` line:

```php
        if (array_key_exists('cfsCutoff',   $body)) $consol->setCfsCutoff($body['cfsCutoff'] ? new \DateTime($body['cfsCutoff']) : null);
        if (array_key_exists('docCutoff',   $body)) $consol->setDocCutoff($body['docCutoff'] ? new \DateTime($body['docCutoff']) : null);
        if (array_key_exists('maxWeightKg', $body)) $consol->setMaxWeightKg($body['maxWeightKg'] !== null ? (float) $body['maxWeightKg'] : null);
        if (array_key_exists('maxVolumeCbm',$body)) $consol->setMaxVolumeCbm($body['maxVolumeCbm'] !== null ? (float) $body['maxVolumeCbm'] : null);
```

- [ ] **Step 4: Update serializeDetail() to include new fields**

In the `serializeDetail()` method, add to the `array_merge` array:

```php
            'cfsCutoff'        => $c->getCfsCutoff()?->format('Y-m-d\TH:i:s'),
            'docCutoff'        => $c->getDocCutoff()?->format('Y-m-d\TH:i:s'),
            'maxWeightKg'      => $c->getMaxWeightKg(),
            'maxVolumeCbm'     => $c->getMaxVolumeCbm(),
```

Also add `createdBy` to `serializeDetail()` for completeness:
```php
            'createdBy'        => $c->getCreatedBy() ? ['id' => $c->getCreatedBy()->getId(), 'name' => $c->getCreatedBy()->getFullName()] : null,
```

- [ ] **Step 5: Commit**

```bash
cd d:\Projects\make-cargo-client
git add src/Controller/Api/ConsolidationController.php
git commit -m "feat(consolidation): add depart/arrive endpoints with milestone fan-out"
```

---

## Task 4: Cargo Manifest PDF

**Files:**
- Modify: `src/Controller/Api/ConsolidationController.php`
- Create: `templates/pdf/consolidation-manifest.html.twig`

The manifest PDF shows the consolidation header + a table of all child shipments. It follows the exact same pattern as `DangerousGoodsController::pdf()`.

- [ ] **Step 1: Add PDF-related imports and constructor params to ConsolidationController**

Add to the use block in `src/Controller/Api/ConsolidationController.php`:

```php
use App\Misc\Enum\Magnum;
use App\Repository\ProviderRepository;
use App\Service\MediaService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Translation\LocaleSwitcher;
```

Update the constructor to add:

```php
        private readonly ProviderRepository         $providerRepository,
```

(Add this as the last constructor parameter.)

- [ ] **Step 2: Add manifest PDF routes to ConsolidationController**

Add these two methods at the end of the class (before the closing brace):

```php
    #[Route('/{id}/manifest-pdf/{language}', methods: ['GET'])]
    public function manifestPdf(
        int $id, string $language,
        Request $request,
        MediaService $mediaService,
        RequestStack $requestStack
    ): StreamedResponse {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        $company  = $this->providerRepository->find(Magnum::COMPANY_PROVIDER_ID);
        $children = $this->shipmentRepository->findBy(['consolId' => $id]);

        $data = [
            'company'  => $company,
            'consol'   => $consol,
            'children' => $children,
            'basePath' => $request->getUriForPath(''),
            'filename' => 'Manifest_' . $consol->getCode() . '_' . $language . '.pdf',
            'template' => 'pdf/consolidation-manifest.html.twig',
            'renderMode' => 'pdf',
        ];

        $response = new StreamedResponse();
        $response->setCallback(function () use ($mediaService, $consol, $request, $requestStack, $data, $language): void {
            $requestStack->push($request);
            date_default_timezone_set($request->query->get('timezone', 'UTC'));
            $mediaService->generatePdf($consol, $data, $language);
        });
        return $response;
    }

    #[Route('/{id}/manifest-pdf-preview/{language}', methods: ['GET'])]
    public function manifestPdfPreview(
        int $id, string $language,
        Request $request,
        LocaleSwitcher $localeSwitcher
    ): Response {
        $consol = $this->consolRepository->find($id);
        if (!$consol) throw $this->createNotFoundException();

        $company  = $this->providerRepository->find(Magnum::COMPANY_PROVIDER_ID);
        $children = $this->shipmentRepository->findBy(['consolId' => $id]);

        $data = [
            'company'  => $company,
            'consol'   => $consol,
            'children' => $children,
            'basePath' => $request->getUriForPath(''),
            'renderMode' => 'preview',
        ];

        date_default_timezone_set($request->query->get('timezone', 'UTC'));
        $localeSwitcher->setLocale($language);
        $view = $this->renderView('pdf/consolidation-manifest.html.twig', $data);
        $localeSwitcher->reset();
        date_default_timezone_set('UTC');
        return new Response($view);
    }
```

Note: `RequestStack` use statement is already there from previous tasks. If not added yet, add:
```php
use Symfony\Component\HttpFoundation\RequestStack;
```

- [ ] **Step 3: Create manifest PDF template**

Create `templates/pdf/consolidation-manifest.html.twig`:

```twig
{% trans_default_domain 'app' %}
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8" />
  {% if basePath is defined %}<base href="{{ basePath }}" />{% endif %}
  <link rel="stylesheet" href="{{ api_path_prefix }}/build/css/pdf.css">
  <title>Cargo Manifest – {{ consol.code }}</title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 10px; color: #222; }
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
    .logo img { max-height: 48px; }
    .doc-title { text-align: right; }
    .doc-title h2 { font-size: 16px; margin: 0 0 4px; }
    .doc-title .code { font-size: 13px; font-weight: bold; color: #444; }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 16px; border: 1px solid #ddd; padding: 10px; border-radius: 4px; }
    .info-row { display: flex; gap: 4px; }
    .info-label { color: #666; min-width: 100px; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th { background: #f0f0f0; text-align: left; padding: 6px 8px; border: 1px solid #ccc; font-size: 9px; text-transform: uppercase; }
    td { padding: 6px 8px; border: 1px solid #ddd; vertical-align: top; }
    tr:nth-child(even) td { background: #fafafa; }
    .footer { margin-top: 24px; font-size: 9px; color: #999; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
  </style>
</head>
<body>

  <div class="header">
    <div class="logo">
      {% if company.logo is defined and company.logo %}
        <img src="{{ company.logo.path | imagine_filter('pdf_logo') }}" alt="Logo" />
      {% else %}
        <strong>{{ company.name }}</strong>
      {% endif %}
    </div>
    <div class="doc-title">
      <h2>CARGO MANIFEST</h2>
      <div class="code">{{ consol.code }}</div>
    </div>
  </div>

  <div class="info-grid">
    <div class="info-row"><span class="info-label">Transport Mode:</span><span>{{ consol.transportMode }}</span></div>
    <div class="info-row"><span class="info-label">Service Type:</span><span>{{ consol.serviceType }}</span></div>
    <div class="info-row"><span class="info-label">Status:</span><span>{{ consol.status.label() }}</span></div>
    <div class="info-row"><span class="info-label">Carrier:</span><span>{{ consol.carrier ? consol.carrier.name : '—' }}</span></div>
    <div class="info-row"><span class="info-label">POL:</span><span>{{ consol.pol ? consol.pol.name ~ ' (' ~ consol.pol.code ~ ')' : '—' }}</span></div>
    <div class="info-row"><span class="info-label">POD:</span><span>{{ consol.pod ? consol.pod.name ~ ' (' ~ consol.pod.code ~ ')' : '—' }}</span></div>
    <div class="info-row"><span class="info-label">ETD:</span><span>{{ consol.etd ? consol.etd|date('d/m/Y') : '—' }}</span></div>
    <div class="info-row"><span class="info-label">ETA:</span><span>{{ consol.eta ? consol.eta|date('d/m/Y') : '—' }}</span></div>

    {% if consol.transportMode == 'SEA' %}
    <div class="info-row"><span class="info-label">Vessel:</span><span>{{ consol.vessel ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">Voyage:</span><span>{{ consol.voyage ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">MBL Number:</span><span>{{ consol.mblNumber ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">Container No:</span><span>{{ consol.containerNumber ?? '—' }}</span></div>
    {% endif %}

    {% if consol.transportMode == 'AIR' %}
    <div class="info-row"><span class="info-label">Flight:</span><span>{{ consol.flightNumber ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">MAWB:</span><span>{{ consol.mawbNumber ?? '—' }}</span></div>
    <div class="info-row"><span class="info-label">ULD:</span><span>{{ consol.uldNumber ?? '—' }}</span></div>
    {% endif %}

    {% if consol.cfsCutoff %}
    <div class="info-row"><span class="info-label">CFS Cutoff:</span><span>{{ consol.cfsCutoff|date('d/m/Y H:i') }}</span></div>
    {% endif %}
    {% if consol.docCutoff %}
    <div class="info-row"><span class="info-label">Doc Cutoff:</span><span>{{ consol.docCutoff|date('d/m/Y H:i') }}</span></div>
    {% endif %}
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Job Code</th>
        <th>Client</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      {% for child in children %}
      <tr>
        <td>{{ loop.index }}</td>
        <td><strong>{{ child.code }}</strong></td>
        <td>{{ child.quote.client ? child.quote.client.name : '—' }}</td>
        <td>{{ child.status.value }}</td>
      </tr>
      {% else %}
      <tr><td colspan="4" style="text-align:center;color:#999;">No child shipments.</td></tr>
      {% endfor %}
    </tbody>
  </table>

  <div class="footer">
    Generated on {{ "now"|date("d/m/Y H:i") }} · Total shipments: {{ children|length }}
  </div>

</body>
</html>
```

- [ ] **Step 4: Commit**

```bash
cd d:\Projects\make-cargo-client
git add src/Controller/Api/ConsolidationController.php templates/pdf/consolidation-manifest.html.twig
git commit -m "feat(consolidation): add cargo manifest PDF route and template"
```

---

## Task 5: BO — ConsolidationService.js Updates

**Files:**
- Modify: `d:\Projects\make-cargo-client-bo\src\services\ConsolidationService.js`

Add `depart(id)`, `arrive(id)`, and `downloadManifestPdf(id, language)` methods. The PDF download URL embeds the auth token and email as obfuscated query params (same pattern as other PDF services: `YXV0aFRva2Vu` = base64('authToken'), `ZW1haWw` = base64('email')).

- [ ] **Step 1: Update ConsolidationService.js**

Replace the entire file content with:

```javascript
import { useAuthStore } from '@/stores/auth'

const BASE = 'consolidation'

export default {
  list (params = '') {
    return $api(`${BASE}?${params}`)
  },

  get (id) {
    return $api(`${BASE}/${id}`)
  },

  create (data) {
    return $api(BASE, { method: 'POST', body: data })
  },

  update (id, data) {
    return $api(`${BASE}/${id}`, { method: 'PUT', body: data })
  },

  close (id) {
    return $api(`${BASE}/${id}/close`, { method: 'PATCH' })
  },

  depart (id) {
    return $api(`${BASE}/${id}/depart`, { method: 'PATCH' })
  },

  arrive (id) {
    return $api(`${BASE}/${id}/arrive`, { method: 'PATCH' })
  },

  cancel (id) {
    return $api(`${BASE}/${id}`, { method: 'DELETE' })
  },

  addShipment (id, shipmentId) {
    return $api(`${BASE}/${id}/shipments`, { method: 'POST', body: { shipmentId } })
  },

  removeShipment (id, shipId) {
    return $api(`${BASE}/${id}/shipments/${shipId}`, { method: 'DELETE' })
  },

  downloadManifestPdf (id, language) {
    const auth = useAuthStore()
    const baseApiUrl = import.meta.env.VITE_API_URL
    const params = new URLSearchParams({
      YXV0aFRva2Vu: auth.token,
      ZW1haWw: auth.user?.email ?? '',
    })
    return `${baseApiUrl}/${BASE}/${id}/manifest-pdf/${language}?${params}`
  },

  previewManifestPdf (id, language) {
    const auth = useAuthStore()
    const baseApiUrl = import.meta.env.VITE_API_URL
    const params = new URLSearchParams({
      YXV0aFRva2Vu: auth.token,
      ZW1haWw: auth.user?.email ?? '',
    })
    return `${baseApiUrl}/${BASE}/${id}/manifest-pdf-preview/${language}?${params}`
  },
}
```

- [ ] **Step 2: Commit**

```bash
cd d:\Projects\make-cargo-client-bo
git add src/services/ConsolidationService.js
git commit -m "feat(consolidation): add depart, arrive, manifest PDF methods to service"
```

---

## Task 6: BO — ConsolidationDetail.vue Updates

**Files:**
- Modify: `d:\Projects\make-cargo-client-bo\src\views\consolidation\ConsolidationDetail.vue`

Changes:
1. Add Depart/Arrive buttons to the header bar (CLOSED → Depart; DEPARTED → Arrive)
2. Add confirmation dialogs for Depart and Arrive actions
3. Add `cfsCutoff`, `docCutoff`, `maxWeightKg`, `maxVolumeCbm` fields to header form
4. Update `isEditable` to restrict editing to OPEN status only
5. Add manifest PDF download menu button in manifest tab (with English/Vietnamese language options)

- [ ] **Step 1: Update the `<script setup>` section**

Replace the entire `<script setup>` content with:

```javascript
import ConsolidationService from '@/services/ConsolidationService'
import ShipmentService from '@/services/ShipmentService'

const route = useRoute()
const router = useRouter()
const consol = ref(null)
const loading = ref(false)
const activeTab = ref('header')
const saving = ref(false)
const addShipmentDialog = ref(false)
const addShipmentCode = ref('')
const addShipmentSearching = ref(false)
const addShipmentResults = ref([])
const confirmClose = ref(false)
const confirmDepart = ref(false)
const confirmArrive = ref(false)
const confirmCancel = ref(false)
const ports = ref([])
const carriers = ref([])

const id = computed(() => route.params.id)
const isEditable = computed(() => consol.value?.status === 'OPEN')
const statusColor = (s) => ({ OPEN: 'primary', CLOSED: 'success', DEPARTED: 'info', ARRIVED: 'warning', CANCELLED: 'error' }[s] ?? 'default')
const modeIcon = (m) => ({ SEA: 'tabler-ship', AIR: 'tabler-plane', ROAD: 'tabler-truck' }[m] ?? 'tabler-package')

const form = ref({})
function initForm(c) {
  form.value = {
    carrierId:        c.carrier?.id ?? null,
    polId:            c.pol?.id ?? null,
    podId:            c.pod?.id ?? null,
    vessel:           c.vessel ?? '',
    voyage:           c.voyage ?? '',
    mblNumber:        c.mblNumber ?? '',
    flightNumber:     c.flightNumber ?? '',
    mawbNumber:       c.mawbNumber ?? '',
    containerNumber:  c.containerNumber ?? '',
    uldNumber:        c.uldNumber ?? '',
    etd:              c.etd ?? '',
    eta:              c.eta ?? '',
    apportionmentBasis: c.apportionmentBasis ?? 'WEIGHT',
    cfsCutoff:        c.cfsCutoff ? c.cfsCutoff.substring(0, 16) : '',
    docCutoff:        c.docCutoff ? c.docCutoff.substring(0, 16) : '',
    maxWeightKg:      c.maxWeightKg ?? '',
    maxVolumeCbm:     c.maxVolumeCbm ?? '',
  }
}

async function load() {
  loading.value = true
  consol.value = await ConsolidationService.get(id.value)
  initForm(consol.value)
  loading.value = false
}

async function loadRefData() {
  const [portRes, clientRes] = await Promise.all([
    $api('port?limit=500'),
    $api('client?type=carrier&limit=500'),
  ])
  ports.value = portRes?.data ?? portRes ?? []
  carriers.value = clientRes?.data ?? clientRes ?? []
}

async function save() {
  saving.value = true
  consol.value = await ConsolidationService.update(id.value, form.value)
  initForm(consol.value)
  saving.value = false
}

async function doClose() {
  consol.value = await ConsolidationService.close(id.value)
  confirmClose.value = false
}

async function doDepart() {
  consol.value = await ConsolidationService.depart(id.value)
  confirmDepart.value = false
}

async function doArrive() {
  consol.value = await ConsolidationService.arrive(id.value)
  confirmArrive.value = false
}

async function doCancel() {
  await ConsolidationService.cancel(id.value)
  confirmCancel.value = false
  router.push({ name: 'consolidation' })
}

async function searchShipment() {
  if (!addShipmentCode.value.trim()) return
  addShipmentSearching.value = true
  const res = await ShipmentService.list(`code=${addShipmentCode.value.trim()}`)
  addShipmentResults.value = res?.data ?? res ?? []
  addShipmentSearching.value = false
}

async function attachShipment(shipmentId) {
  await ConsolidationService.addShipment(id.value, shipmentId)
  addShipmentDialog.value = false
  addShipmentCode.value = ''
  addShipmentResults.value = []
  await load()
}

async function detachShipment(shipId) {
  await ConsolidationService.removeShipment(id.value, shipId)
  await load()
}

function downloadManifest(language) {
  window.open(ConsolidationService.downloadManifestPdf(id.value, language), '_blank')
}

onMounted(() => {
  load()
  loadRefData()
})
```

- [ ] **Step 2: Update the `<template>` section**

Replace the entire `<template>` content with:

```html
<template>
  <VContainer fluid v-if="consol">
    <!-- Header bar -->
    <VRow align="center" class="mb-4">
      <VCol>
        <div class="d-flex align-center gap-3">
          <VBtn icon variant="text" @click="$router.push({ name: 'consolidation' })">
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <VIcon :icon="modeIcon(consol.transportMode)" size="20" />
          <h4 class="text-h5 font-weight-bold mb-0">{{ consol.code }}</h4>
          <VChip :color="statusColor(consol.status)" label size="small">{{ consol.statusLabel ?? consol.status }}</VChip>
        </div>
        <div class="text-caption text-medium-emphasis ms-12">
          {{ consol.transportMode }} · {{ consol.serviceType }} · {{ consol.branch?.name }}
        </div>
      </VCol>
      <VCol cols="auto" class="d-flex gap-2 flex-wrap">
        <VBtn v-if="consol.status === 'OPEN'" color="success" variant="tonal" prepend-icon="tabler-lock" @click="confirmClose = true">
          Close Consol
        </VBtn>
        <VBtn v-if="consol.status === 'CLOSED'" color="info" variant="tonal" prepend-icon="tabler-plane-departure" @click="confirmDepart = true">
          Mark Departed
        </VBtn>
        <VBtn v-if="consol.status === 'DEPARTED'" color="warning" variant="tonal" prepend-icon="tabler-plane-arrival" @click="confirmArrive = true">
          Mark Arrived
        </VBtn>
        <VBtn v-if="!['CANCELLED', 'ARRIVED'].includes(consol.status)" color="error" variant="text" prepend-icon="tabler-trash" @click="confirmCancel = true">
          Cancel
        </VBtn>
      </VCol>
    </VRow>

    <!-- Tabs -->
    <VTabs v-model="activeTab" class="mb-4">
      <VTab value="header"><VIcon icon="tabler-info-circle" size="16" class="me-1" />Header</VTab>
      <VTab value="manifest"><VIcon icon="tabler-list" size="16" class="me-1" />Manifest <VChip size="x-small" class="ms-1">{{ consol.childCount ?? consol.children?.length ?? 0 }}</VChip></VTab>
    </VTabs>

    <VWindow v-model="activeTab">
      <!-- Header tab -->
      <VWindowItem value="header">
        <VCard>
          <VCardText>
            <VRow>
              <VCol cols="12" md="6">
                <VAutocomplete v-model="form.polId" :items="ports" item-value="id" item-title="name" label="POL (Port of Loading)" clearable :disabled="!isEditable" />
              </VCol>
              <VCol cols="12" md="6">
                <VAutocomplete v-model="form.podId" :items="ports" item-value="id" item-title="name" label="POD (Port of Discharge)" clearable :disabled="!isEditable" />
              </VCol>
              <VCol cols="12" md="6">
                <VAutocomplete v-model="form.carrierId" :items="carriers" item-value="id" item-title="name" label="Carrier" clearable :disabled="!isEditable" />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField v-model="form.etd" type="date" label="ETD" :disabled="!isEditable" />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField v-model="form.eta" type="date" label="ETA" :disabled="!isEditable" />
              </VCol>

              <!-- Sea fields -->
              <template v-if="consol.transportMode === 'SEA'">
                <VCol cols="12" md="6">
                  <VTextField v-model="form.vessel" label="Vessel" :disabled="!isEditable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="form.voyage" label="Voyage" :disabled="!isEditable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="form.mblNumber" label="MBL Number" :disabled="!isEditable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="form.containerNumber" label="Container Number" :disabled="!isEditable" />
                </VCol>
              </template>

              <!-- Air fields -->
              <template v-if="consol.transportMode === 'AIR'">
                <VCol cols="12" md="6">
                  <VTextField v-model="form.flightNumber" label="Flight Number" :disabled="!isEditable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="form.mawbNumber" label="MAWB Number" :disabled="!isEditable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="form.uldNumber" label="ULD Number" :disabled="!isEditable" />
                </VCol>
              </template>

              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.apportionmentBasis"
                  :items="['WEIGHT', 'VOLUME', 'REVENUE_WEIGHT', 'UNITS']"
                  label="Apportionment Basis"
                  :disabled="!isEditable"
                />
              </VCol>

              <!-- Cutoff dates -->
              <VCol cols="12" md="3">
                <VTextField v-model="form.cfsCutoff" type="datetime-local" label="CFS Cutoff" :disabled="!isEditable" />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField v-model="form.docCutoff" type="datetime-local" label="Doc Cutoff" :disabled="!isEditable" />
              </VCol>

              <!-- Capacity -->
              <VCol cols="12" md="3">
                <VTextField v-model="form.maxWeightKg" type="number" label="Max Weight (kg)" :disabled="!isEditable" />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField v-model="form.maxVolumeCbm" type="number" label="Max Volume (CBM)" :disabled="!isEditable" />
              </VCol>
            </VRow>
          </VCardText>
          <VCardActions v-if="isEditable" class="justify-end">
            <VBtn color="primary" :loading="saving" @click="save">Save Changes</VBtn>
          </VCardActions>
        </VCard>
      </VWindowItem>

      <!-- Manifest tab -->
      <VWindowItem value="manifest">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between pa-4">
            <span>Child Shipments</span>
            <div class="d-flex gap-2">
              <VMenu v-if="consol.children?.length">
                <template #activator="{ props }">
                  <VBtn v-bind="props" variant="tonal" color="secondary" size="small" prepend-icon="tabler-file-type-pdf">
                    Manifest PDF
                    <VIcon icon="tabler-chevron-down" size="14" class="ms-1" />
                  </VBtn>
                </template>
                <VList density="compact">
                  <VListItem title="English" @click="downloadManifest('en')" />
                  <VListItem title="Vietnamese" @click="downloadManifest('vi')" />
                </VList>
              </VMenu>
              <VBtn v-if="isEditable" color="primary" size="small" prepend-icon="tabler-plus" @click="addShipmentDialog = true">
                Add Shipment
              </VBtn>
            </div>
          </VCardTitle>
          <VTable>
            <thead>
              <tr>
                <th>Job Code</th>
                <th>Status</th>
                <th>Client</th>
                <th v-if="isEditable"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!consol.children?.length">
                <td :colspan="isEditable ? 4 : 3" class="text-center text-medium-emphasis pa-4">
                  No shipments in this consolidation yet.
                </td>
              </tr>
              <tr
                v-for="child in consol.children"
                :key="child.id"
                class="cursor-pointer"
                @click="$router.push({ name: 'shipment-id-tab1-tab2', params: { id: child.id, tab1: 'overview', tab2: 'detail' } })"
              >
                <td><span class="font-weight-medium">{{ child.code }}</span></td>
                <td><VChip size="small" label>{{ child.status }}</VChip></td>
                <td>{{ child.client ?? '—' }}</td>
                <td v-if="isEditable">
                  <VBtn icon variant="text" size="small" color="error" @click.stop="detachShipment(child.id)">
                    <VIcon icon="tabler-x" size="16" />
                  </VBtn>
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCard>
      </VWindowItem>
    </VWindow>
  </VContainer>

  <VContainer v-else-if="loading" class="d-flex justify-center pa-8">
    <VProgressCircular indeterminate />
  </VContainer>

  <!-- Add Shipment Dialog -->
  <VDialog v-model="addShipmentDialog" max-width="500">
    <VCard title="Add Shipment to Consolidation">
      <VCardText>
        <VRow>
          <VCol cols="12">
            <VTextField
              v-model="addShipmentCode"
              label="Search by Job Code"
              placeholder="e.g. OEX-2025-001"
              append-inner-icon="tabler-search"
              @click:append-inner="searchShipment"
              @keyup.enter="searchShipment"
            />
          </VCol>
        </VRow>
        <VList v-if="addShipmentResults.length">
          <VListItem
            v-for="s in addShipmentResults"
            :key="s.id"
            :title="s.code"
            :subtitle="s.status"
            @click="attachShipment(s.id)"
            class="cursor-pointer"
          />
        </VList>
        <div v-else-if="addShipmentSearching" class="text-center pa-4">
          <VProgressCircular indeterminate size="24" />
        </div>
        <div v-else-if="addShipmentCode" class="text-center text-medium-emphasis pa-2">No results.</div>
      </VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" @click="addShipmentDialog = false">Close</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Close confirmation -->
  <VDialog v-model="confirmClose" max-width="400">
    <VCard title="Close Consolidation?">
      <VCardText>This will write the MBL/MAWB number to all child shipments and lock the consolidation.</VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" @click="confirmClose = false">Cancel</VBtn>
        <VBtn color="success" @click="doClose">Confirm Close</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Depart confirmation -->
  <VDialog v-model="confirmDepart" max-width="400">
    <VCard title="Mark as Departed?">
      <VCardText>This will set the consolidation status to DEPARTED and automatically record a departure milestone on all child shipments.</VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" @click="confirmDepart = false">Cancel</VBtn>
        <VBtn color="info" @click="doDepart">Confirm Departed</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Arrive confirmation -->
  <VDialog v-model="confirmArrive" max-width="400">
    <VCard title="Mark as Arrived?">
      <VCardText>This will set the consolidation status to ARRIVED and automatically record an arrival milestone on all child shipments.</VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" @click="confirmArrive = false">Cancel</VBtn>
        <VBtn color="warning" @click="doArrive">Confirm Arrived</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>

  <!-- Cancel confirmation -->
  <VDialog v-model="confirmCancel" max-width="400">
    <VCard title="Cancel Consolidation?">
      <VCardText>This action cannot be undone. All child shipments must be removed first.</VCardText>
      <VCardActions class="justify-end">
        <VBtn variant="text" @click="confirmCancel = false">Back</VBtn>
        <VBtn color="error" @click="doCancel">Cancel Consolidation</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
```

- [ ] **Step 3: Commit**

```bash
cd d:\Projects\make-cargo-client-bo
git add src/views/consolidation/ConsolidationDetail.vue
git commit -m "feat(consolidation): add depart/arrive buttons, cutoff/capacity fields, manifest PDF download"
```

---

## Task 7: Guide Document

**Files:**
- Create: `docs/guides/consolidation.md` (in API project)

- [ ] **Step 1: Create the guide**

Create `d:\Projects\make-cargo-client\docs\guides\consolidation.md`:

```markdown
# Consolidation Management Guide

This guide covers the consolidation (LCL/groupage) management feature: entity structure, API endpoints, status lifecycle, and BO UI.

---

## Architecture Overview

Consolidation is a standalone feature — `ConsolidationController` extends `AbstractController` (not `CrudController`) and serializes manually. There is no BaseService layer. Repositories are injected directly.

**API:** `src/Controller/Api/ConsolidationController.php`  
**Entity:** `src/Entity/Consolidation.php`  
**Repository:** `src/Repository/ConsolidationRepository.php`  
**BO Service:** `src/services/ConsolidationService.js`  
**BO View:** `src/views/consolidation/ConsolidationDetail.vue`

---

## Entity Fields

| Field | Type | Notes |
|-------|------|-------|
| `code` | string(64) | Auto-generated: `CONSOL-{MODE}-{YYYYMM}-{NNN}` |
| `transportMode` | string(8) | `SEA`, `AIR`, `ROAD` |
| `serviceType` | string(16) | e.g. `FCL`, `LCL`, `AIR` |
| `status` | ConsolidationStatus | `OPEN` → `CLOSED` → `DEPARTED` → `ARRIVED` |
| `branch` | ManyToOne → Branch | Required |
| `carrier` | ManyToOne → Client | nullable, onDelete SET NULL |
| `pol` / `pod` | ManyToOne → Port | nullable |
| `etd` / `eta` | date | nullable |
| `vessel` / `voyage` | string | SEA fields, nullable |
| `mblNumber` | string(32) | nullable |
| `flightNumber` | string(16) | AIR field, nullable |
| `mawbNumber` | string(32) | AIR field, nullable |
| `containerNumber` | string(16) | SEA field, nullable |
| `uldNumber` | string(32) | AIR field, nullable |
| `apportionmentBasis` | string(16) | `WEIGHT`, `VOLUME`, `REVENUE_WEIGHT`, `UNITS` |
| `cfsCutoff` | datetime | CFS cargo cut-off, nullable |
| `docCutoff` | datetime | Documentation cut-off, nullable |
| `maxWeightKg` | float | Max capacity in kg, nullable |
| `maxVolumeCbm` | float | Max capacity in CBM, nullable |

Child shipments reference the consolidation via `Shipment.consolId` (bare integer FK, no Doctrine relation object).

---

## API Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/consolidation` | List all (filterable by `status`, `transportMode`) |
| POST | `/consolidation` | Create new (requires `transportMode`, `serviceType`, `branchId`) |
| GET | `/consolidation/{id}` | Get detail with children array |
| PUT | `/consolidation/{id}` | Update header fields |
| DELETE | `/consolidation/{id}` | Cancel (requires no active children) |
| PATCH | `/consolidation/{id}/close` | OPEN → CLOSED (writes MBL/MAWB to children) |
| PATCH | `/consolidation/{id}/depart` | CLOSED → DEPARTED (fans out departure milestone) |
| PATCH | `/consolidation/{id}/arrive` | DEPARTED → ARRIVED (fans out arrival milestone) |
| POST | `/consolidation/{id}/shipments` | Attach shipment by `{ shipmentId }` |
| DELETE | `/consolidation/{id}/shipments/{shipId}` | Detach shipment |
| GET | `/consolidation/{id}/manifest-pdf/{language}` | Stream manifest PDF |
| GET | `/consolidation/{id}/manifest-pdf-preview/{language}` | HTML preview for iframe |

---

## Status Lifecycle

```
OPEN → (close) → CLOSED → (depart) → DEPARTED → (arrive) → ARRIVED
 ↓ (cancel at any point except ARRIVED)
CANCELLED
```

**Status rules:**
- `OPEN`: Fully editable. Can add/remove shipments.
- `CLOSED`: Read-only. MBL/MAWB has been written to children.
- `DEPARTED`: Read-only. Departure milestone auto-created on children (SEA: `VESSEL_DEPARTED`, AIR: `FLIGHT_DEPARTED`).
- `ARRIVED`: Read-only. Arrival milestone auto-created on children (SEA: `VESSEL_ARRIVED`, AIR: `FLIGHT_ARRIVED`).
- `CANCELLED`: Requires all active children to be removed first.

---

## Milestone Fan-Out

When `depart` or `arrive` is called:
1. All child shipments are fetched via `findBy(['consolId' => $id])`
2. For each child, the milestone record is upserted (`findByShipmentAndCode` or new)
3. `actualDate` is only set if it's currently null (does not overwrite manual entries)
4. `source` is set to `'CONSOL_AUTO'`

Milestone codes:
- SEA depart → `VESSEL_DEPARTED`
- SEA arrive → `VESSEL_ARRIVED`
- AIR depart → `FLIGHT_DEPARTED`
- AIR arrive → `FLIGHT_ARRIVED`
- ROAD → no automatic milestone (status changes only)

---

## Cargo Manifest PDF

**Route:** `GET /consolidation/{id}/manifest-pdf/{language}`

**Template:** `templates/pdf/consolidation-manifest.html.twig`

**Data passed:**
| Key | Source |
|-----|--------|
| `company` | Provider #1 (Magnum::COMPANY_PROVIDER_ID) |
| `consol` | Consolidation entity |
| `children` | All Shipment entities with consolId = id |
| `basePath` | Request URI for base tag |
| `filename` | `Manifest_{code}_{language}.pdf` |

**BO download:** `ConsolidationService.downloadManifestPdf(id, language)` — returns signed URL opened in new tab. Available via the "Manifest PDF" menu in the manifest tab, shown when at least one child shipment exists.

---

## Migrations

| Version | Description |
|---------|-------------|
| `Version20260622140000` | Create consolidation table, add consolId/parentJobId to shipment |
| `Version20260624060000` | Add cfsCutoff, docCutoff, maxWeightKg, maxVolumeCbm to consolidation |

Both MySQL and SQLite migrations exist in `migrations/mysql/` and `migrations/sqlite/`.
```

- [ ] **Step 2: Commit**

```bash
cd d:\Projects\make-cargo-client
git add docs/guides/consolidation.md
git commit -m "docs: add consolidation management guide"
```

---

## Self-Review

**Spec coverage:**
- ✅ Serializer groups YAML (Task 1)
- ✅ cfsCutoff, docCutoff, maxWeightKg, maxVolumeCbm entity fields (Task 2)
- ✅ MySQL + SQLite migrations for new fields (Task 2)
- ✅ Depart endpoint with milestone fan-out (Task 3)
- ✅ Arrive endpoint with milestone fan-out (Task 3)
- ✅ hydrate() handles new fields (Task 3)
- ✅ serializeDetail() exposes new fields (Task 3)
- ✅ Cargo Manifest PDF route (Task 4)
- ✅ Cargo Manifest PDF template (Task 4)
- ✅ BO: depart/arrive methods in service (Task 5)
- ✅ BO: manifest PDF download method in service (Task 5)
- ✅ BO: Depart/Arrive buttons with confirmations (Task 6)
- ✅ BO: cfsCutoff, docCutoff, maxWeightKg, maxVolumeCbm form fields (Task 6)
- ✅ BO: isEditable restricted to OPEN only (Task 6)
- ✅ BO: Manifest PDF download menu in manifest tab (Task 6)
- ✅ Guide document (Task 7)

**Not in scope (requires full proportional charge spec):**
- Proportional charge allocation on addShipment/removeShipment — left for a dedicated spec
