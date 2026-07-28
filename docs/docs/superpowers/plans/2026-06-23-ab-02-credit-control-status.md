# AB-02: Credit Control Status Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a 4-state `creditStatus` field (ACTIVE / ON_HOLD / BLOCKED / BLACKLISTED) to both Client and Provider, with `creditHoldReason`, `creditReviewedAt`, and `creditReviewedBy` fields, and a guard in the Quote creation flow that blocks quotes for BLOCKED or BLACKLISTED clients.

**Architecture:** A new `CreditStatus` PHP enum is added. `Client` and `Provider` entities gain 4 new fields. `QuoteController::POST` checks the client's creditStatus before persisting. The BO shows the credit status as a coloured chip on the client list and provides a dialog for credit managers to change status + reason. No automatic status transitions in this plan — those belong in a future accounts-receivable reconciliation job.

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine ORM, MySQL + SQLite migrations, Vue 3 + Vuetify (BO)

---

## File Structure

**API repo (`d:\Projects\make-cargo-client`):**
- Create: `src/Misc/Enum/CreditStatus.php`
- Modify: `src/Entity/Client.php`
- Modify: `src/Entity/Provider.php`
- Modify: `config/serializer_groups/Client.yaml`
- Modify: `config/serializer_groups/Provider.yaml`
- Modify: `src/Controller/Api/QuoteController.php` — guard on POST
- Create: `migrations/mysql/Version20260623020000.php`
- Create: `migrations/sqlite/Version20260623020000.php`

**BO repo (`d:\Projects\make-cargo-client-bo`):**
- Create: `src/config/enums/CreditStatus.js`
- Modify: `src/config/tables/Client.js` — credit status chip column
- Modify: `src/config/tables/Provider.js` — credit status chip column
- Modify: `src/views/client/ClientGeneral.vue` — add credit status section
- Modify: `src/views/provider/ProviderGeneral.vue` — add credit status section
- Modify: `src/services/ClientService.js` — add updateCreditStatus method
- Modify: `src/services/ProviderService.js` — add updateCreditStatus method

---

### Task 1: CreditStatus enum + entity fields + migration

**Files:**
- Create: `src/Misc/Enum/CreditStatus.php`
- Modify: `src/Entity/Client.php`
- Modify: `src/Entity/Provider.php`
- Create: `migrations/mysql/Version20260623020000.php`
- Create: `migrations/sqlite/Version20260623020000.php`

- [ ] **Step 1: Create `src/Misc/Enum/CreditStatus.php`**

```php
<?php
namespace App\Misc\Enum;

enum CreditStatus: string
{
    case Active      = 'ACTIVE';
    case OnHold      = 'ON_HOLD';
    case Blocked     = 'BLOCKED';
    case Blacklisted = 'BLACKLISTED';
}
```

- [ ] **Step 2: Add fields to `src/Entity/Client.php`**

Add these imports at the top of the file:
```php
use App\Misc\Enum\CreditStatus;
```

Add these fields after the existing `$creditLimit` and `$unpaid` fields:

```php
    #[ORM\Column(length: 16, enumType: CreditStatus::class)]
    private CreditStatus $creditStatus = CreditStatus::Active;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $creditHoldReason = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $creditReviewedAt = null;

    #[ORM\ManyToOne]
    private ?User $creditReviewedBy = null;
```

Add getters/setters:

```php
    public function getCreditStatus(): CreditStatus { return $this->creditStatus; }
    public function setCreditStatus(CreditStatus $creditStatus): static { $this->creditStatus = $creditStatus; return $this; }

    public function getCreditHoldReason(): ?string { return $this->creditHoldReason; }
    public function setCreditHoldReason(?string $creditHoldReason): static { $this->creditHoldReason = $creditHoldReason; return $this; }

    public function getCreditReviewedAt(): ?\DateTimeInterface { return $this->creditReviewedAt; }
    public function setCreditReviewedAt(?\DateTimeInterface $creditReviewedAt): static { $this->creditReviewedAt = $creditReviewedAt; return $this; }

    public function getCreditReviewedBy(): ?User { return $this->creditReviewedBy; }
    public function setCreditReviewedBy(?User $creditReviewedBy): static { $this->creditReviewedBy = $creditReviewedBy; return $this; }
```

- [ ] **Step 3: Add the same 4 fields to `src/Entity/Provider.php`**

Add import at top:
```php
use App\Misc\Enum\CreditStatus;
```

Add fields after the existing `$creditLimit` field:

```php
    #[ORM\Column(length: 16, enumType: CreditStatus::class)]
    private CreditStatus $creditStatus = CreditStatus::Active;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $creditHoldReason = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $creditReviewedAt = null;

    #[ORM\ManyToOne]
    private ?User $creditReviewedBy = null;
```

Add the same getters/setters as Client.

- [ ] **Step 4: Create MySQL migration**

Create `migrations/mysql/Version20260623020000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add credit control status fields to client and provider';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE client ADD credit_status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE'");
        $this->addSql('ALTER TABLE client ADD credit_hold_reason LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD credit_reviewed_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD credit_reviewed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD CONSTRAINT FK_client_credit_reviewed_by FOREIGN KEY (credit_reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_client_credit_status ON client (credit_status)');

        $this->addSql("ALTER TABLE provider ADD credit_status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE'");
        $this->addSql('ALTER TABLE provider ADD credit_hold_reason LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider ADD credit_reviewed_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE provider ADD credit_reviewed_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider ADD CONSTRAINT FK_provider_credit_reviewed_by FOREIGN KEY (credit_reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP FOREIGN KEY FK_client_credit_reviewed_by');
        $this->addSql('DROP INDEX IDX_client_credit_status ON client');
        $this->addSql('ALTER TABLE client DROP credit_status, DROP credit_hold_reason, DROP credit_reviewed_at, DROP credit_reviewed_by_id');
        $this->addSql('ALTER TABLE provider DROP FOREIGN KEY FK_provider_credit_reviewed_by');
        $this->addSql('ALTER TABLE provider DROP credit_status, DROP credit_hold_reason, DROP credit_reviewed_at, DROP credit_reviewed_by_id');
    }
}
```

- [ ] **Step 5: Create SQLite migration**

Create `migrations/sqlite/Version20260623020000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add credit control status fields to client and provider';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE client ADD COLUMN credit_status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE'");
        $this->addSql('ALTER TABLE client ADD COLUMN credit_hold_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD COLUMN credit_reviewed_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD COLUMN credit_reviewed_by_id INTEGER DEFAULT NULL');

        $this->addSql("ALTER TABLE provider ADD COLUMN credit_status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE'");
        $this->addSql('ALTER TABLE provider ADD COLUMN credit_hold_reason TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE provider ADD COLUMN credit_reviewed_at DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE provider ADD COLUMN credit_reviewed_by_id INTEGER DEFAULT NULL');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 6: Validate schema**

```bash
php bin/console doctrine:schema:validate --env=test
```

Expected: `[OK] The mapping files are correct.` and database in sync.

- [ ] **Step 7: Commit**

```bash
git add src/Misc/Enum/CreditStatus.php src/Entity/Client.php src/Entity/Provider.php migrations/mysql/Version20260623020000.php migrations/sqlite/Version20260623020000.php
git commit -m "feat(ab-02): add CreditStatus enum and credit control fields to Client and Provider"
```

---

### Task 2: Update serializer groups + add credit status guard to QuoteController

**Files:**
- Modify: `config/serializer_groups/Client.yaml`
- Modify: `config/serializer_groups/Provider.yaml`
- Modify: `src/Controller/Api/QuoteController.php`

- [ ] **Step 1: Expose credit fields in Client serializer**

In `config/serializer_groups/Client.yaml`, add to the `list` group:

```yaml
        - creditStatus
        - creditHoldReason
        - creditReviewedAt
        - creditReviewedBy
```

- [ ] **Step 2: Expose credit fields in Provider serializer**

In `config/serializer_groups/Provider.yaml`, add to the `list` group:

```yaml
        - creditStatus
        - creditHoldReason
```

- [ ] **Step 3: Find the POST method in QuoteController**

Open `src/Controller/Api/QuoteController.php`. Find the `POST` method (the one with `#[Route('', methods: ['POST'])]`). Locate where `$entity` (the Quote) is saved. The Quote entity has a `client` property.

Add this check immediately before the `$this->repository->save(...)` call:

```php
        $client = $entity->getClient();
        if ($client !== null) {
            $blockedStatuses = [\App\Misc\Enum\CreditStatus::Blocked, \App\Misc\Enum\CreditStatus::Blacklisted];
            if (in_array($client->getCreditStatus(), $blockedStatuses)) {
                return $this->json([
                    'error' => 'Client credit status does not allow new quotes',
                    'creditStatus' => $client->getCreditStatus()->value,
                    'creditHoldReason' => $client->getCreditHoldReason(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
```

- [ ] **Step 4: Commit**

```bash
git add config/serializer_groups/Client.yaml config/serializer_groups/Provider.yaml src/Controller/Api/QuoteController.php
git commit -m "feat(ab-02): expose credit status in serializer; block quotes for BLOCKED/BLACKLISTED clients"
```

---

### Task 3: BO — CreditStatus enum + client/provider list chips

**Files:**
- Create: `src/config/enums/CreditStatus.js` (BO repo)
- Modify: `src/config/tables/Client.js` (BO repo)
- Modify: `src/config/tables/Provider.js` (BO repo)

- [ ] **Step 1: Create `src/config/enums/CreditStatus.js`**

```js
function list() {
  return [
    { value: 'ACTIVE',      title: $gettext('Active'),      color: 'success' },
    { value: 'ON_HOLD',     title: $gettext('On Hold'),     color: 'warning' },
    { value: 'BLOCKED',     title: $gettext('Blocked'),     color: 'error' },
    { value: 'BLACKLISTED', title: $gettext('Blacklisted'), color: 'error' },
  ]
}
export const findByValue = (value) => list().find(s => s.value === value) ?? null
export const getList = () => list()
```

- [ ] **Step 2: Add `creditStatus` column to `src/config/tables/Client.js`**

Add this header entry:

```js
{ key: 'creditStatus', sortable: false, text: $gettext('Credit Status'), renderSlot: 'creditStatus' },
```

Also add a filter:

```js
{ name: 'creditStatus', text: $gettext('Credit Status'), type: 'select', items: [
  { value: 'ACTIVE', title: $gettext('Active') },
  { value: 'ON_HOLD', title: $gettext('On Hold') },
  { value: 'BLOCKED', title: $gettext('Blocked') },
  { value: 'BLACKLISTED', title: $gettext('Blacklisted') },
]},
```

- [ ] **Step 3: Add `creditStatus` column to `src/config/tables/Provider.js`**

Same as Client — add header entry and filter.

- [ ] **Step 4: Commit**

```bash
git add src/config/enums/CreditStatus.js src/config/tables/Client.js src/config/tables/Provider.js
git commit -m "feat(ab-02): add CreditStatus enum and credit status column to BO Client/Provider list"
```

---

### Task 4: BO — credit status management UI on Client/Provider detail

**Files:**
- Modify: `src/views/client/ClientGeneral.vue` (BO repo)
- Modify: `src/views/provider/ProviderGeneral.vue` (BO repo)
- Modify: `src/services/ClientService.js` (BO repo)
- Modify: `src/services/ProviderService.js` (BO repo)

- [ ] **Step 1: Add `updateCreditStatus` to `src/services/ClientService.js`**

```js
updateCreditStatus(id, data) {
  return $api(`client/${id}`, {
    method: 'PUT',
    body: CommonService.formData({ id, ...data }),
    loading: true
  })
},
```

Do the same in `src/services/ProviderService.js` (replacing `client` with `provider`).

- [ ] **Step 2: Add credit status chip + dialog to `src/views/client/ClientGeneral.vue`**

In the script setup section, add:

```js
import { findByValue as findCreditStatus, getList as creditStatusList } from '@/config/enums/CreditStatus'
import ClientService from '@/services/ClientService'

const creditDialogOpen = ref(false)
const creditStatusInput = ref('')
const creditHoldReasonInput = ref('')

async function saveCreditStatus() {
  await ClientService.updateCreditStatus(props.client.id, {
    creditStatus: creditStatusInput.value,
    creditHoldReason: creditHoldReasonInput.value || null,
    creditReviewedAt: new Date().toISOString().split('T')[0],
  })
  creditDialogOpen.value = false
  emit('clientChanged')
}
function openCreditDialog() {
  creditStatusInput.value = props.client.creditStatus
  creditHoldReasonInput.value = props.client.creditHoldReason ?? ''
  creditDialogOpen.value = true
}
```

In the template, find where credit limit/period are shown and add directly below:

```html
<div class="mt-4">
  <div class="text-disabled font-weight-bold mb-1">{{ $gettext('Credit Status') }}</div>
  <VChip :color="findCreditStatus(client.creditStatus)?.color ?? 'default'" class="me-2">
    {{ findCreditStatus(client.creditStatus)?.title ?? client.creditStatus }}
  </VChip>
  <VBtn v-if="$can('PUT', 'Client')" size="small" variant="text" @click="openCreditDialog">
    <VIcon size="16" icon="tabler-pencil" />
  </VBtn>
  <div v-if="client.creditHoldReason" class="text-caption text-medium-emphasis mt-1">
    {{ client.creditHoldReason }}
  </div>
</div>

<VDialog v-model="creditDialogOpen" max-width="480">
  <VCard :title="$gettext('Update Credit Status')">
    <VCardText>
      <VSelect
        v-model="creditStatusInput"
        :label="$gettext('Credit Status')"
        :items="creditStatusList()"
        item-value="value"
        item-title="title"
        class="mb-3"
      />
      <VTextarea
        v-model="creditHoldReasonInput"
        :label="$gettext('Reason / Notes')"
        rows="3"
      />
    </VCardText>
    <VCardActions>
      <VSpacer />
      <VBtn variant="text" @click="creditDialogOpen = false">{{ $gettext('Cancel') }}</VBtn>
      <VBtn color="primary" @click="saveCreditStatus">{{ $gettext('Save') }}</VBtn>
    </VCardActions>
  </VCard>
</VDialog>
```

Apply the same pattern to `src/views/provider/ProviderGeneral.vue`, replacing `client` with `provider` and `ClientService` with `ProviderService`.

- [ ] **Step 5: Commit**

```bash
git add src/views/client/ClientGeneral.vue src/views/provider/ProviderGeneral.vue src/services/ClientService.js src/services/ProviderService.js
git commit -m "feat(ab-02): credit status management UI on Client and Provider detail views"
```
