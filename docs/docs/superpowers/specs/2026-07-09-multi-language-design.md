# Multi-Language Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add full multi-language support across `make-cargo-client` (Symfony API) and `make-cargo-client-bo` (Vue 3 BO), with the active language stored on the user profile and applied consistently on both sides.

**Architecture:** Two independent translation pipelines (Symfony `.po` for API, vue3-gettext `.po` → lazy-loaded `.json` for BO) sharing the same language set and the same source of truth (the `User.language` DB column). Raw English text is used as the `msgid` in all `.po` files — no dot-notation keys.

**Tech Stack:** Symfony Translation (API), vue3-gettext (BO), GNU gettext `.po` format, easygettext for BO string extraction, Vite dynamic import for lazy loading.

---

## Supported Languages

| Language           | Code    | RTL |
|--------------------|---------|-----|
| English            | `en`    | No  |
| Chinese Simplified | `zh_CN` | No  |
| Vietnamese         | `vi`    | No  |
| Japanese           | `ja`    | No  |
| Korean             | `ko`    | No  |
| German             | `de`    | No  |
| Spanish            | `es`    | No  |
| Arabic             | `ar`    | Yes |

English is the source language. No translation file is required for it — both Symfony and vue3-gettext fall back to the raw `msgid` string when no translation file exists for the active locale.

---

## Data Flow

```
User selects language in BO
  → loadLocale(lang) lazy-loads /locales/{lang}.json
  → PATCH /my-profile { language: lang }
  → User.language saved in DB

Every subsequent API request
  → LocaleSubscriber reads User.language from auth token
  → $request->setLocale(lang)
  → Symfony translator uses the correct .po file
  → Validation errors + custom messages returned in user's language

On next login / page refresh
  → GET /my-profile returns { language: 'zh_CN' }
  → BO calls loadLocale('zh_CN') on startup
  → UI renders in the user's saved language
```

---

## Section 1: API — User Entity & Migration

**User entity** (`src/Module/Core/Entity/User.php`):
- Add `language` property: `string`, max length 10, default `'en'`
- Exposed in the user profile serialization group

**Migration** (new SQLite + MySQL migrations):
```sql
ALTER TABLE user ADD COLUMN language VARCHAR(10) NOT NULL DEFAULT 'en';
```

**Profile endpoint** — the existing `PATCH /my-profile` endpoint already accepts user fields; `language` is added to the allowed fields list.

---

## Section 2: API — Locale Subscriber

New `src/EventListener/LocaleSubscriber.php`:

```php
#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleSubscriber
{
    public function __invoke(RequestEvent $event): void
    {
        $token = $this->security->getToken();
        $user  = $token?->getUser();
        if ($user instanceof User && $user->getLanguage()) {
            $event->getRequest()->setLocale($user->getLanguage());
        }
    }
}
```

Priority 20 ensures this fires after the firewall (priority 8) so the user is already authenticated.

---

## Section 3: API — Translation Files

**Directory:** `translations/`

**Structure:**
```
translations/
  messages.zh_CN.po
  messages.vi.po
  messages.ja.po
  messages.ko.po
  messages.de.po
  messages.es.po
  messages.ar.po
  countries+intl-icu.zh_CN.po
  countries+intl-icu.vi.po
  countries+intl-icu.ja.po
  countries+intl-icu.ko.po
  countries+intl-icu.de.po
  countries+intl-icu.es.po
  countries+intl-icu.ar.po
```

**`.po` file format** (raw English msgid):
```po
msgid "This field is required."
msgstr "此字段为必填项。"
```

**What gets translated via these files:**
- Custom validation messages from custom Symfony validators
- Custom error messages in controllers/services via `$this->trans('Error message text')`

**What is automatically translated for free:**
- All Symfony built-in validator messages — `symfony/validator` already ships `.po` files for all 8 languages. No work needed.

**What is NOT translated (intentionally):**
- DB-stored data (status codes, type labels) — these are display-name concerns handled in the BO

**Usage in controllers/services:**
```php
throw new \RuntimeException($this->trans('Failed to process the request.'));
```

---

## Section 4: BO — vue3-gettext Configuration

**File:** `src/plugins/gettext.js`

Updated configuration:
```javascript
import { createGettext } from 'vue3-gettext'

export const gettext = createGettext({
  availableLanguages: {
    en:    'English',
    zh_CN: '中文（简体）',
    vi:    'Tiếng Việt',
    ja:    '日本語',
    ko:    '한국어',
    de:    'Deutsch',
    es:    'Español',
    ar:    'العربية',
  },
  defaultLanguage: 'en',
  translations: {},   // empty — all languages lazy-loaded
  silent: true,
})

export async function loadLocale(lang) {
  if (lang !== 'en') {
    const data = await import(`../locales/${lang}.json`)
    gettext.loadTranslations(lang, data.default ?? data)
  }
  gettext.current = lang

  // RTL toggle
  const isRtl = lang === 'ar'
  document.documentElement.setAttribute('dir', isRtl ? 'rtl' : 'ltr')
  document.documentElement.setAttribute('lang', lang)
  // Vuetify RTL — import the vuetify instance and update its locale
  const { vuetify } = await import('@/plugins/vuetify')
  vuetify.locale.current.value = lang
}
```

---

## Section 5: BO — Translation Files & Pipeline

**Translation source files:**
```
src/locales/
  messages.pot      ← extracted template (committed, regenerated on extract)
  zh_CN.po
  vi.po
  ja.po
  ko.po
  de.po
  es.po
  ar.po
```

**Compiled output** (generated at build time, not committed):
```
public/locales/
  zh_CN.json
  vi.json
  ja.json
  ko.json
  de.json
  es.json
  ar.json
```

**npm scripts in `package.json`:**
```json
{
  "i18n:extract": "vue-gettext-extract --config gettext.config.js",
  "i18n:compile": "vue-gettext-compile --config gettext.config.js",
  "i18n:merge":   "node scripts/i18n-merge.js"
}
```

**`scripts/i18n-merge.js`** (cross-platform, replaces the bash loop):
```javascript
const { execSync } = require('child_process')
const langs = ['zh_CN', 'vi', 'ja', 'ko', 'de', 'es', 'ar']
for (const lang of langs) {
  execSync(`msgmerge --update src/locales/${lang}.po src/locales/messages.pot`, { stdio: 'inherit' })
}
```

**`gettext.config.js`** (project root):
```javascript
module.exports = {
  input: {
    path: './src',
    include: ['**/*.vue', '**/*.js', '**/*.ts'],
  },
  output: {
    path: './src/locales',
    potPath: './src/locales/messages.pot',
    locales: ['zh_CN', 'vi', 'ja', 'ko', 'de', 'es', 'ar'],
    flat: false,
    splitLocaleFiles: true,
    compiledLocalesPath: './public/locales',
  },
}
```

**Developer workflow:**
1. Add new UI string using `$gettext('My new string')` as usual
2. Run `npm run i18n:extract` → updates `messages.pot`
3. Run `npm run i18n:merge` → adds new `msgid` entries to each `.po` file without losing existing translations
4. Translators fill in `msgstr` for new entries in each `.po` file
5. Run `npm run i18n:compile` → regenerates `public/locales/*.json`
6. Vite build picks up the JSON files as static assets

---

## Section 6: BO — Startup & Language Switcher

**App startup** (`src/plugins/gettext.js` or `src/App.vue`):
```javascript
// After login / on app mount, read from user profile
const lang = authStore.user?.language ?? 'en'
await loadLocale(lang)
```

**Language switcher component** (`src/components/common/LanguageSwitcher.vue`):
- Uses `useGettext()` from `vue3-gettext` — no `vue-i18n`
- Displays current language flag/label
- On selection: calls `loadLocale(lang)` then PATCHes `/my-profile` with `{ language: lang }`
- The existing `I18n.vue` (which uses `useI18n`) is replaced by this component

```javascript
import { useGettext } from 'vue3-gettext'
import { loadLocale } from '@/plugins/gettext'
import MyProfileService from '@/services/MyProfileService'

const { current, available } = useGettext()

async function switchLanguage(lang) {
  await loadLocale(lang)
  await MyProfileService.profile({ language: lang })
}
```

---

## Section 7: Vuetify RTL Integration

Vuetify 3 RTL is configured at the plugin level. In `src/plugins/vuetify/index.js`, add RTL mapping:

```javascript
const vuetify = createVuetify({
  locale: {
    locale: 'en',
    rtl: { ar: true },   // only Arabic is RTL
  },
  // ...
})
```

The `loadLocale()` function (Section 4) also calls:
```javascript
vuetify.locale.current.value = lang   // updates Vuetify's active locale
```

This ensures Vuetify components (dialogs, data tables, date pickers) also mirror correctly for Arabic.

---

## Out of Scope

- Machine translation or external translation platforms
- Per-route language prefixes in URLs (`/ar/setting/...`)
- Translation of DB-stored content (status labels, type names displayed in BO are handled with hardcoded label maps in the BO config)
- SMS / email template translation (separate concern)
