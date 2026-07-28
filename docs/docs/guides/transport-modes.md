# Transport Modes Guide

The four additional transport modes — Road Freight (RD), Rail Freight (RAL), Courier/Express (COU), and Multimodal (MMD) — each extend the base Shipment record with mode-specific detail objects linked by `shipmentId`.

---

## Road Freight (RD) — Truck Details

For Road Freight shipments (FTL service type), attach a `Truck` record to capture vehicle, driver, haulier, and proof-of-delivery details.

### Endpoints

#### List trucks for a shipment
```
GET /shipment/{shipmentId}/truck
Authorization: Bearer <token>
```

Returns an array of truck objects.

#### Create a truck record
```
POST /shipment/{shipmentId}/truck
Authorization: Bearer <token>
Content-Type: application/json

{
  "truckType":          "CURTAINSIDER",          (required) BOX|CURTAINSIDER|FLATBED|REEFER|TANKER
  "payloadKg":          5000,                    (optional)
  "truckPlate":         "51A-12345",             (optional)
  "driverName":         "Nguyen Van A",          (optional)
  "haulierId":          42,                      (optional) Provider ID of the haulier
  "pickupAddress":      "123 Industrial St",     (optional)
  "deliveryAddress":    "456 Port Rd",           (optional)
  "scheduledPickup":    "2026-07-01T08:00:00Z",  (optional) ISO 8601 datetime
  "scheduledDelivery":  "2026-07-02T17:00:00Z",  (optional) ISO 8601 datetime
  "actualPickup":       null,                    (optional)
  "actualDelivery":     null,                    (optional)
  "podSignedBy":        null,                    (optional) Name of person who signed POD
  "podImageUrl":        null                     (optional) URL to POD image
}
```

Response: truck object with `id`, all above fields, `haulier` (`{id, name}`), `createdAt`, `updatedAt`.

#### Update a truck record
```
PUT /shipment/{shipmentId}/truck/{truckId}
Authorization: Bearer <token>
Content-Type: application/json
```

#### Delete a truck record
```
DELETE /shipment/{shipmentId}/truck/{truckId}
Authorization: Bearer <token>
```

Response: `204 No Content`

### Truck types

| Code | Description |
|---|---|
| `BOX` | Enclosed box truck |
| `CURTAINSIDER` | Side-curtain trailer — most common for general cargo |
| `FLATBED` | Open flatbed for heavy machinery or project cargo |
| `REEFER` | Temperature-controlled refrigerated truck |
| `TANKER` | Liquid or bulk tanker |

---

## Rail Freight (RAL) — Rail Booking Details

For Rail Freight shipments, attach a `RailBooking` record to capture train service, ICD locations, and the CIM waybill.

### Endpoints

#### List rail bookings for a shipment
```
GET /shipment/{shipmentId}/rail-booking
Authorization: Bearer <token>
```

#### Create a rail booking
```
POST /shipment/{shipmentId}/rail-booking
Authorization: Bearer <token>
Content-Type: application/json

{
  "trainService":     "CR Europe 1023",          (optional) Train service reference
  "departureIcd":     "CNCTU",                   (optional) UN/LOCODE of origin ICD
  "arrivalIcd":       "DEHAM",                   (optional) UN/LOCODE of destination ICD
  "operator":         "DB Cargo",                (optional) Rail operator name
  "cimWaybillNumber": "CIM-2026-0012",           (optional) CIM consignment note number
  "cimWaybillDate":   "2026-07-01",              (optional) Date issued (YYYY-MM-DD)
  "departureDate":    "2026-07-05T18:00:00Z",    (optional) ISO 8601 datetime
  "arrivalDate":      "2026-07-19T10:00:00Z",    (optional) ~14 days China→Europe
  "containerCount":   2,                         (optional) Number of containers on this booking
  "note":             null                       (optional)
}
```

Response: rail booking object with all above fields plus `id`, `createdAt`, `updatedAt`. `cimWaybillDate` is returned as `YYYY-MM-DD`; other dates as ISO 8601.

#### Update a rail booking
```
PUT /shipment/{shipmentId}/rail-booking/{rbId}
```

#### Delete a rail booking
```
DELETE /shipment/{shipmentId}/rail-booking/{rbId}
```

### CIM Waybill

The CIM (Convention Internationale concernant le transport des Marchandises par chemin de fer) is the international rail consignment note — the rail equivalent of the ocean Bill of Lading. Record the number as soon as the rail operator issues it.

### ICD Codes

Use UN/LOCODE codes for ICDs (Inland Container Depots), the same format used for port codes elsewhere in the system. Common examples for China–Europe rail:

| Code | Location |
|---|---|
| `CNCTU` | Chengdu ICD, China |
| `CNXFW` | Xi'an ICD, China |
| `PLMWR` | Małaszewicze, Poland (main China–EU border crossing) |
| `DEHAM` | Hamburg, Germany |
| `NLRTM` | Rotterdam, Netherlands |

---

## Courier / Express (COU) — Parcel Details

For Courier/Express shipments, attach one `Parcel` record per physical parcel or integrator tracking number.

### Endpoints

#### List parcels for a shipment
```
GET /shipment/{shipmentId}/parcel
Authorization: Bearer <token>
```

#### Create a parcel record
```
POST /shipment/{shipmentId}/parcel
Authorization: Bearer <token>
Content-Type: application/json

{
  "serviceLevel":     "EXPRESS",                 (required) ECONOMY|EXPRESS|OVERNIGHT|SAME-DAY
  "grossWeightKg":    2.5,                       (required, > 0)
  "pieces":           1,                         (optional, default 1)
  "trackingNumber":   "1Z999AA10123456784",      (optional) Integrator tracking number
  "integrator":       "UPS",                     (optional) FEDEX|DHL|UPS|TNT
  "declaredValue":    150.00,                    (optional) Customs declared value
  "declaredCurrency": "USD"                      (optional) ISO 4217 currency for declared value
}
```

Response: parcel object with all above fields plus `id`, `createdAt`, `updatedAt`.

#### Update a parcel record
```
PUT /shipment/{shipmentId}/parcel/{parcelId}
```

#### Delete a parcel record
```
DELETE /shipment/{shipmentId}/parcel/{parcelId}
```

### Service levels

| Code | Typical transit |
|---|---|
| `ECONOMY` | 3–7 business days |
| `EXPRESS` | 1–3 business days |
| `OVERNIGHT` | Next business day |
| `SAME-DAY` | Same-day delivery within a city or region |

### Chargeable weight (IATA volumetric)

Integrators bill on `MAX(grossWeightKg, (L × W × H cm³) / 6000)`. Record `grossWeightKg` accurately — the front-end can compute volumetric weight for display if dimensions are available on the instruction.

---

## Multimodal (MMD) — Sub-Leg Management

A Multimodal shipment is a parent job that ties two or more transport legs together under one contract and one document (the MTD — Multimodal Transport Document). Each sub-leg is a child Shipment linked via `parentJobId`.

### Workflow

1. Create the parent MMD shipment through the normal shipment flow.
2. Add sub-legs via `POST /shipment/{id}/legs`. Each sub-leg is a standalone Shipment whose mode-specific data (booking, truck, rail booking, parcel) is then added using its own shipment ID.
3. Quote lines and charges exist at the parent MMD level; mode-specific operational data lives at the sub-leg level.

### Endpoints

#### List sub-legs
```
GET /shipment/{shipmentId}/legs
Authorization: Bearer <token>
```

Returns an array of child shipment objects: `id`, `parentJobId`, `code`, `note`.

#### Add a sub-leg
```
POST /shipment/{shipmentId}/legs
Authorization: Bearer <token>
Content-Type: application/json

{
  "code": "SHP-2026-0012-LEG2",   (optional) Internal reference for this leg
  "note": "Rail leg China-Poland" (optional)
}
```

Response: `201 Created` with the new sub-leg shipment object.

#### Delete a sub-leg
```
DELETE /shipment/{shipmentId}/legs/{legId}
```

Response: `204 No Content`

### Example: China → Europe SEA-RAIL-ROAD

```
1. Create parent MMD shipment → id=100

2. Add sub-legs:
   POST /shipment/100/legs  → id=101 (code: "LEG-1-OCEAN")
   POST /shipment/100/legs  → id=102 (code: "LEG-2-RAIL")
   POST /shipment/100/legs  → id=103 (code: "LEG-3-ROAD")

3. Add mode-specific data to each sub-leg:
   POST /shipment/101/booking       → ocean booking (vessel, voyage, ETD, ETA)
   POST /shipment/102/rail-booking  → rail booking (ICD codes, CIM waybill)
   POST /shipment/103/truck         → truck (plate, driver, pickup/delivery dates)
```
