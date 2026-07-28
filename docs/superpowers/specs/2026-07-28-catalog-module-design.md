# Catalog Module Design

**Date:** 2026-07-28
**Scope:** Branch → Location rename + full Catalog module (ProductCategory, Product, ModifierGroup, Modifier, ProductModifierGroup, ProductModifier)

---

## 0. Phase 0: Branch → Location Rename

Before building the Catalog module, rename `Branch` → `Location` throughout both repos to align code terminology with POS product language.

### Backend (`make-pos-client`)

| Old | New |
|-----|-----|
| `Branch` entity | `Location` entity |
| `branch` DB table | `location` DB table |
| `branch_id` FK columns | `location_id` FK columns |
| `BranchRepository` | `LocationRepository` |
| `BranchController` | `LocationController` |
| `BranchService` | `LocationService` |
| `$branch` properties | `$location` properties |
| `/branch` API route prefix | `/location` API route prefix |

A new Doctrine migration renames the `branch` table to `location` and updates all `branch_id` foreign key columns to `location_id`.

### Frontend (`make-pos-client-bo`)

| Old | New |
|-----|-----|
| `BranchService.js` | `LocationService.js` |
| `src/pages/setting/branch.vue` | `src/pages/setting/location.vue` |
| All "Branch" UI labels | "Location" |
| All `/branch` API calls | `/location` |

---

## 1. Architecture

The Catalog module lives in `src/Module/Catalog/` (backend) and `src/pages/product/` (frontend).

**Catalog is per-location:** every entity (ProductCategory, Product, ModifierGroup) carries a `location_id` FK. There is no shared org-level catalog — each location manages its own independent product set.

**Modifier architecture (hybrid):**
- `ModifierGroup` + `Modifier` — reusable groups defined per-location, attachable to any F&B product
- `ProductModifierGroup` — join table attaching an entire group to a product
- `ProductModifier` — custom one-off modifiers defined directly on a product (not part of any group)

---

## 2. Data Model

### ProductCategory

```
id               int, PK, auto-increment
location_id      int, FK → location.id, NOT NULL
parent_id        int, FK → product_category.id, nullable (null = top-level)
name             varchar(255), NOT NULL
position         int, NOT NULL, default 0
```

**Constraint:** max 2 levels. A category with `parent_id IS NOT NULL` cannot itself be a parent (enforced at service layer).

### Product

```
id               int, PK, auto-increment
location_id      int, FK → location.id, NOT NULL
category_id      int, FK → product_category.id, nullable
image_id         int, FK → media.id, nullable
sku              varchar(64), NOT NULL, UNIQUE per location_id
name             varchar(255), NOT NULL
description      longtext, nullable
price            decimal(20,6), NOT NULL
cost             decimal(20,6), nullable
type             varchar(8), NOT NULL  — 'retail' | 'food'
active           tinyint(1), NOT NULL, default 1
created_at       datetime, NOT NULL
updated_at       datetime, NOT NULL
```

### ModifierGroup

```
id               int, PK, auto-increment
location_id      int, FK → location.id, NOT NULL
name             varchar(255), NOT NULL
required         tinyint(1), NOT NULL, default 0
min              int, NOT NULL, default 0
max              int, nullable (null = unlimited)
position         int, NOT NULL, default 0
```

### Modifier

```
id               int, PK, auto-increment
group_id         int, FK → modifier_group.id, NOT NULL
name             varchar(255), NOT NULL
price_delta      decimal(20,6), NOT NULL, default 0
position         int, NOT NULL, default 0
```

### ProductModifierGroup (join)

```
product_id        int, FK → product.id, NOT NULL
modifier_group_id int, FK → modifier_group.id, NOT NULL
PRIMARY KEY (product_id, modifier_group_id)
```

### ProductModifier (per-product custom)

```
id               int, PK, auto-increment
product_id       int, FK → product.id, NOT NULL
name             varchar(255), NOT NULL
price_delta      decimal(20,6), NOT NULL, default 0
position         int, NOT NULL, default 0
```

---

## 3. Backend Module Structure

```
src/Module/Catalog/
  Entity/
    ProductCategory.php
    Product.php
    ModifierGroup.php
    Modifier.php
    ProductModifier.php
  Enum/
    ProductType.php          — retail | food
  Repository/
    ProductCategoryRepository.php
    ProductRepository.php
    ModifierGroupRepository.php
    ModifierRepository.php
    ProductModifierRepository.php
  Controller/
    ProductCategoryController.php
    ProductController.php
    ModifierGroupController.php
  Service/
    CatalogService.php       — business logic (depth check, SKU uniqueness, location scoping)
```

All controllers extend `CrudController`, use `#[Route]` PHP attributes, and are guarded with `#[IsGranted('ROLE_USER')]` + `#[AppModule('catalog')]`.

---

## 4. API Endpoints

All endpoints are location-scoped: the authenticated user's location_id is applied automatically.

### Product Categories

| Method | Path | Description |
|--------|------|-------------|
| GET | `/product-category` | List all categories for location (nested tree: parents with children array) |
| POST | `/product-category` | Create category |
| GET | `/product-category/{id}` | Get one |
| PUT | `/product-category/{id}` | Update |
| DELETE | `/product-category/{id}` | Delete (fails if products are assigned) |

### Products

| Method | Path | Description |
|--------|------|-------------|
| GET | `/product` | List products (filters: `category_id`, `type`, `active`, `q`) |
| POST | `/product` | Create product |
| GET | `/product/{id}` | Get product with attached modifier groups + custom modifiers |
| PUT | `/product/{id}` | Update product |
| DELETE | `/product/{id}` | Delete product |

### Modifier Groups

| Method | Path | Description |
|--------|------|-------------|
| GET | `/modifier-group` | List all groups for location (includes modifiers array) |
| POST | `/modifier-group` | Create group (with optional nested modifiers) |
| GET | `/modifier-group/{id}` | Get group with modifiers |
| PUT | `/modifier-group/{id}` | Update group |
| DELETE | `/modifier-group/{id}` | Delete group (detaches from all products first) |

### Modifiers (within a group)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/modifier-group/{id}/modifier` | Add modifier to group |
| PUT | `/modifier-group/{id}/modifier/{mid}` | Update modifier |
| DELETE | `/modifier-group/{id}/modifier/{mid}` | Delete modifier |

### Product–Modifier Relationships

| Method | Path | Description |
|--------|------|-------------|
| POST | `/product/{id}/modifier-group/{gid}` | Attach modifier group to product |
| DELETE | `/product/{id}/modifier-group/{gid}` | Detach modifier group from product |
| POST | `/product/{id}/modifier` | Add custom modifier to product |
| PUT | `/product/{id}/modifier/{mid}` | Update custom modifier |
| DELETE | `/product/{id}/modifier/{mid}` | Delete custom modifier |

---

## 5. Frontend Pages

**Working directory:** `make-pos-client-bo`

### `src/pages/product/index.vue` — Product List
- Data table: image thumbnail, name, SKU, category, type badge (retail/food), price, active toggle
- Filters: category dropdown, type (all/retail/food), active/inactive, search input (name or SKU)
- Row actions: edit (navigates to `[id].vue`), delete (with confirmation dialog)
- Header: "Add Product" button

### `src/pages/product/[id].vue` — Product Create/Edit Form
- Fields: name, SKU (auto-generate option), category (dropdown), type (retail/food segmented button), price, cost, description (textarea), image upload, active toggle
- F&B section (visible only when `type === 'food'`):
  - Modifier Groups: multi-select from existing groups in this location
  - Custom Modifiers: inline add/edit/delete table (name, price delta)
- Footer: Save / Cancel

### `src/pages/product/categories.vue` — Category Management
- Two-column layout: left panel = parent categories, right panel = subcategories of selected parent
- Inline create/rename/delete for both levels
- Drag-to-reorder within each column (updates `position` field)

### `src/pages/product/modifier-groups.vue` — Modifier Group Management
- Expandable list of groups
- Group header: name, required badge, min/max, edit/delete buttons
- Expanded body: table of modifiers (name, price delta, position, edit/delete)
- "Add Group" and "Add Modifier" actions

### `src/services/CatalogService.js`
Replaces the existing placeholder. Implements:
- `listCategories(params)`, `createCategory(data)`, `updateCategory(data)`, `deleteCategory(id)`
- `listProducts(params)`, `createProduct(data)`, `getProduct(id)`, `updateProduct(data)`, `deleteProduct(id)`
- `listModifierGroups(params)`, `createModifierGroup(data)`, `updateModifierGroup(data)`, `deleteModifierGroup(id)`
- `addModifier(groupId, data)`, `updateModifier(groupId, mid, data)`, `deleteModifier(groupId, mid)`
- `attachModifierGroup(productId, groupId)`, `detachModifierGroup(productId, groupId)`
- `addProductModifier(productId, data)`, `updateProductModifier(productId, mid, data)`, `deleteProductModifier(productId, mid)`

Follows the same `$api()` pattern as `ClientService.js`.

---

## 6. Business Rules

- **SKU uniqueness:** enforced per-location (two locations can have the same SKU independently)
- **Category depth:** service layer rejects creating a subcategory under a category that already has a parent
- **Category deletion:** blocked if any products are assigned to that category
- **Modifier group deletion:** cascades detach from all `ProductModifierGroup` join records; does not delete the products themselves
- **type = retail products:** modifier group attachment and custom modifier endpoints return 400 if attempted on a retail product
- **Active flag:** inactive products are hidden from the POS terminal (future) but remain visible in back-office

---

## 7. Migration Strategy

Two migrations required:
1. **Phase 0 migration:** `ALTER TABLE branch RENAME TO location` + rename all `branch_id` FK columns to `location_id`
2. **Catalog migration:** `CREATE TABLE product_category`, `product`, `modifier_group`, `modifier`, `product_modifier_group`, `product_modifier`
