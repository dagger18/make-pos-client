# Feature 3: Dangerous Goods (DangerousGoods)

## Overview

A self-contained sub-object on the shipment for hazardous/DG cargo. When a shipment carries dangerous goods the operator must declare IMO class, UN number, packing group, and related quantities. This data drives surcharge application, carrier acceptance validation, and required additional documentation (DG Declaration, MSDS). Reference standard: IMDG Code (sea), IATA DGR (air), ADR (road).

All major platforms implement this as a separate table: CargoWise `eDGDeclaration`, Magaya `DangerousGoods`, Descartes `HazmatDetail`.

---

## Data Model

```sql
CREATE TABLE dangerous_goods (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id         INT           NOT NULL REFERENCES shipment(id) ON DELETE CASCADE,

  -- Classification
  imo_class           VARCHAR(8)    NOT NULL,   -- 1 | 2 | 2.1 | 2.2 | 2.3 | 3 | 4.1 | 4.2 | 4.3 | 5.1 | 5.2 | 6.1 | 6.2 | 7 | 8 | 9
  un_number           VARCHAR(8)    NOT NULL,   -- UN1263, UN1950, UN3480 etc.
  packing_group       VARCHAR(4)    DEFAULT NULL, -- I | II | III (not applicable for class 1, 2, 7)
  proper_name         VARCHAR(255)  NOT NULL,   -- official IMDG/IATA proper shipping name
  technical_name      VARCHAR(255)  DEFAULT NULL, -- required when proper name is generic (e.g. "flammable liquid, N.O.S.")

  -- Quantity
  net_quantity        DECIMAL(12,3) DEFAULT NULL,
  gross_quantity      DECIMAL(12,3) DEFAULT NULL,
  uom                 VARCHAR(16)   DEFAULT NULL,  -- KG | L | units

  -- Physical properties
  flash_point         DECIMAL(6,2)  DEFAULT NULL,  -- °C, required for class 3
  subsidiary_risk     VARCHAR(16)   DEFAULT NULL,  -- secondary hazard class if applicable

  -- Flags
  is_marine_pollutant TINYINT(1)    NOT NULL DEFAULT 0,
  is_limited_qty      TINYINT(1)    NOT NULL DEFAULT 0,  -- limited quantity exemption
  is_excepted_qty     TINYINT(1)    NOT NULL DEFAULT 0,  -- excepted quantity exemption

  -- Emergency / compliance
  emergency_contact   VARCHAR(128)  DEFAULT NULL,  -- 24h emergency number
  msds_url            TEXT          DEFAULT NULL,   -- link to Material Safety Data Sheet

  -- Audit
  created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_dg_shipment (shipment_id)
);
```

### ImoClass enum

| Value | Description | Flash point req. | Examples |
|---|---|---|---|
| `1` | Explosives | — | Fireworks, ammunition |
| `2.1` | Flammable gases | — | LPG, propane |
| `2.2` | Non-flammable non-toxic gases | — | Oxygen, CO2 |
| `2.3` | Toxic gases | — | Chlorine |
| `3` | Flammable liquids | Yes (≤60°C) | Paints, solvents, fuel |
| `4.1` | Flammable solids | — | Matches, metal powders |
| `4.2` | Spontaneously combustible | — | Wet cotton, pyrophoric metals |
| `4.3` | Dangerous when wet | — | Sodium, carbide |
| `5.1` | Oxidising substances | — | Bleach, hydrogen peroxide |
| `5.2` | Organic peroxides | — | Benzoyl peroxide |
| `6.1` | Toxic substances | — | Pesticides, methanol |
| `6.2` | Infectious substances | — | Medical/clinical waste |
| `7` | Radioactive material | — | Medical isotopes |
| `8` | Corrosive substances | — | Acids, batteries |
| `9` | Miscellaneous | — | Dry ice, lithium batteries, magnetised material |

### PackingGroup enum

| Value | Danger level |
|---|---|
| `I` | High danger |
| `II` | Medium danger |
| `III` | Low danger |

Not applicable for classes 1, 2, 5.2, 6.2, 7.

---

## Business Rules

1. A shipment may have **multiple DG line items** (e.g. two different UN numbers in the same container).
2. `imo_class` and `un_number` are always required. `packing_group` is required when applicable to the class.
3. `flash_point` is required when `imo_class = '3'` (flammable liquids).
4. `technical_name` is required when `proper_name` ends with "N.O.S." (not otherwise specified) — common for class 3 and 6.1.
5. `is_limited_qty` and `is_excepted_qty` are mutually exclusive.
6. When any DG record exists on a shipment, the **DG Declaration** document type is added to the document checklist as `is_required = true` (Feature 4 integration).
7. The presence of DG cargo triggers a visual warning badge on the shipment list and detail header.

---

## API

```
GET    /shipment/{id}/dangerous-goods              — list all DG lines
POST   /shipment/{id}/dangerous-goods              — add DG line
PUT    /shipment/{id}/dangerous-goods/{dgId}       — update DG line
DELETE /shipment/{id}/dangerous-goods/{dgId}       — remove DG line
```

### POST/PUT body example

```json
{
  "imoClass": "3",
  "unNumber": "UN1263",
  "packingGroup": "III",
  "properName": "Paint",
  "netQuantity": 120.5,
  "grossQuantity": 135.0,
  "uom": "KG",
  "flashPoint": 23.0,
  "isMarine_pollutant": false,
  "isLimitedQty": false,
  "isExceptedQty": false,
  "emergencyContact": "+84 28 1234 5678"
}
```

### Serializer groups

- `dangerous_goods:list` → id, imoClass, unNumber, packingGroup, properName, grossQuantity, uom, isMarinePollutant
- `dangerous_goods:detail` → all fields
- `dangerous_goods:write` → all fields except id, createdAt, updatedAt

---

## BO UI

### DG section in Shipment Info tab

- **Toggle / checkbox** at the top of the Booking section: "This shipment contains Dangerous Goods".
- When toggled on → inline collapsible DG card appears.
- DG card shows a table of DG lines with columns: IMO Class | UN Number | Packing Group | Proper Name | Gross Qty | UOM | Marine Pollutant.
- **Add DG Line** button opens a dialog form.
- Each row has Edit / Delete inline actions.
- **DG badge** (red hazmat diamond icon) shown in the shipment detail header when any DG lines exist.

### Form fields for DG line dialog

```
IMO Class         — select dropdown (grouped by class family)
UN Number         — text input with format hint "UN####"
Packing Group     — select I / II / III (hidden if class 1/2/7)
Proper Name       — text input
Technical Name    — text input (shown if proper name contains N.O.S.)
Net Qty + UOM     — number + unit select
Gross Qty         — number
Flash Point (°C)  — number (shown if class 3)
Marine Pollutant  — checkbox
Limited Qty       — checkbox
Excepted Qty      — checkbox (mutually exclusive with Limited Qty)
Emergency Contact — text input
```

---

## Migration

```sql
-- MySQL
CREATE TABLE dangerous_goods (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  shipment_id         INT NOT NULL,
  imo_class           VARCHAR(8) NOT NULL,
  un_number           VARCHAR(8) NOT NULL,
  packing_group       VARCHAR(4) DEFAULT NULL,
  proper_name         VARCHAR(255) NOT NULL,
  technical_name      VARCHAR(255) DEFAULT NULL,
  net_quantity        DECIMAL(12,3) DEFAULT NULL,
  gross_quantity      DECIMAL(12,3) DEFAULT NULL,
  uom                 VARCHAR(16) DEFAULT NULL,
  flash_point         DECIMAL(6,2) DEFAULT NULL,
  subsidiary_risk     VARCHAR(16) DEFAULT NULL,
  is_marine_pollutant TINYINT(1) NOT NULL DEFAULT 0,
  is_limited_qty      TINYINT(1) NOT NULL DEFAULT 0,
  is_excepted_qty     TINYINT(1) NOT NULL DEFAULT 0,
  emergency_contact   VARCHAR(128) DEFAULT NULL,
  msds_url            TEXT DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_dg_shipment (shipment_id),
  CONSTRAINT FK_dg_shipment FOREIGN KEY (shipment_id) REFERENCES shipment(id) ON DELETE CASCADE
);

-- SQLite
CREATE TABLE dangerous_goods (
  id                  INTEGER PRIMARY KEY AUTOINCREMENT,
  shipment_id         INTEGER NOT NULL,
  imo_class           TEXT NOT NULL,
  un_number           TEXT NOT NULL,
  packing_group       TEXT DEFAULT NULL,
  proper_name         TEXT NOT NULL,
  technical_name      TEXT DEFAULT NULL,
  net_quantity        REAL DEFAULT NULL,
  gross_quantity      REAL DEFAULT NULL,
  uom                 TEXT DEFAULT NULL,
  flash_point         REAL DEFAULT NULL,
  subsidiary_risk     TEXT DEFAULT NULL,
  is_marine_pollutant INTEGER NOT NULL DEFAULT 0,
  is_limited_qty      INTEGER NOT NULL DEFAULT 0,
  is_excepted_qty     INTEGER NOT NULL DEFAULT 0,
  emergency_contact   TEXT DEFAULT NULL,
  msds_url            TEXT DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME DEFAULT NULL
);
```

---

## Reference: Industry Patterns

- **CargoWise One** has a dedicated `Dangerous Goods` panel on every sea/air job. It stores IMDG class, UN number, packing group, proper shipping name, EMS (Emergency Schedule), and MFAG (Medical First Aid Guide) numbers. Each line links to a pre-loaded IMDG database.
- **Magaya** has a `DG` checkbox on cargo detail with a multi-line DG table. It generates the DG certificate automatically from the entered data.
- **Descartes** validates UN numbers against the IATA DGR and IMDG databases at entry time and flags non-compliant declarations.
- **Shipsy** shows a red hazmat icon on the shipment card in the list view whenever DG is present.
