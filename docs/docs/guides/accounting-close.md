# Accounting Close Guide

Covers the close-checklist validation panel: matched AP bills, settled AR invoices, and posted journal entries.

---

## Architecture

| Component | Location |
|---|---|
| API Controller | `src/Module/Finance/Controller/AccountingCloseController.php` |
| BO Panel | `src/views/shipment/CloseChecklistPanel.vue` |

---

## API Endpoints

| Method | Path | Description |
|---|---|---|
| `GET` | `/report/accounting-close/{shipmentId}/checklist` | Run the 3-point close checklist and return results |
| `POST` | `/report/accounting-close/{shipmentId}` | Lock all EbitNotes and stamp `accountingClosedAt` |

---

## Close Checklist

`GET /report/accounting-close/{shipmentId}/checklist` runs three checks against the shipment's financial documents.

### Response

```json
{
  "shipmentId": 42,
  "accountingClosedAt": null,
  "allPassed": false,
  "checks": [
    {
      "code": "ap_matched",
      "label": "All AP bills matched or approved",
      "passed": true,
      "total": 3,
      "failing": 0,
      "items": []
    },
    {
      "code": "ar_settled",
      "label": "All AR invoices have at least one payment recorded",
      "passed": false,
      "total": 2,
      "failing": 1,
      "items": [{ "id": 17, "code": "ID-HCM-202604-00017" }]
    },
    {
      "code": "journals_posted",
      "label": "All journal entries posted",
      "passed": true,
      "total": 5,
      "failing": 0,
      "items": []
    }
  ]
}
```

### Check rules

| Code | Check | Pass condition |
|---|---|---|
| `ap_matched` | AP bills (EbitNote type=IC) | All have `varianceStatus` = MATCHED or APPROVED |
| `ar_settled` | AR invoices (EbitNote type=ID) | All have at least one receipt (type=RPT) recorded |
| `journals_posted` | Journal entries linked to this shipment's EbitNotes | All have `isPosted = true` |

Cancelled EbitNotes (`status = D`) are excluded from all three checks.

---

## Closing the Job

`POST /report/accounting-close/{shipmentId}` requires all three checks to pass (enforced by the BO panel — the button is disabled if `allPassed = false`).

On success:
- All EbitNotes for the shipment have `isLocked = true` set
- `Shipment.accountingClosedAt` is stamped with the current timestamp
- Response: `{ "accountingClosedAt": "2026-06-26 14:30:00" }`

Once closed, the endpoint returns HTTP 422 if called again.

---

## Back-Office Panel

The panel is the **Accounting Close** tab in ShipmentDetail.vue (icon: `tabler-lock-check`), visible to users with `MANAGE_Ebitnote` permission.

### Panel behaviour

| State | UI |
|---|---|
| Not yet run | "Run Checklist" button only |
| Checks run, some failing | 3 check cards (red/green), overall warning, "Close Accounting" disabled |
| All checks passing | 3 green cards, "Close Accounting" button enabled |
| Already closed | Green banner with `accountingClosedAt` timestamp |

Each failing check card lists the specific document codes (EbitNote codes or journal numbers) that failed, so the operator can navigate directly to the issue.

---

## Golden Rules

1. **AP bills must be MATCHED or APPROVED** before close. UNMATCHED/VARIANCE/DISPUTED AP bills mean the cost side is unknown.
2. **Every AR invoice needs at least one payment recorded.** A receipt (RPT) child is required — the checklist does not verify full payment amount, only that collection has started.
3. **All journal entries must be posted.** Unposted journals mean the general ledger is not up to date.
4. **Closing is irreversible.** All EbitNotes become `isLocked = true`. Any corrections after close require a new credit note.
