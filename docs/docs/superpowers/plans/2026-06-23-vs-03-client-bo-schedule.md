# VS-03: Client BO — Schedule UI (Sailing Picker + Vessel Roll)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a sailing picker to `BookingForm.vue` that lets operators search vessel sailings (ocean) or flight schedules (air) from the master API and auto-populate ETD, ETA, vessel number, and cutoffs. Add a vessel roll recording dialog accessible from the shipment booking area.

**Architecture:** New `VesselSailingService.js`, `FlightScheduleService.js`, `VesselRollService.js` call the client API proxy endpoints. `BookingForm.vue` gets a "Search Sailing" button that opens a search dialog; selecting a sailing auto-fills the form. A new `VesselRolls.vue` component lists rolls for the shipment and allows recording a new roll. `BookingForm.vue` uses `entityPreForm`/`entityPreSubmit` pattern — the sailing picker fires alongside the form, not inside it.

**Tech Stack:** Vue 3, Vuetify, `$api()` global, `$gettext()` for i18n.

**Target repo:** `d:\Projects\make-cargo-client-bo`

**Context (existing patterns):**
- `$api(url, options)` global from `src/utils/api.js` — all API calls use it.
- Services live in `src/services/` and follow the pattern in e.g. `src/services/PortService.js`.
- `BookingForm.vue` is at `src/views/shipment/info/BookingForm.vue`. It has `entityPreForm(entity)` and `entityPreSubmit(entity)` hooks called by AppForm.
- The booking object has fields: `etd`, `eta`, `vesselNo`, `motherVessel`, `motherVoyage`, `siCutOff`, `vgmCutOff`, `cyCutOff`, `gateIn`, `sailingRef`, `flightRef`.
- Port objects are `{ id, name, code, codeFull }`. The booking stores `portLoading` and `portDischarge` as port objects.
- `ShipmentDetail.vue` at `src/views/shipment/ShipmentDetail.vue` is where shipment tabs/sections are composed. Check it to understand where to add the VesselRolls component.
- `VDialog`, `VCard`, `VCardText`, `VCardActions`, `VBtn`, `VTable`, `VChip`, `VTextField`, `VDatePicker` are all available from Vuetify.

---

## File Structure

**Target repo:** `d:\Projects\make-cargo-client-bo`

- Create: `src/services/VesselSailingService.js`
- Create: `src/services/FlightScheduleService.js`
- Create: `src/services/VesselRollService.js`
- Modify: `src/views/shipment/info/BookingForm.vue` — add sailing picker
- Create: `src/views/shipment/VesselRolls.vue` — vessel roll list + create dialog
- Modify: `src/views/shipment/ShipmentDetail.vue` (or equivalent) — add VesselRolls section

---

### Task 1: Service files for vessel sailing, flight schedule, and vessel roll

**Files:**
- Create: `src/services/VesselSailingService.js`
- Create: `src/services/FlightScheduleService.js`
- Create: `src/services/VesselRollService.js`

- [ ] **Step 1: Read an existing service file for exact pattern**

Read `src/services/PortService.js` to confirm the exact pattern used (`$api`, function names, parameter format). Then create the three files.

- [ ] **Step 2: Create `src/services/VesselSailingService.js`**

```javascript
const BASE_URI = 'vessel-sailing'

export default {
  search(pol, pod, etdFrom, etdTo) {
    const params = new URLSearchParams({ pol, pod, etd_from: etdFrom, etd_to: etdTo }).toString()
    return $api(`${BASE_URI}/search?${params}`)
  },
}
```

- [ ] **Step 3: Create `src/services/FlightScheduleService.js`**

```javascript
const BASE_URI = 'flight-schedule'

export default {
  search(origin, destination, date) {
    const params = new URLSearchParams({ origin, destination, date }).toString()
    return $api(`${BASE_URI}/search?${params}`)
  },
}
```

- [ ] **Step 4: Create `src/services/VesselRollService.js`**

```javascript
const BASE_URI = 'vessel-roll'

export default {
  list(shipmentId) {
    return $api(`${BASE_URI}?shipmentId=${shipmentId}`)
  },
  create(entity) {
    return $api(BASE_URI, { method: 'POST', body: JSON.stringify(entity), headers: { 'Content-Type': 'application/json' } })
  },
  markNotified(id) {
    return $api(`${BASE_URI}/${id}/notify`, { method: 'PUT' })
  },
}
```

- [ ] **Step 5: Commit**

```bash
git add src/services/VesselSailingService.js src/services/FlightScheduleService.js src/services/VesselRollService.js
git commit -m "feat(vs-03): add VesselSailingService, FlightScheduleService, and VesselRollService"
```

---

### Task 2: Add sailing picker to BookingForm.vue

**Files:**
- Modify: `src/views/shipment/info/BookingForm.vue`

Read the **complete** `src/views/shipment/info/BookingForm.vue` before making any changes. Understand:
1. The `entityPreForm(entity)` function — how it splits the booking into sub-forms.
2. The `entityPreSubmit(entity)` function — how it reassembles sub-forms before POST/PUT.
3. Where the ETD, ETA, vessel number, and cutoff fields are in the template. They are likely in a sub-form object rendered by AppForm.
4. Whether there is a "mode" field (`AIR` vs `OCEAN`) accessible from `shipment.mode` or the entity.

- [ ] **Step 1: Read the full `BookingForm.vue`**

Read `src/views/shipment/info/BookingForm.vue` (all lines).

- [ ] **Step 2: Add imports and reactive state to `<script setup>` in `BookingForm.vue`**

In the `<script setup>` block, add imports and refs. Insert after the existing imports:

```javascript
import VesselSailingService from '@/services/VesselSailingService'
import FlightScheduleService from '@/services/FlightScheduleService'
```

Add these reactive refs (after existing refs/computed declarations):

```javascript
const sailingPickerOpen = ref(false)
const sailingSearchResults = ref([])
const sailingSearchLoading = ref(false)
const sailingSearchPol = ref('')
const sailingSearchPod = ref('')
const sailingSearchDate = ref(new Date().toISOString().slice(0, 10))
```

- [ ] **Step 3: Add the `openSailingPicker()` and `selectSailing()` functions**

Add these functions in the `<script setup>` block (after existing functions):

```javascript
function openSailingPicker() {
  // Pre-fill POL/POD from the booking's portLoading/portDischarge
  const booking = form.value?.entity ?? form.value ?? {}
  sailingSearchPol.value = booking.portLoading?.code ?? booking.portLoading ?? ''
  sailingSearchPod.value = booking.portDischarge?.code ?? booking.portDischarge ?? ''
  sailingSearchResults.value = []
  sailingPickerOpen.value = true
}

async function searchSailings() {
  if (!sailingSearchPol.value || !sailingSearchPod.value) return
  sailingSearchLoading.value = true
  try {
    const etdTo = new Date(sailingSearchDate.value)
    etdTo.setDate(etdTo.getDate() + 60)
    const results = await VesselSailingService.search(
      sailingSearchPol.value,
      sailingSearchPod.value,
      sailingSearchDate.value,
      etdTo.toISOString().slice(0, 10)
    )
    sailingSearchResults.value = Array.isArray(results) ? results : []
  } finally {
    sailingSearchLoading.value = false
  }
}

function selectSailing(sailing) {
  // Determine which sub-form holds the vessel/ETD fields.
  // Look at the entityPreForm structure: it may be `originSubForm` or directly on the entity.
  // Adjust the target object below to match how BookingForm stores these fields.
  const target = originSubForm.value ?? form.value?.entity ?? {}
  target.etd = sailing.etd ? sailing.etd.slice(0, 10) : target.etd
  target.eta = sailing.eta ? sailing.eta.slice(0, 10) : target.eta
  target.vesselNo = sailing.vessel ?? target.vesselNo
  target.motherVessel = sailing.vessel ?? target.motherVessel
  target.motherVoyage = sailing.voyageNo ?? target.motherVoyage
  target.siCutOff = sailing.siCutOff ? sailing.siCutOff.slice(0, 10) : target.siCutOff
  target.vgmCutOff = sailing.vgmCutOff ? sailing.vgmCutOff.slice(0, 10) : target.vgmCutOff
  target.cyCutOff = sailing.cyCutOff ? sailing.cyCutOff.slice(0, 10) : target.cyCutOff
  target.sailingRef = String(sailing.id)
  sailingPickerOpen.value = false
}
```

**Important:** After reading `BookingForm.vue`, confirm the sub-form variable names. The plan uses `originSubForm` as a guess — adjust to match the actual variable name that holds ETD, vessel, and cutoff fields.

- [ ] **Step 4: Add flight search state and functions (for AIR mode)**

```javascript
const flightPickerOpen = ref(false)
const flightSearchResults = ref([])
const flightSearchLoading = ref(false)
const flightSearchOrigin = ref('')
const flightSearchDest = ref('')
const flightSearchDate = ref(new Date().toISOString().slice(0, 10))

function openFlightPicker() {
  const booking = form.value?.entity ?? form.value ?? {}
  flightSearchOrigin.value = booking.portLoading?.code ?? booking.portLoading ?? ''
  flightSearchDest.value = booking.portDischarge?.code ?? booking.portDischarge ?? ''
  flightSearchResults.value = []
  flightPickerOpen.value = true
}

async function searchFlights() {
  if (!flightSearchOrigin.value || !flightSearchDest.value) return
  flightSearchLoading.value = true
  try {
    const results = await FlightScheduleService.search(flightSearchOrigin.value, flightSearchDest.value, flightSearchDate.value)
    flightSearchResults.value = Array.isArray(results) ? results : []
  } finally {
    flightSearchLoading.value = false
  }
}

function selectFlight(flight) {
  const target = originSubForm.value ?? form.value?.entity ?? {}
  target.etd = flight.std ? flight.std.slice(0, 10) : target.etd
  target.eta = flight.sta ? flight.sta.slice(0, 10) : target.eta
  target.vesselNo = flight.flightNo ?? target.vesselNo
  target.siCutOff = flight.docCutOff ? flight.docCutOff.slice(0, 10) : target.siCutOff
  target.cyCutOff = flight.cargoCutOff ? flight.cargoCutOff.slice(0, 10) : target.cyCutOff
  target.flightRef = String(flight.id)
  flightPickerOpen.value = false
}
```

- [ ] **Step 5: Add sailing picker button + dialog to the template**

Find the template section where the ETD field (or vessel number field) is rendered. Add a "Search Sailing" button right next to the ETD field (use `append-inner` or a button next to the field row).

Example: After the ETD/vessel row, add this button:

```html
<VBtn
  v-if="shipment?.mode !== 'AIR'"
  size="small"
  variant="tonal"
  color="primary"
  prepend-icon="tabler-ship"
  class="mb-2"
  @click="openSailingPicker"
>{{ $gettext('Search Sailing') }}</VBtn>

<VBtn
  v-if="shipment?.mode === 'AIR'"
  size="small"
  variant="tonal"
  color="info"
  prepend-icon="tabler-plane"
  class="mb-2"
  @click="openFlightPicker"
>{{ $gettext('Search Flight') }}</VBtn>
```

**Note:** If `shipment.mode` is not available as a prop/computed in BookingForm, add it as a prop or look at how mode is accessed. Check the `shipment` prop to confirm the mode field name.

- [ ] **Step 6: Add the Vessel Sailing Picker VDialog to the template**

Add these two dialogs at the end of the template (inside the root element, after AppForm):

```html
<!-- Vessel Sailing Picker Dialog -->
<VDialog v-model="sailingPickerOpen" max-width="860" scrollable>
  <VCard :title="$gettext('Search Vessel Sailing')">
    <VCardText>
      <VRow class="mb-3">
        <VCol cols="3">
          <VTextField v-model="sailingSearchPol" :label="$gettext('POL (UN/LOCODE)')" density="compact" />
        </VCol>
        <VCol cols="3">
          <VTextField v-model="sailingSearchPod" :label="$gettext('POD (UN/LOCODE)')" density="compact" />
        </VCol>
        <VCol cols="3">
          <VTextField v-model="sailingSearchDate" :label="$gettext('ETD From')" type="date" density="compact" />
        </VCol>
        <VCol cols="3" class="d-flex align-end">
          <VBtn block color="primary" :loading="sailingSearchLoading" @click="searchSailings">
            {{ $gettext('Search') }}
          </VBtn>
        </VCol>
      </VRow>
      <VTable v-if="sailingSearchResults.length > 0" density="compact">
        <thead>
          <tr>
            <th>{{ $gettext('Carrier') }}</th>
            <th>{{ $gettext('Vessel') }}</th>
            <th>{{ $gettext('Voyage') }}</th>
            <th>{{ $gettext('Service') }}</th>
            <th>{{ $gettext('ETD') }}</th>
            <th>{{ $gettext('ETA') }}</th>
            <th>{{ $gettext('Transit') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in sailingSearchResults" :key="s.id ?? s.voyageNo">
            <td>{{ s.carrier }}</td>
            <td>{{ s.vessel }}</td>
            <td>{{ s.voyageNo }}</td>
            <td>{{ s.service }}</td>
            <td>{{ s.etd ? s.etd.slice(0, 10) : '—' }}</td>
            <td>{{ s.eta ? s.eta.slice(0, 10) : '—' }}</td>
            <td>{{ s.transitDays ?? '—' }}d</td>
            <td>
              <VBtn size="x-small" color="primary" @click="selectSailing(s)">
                {{ $gettext('Select') }}
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
      <p v-else-if="!sailingSearchLoading" class="text-medium-emphasis text-caption mt-2">
        {{ $gettext('Enter POL and POD and click Search to find available sailings.') }}
      </p>
    </VCardText>
    <VCardActions>
      <VSpacer />
      <VBtn variant="text" @click="sailingPickerOpen = false">{{ $gettext('Cancel') }}</VBtn>
    </VCardActions>
  </VCard>
</VDialog>

<!-- Flight Schedule Picker Dialog -->
<VDialog v-model="flightPickerOpen" max-width="800" scrollable>
  <VCard :title="$gettext('Search Flight Schedule')">
    <VCardText>
      <VRow class="mb-3">
        <VCol cols="3">
          <VTextField v-model="flightSearchOrigin" :label="$gettext('Origin (IATA)')" density="compact" />
        </VCol>
        <VCol cols="3">
          <VTextField v-model="flightSearchDest" :label="$gettext('Destination (IATA)')" density="compact" />
        </VCol>
        <VCol cols="3">
          <VTextField v-model="flightSearchDate" :label="$gettext('Date')" type="date" density="compact" />
        </VCol>
        <VCol cols="3" class="d-flex align-end">
          <VBtn block color="info" :loading="flightSearchLoading" @click="searchFlights">
            {{ $gettext('Search') }}
          </VBtn>
        </VCol>
      </VRow>
      <VTable v-if="flightSearchResults.length > 0" density="compact">
        <thead>
          <tr>
            <th>{{ $gettext('Flight') }}</th>
            <th>{{ $gettext('Airline') }}</th>
            <th>{{ $gettext('STD') }}</th>
            <th>{{ $gettext('STA') }}</th>
            <th>{{ $gettext('Cargo Cutoff') }}</th>
            <th>{{ $gettext('Doc Cutoff') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in flightSearchResults" :key="f.id ?? f.flightNo">
            <td>{{ f.flightNo }}</td>
            <td>{{ f.carrierName ?? f.carrierIata }}</td>
            <td>{{ f.std ? f.std.slice(0, 16).replace('T', ' ') : '—' }}</td>
            <td>{{ f.sta ? f.sta.slice(0, 16).replace('T', ' ') : '—' }}</td>
            <td>{{ f.cargoCutOff ? f.cargoCutOff.slice(0, 16).replace('T', ' ') : '—' }}</td>
            <td>{{ f.docCutOff ? f.docCutOff.slice(0, 16).replace('T', ' ') : '—' }}</td>
            <td>
              <VBtn size="x-small" color="info" @click="selectFlight(f)">
                {{ $gettext('Select') }}
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
      <p v-else-if="!flightSearchLoading" class="text-medium-emphasis text-caption mt-2">
        {{ $gettext('Enter origin and destination IATA codes and click Search.') }}
      </p>
    </VCardText>
    <VCardActions>
      <VSpacer />
      <VBtn variant="text" @click="flightPickerOpen = false">{{ $gettext('Cancel') }}</VBtn>
    </VCardActions>
  </VCard>
</VDialog>
```

- [ ] **Step 7: Commit**

```bash
git add src/views/shipment/info/BookingForm.vue
git commit -m "feat(vs-03): add vessel sailing and flight schedule picker to BookingForm"
```

---

### Task 3: VesselRolls component + integration into ShipmentDetail

**Files:**
- Create: `src/views/shipment/VesselRolls.vue`
- Modify: `src/views/shipment/ShipmentDetail.vue` (or the file that composes the booking/shipment tabs)

- [ ] **Step 1: Read ShipmentDetail.vue to understand composition**

Read `src/views/shipment/ShipmentDetail.vue`. Look for:
1. How tabs or sections are composed (tabs array, v-for over sections, or explicit `<component :is="..." />`).
2. What prop the shipment object is passed as.
3. Where `BookingForm.vue` or `Booking.vue` is included.
4. Whether there is already a "Rolls" or "Activity" section.

- [ ] **Step 2: Create `src/views/shipment/VesselRolls.vue`**

```vue
<script setup>
import VesselRollService from '@/services/VesselRollService'

const props = defineProps({
  shipmentId: { type: Number, required: true },
})

const rolls = ref([])
const loading = ref(false)
const dialogOpen = ref(false)

const form = reactive({
  originalSailingRef: '',
  originalEtd: '',
  newSailingRef: '',
  newEtd: '',
  reason: '',
})

async function loadRolls() {
  loading.value = true
  try {
    rolls.value = await VesselRollService.list(props.shipmentId)
  } finally {
    loading.value = false
  }
}

function openDialog() {
  Object.assign(form, { originalSailingRef: '', originalEtd: '', newSailingRef: '', newEtd: '', reason: '' })
  dialogOpen.value = true
}

async function submitRoll() {
  await VesselRollService.create({ ...form, shipmentId: props.shipmentId })
  dialogOpen.value = false
  await loadRolls()
}

async function markNotified(id) {
  await VesselRollService.markNotified(id)
  await loadRolls()
}

onMounted(loadRolls)
</script>

<template>
  <div>
    <div class="d-flex align-center mb-3">
      <span class="text-h6">{{ $gettext('Vessel Rolls') }}</span>
      <VSpacer />
      <VBtn size="small" color="warning" prepend-icon="tabler-rotate-clockwise" @click="openDialog">
        {{ $gettext('Record Roll') }}
      </VBtn>
    </div>

    <VTable v-if="rolls.length > 0" density="compact">
      <thead>
        <tr>
          <th>{{ $gettext('Rolled At') }}</th>
          <th>{{ $gettext('Original Sailing') }}</th>
          <th>{{ $gettext('Original ETD') }}</th>
          <th>{{ $gettext('New Sailing') }}</th>
          <th>{{ $gettext('New ETD') }}</th>
          <th>{{ $gettext('Reason') }}</th>
          <th>{{ $gettext('Customer Notified') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="roll in rolls" :key="roll.id">
          <td>{{ roll.rolledAt ? roll.rolledAt.slice(0, 16).replace('T', ' ') : '—' }}</td>
          <td>{{ roll.originalSailingRef ?? '—' }}</td>
          <td>{{ roll.originalEtd ? roll.originalEtd.slice(0, 10) : '—' }}</td>
          <td>{{ roll.newSailingRef ?? '—' }}</td>
          <td>{{ roll.newEtd ? roll.newEtd.slice(0, 10) : '—' }}</td>
          <td>{{ roll.reason ?? '—' }}</td>
          <td>
            <VChip v-if="roll.notifiedAt" size="x-small" color="success">{{ $gettext('Yes') }}</VChip>
            <VChip v-else size="x-small" color="warning">{{ $gettext('Pending') }}</VChip>
          </td>
          <td>
            <VBtn v-if="!roll.notifiedAt" size="x-small" variant="text" @click="markNotified(roll.id)">
              {{ $gettext('Mark Notified') }}
            </VBtn>
          </td>
        </tr>
      </tbody>
    </VTable>

    <p v-else-if="!loading" class="text-medium-emphasis text-caption">
      {{ $gettext('No vessel rolls recorded.') }}
    </p>

    <VDialog v-model="dialogOpen" max-width="560">
      <VCard :title="$gettext('Record Vessel Roll')">
        <VCardText>
          <VRow>
            <VCol cols="6">
              <VTextField v-model="form.originalSailingRef" :label="$gettext('Original Sailing Ref')" density="compact" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.originalEtd" :label="$gettext('Original ETD')" type="date" density="compact" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.newSailingRef" :label="$gettext('New Sailing Ref')" density="compact" />
            </VCol>
            <VCol cols="6">
              <VTextField v-model="form.newEtd" :label="$gettext('New ETD')" type="date" density="compact" />
            </VCol>
            <VCol cols="12">
              <VTextField v-model="form.reason" :label="$gettext('Reason')" density="compact" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogOpen = false">{{ $gettext('Cancel') }}</VBtn>
          <VBtn color="warning" @click="submitRoll">{{ $gettext('Record Roll') }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
```

- [ ] **Step 3: Add VesselRolls to ShipmentDetail.vue**

After reading `ShipmentDetail.vue`, find where the booking section is composed. Add the `VesselRolls` component beneath the booking section (or as a new tab if the layout uses tabs).

Import at the top of the script section:

```javascript
import VesselRolls from '@/views/shipment/VesselRolls.vue'
```

In the template, add after the booking component (adjust placement based on actual layout):

```html
<VesselRolls v-if="state.entity?.id" :shipment-id="state.entity.id" class="mt-4" />
```

**Note:** Replace `state.entity.id` with the correct reference to the shipment ID used in that component. It might be `shipment.id`, `entity.id`, or similar — check after reading the file.

- [ ] **Step 4: Commit**

```bash
git add src/views/shipment/VesselRolls.vue src/views/shipment/ShipmentDetail.vue
git commit -m "feat(vs-03): add VesselRolls component and integrate into ShipmentDetail"
```
