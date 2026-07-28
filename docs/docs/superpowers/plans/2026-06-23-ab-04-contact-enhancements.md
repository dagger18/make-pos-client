# AB-04: Contact Enhancements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add notification routing flags (`receivesInvoice`, `receivesTracking`, `receivesArrival`), communication fields (`salutation`, `mobile`, `whatsapp`, `language`), and operational flags (`isActive`) to the Contact entity. These fields enable the future email notification system to know who receives what.

**Architecture:** Pure field additions to the existing `Contact` entity. One migration adds all columns. The serializer and BO form/contact list are updated. No new entities. The `isActive` flag allows soft-deactivating a contact without deleting it.

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine ORM, MySQL + SQLite migrations, Vue 3 + Vuetify (BO)

---

## File Structure

**API repo (`d:\Projects\make-cargo-client`):**
- Modify: `src/Entity/Contact.php`
- Modify: `config/serializer_groups/Contact.yaml`
- Create: `migrations/mysql/Version20260623040000.php`
- Create: `migrations/sqlite/Version20260623040000.php`

**BO repo (`d:\Projects\make-cargo-client-bo`):**
- Modify: `src/config/forms/Contact.js`
- Modify: `src/views/provider/Contacts.vue` (shared by Client and Provider)

---

### Task 1: Add fields to Contact entity + migration

**Files:**
- Modify: `src/Entity/Contact.php`
- Create: `migrations/mysql/Version20260623040000.php`
- Create: `migrations/sqlite/Version20260623040000.php`

- [ ] **Step 1: Add 8 new fields to `src/Entity/Contact.php`**

Insert after the existing `$gender` field:

```php
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $salutation = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $mobile = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $whatsapp = null;

    #[ORM\Column(length: 2)]
    private string $language = 'en';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $receivesInvoice = false;

    #[ORM\Column]
    private bool $receivesTracking = false;

    #[ORM\Column]
    private bool $receivesArrival = false;
```

Add getters/setters before the `getFullName()` method:

```php
    public function getSalutation(): ?string { return $this->salutation; }
    public function setSalutation(?string $salutation): static { $this->salutation = $salutation; return $this; }

    public function getMobile(): ?string { return $this->mobile; }
    public function setMobile(?string $mobile): static { $this->mobile = $mobile; return $this; }

    public function getWhatsapp(): ?string { return $this->whatsapp; }
    public function setWhatsapp(?string $whatsapp): static { $this->whatsapp = $whatsapp; return $this; }

    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $language): static { $this->language = $language; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function isReceivesInvoice(): bool { return $this->receivesInvoice; }
    public function setReceivesInvoice(bool $receivesInvoice): static { $this->receivesInvoice = $receivesInvoice; return $this; }

    public function isReceivesTracking(): bool { return $this->receivesTracking; }
    public function setReceivesTracking(bool $receivesTracking): static { $this->receivesTracking = $receivesTracking; return $this; }

    public function isReceivesArrival(): bool { return $this->receivesArrival; }
    public function setReceivesArrival(bool $receivesArrival): static { $this->receivesArrival = $receivesArrival; return $this; }
```

- [ ] **Step 2: Create MySQL migration**

Create `migrations/mysql/Version20260623040000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification flags, salutation, mobile, whatsapp, language, isActive to Contact';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD salutation VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD mobile VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD whatsapp VARCHAR(32) DEFAULT NULL');
        $this->addSql("ALTER TABLE contact ADD language VARCHAR(2) NOT NULL DEFAULT 'en'");
        $this->addSql('ALTER TABLE contact ADD is_active TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE contact ADD receives_invoice TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE contact ADD receives_tracking TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE contact ADD receives_arrival TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact DROP salutation, DROP mobile, DROP whatsapp, DROP language, DROP is_active, DROP receives_invoice, DROP receives_tracking, DROP receives_arrival');
    }
}
```

- [ ] **Step 3: Create SQLite migration**

Create `migrations/sqlite/Version20260623040000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification flags, salutation, mobile, whatsapp, language, isActive to Contact';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact ADD COLUMN salutation VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD COLUMN mobile VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD COLUMN whatsapp VARCHAR(32) DEFAULT NULL');
        $this->addSql("ALTER TABLE contact ADD COLUMN language VARCHAR(2) NOT NULL DEFAULT 'en'");
        $this->addSql('ALTER TABLE contact ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE contact ADD COLUMN receives_invoice INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE contact ADD COLUMN receives_tracking INTEGER NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE contact ADD COLUMN receives_arrival INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 4: Validate schema**

```bash
php bin/console doctrine:schema:validate --env=test
```

Expected: `[OK] The mapping files are correct.`

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Contact.php migrations/mysql/Version20260623040000.php migrations/sqlite/Version20260623040000.php
git commit -m "feat(ab-04): add notification flags and communication fields to Contact entity"
```

---

### Task 2: Update Contact serializer + BO form

**Files:**
- Modify: `config/serializer_groups/Contact.yaml`
- Modify: `src/config/forms/Contact.js` (BO repo)
- Modify: `src/views/provider/Contacts.vue` (BO repo)

- [ ] **Step 1: Update `config/serializer_groups/Contact.yaml`**

Find this file (check that it exists at `config/serializer_groups/Contact.yaml`). Add the new fields:

```yaml
App\Entity\Contact:

    list:
        - id
        - salutation
        - firstName
        - lastName
        - fullName
        - title
        - department
        - email
        - phone
        - mobile
        - whatsapp
        - language
        - isActive
        - receivesInvoice
        - receivesTracking
        - receivesArrival
```

- [ ] **Step 2: Update `src/config/forms/Contact.js`**

Replace the full `layout` function:

```js
export const layout = (entity) => {
  const { $gettext } = useGettext();
  return [
    [
      [{ name: 'salutation', text: $gettext('Salutation'), columnSpan: 2 }],
      [{ name: 'firstName', text: $gettext('First Name'), columnSpan: 4 }],
      [{ name: 'lastName', text: $gettext('Last Name'), columnSpan: 4 }],
      [{ name: 'title', text: $gettext('Title'), columnSpan: 4 }],
    ],
    [
      [{ name: 'email', text: $gettext('Email'), columnSpan: 4 }],
      [{ name: 'phone', text: $gettext('Phone'), columnSpan: 4 }],
      [{ name: 'mobile', text: $gettext('Mobile'), columnSpan: 4 }],
    ],
    [
      [{ name: 'whatsapp', text: $gettext('WhatsApp'), columnSpan: 4 }],
      [{ name: 'language', text: $gettext('Language'), columnSpan: 2 }],
      [{ name: 'department', text: $gettext('Department'), columnSpan: 3 }],
      [{ name: 'isActive', text: $gettext('Active'), type: 'checkbox', columnSpan: 3 }],
    ],
    [
      [{
        columnName: $gettext('Notification Preferences')
      }]
    ],
    [
      [{ name: 'receivesInvoice', text: $gettext('Receives Invoices'), type: 'checkbox', columnSpan: 4 }],
      [{ name: 'receivesTracking', text: $gettext('Receives Tracking Updates'), type: 'checkbox', columnSpan: 4 }],
      [{ name: 'receivesArrival', text: $gettext('Receives Arrival Notices'), type: 'checkbox', columnSpan: 4 }],
    ],
  ]
}
```

- [ ] **Step 3: Update `src/views/provider/Contacts.vue` to show notification icons in the contact list**

In the contacts table template, find where contact rows are rendered. After the phone number, add notification icons:

```html
<span class="ms-2">
  <VIcon v-if="contact.receivesInvoice" size="14" icon="tabler-receipt" :title="$gettext('Receives Invoices')" />
  <VIcon v-if="contact.receivesTracking" size="14" icon="tabler-radar" :title="$gettext('Receives Tracking')" class="ms-1" />
  <VIcon v-if="contact.receivesArrival" size="14" icon="tabler-bell" :title="$gettext('Receives Arrivals')" class="ms-1" />
</span>
```

Also add an inactive badge:

```html
<VChip v-if="!contact.isActive" size="x-small" color="warning" class="ms-1">{{ $gettext('Inactive') }}</VChip>
```

- [ ] **Step 4: Commit**

```bash
git add config/serializer_groups/Contact.yaml src/config/forms/Contact.js src/views/provider/Contacts.vue
git commit -m "feat(ab-04): update Contact serializer and BO form with notification flags and new fields"
```
