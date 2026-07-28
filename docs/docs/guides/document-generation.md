# Document Generation Guide

This guide covers all PDF document generation features in the client API. Each document type follows the same pattern: a Twig template rendered via `PdfActionTrait` (or a manual route), with data prepared in a `pdfData()` service method.

---

## Architecture Overview

### API Pattern

1. **Service** — implements `pdfData(mixed $entity, Request $request, string $language): array`
2. **Controller** — extends `CrudController` and uses `PdfActionTrait` (or manual routes)
3. **Template** — `templates/pdf/<name>.html.twig`

`PdfActionTrait` provides two routes automatically:
- `GET /pdf/{language}/{id}` — streams the PDF file
- `GET /pdf-preview/{language}/{id}` — returns rendered HTML for iframe preview

### BO Pattern

Each document has a JS service in `src/services/` with:
- `downloadPdf(...)` — returns a signed URL to open in a new tab
- `previewPdf(...)` — returns a signed URL for iframe `src`

Auth tokens are embedded as obfuscated query params (`YXV0aFRva2Vu`, `ZW1haWw`).

---

## House Bill of Lading (HBL)

**Routes:** `GET /instruction/pdf/{language}/{id}`, `GET /instruction/pdf-preview/{language}/{id}`

**Service:** `InstructionService::pdfData()`

**Template:** `templates/pdf/instruction.html.twig`

**Document number format:** `PMD` + `booking.etd.format('Ymd')` + `parseNumberFromCode(shipment)`

**Data passed to template:**
| Key | Source |
|-----|--------|
| `company` | Provider #1 (Magnum::COMPANY_PROVIDER_ID) |
| `instruction` | The Instruction entity |
| `booking` | `shipment.booking` |
| `documentNo` | Computed from ETD + shipment code |

**BO:** HBL Preview tab in `ShipmentInfo.vue` → `InstructionPreview.vue`

---

## House Airway Bill (HAWB)

**Routes:** `GET /instruction/hawb-pdf/{language}/{id}`, `GET /instruction/hawb-pdf-preview/{language}/{id}`

**Service:** `InstructionService::hawbPdfData()`

**Template:** `templates/pdf/hawb.html.twig`

**Document number format:** `HAWB` + `parseNumberFromCode(shipment)`

**BO:** Download buttons in `InstructionPreview.vue` (visible for all transport types — guard with transport type check in BO if needed)

**Note:** Uses air-specific fields from `Instruction` entity: `flight1`, `flight1Date`, `toCarrier1`, `agentIataCode`, `chargeableWeight`, `rateClass`, etc.

---

## Packing List

**Routes:** `GET /instruction/packing-list-pdf/{language}/{id}`, `GET /instruction/packing-list-pdf-preview/{language}/{id}`

**Service:** `InstructionService::packingListPdfData()`

**Template:** `templates/pdf/packing-list.html.twig`

**Document number format:** `PKL` + `parseNumberFromCode(shipment)`

**Template logic:** Shows container table if `instruction.containers` is non-empty; otherwise shows a simple package summary row.

**BO:** Download button in `InstructionPreview.vue`

---

## Arrival Notice

**Routes (CRUD + PDF):** `/arrival-notice` via `ArrivalNoticeController` (extends `CrudController` + `PdfActionTrait`)

**Entity:** `App\Entity\ArrivalNotice`

| Field | Type | Notes |
|-------|------|-------|
| `shipment` | ManyToOne → Shipment | FK, CASCADE DELETE |
| `issueDate` | datetime | nullable |
| `consigneeName` | string(255) | nullable |
| `consigneeAddress` | text | nullable |
| `destinationChargesNote` | text | nullable |
| `requiredDocuments` | json | nullable array of strings |
| `note` | text | nullable |

**Migrations:** `Version20260624040000` (MySQL + SQLite)

**Service:** `ArrivalNoticeService::pdfData()`

**Template:** `templates/pdf/arrival-notice.html.twig`

**BO:**
- Service: `src/services/ArrivalNoticeService.js`
- View: `src/views/shipment/ArrivalNoticePanel.vue` — form + Download PDF menu
- Tab: Added to `ShipmentDetail.vue` under permission `MANAGE_Instruction / Shipment`

**Saving:** The BO sends `{ shipment: shipmentId, ...fields }`. The `DoctrineEntityDenormalizer` resolves the shipment ID to a Shipment proxy automatically. No `parentType/parentProperty` pattern needed.

**Listing:** BO filters with `filter_shipment=<shipmentId>` query param.

---

## Delivery Order

**Routes (CRUD + PDF):** `/delivery-order` via `DeliveryOrderController` (extends `CrudController` + `PdfActionTrait`)

**Entity:** `App\Entity\DeliveryOrder`

| Field | Type | Notes |
|-------|------|-------|
| `shipment` | ManyToOne → Shipment | FK, CASCADE DELETE |
| `issueDate` | datetime | nullable |
| `consigneeName` | string(255) | nullable |
| `consigneeAddress` | text | nullable |
| `releaseDate` | datetime | nullable |
| `releaseNote` | text | nullable |
| `note` | text | nullable |

**Migrations:** `Version20260624050000` (MySQL + SQLite)

**Service:** `DeliveryOrderService::pdfData()`

**Template:** `templates/pdf/delivery-order.html.twig`

**BO:**
- Service: `src/services/DeliveryOrderService.js`
- View: `src/views/shipment/DeliveryOrderPanel.vue` — form + Download PDF menu
- Tab: Added to `ShipmentDetail.vue` under permission `MANAGE_Instruction / Shipment`

---

## Dangerous Goods Declaration

**Routes:** Added manually to `DangerousGoodsController` (does **not** extend `CrudController`):
- `GET /shipment/{shipmentId}/dangerous-goods/pdf/{language}`
- `GET /shipment/{shipmentId}/dangerous-goods/pdf-preview/{language}`

**Template:** `templates/pdf/dangerous-goods.html.twig`

**Data passed to template:**
| Key | Source |
|-----|--------|
| `company` | Provider #1 |
| `shipment` | Shipment entity |
| `booking` | `shipment.booking` |
| `dgList` | All `DangerousGoods` records for the shipment |

**BO:**
- `DangerousGoodsService.downloadPdf(shipment, language)` — opens PDF in new tab
- Download button added to `DangerousGoodsPanel.vue` (appears when dgList is non-empty)

---

## Adding a New Document Type

1. **If new entity needed:**
   - Create `src/Entity/<Name>.php` implementing `SubEntity`, using `EntityDateTimeAbleTrait`
   - Create `src/Repository/<Name>Repository.php` extending `BaseRepository`
   - Create MySQL + SQLite migrations in `migrations/mysql/` and `migrations/sqlite/`
   - Create `config/serializer_groups/<Name>.yaml`
   - Register service in `config/services.yaml` under `app.auto_service_locator`

2. **Create service** `src/Service/<Name>Service.php`:
   ```php
   class <Name>Service extends BaseService {
       public function __construct(
           protected BaseService $baseService,
           public <Name>Repository $repository,
       ) { $this->reflectFromParent($baseService); }

       public function pdfData(mixed $entity, Request $request, string $language): array {
           $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
           return [
               'company' => $company,
               'basePath' => $request->getUriForPath(''),
               'filename' => '<Name>_' . $language . '.pdf',
               'template' => 'pdf/<name>.html.twig',
           ];
       }
   }
   ```

3. **Create controller** extending `CrudController` with `PdfActionTrait`

4. **Create template** `templates/pdf/<name>.html.twig` — use `api_path_prefix` for CSS links, `company.logo.path | imagine_filter('pdf_logo')` for logo

5. **BO service** — add `downloadPdf()` and `previewPdf()` methods with obfuscated auth params

6. **BO view** — `<Name>Panel.vue` with form + download menu

---

## Template Conventions

- CSS: `<link rel="stylesheet" href="{{ api_path_prefix }}/build/css/pdf.css">`
- Logo: `<img src="{{ company.logo.path | imagine_filter('pdf_logo') }}" />`
- Twig filters available: `summarizeContainers()`, `printPort()`, `removeContentInsideRoundBrackets()`, `format_money()`, `imagine_filter()`
- Use `white-space: pre-wrap` for address/notes fields that may contain newlines
- Always include `{% trans_default_domain 'app' %}` at the top
- Check `basePath` before setting `<base>` tag: `{% if basePath is defined %}<base href="{{ basePath }}" />{% endif %}`
