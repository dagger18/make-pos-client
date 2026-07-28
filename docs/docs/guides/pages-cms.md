# Pages (CMS) — Setup Guide

## Overview

The Pages feature provides a content management layer for the back-office. Each "Page" is a named, orderable container that holds **Components** — data tables and charts driven by Datasets. Pages are categorised by type to control where they appear in the UI.

---

## Page Types

There are two groups of page types:

### Report pages
Appear as tabs inside the Reports section of the BO.

| Type | Where used |
|------|-----------|
| `report-shipment` | Reports → Shipment Report |
| `report-staff` | Reports → Staff Report |
| `report-charge` | Reports → Charge Report |

### Manage pages
Reserved for configurable list views on entity management screens (shipments, clients, etc.). These types exist in the API and can have pages/components configured now; display integration per screen is added separately.

| Type | Intended screen |
|------|----------------|
| `manage-shipment` | Shipment list |
| `manage-client` | Client list |
| `manage-provider` | Provider list |
| `manage-quote` | Quote list |
| `manage-accounting` | Accounting list |
| `manage-notification` | Notification list |

---

## API Endpoints

All page operations go through the `/page` resource:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/page?filter_type=report-shipment&sort_asc=orderNumber` | List pages by type, ordered |
| POST | `/page` | Create a page (`name`, `type`) |
| PUT | `/page/{id}` | Update page (`name`) |
| DELETE | `/page/{id}` | Delete a page (cascades to components) |
| POST | `/page/re-order` | Reorder pages (`pages` = ordered array of IDs) |

Components live at `/component` and are filtered by `filter_page={pageId}`.

---

## BO Administration

Navigate to **Settings → Pages** in the back-office.

### Managing Pages

The admin table shows all pages across all types. Use the type filter chips at the top to narrow the view by type group.

**Actions per row:**
- **↑ / ↓** — move the page up or down within its type (calls re-order)
- **⊞** (grid icon) — open the Component Editor dialog for this page
- **pencil** — rename the page
- **trash** — delete the page and all its components

### Creating a Page

1. Click **New Page**
2. Enter a name (e.g. "Export Overview")
3. Select the page type from the dropdown — type cannot be changed after creation
4. Click **Save**

### Adding Components to a Page

1. Click the grid icon (⊞) on a page row — opens the **Component Editor** dialog
2. Click **Add Component** in the dialog header
3. Fill in the component form:
   - **Dataset** — the data source (from Reports → Dataset)
   - **Type** — Table / ColumnLine / Pie / AreaStacked / HeatMap
   - **Title** — optional display title (defaults to dataset name)
   - **Column Width** — 1–12 grid units (12 = full width)
   - **Row / Order** — controls layout position within the page
4. For chart types: configure **Series** (which dataset columns to plot, colours)
5. Submit — the component appears in the editor preview

### Reordering Pages

Use the ↑ / ↓ arrows in the table to change a page's position within its type. Order is shared across all users viewing that page type — this is a global admin setting.

Drag-and-drop reordering is also available within the Report dashboard tabs (Reports → Shipment Report etc.).

---

## Component Types

| Type | Description |
|------|-------------|
| `Table` | Filterable data table with optional export |
| `ColumnLine` | Bar/column and line combination chart |
| `Pie` | Pie or donut chart |
| `AreaStacked` | Stacked area chart |
| `HeatMap` | Heatmap for frequency/density data |

---

## Report Dashboard vs. Settings Admin

| Feature | Report Pages (in Reports menu) | Settings → Pages |
|---------|-------------------------------|-----------------|
| Scope | `report-*` types only | All types |
| Drag-and-drop tab order | ✅ | — |
| Create / rename pages | ✅ | ✅ |
| Component editor | ✅ (inline) | ✅ (dialog) |
| Manage `manage-*` types | ❌ | ✅ |

Use **Settings → Pages** as the primary admin. The report dashboards provide the end-user view with drag-and-drop reordering.
