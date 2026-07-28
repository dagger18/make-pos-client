# AB-01: Organisation Core Fields Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add missing core fields to the Client entity (email, taxNumber, tradingName, registrationNo, website, isActive) and expose them in the API serializer and BO forms/list.

**Architecture:** Pure field additions to the existing `Client` entity following the same pattern as existing fields. A single migration adds the columns. Serializer YAML and BO form config are updated to expose the fields. No new entities or relationships needed.

**Tech Stack:** PHP 8.2, Symfony 6, Doctrine ORM, MySQL + SQLite migrations, Vue 3 + Vuetify (BO)

---

## File Structure

**API repo (`d:\Projects\make-cargo-client`):**
- Modify: `src/Entity/Client.php` — add 6 new fields with getters/setters
- Modify: `config/serializer_groups/Client.yaml` — expose new fields in `list` group
- Create: `migrations/mysql/Version20260623010000.php`
- Create: `migrations/sqlite/Version20260623010000.php`

**BO repo (`d:\Projects\make-cargo-client-bo`):**
- Modify: `src/config/forms/ClientGeneral.js` — add fields to form layout
- Modify: `src/config/tables/Client.js` — add email column to list view

---

### Task 1: Add fields to Client entity + migration

**Files:**
- Modify: `src/Entity/Client.php`
- Create: `migrations/mysql/Version20260623010000.php`
- Create: `migrations/sqlite/Version20260623010000.php`

- [ ] **Step 1: Add the 6 new fields to `src/Entity/Client.php`**

Insert after the existing `$phone` field (line 45):

```php
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tradingName = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $taxNumber = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $registrationNo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column]
    private bool $isActive = true;
```

Then add the getters/setters before `__construct()`:

```php
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(?string $email): static { $this->email = $email; return $this; }

    public function getTradingName(): ?string { return $this->tradingName; }
    public function setTradingName(?string $tradingName): static { $this->tradingName = $tradingName; return $this; }

    public function getTaxNumber(): ?string { return $this->taxNumber; }
    public function setTaxNumber(?string $taxNumber): static { $this->taxNumber = $taxNumber; return $this; }

    public function getRegistrationNo(): ?string { return $this->registrationNo; }
    public function setRegistrationNo(?string $registrationNo): static { $this->registrationNo = $registrationNo; return $this; }

    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): static { $this->website = $website; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
```

- [ ] **Step 2: Create MySQL migration**

Create `migrations/mysql/Version20260623010000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing core fields to client: email, tradingName, taxNumber, registrationNo, website, isActive';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD trading_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD tax_number VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD registration_no VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD website VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD is_active TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client DROP email');
        $this->addSql('ALTER TABLE client DROP trading_name');
        $this->addSql('ALTER TABLE client DROP tax_number');
        $this->addSql('ALTER TABLE client DROP registration_no');
        $this->addSql('ALTER TABLE client DROP website');
        $this->addSql('ALTER TABLE client DROP is_active');
    }
}
```

- [ ] **Step 3: Create SQLite migration**

Create `migrations/sqlite/Version20260623010000.php`:

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing core fields to client: email, tradingName, taxNumber, registrationNo, website, isActive';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE client ADD COLUMN email VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD COLUMN trading_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD COLUMN tax_number VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD COLUMN registration_no VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD COLUMN website VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE client ADD COLUMN is_active INTEGER NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void {}
}
```

- [ ] **Step 4: Validate schema**

```bash
php bin/console doctrine:schema:validate --env=test
```

Expected: `[OK] The mapping files are correct.` and `[OK] The database schema is in sync with the mapping files.`

- [ ] **Step 5: Commit**

```bash
git add src/Entity/Client.php migrations/mysql/Version20260623010000.php migrations/sqlite/Version20260623010000.php
git commit -m "feat(ab-01): add email, tradingName, taxNumber, registrationNo, website, isActive to Client entity"
```

---

### Task 2: Update serializer group

**Files:**
- Modify: `config/serializer_groups/Client.yaml`

- [ ] **Step 1: Add new fields to the list serializer group**

The full `config/serializer_groups/Client.yaml` should be:

```yaml
App\Entity\Client:

    list:
        - id
        - name
        - tradingName
        - code
        - country
        - email
        - taxNumber
        - registrationNo
        - website
        - isActive
        - defaultContact
        - defaultInvoiceInfo
        - phone
        - creditPeriod
        - creditLimit
        - unpaid
        - createdDate
        - createdBy
        - accountManager
        - establishmentDate
```

- [ ] **Step 2: Commit**

```bash
git add config/serializer_groups/Client.yaml
git commit -m "feat(ab-01): expose new Client fields in list serializer group"
```

---

### Task 3: BO — update form and list config

**Files:**
- Modify: `src/config/forms/ClientGeneral.js` (BO repo)
- Modify: `src/config/tables/Client.js` (BO repo)

- [ ] **Step 1: Add new fields to `src/config/forms/ClientGeneral.js`**

Insert a new row after the `name`/`code` row (after line 41) and add `isActive` to the note row. The updated `layout` function:

```js
export const layout = (entity) => {
  const { required } = CommonService.rules()
  const { $gettext } = gettext
  const appStore = useAppStore()
  return [
    [
      [{ columnName: $gettext('General'), isTopLegend: true }]
    ],
    [
      [{ name: 'name', text: $gettext('Company Name'), rules: [required], columnSpan: 7 }],
      [{ name: 'tradingName', text: $gettext('Trading Name'), columnSpan: 5 }],
    ],
    [
      [{ name: 'code', text: $gettext('Client Id'), rules: [required], columnSpan: 3 }],
      [{ name: 'taxNumber', text: $gettext('Tax Number'), columnSpan: 3 }],
      [{ name: 'registrationNo', text: $gettext('Registration No.'), columnSpan: 3 }],
      [{ name: 'website', text: $gettext('Website'), columnSpan: 3 }],
    ],
    [
      [{ name: 'address', text: $gettext('Address'), columnSpan: 9 }],
      [{ name: 'phone', text: $gettext('Phone'), columnSpan: 3 }],
    ],
    [
      [{ name: 'email', text: $gettext('Email'), columnSpan: 3 }],
      [{ name: 'province', text: $gettext('State/Province'), columnSpan: 3 }],
      [{ name: 'city', text: $gettext('City'), columnSpan: 3 }],
      [{ name: 'zipCode', text: $gettext('Zip Code'), columnSpan: 1 }],
      [{ name: 'country', text: $gettext('Country'), columnSpan: 2 }],
    ],
    [
      [
        { name: 'note', text: $gettext('Note'), type: 'textarea', rows: 5, columnSpan: 6 }
      ],
      [
        { name: 'type', text: $gettext('Client Type'), type: 'select', rules: [required], items: clientTypeList, columnSpan: 3, returnObject: false },
        { name: 'priceMarkup', text: $gettext('Pricing Level'), type: 'select', rules: [required], items: appStore.getList('priceMarkups'), itemValue: 'id', itemTitle: 'name' },
      ],
      [
        { name: 'residenceType', text: $gettext('Residence Type'), type: 'select', items: residenceTypeList, columnSpan: 3, returnObject: false },
        { name: 'establishmentDate', text: $gettext('Establishment Date'), type: 'datePicker' },
        { name: 'isActive', text: $gettext('Active'), type: 'checkbox' },
      ]
    ],
    [[{ columnName: $gettext('Credit Terms') }]],
    [
      [{ name: 'creditLimit', text: $gettext('Credit Limit'), type: 'number', numberMode: 'money' }],
      [{ name: 'creditPeriod', text: $gettext('Credit Period'), suffix: $gettext('days') }],
    ],
  ]
}
```

- [ ] **Step 2: Check `src/config/tables/Client.js` header list and add `email` and `isActive` columns**

Open `src/config/tables/Client.js` and add two header entries after the existing `name` entry:

```js
{ key: 'email', sortable: false, text: $gettext('Email') },
{ key: 'isActive', sortable: false, text: $gettext('Active'), renderSlot: 'isActive' },
```

Also add an `isActive` filter to `filterConfigs`:

```js
{ name: 'isActive', text: $gettext('Active'), type: 'select', items: [{ value: '1', title: $gettext('Active') }, { value: '0', title: $gettext('Inactive') }] },
```

- [ ] **Step 3: Commit**

```bash
git add src/config/forms/ClientGeneral.js src/config/tables/Client.js
git commit -m "feat(ab-01): add new Client fields to BO form and list table"
```
