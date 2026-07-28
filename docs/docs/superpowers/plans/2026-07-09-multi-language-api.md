# Multi-Language Support — API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add `language` field to the `User` entity, set the Symfony request locale from the authenticated user on every request, and create empty `.po` translation files for all 7 non-English languages.

**Architecture:** A `LocaleSubscriber` event listener fires on `kernel.request` (priority 7, after the firewall authenticates at priority 8), reads `$user->getLanguage()`, and calls `$request->setLocale()`. Symfony's translator then automatically uses the correct `.po` file for all built-in validator messages and any custom `$this->trans()` calls.

**Tech Stack:** Symfony 7, symfony/translation, symfony/validator, Doctrine ORM, GNU gettext `.po` format.

---

## File Map

| Action | Path |
|--------|------|
| Modify | `src/Module/Core/Entity/User.php` |
| Modify | `config/packages/translation.yaml` |
| Create | `migrations/sqlite/Version20260709000000.php` |
| Create | `migrations/mysql/Version20260709000000.php` |
| Create | `src/EventListener/LocaleSubscriber.php` |
| Create | `translations/messages.zh_CN.po` |
| Create | `translations/messages.vi.po` |
| Create | `translations/messages.ja.po` |
| Create | `translations/messages.ko.po` |
| Create | `translations/messages.de.po` |
| Create | `translations/messages.es.po` |
| Create | `translations/messages.ar.po` |

---

## Task 1: Add `language` field to User entity

**Files:**
- Modify: `src/Module/Core/Entity/User.php`

- [ ] **Step 1: Add the property**

In `src/Module/Core/Entity/User.php`, add the `language` property after the `$tableConfig` property (line 67):

```php
    #[ORM\Column(length: 10, options: ['default' => 'en'])]
    private string $language = 'en';
```

- [ ] **Step 2: Add getter and setter**

Add these two methods after `setTableConfig()` (before the `$branches` property / `getBranches()` method):

```php
    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): static
    {
        $this->language = $language;
        return $this;
    }
```

- [ ] **Step 3: Verify the entity parses**

Run:
```bash
php bin/console doctrine:schema:validate --skip-sync
```
Expected: "The mapping files are correct." (skip-sync because DB hasn't been migrated yet)

- [ ] **Step 4: Commit**

```bash
git add src/Module/Core/Entity/User.php
git commit -m "feat(i18n): add language field to User entity"
```

---

## Task 2: Create SQLite migration

**Files:**
- Create: `migrations/sqlite/Version20260709000000.php`

- [ ] **Step 1: Create the file**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add language column to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'en'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
```

- [ ] **Step 2: Run the migration**

```bash
php bin/console doctrine:migrations:migrate --db=sqlite --no-interaction
```
Expected: `[notice] Migrating up to Version20260709000000`

- [ ] **Step 3: Commit**

```bash
git add migrations/sqlite/Version20260709000000.php
git commit -m "feat(i18n): SQLite migration — add user.language"
```

---

## Task 3: Create MySQL migration

**Files:**
- Create: `migrations/mysql/Version20260709000000.php`

- [ ] **Step 1: Create the file**

```php
<?php
declare(strict_types=1);
namespace SqlEngineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add language column to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'en'");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add migrations/mysql/Version20260709000000.php
git commit -m "feat(i18n): MySQL migration — add user.language"
```

---

## Task 4: Create LocaleSubscriber

**Files:**
- Create: `src/EventListener/LocaleSubscriber.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\EventListener;

use App\Module\Core\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class LocaleSubscriber
{
    public function __construct(private readonly Security $security) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $user = $this->security->getUser();
        if ($user instanceof User && $user->getLanguage() !== 'en') {
            $event->getRequest()->setLocale($user->getLanguage());
        }
    }
}
```

Priority 7 is intentionally below the firewall's priority 8, ensuring the user is already authenticated when this listener runs.

- [ ] **Step 2: Verify the service is auto-wired**

```bash
php bin/console debug:event-dispatcher kernel.request
```
Expected: `App\EventListener\LocaleSubscriber` appears in the list at priority 7.

- [ ] **Step 3: Verify locale is applied manually**

Start the dev server, log in as a user, temporarily hardcode `$user->getLanguage()` return `'fr'` in the entity, make any API call, and check Symfony profiler → Request/Response tab → Locale shows `fr`. Revert the hardcode.

- [ ] **Step 4: Commit**

```bash
git add src/EventListener/LocaleSubscriber.php
git commit -m "feat(i18n): add LocaleSubscriber — sets request locale from user.language"
```

---

## Task 5: Update translation.yaml with enabled locales

**Files:**
- Modify: `config/packages/translation.yaml`

- [ ] **Step 1: Add enabled_locales**

Replace the full content of `config/packages/translation.yaml` with:

```yaml
framework:
    default_locale: en
    enabled_locales: [en, zh_CN, vi, ja, ko, de, es, ar]
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks:
            - en
        providers:
```

- [ ] **Step 2: Verify**

```bash
php bin/console debug:config framework translator
```
Expected: shows `fallbacks: [en]` and the `default_path`.

- [ ] **Step 3: Commit**

```bash
git add config/packages/translation.yaml
git commit -m "feat(i18n): register all 8 supported locales in framework config"
```

---

## Task 6: Create empty `.po` files for all 7 non-English languages

**Files:**
- Create: `translations/messages.zh_CN.po`, `messages.vi.po`, `messages.ja.po`, `messages.ko.po`, `messages.de.po`, `messages.es.po`, `messages.ar.po`

- [ ] **Step 1: Create `translations/messages.zh_CN.po`**

```po
# Chinese Simplified translations for make-cargo-client.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: zh_CN\n"
```

- [ ] **Step 2: Create `translations/messages.vi.po`**

```po
# Vietnamese translations for make-cargo-client.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: vi\n"
```

- [ ] **Step 3: Create `translations/messages.ja.po`**

```po
# Japanese translations for make-cargo-client.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: ja\n"
```

- [ ] **Step 4: Create `translations/messages.ko.po`**

```po
# Korean translations for make-cargo-client.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: ko\n"
```

- [ ] **Step 5: Create `translations/messages.de.po`**

```po
# German translations for make-cargo-client.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: de\n"
```

- [ ] **Step 6: Create `translations/messages.es.po`**

```po
# Spanish translations for make-cargo-client.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: es\n"
```

- [ ] **Step 7: Create `translations/messages.ar.po`**

```po
# Arabic translations for make-cargo-client.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: ar\n"
```

- [ ] **Step 8: Verify Symfony sees the files**

```bash
php bin/console debug:translation zh_CN
```
Expected: command runs without error (may show 0 messages — that is correct for empty files).

- [ ] **Step 9: Commit**

```bash
git add translations/messages.*.po
git commit -m "feat(i18n): add empty .po translation files for all 7 non-English languages"
```
