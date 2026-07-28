# AB-03: Customer Tier Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `tier` field (PLATINUM / GOLD / SILVER / STANDARD) to the Client entity, expose it in the API, and display it in the BO client list and detail view. The tier communicates pricing priority to operators and sets the stage for rate-engine integration.

**Architecture:** A new `ClientTier` PHP enum is added. A single nullable column is added to the `client` table. The serializer exposes `tier`. The BO shows a coloured tier chip on the list and a dropdown selector on the detail view. Rate engine integration (using tier to select rate card pools) is out of scope for this plan.

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine ORM, MySQL + SQLite migrations, Vue 3 + Vuetify (BO)

---

## File Structure

**API repo (`d:\Projects\make-cargo-client`):**
- Create: `src/Misc/Enum/ClientTier.php`
- Modify: `src/Entity/Client.php`
- Modify: `config/serializer_groups/Client.yaml`
- Create: `migrations/mysql/Version20260623030000.php`
- Create: `migrations/sqlite/Version20260623030000.php`

**BO repo (`d:\Projects\make-cargo-client-bo`):**
- Create: `src/config/enums/ClientTier.js`
- Modify: `src/config/forms/ClientGeneral.js`
- Modify: `src/config/tables/Client.js`

---

### Task 1: ClientTier enum + entity field + migration

**Files:**
- Create: `src/Misc/Enum/ClientTier.php`
- Modify: `src/Entity/Client.php`
- Create: `migrations/mysql/Version20260623030000.php`
- Create: `migrations/sqlite/Version20260623030000.php`

- [ ] **Step 1: Create `src/Misc/Enum/ClientTier.php`**

```php
<?php
namespace App\Misc\Enum;

enum ClientTier: string
{
    case Platinum = 'PLATINUM';
    case Gold     = 'GOLD';
    case Silver   = 'SILVER';
    case Standard = 'STANDARD';
}
```

- [ ] **Step 2: Add `$tier` field to `src/Entity/Client.php`**

Add import at top:
```php
use App\Misc\Enum\ClientTier;
```

Add field after the existing `$type` field:

```php
    #[ORM\Column(length: 16, nullable: true, enumType: ClientTier::class)]
    private ?ClientTier $tier = null;
```

Add getter/setter before `__construct()`:

```php
    public function getTier(): ?ClientTier { return $this->tier; }
    public function setTier(?ClientTier $tier): static { $this->tier = $tier; return $this; }
```

- [ ] **Step 3: Create MySQL migration**

Create `migrations/mysql/Version20260623030000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tier field to client (PLATINUM/GOLD/SILVER/STANDARD)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD tier VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_client_tier ON client (tier)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_client_tier ON client');
        $this->addSql('ALTER TABLE client DROP tier');
    }
}
```

- [ ] **Step 4: Create SQLite migration**

Create `migrations/sqlite/Version20260623030000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623030000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tier field to client (PLATINUM/GOLD/SILVER/STANDARD)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD COLUMN tier VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_client_tier ON client (tier)');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 5: Validate schema**

```bash
php bin/console doctrine:schema:validate --env=test
```

Expected: `[OK] The mapping files are correct.`

- [ ] **Step 6: Commit**

```bash
git add src/Misc/Enum/ClientTier.php src/Entity/Client.php migrations/mysql/Version20260623030000.php migrations/sqlite/Version20260623030000.php
git commit -m "feat(ab-03): add ClientTier enum and tier field to Client entity"
```

---

### Task 2: Expose tier in serializer

**Files:**
- Modify: `config/serializer_groups/Client.yaml`

- [ ] **Step 1: Add `tier` to the list group**

In `config/serializer_groups/Client.yaml`, add `- tier` after `- id`:

```yaml
App\Entity\Client:

    list:
        - id
        - tier
        - name
        ...
```

- [ ] **Step 2: Commit**

```bash
git add config/serializer_groups/Client.yaml
git commit -m "feat(ab-03): expose tier in Client list serializer group"
```

---

### Task 3: BO — tier enum + list chip + form dropdown

**Files:**
- Create: `src/config/enums/ClientTier.js` (BO repo)
- Modify: `src/config/forms/ClientGeneral.js` (BO repo)
- Modify: `src/config/tables/Client.js` (BO repo)

- [ ] **Step 1: Create `src/config/enums/ClientTier.js`**

```js
function list() {
  return [
    { value: 'PLATINUM', title: $gettext('Platinum'), color: 'deep-purple' },
    { value: 'GOLD',     title: $gettext('Gold'),     color: 'amber-darken-2' },
    { value: 'SILVER',   title: $gettext('Silver'),   color: 'blue-grey' },
    { value: 'STANDARD', title: $gettext('Standard'), color: 'default' },
  ]
}
export const findByValue = (value) => list().find(s => s.value === value) ?? null
export const getList = () => list()
```

- [ ] **Step 2: Add tier dropdown to `src/config/forms/ClientGeneral.js`**

Add this import at the top:
```js
import { getList as clientTierList } from '@/config/enums/ClientTier';
```

In the form `layout`, find the row with `type` and `residenceType` and add tier to it:

```js
      [
        { name: 'tier', text: $gettext('Client Tier'), type: 'select', items: clientTierList(), columnSpan: 3, returnObject: false },
        { name: 'residenceType', text: $gettext('Residence Type'), type: 'select', items: residenceTypeList, columnSpan: 3, returnObject: false },
        { name: 'establishmentDate', text: $gettext('Establishment Date'), type: 'datePicker', columnSpan: 3 },
        { name: 'isActive', text: $gettext('Active'), type: 'checkbox', columnSpan: 3 },
      ]
```

- [ ] **Step 3: Add `tier` chip column to `src/config/tables/Client.js`**

Add after the `name` header:

```js
{ key: 'tier', sortable: false, text: $gettext('Tier'), renderSlot: 'tier' },
```

Add tier filter:

```js
{ name: 'tier', text: $gettext('Tier'), type: 'select', items: [
  { value: 'PLATINUM', title: $gettext('Platinum') },
  { value: 'GOLD',     title: $gettext('Gold') },
  { value: 'SILVER',   title: $gettext('Silver') },
  { value: 'STANDARD', title: $gettext('Standard') },
]},
```

In `src/pages/client/index.vue` (or wherever the client list table renders slots), add the tier slot:

```html
<template #tier="{item}">
  <VChip v-if="item.tier" :color="findTier(item.tier)?.color" size="small">
    {{ findTier(item.tier)?.title ?? item.tier }}
  </VChip>
</template>
```

And import `findByValue as findTier` from `@/config/enums/ClientTier`.

- [ ] **Step 4: Commit**

```bash
git add src/config/enums/ClientTier.js src/config/forms/ClientGeneral.js src/config/tables/Client.js
git commit -m "feat(ab-03): add tier chip to client list and tier dropdown to client form"
```
