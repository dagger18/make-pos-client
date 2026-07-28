# AB-07: Duplicate Detection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a duplicate check endpoint for both Client and Provider that returns potential duplicates based on exact tax number match or name similarity (LIKE-based, works on MySQL and SQLite). The BO calls this before submitting the creation form and shows a warning dialog listing duplicates, letting the operator pick an existing record or proceed anyway.

**Architecture:** Two new `GET /client/check-duplicates` and `GET /provider/check-duplicates` endpoints return an array of candidate records. Name matching uses `LIKE '%name%'` (case-insensitive) rather than `pg_trgm` since the app targets MySQL. Tax number is an exact match. The BO `ClientForm.vue` and `ProviderForm.vue` call the endpoint before the final POST and gate on a confirmation dialog if duplicates are found.

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine DBAL, Vue 3 + Vuetify (BO)

---

## File Structure

**API repo (`d:\Projects\make-cargo-client`):**
- Modify: `src/Repository/ClientRepository.php` — add `findPotentialDuplicates()`
- Modify: `src/Repository/ProviderRepository.php` — add `findPotentialDuplicates()`
- Modify: `src/Controller/Api/ClientController.php` — add `CHECK_DUPLICATES` route
- Modify: `src/Controller/Api/ProviderController.php` — add `CHECK_DUPLICATES` route

**BO repo (`d:\Projects\make-cargo-client-bo`):**
- Modify: `src/services/ClientService.js` — add `checkDuplicates()`
- Modify: `src/services/ProviderService.js` — add `checkDuplicates()`
- Modify: `src/views/client/ClientForm.vue` — intercept submit, show duplicate warning
- Modify: `src/views/provider/ProviderForm.vue` — intercept submit, show duplicate warning

---

### Task 1: Repository duplicate check methods

**Files:**
- Modify: `src/Repository/ClientRepository.php`
- Modify: `src/Repository/ProviderRepository.php`

- [ ] **Step 1: Add `findPotentialDuplicates()` to `src/Repository/ClientRepository.php`**

Open `src/Repository/ClientRepository.php`. Add this method:

```php
public function findPotentialDuplicates(string $name, ?string $taxNumber = null): array
{
    $qb = $this->createQueryBuilder('c')
        ->select('c.id, c.name, c.code, c.taxNumber, c.country')
        ->where('LOWER(c.name) LIKE LOWER(:name)')
        ->setParameter('name', '%' . $name . '%')
        ->setMaxResults(5);

    if ($taxNumber) {
        $qb->orWhere('c.taxNumber = :taxNumber')
           ->setParameter('taxNumber', $taxNumber);
    }

    return $qb->getQuery()->getArrayResult();
}
```

- [ ] **Step 2: Add `findPotentialDuplicates()` to `src/Repository/ProviderRepository.php`**

Same method, but operates on the `Provider` entity. Replace `c.taxNumber` with `p.taxNumber` and the entity alias `c` with `p`:

```php
public function findPotentialDuplicates(string $name, ?string $taxNumber = null): array
{
    $qb = $this->createQueryBuilder('p')
        ->select('p.id, p.name, p.code, p.taxNumber, p.country')
        ->where('LOWER(p.name) LIKE LOWER(:name)')
        ->setParameter('name', '%' . $name . '%')
        ->setMaxResults(5);

    if ($taxNumber) {
        $qb->orWhere('p.taxNumber = :taxNumber')
           ->setParameter('taxNumber', $taxNumber);
    }

    return $qb->getQuery()->getArrayResult();
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Repository/ClientRepository.php src/Repository/ProviderRepository.php
git commit -m "feat(ab-07): add findPotentialDuplicates to ClientRepository and ProviderRepository"
```

---

### Task 2: Check-duplicates endpoints in controllers

**Files:**
- Modify: `src/Controller/Api/ClientController.php`
- Modify: `src/Controller/Api/ProviderController.php`

- [ ] **Step 1: Add route to `src/Controller/Api/ClientController.php`**

Add this method before the `POST` method:

```php
    #[Route('/check-duplicates', methods: ['GET'])]
    public function CHECK_DUPLICATES(Request $request): JsonResponse
    {
        $name = trim($request->query->getString('name', ''));
        $taxNumber = trim($request->query->getString('taxNumber', ''));
        if (strlen($name) < 2) {
            return $this->json([]);
        }
        return $this->json(
            $this->repository->findPotentialDuplicates($name, $taxNumber ?: null)
        );
    }
```

The `$this->repository` is already available via `CrudController`. The return is a plain array of `[id, name, code, taxNumber, country]` — no serializer group needed.

- [ ] **Step 2: Add the same route to `src/Controller/Api/ProviderController.php`**

```php
    #[Route('/check-duplicates', methods: ['GET'])]
    public function CHECK_DUPLICATES(Request $request): JsonResponse
    {
        $name = trim($request->query->getString('name', ''));
        $taxNumber = trim($request->query->getString('taxNumber', ''));
        if (strlen($name) < 2) {
            return $this->json([]);
        }
        return $this->json(
            $this->repository->findPotentialDuplicates($name, $taxNumber ?: null)
        );
    }
```

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/ClientController.php src/Controller/Api/ProviderController.php
git commit -m "feat(ab-07): add GET /client/check-duplicates and GET /provider/check-duplicates endpoints"
```

---

### Task 3: BO — service methods + duplicate warning dialog in creation forms

**Files:**
- Modify: `src/services/ClientService.js` (BO repo)
- Modify: `src/services/ProviderService.js` (BO repo)
- Modify: `src/views/client/ClientForm.vue` (BO repo)
- Modify: `src/views/provider/ProviderForm.vue` (BO repo)

- [ ] **Step 1: Add `checkDuplicates` to `src/services/ClientService.js`**

```js
checkDuplicates(name, taxNumber = '') {
  const params = new URLSearchParams({ name, taxNumber }).toString()
  return $api(`client/check-duplicates?${params}`)
},
```

- [ ] **Step 2: Add `checkDuplicates` to `src/services/ProviderService.js`**

```js
checkDuplicates(name, taxNumber = '') {
  const params = new URLSearchParams({ name, taxNumber }).toString()
  return $api(`provider/check-duplicates?${params}`)
},
```

- [ ] **Step 3: Add duplicate check interception to `src/views/client/ClientForm.vue`**

Open `src/views/client/ClientForm.vue`. Find the `submit` or `onEntitySubmit` function — the function that is called when the creation form is submitted. 

Add these reactive refs in the `<script setup>`:

```js
import ClientService from '@/services/ClientService'

const duplicates = ref([])
const duplicateDialogOpen = ref(false)
const pendingSubmitFn = ref(null)
```

Wrap the existing submit logic with a duplicate check. Find the point where the API POST is called (likely inside an `entityPreSubmit` callback or directly in the form's submit handler). Add an interceptor:

```js
async function checkAndSubmit(originalSubmit) {
  const name = form.value?.name ?? ''
  const taxNumber = form.value?.taxNumber ?? ''
  if (!name) { await originalSubmit(); return }

  const found = await ClientService.checkDuplicates(name, taxNumber)
  if (found && found.length > 0) {
    duplicates.value = found
    pendingSubmitFn.value = originalSubmit
    duplicateDialogOpen.value = true
  } else {
    await originalSubmit()
  }
}

async function proceedDespiteDuplicates() {
  duplicateDialogOpen.value = false
  await pendingSubmitFn.value()
}
```

Add the warning dialog to the template (inside the form's VDialog or at the same level):

```html
<VDialog v-model="duplicateDialogOpen" max-width="520">
  <VCard :title="$gettext('Possible Duplicate Detected')">
    <VCardText>
      <p class="mb-3">{{ $gettext('The following existing clients have a similar name or the same tax number:') }}</p>
      <VList density="compact">
        <VListItem
          v-for="dup in duplicates"
          :key="dup.id"
          :title="dup.name"
          :subtitle="`${dup.code ?? ''} · ${dup.country ?? ''} · Tax: ${dup.taxNumber ?? '—'}`"
        />
      </VList>
      <p class="mt-3 text-medium-emphasis text-caption">{{ $gettext('You can select an existing record or continue creating a new one.') }}</p>
    </VCardText>
    <VCardActions>
      <VSpacer />
      <VBtn variant="text" @click="duplicateDialogOpen = false">{{ $gettext('Cancel') }}</VBtn>
      <VBtn color="warning" @click="proceedDespiteDuplicates">{{ $gettext('Create Anyway') }}</VBtn>
    </VCardActions>
  </VCard>
</VDialog>
```

- [ ] **Step 4: Apply the same pattern to `src/views/provider/ProviderForm.vue`**

Identical structure, replacing `ClientService` with `ProviderService` and `client` references with `provider`.

- [ ] **Step 5: Commit**

```bash
git add src/services/ClientService.js src/services/ProviderService.js src/views/client/ClientForm.vue src/views/provider/ProviderForm.vue
git commit -m "feat(ab-07): add duplicate detection warning dialog to Client and Provider creation forms"
```
