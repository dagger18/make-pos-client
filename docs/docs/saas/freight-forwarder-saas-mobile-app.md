# Freight Forwarder SaaS — Mobile App

## 1. What the Mobile App Covers

The mobile app extends the desktop system to three user groups with distinct needs:

| User group | Primary use | Critical features |
|---|---|---|
| **Operators (on the go)** | Check job status, respond to alerts, approve tasks | Job list, milestone view, notifications, task completion |
| **Warehouse / CFS staff** | Receive cargo, scan containers, record stripping | QR/barcode scan, cargo receipt, photo capture |
| **Drivers / truckers** | Confirm pickup and delivery, capture POD signature | Job details, navigation, signature capture, photo upload |

The mobile app is a companion to the desktop system — not a replacement. It covers field operations that require a phone.

---

## 2. Architecture

```
React Native app (iOS + Android)
        ↓
REST API gateway (same backend as web app)
        ↓
JWT authentication + device registration
        ↓
Offline-first sync layer (SQLite local cache)
        ↓
Push notifications (FCM / APNs)
```

### Offline-first principle

Field operations happen in warehouses, ports, and delivery locations with poor connectivity. The app must work offline:
- Job data is synced to local SQLite when the device connects
- Actions taken offline (cargo receipt, POD signature) are queued locally
- When connectivity restores, the queue is flushed to the server
- Conflicts are resolved with last-write-wins for non-financial data; financial data requires server confirmation

---

## 3. Device and User Registration

```sql
CREATE TABLE mobile_device (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id           UUID          NOT NULL REFERENCES app_user(id),
  device_id         VARCHAR(128)  UNIQUE NOT NULL,   -- platform device ID
  platform          VARCHAR(8)    NOT NULL,           -- IOS / ANDROID
  app_version       VARCHAR(16)   NOT NULL,
  push_token        TEXT,                             -- FCM / APNs push notification token
  last_seen_at      TIMESTAMPTZ,
  is_active         BOOLEAN       NOT NULL DEFAULT true,
  registered_at     TIMESTAMPTZ   NOT NULL DEFAULT now()
);
```

Push tokens are refreshed every time the app is opened and whenever the platform rotates them.

---

## 4. Operator Mobile Features

### 4.1 Job List and Alerts

The operator's home screen shows:
- Jobs requiring action today (overdue tasks, approaching cutoffs)
- Unread in-app notifications
- Jobs with active customs holds or exceptions

```
GET /api/mobile/v1/jobs?operator_id={id}&needs_action=true&limit=20
```

Response:
```json
{
  "jobs": [
    {
      "shipment_id": "HCM-EXP-OCN-202604-00123",
      "status": "BOOKED",
      "sub_status": "AWAITING_SI",
      "etd": "2026-04-15",
      "si_cutoff_hours": 18.5,
      "overdue_tasks": 1,
      "missing_docs": 2,
      "pol": "Ho Chi Minh City",
      "pod": "Rotterdam",
      "shipper": "IKEA Vietnam"
    }
  ]
}
```

### 4.2 Task Completion

```
POST /api/mobile/v1/tasks/{task_id}/complete
Body: {"completed_at": "2026-04-14T09:30:00Z", "notes": "SI submitted via Maersk portal"}
```

### 4.3 Push Notifications

Urgent alerts (customs hold, vessel roll, overdue invoice) are delivered as push notifications that open directly to the relevant job in the app.

```sql
-- When a URGENT in_app_notification is created, also send push
INSERT INTO push_notification_queue (device_id, title, body, deep_link, job_id)
SELECT md.device_id,
       :title,
       :body,
       '/jobs/' || :job_id,
       :job_id
FROM mobile_device md
WHERE md.user_id = :user_id AND md.is_active = true;
```

---

## 5. Warehouse Mobile Features

### 5.1 QR / Barcode Scanning

The warehouse app uses the device camera to scan:
- Container numbers (ISO 6346 format — MSCU1234567)
- HBL/HAWB barcodes
- Packing list QR codes
- Storage location labels

```javascript
// React Native camera scan handler
import { Camera } from 'react-native-camera';

const onBarcodeScan = async ({ data, type }) => {
  if (type === 'QR_CODE' && data.startsWith('JOB:')) {
    const jobId = data.replace('JOB:', '');
    navigation.navigate('CargoReceipt', { jobId });
  } else if (isContainerNumber(data)) {
    navigation.navigate('ContainerDetail', { containerNumber: data });
  }
};
```

### 5.2 Cargo Receipt on Mobile

The warehouse staff member:
1. Scans the HBL barcode from the truckers' delivery paperwork
2. The app loads the expected cargo for that job
3. Staff counts pieces and enters actual weight
4. Photos any damage
5. Assigns a storage location from a dropdown
6. Submits — the warehouse_receipt record is created

```
POST /api/mobile/v1/warehouse/receipt
Body: {
  "job_id": "...",
  "pieces_received": 24,
  "gross_weight_kg": 1240.5,
  "condition": "GOOD",
  "storage_location": "A-03-02",
  "photos": ["base64_photo_1", "base64_photo_2"]
}
```

### 5.3 Photo Upload

Photos of damaged cargo are uploaded immediately with the receipt. They are stored in object storage and linked to the `warehouse_receipt` record.

```sql
CREATE TABLE warehouse_receipt_photo (
  id                UUID          PRIMARY KEY DEFAULT gen_random_uuid(),
  receipt_id        UUID          NOT NULL REFERENCES warehouse_receipt(id),
  file_url          TEXT          NOT NULL,
  thumbnail_url     TEXT,
  taken_at          TIMESTAMPTZ   NOT NULL DEFAULT now(),
  taken_by          UUID          REFERENCES app_user(id)
);
```

---

## 6. Driver / POD Mobile Features

### 6.1 Delivery Job Assignment

Drivers see only the jobs assigned to their vehicle or assigned to them. The job card shows:
- Pickup or delivery address
- Contact name and phone at destination
- Required documents to collect (D/O, ID)
- Navigation button (opens Google Maps / Apple Maps)

### 6.2 Electronic Proof of Delivery (ePOD)

```
POST /api/mobile/v1/delivery/pod
Body: {
  "job_id": "...",
  "delivered_at": "2026-04-16T14:22:00+07:00",
  "recipient_name": "Nguyen Van A",
  "recipient_id_ref": "CCCD: 012345678",
  "signature_base64": "data:image/png;base64,...",
  "delivery_photo_base64": "data:image/png;base64,...",
  "notes": "Delivered to warehouse door, guard signed"
}
```

On submission:
1. Signature and photo are stored in object storage
2. A `job_document` record is created: `doc_type = 'POD'`
3. Milestone `POD_RECEIVED` is written with `actual_date = delivered_at`
4. Operator and customer are notified

### 6.3 Failed Delivery

If delivery cannot be completed:

```
POST /api/mobile/v1/delivery/failed
Body: {
  "job_id": "...",
  "reason": "PREMISES_CLOSED",
  "notes": "Warehouse closed — no staff present at 14:00",
  "photo_base64": "...",
  "next_attempt_date": "2026-04-17"
}
```

---

## 7. Offline Queue

```javascript
// Offline action queue — stores actions taken without connectivity
class OfflineQueue {
  async enqueue(action) {
    const queue = await AsyncStorage.getItem('offline_queue') || '[]';
    const actions = JSON.parse(queue);
    actions.push({ ...action, queued_at: new Date().toISOString(), id: uuid() });
    await AsyncStorage.setItem('offline_queue', JSON.stringify(actions));
  }

  async flush(apiClient) {
    const queue = await AsyncStorage.getItem('offline_queue') || '[]';
    const actions = JSON.parse(queue);
    const failed = [];

    for (const action of actions) {
      try {
        await apiClient.post(action.endpoint, action.payload);
      } catch (err) {
        if (err.status !== 409) {  // 409 = conflict/duplicate — skip
          failed.push(action);
        }
      }
    }
    await AsyncStorage.setItem('offline_queue', JSON.stringify(failed));
  }
}
```

---

## 8. Golden Rules

1. **Offline-first is non-negotiable for field operations.** Ports, warehouses, and delivery locations have unreliable connectivity. Never build a field-operation feature that requires an active connection to function.
2. **Photo capture must be on-device, not linked from gallery.** Photos of damaged cargo must be taken at the time of receipt — not uploaded from a gallery later. Enforce this in the UX.
3. **Signatures are legal records.** Store the raw signature SVG/PNG, the timestamp, and the device ID. These may be needed as evidence in a dispute.
4. **Push notifications must respect the user's role.** Operators receive job alerts. Drivers receive delivery assignments. Warehouse staff receive stuffing instructions. Never send cross-role notifications.
5. **The mobile app is read-heavy, write-light.** Most mobile actions are checking status and completing tasks — not creating complex records. Keep the API mobile-optimised: lightweight payloads, pre-aggregated views, minimal round trips.
