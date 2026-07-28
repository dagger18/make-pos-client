# Multi-Language Support — BO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire up full 8-language support in `make-cargo-client-bo` using `vue3-gettext`: lazy-load per-language `.po`-compiled JSON files, add a `LanguageSwitcher` component, save the preference to the user profile via the API, and enable RTL for Arabic.

**Architecture:** `vue3-gettext` is initialised with an empty `translations: {}` and a `loadLocale(lang)` helper that dynamically imports `/locales/{lang}.json` at runtime. `authStore.login()` and `authStore.setTableConfig()` both call `loadLocale()` so the correct language is active on both fresh login and page refresh. The Vuetify instance is exported from its plugin so `loadLocale` can update RTL state without a circular import.

**Tech Stack:** vue3-gettext 3.0.0-beta.4, Vuetify 3, Pinia, Vite dynamic import.

**Prerequisite:** API plan (`2026-07-09-multi-language-api.md`) must be deployed first so `GET /my-profile/get` returns `user.language` and `POST /my-profile/profile/{id}` accepts it.

---

## File Map

| Action | Path (relative to `make-cargo-client-bo/`) |
|--------|------|
| Rewrite | `src/plugins/gettext.js` |
| Modify | `src/plugins/vuetify/index.js` |
| Modify | `src/stores/authStore.js` |
| Modify | `package.json` |
| Create | `gettext.config.js` |
| Create | `scripts/i18n-merge.js` |
| Create | `src/locales/zh_CN.po` |
| Create | `src/locales/vi.po` |
| Create | `src/locales/ja.po` |
| Create | `src/locales/ko.po` |
| Create | `src/locales/de.po` |
| Create | `src/locales/es.po` |
| Create | `src/locales/ar.po` |
| Create | `src/components/common/LanguageSwitcher.vue` |
| Replace usage | wherever `<I18n` appears in layout files |

---

## Task 1: Rewrite `gettext.js` with lazy loading

**Files:**
- Rewrite: `src/plugins/gettext.js`

- [ ] **Step 1: Replace the full file content**

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
  translations: {},
  silent: true,
})

const loaded = new Set(['en'])

export async function loadLocale(lang) {
  if (!loaded.has(lang) && lang !== 'en') {
    const data = await import(`../locales/${lang}.json`)
    gettext.loadTranslations(lang, data.default ?? data)
    loaded.add(lang)
  }
  gettext.current = lang

  const isRtl = lang === 'ar'
  document.documentElement.setAttribute('dir', isRtl ? 'rtl' : 'ltr')
  document.documentElement.setAttribute('lang', lang)

  // Update Vuetify locale and RTL — imported lazily to avoid circular dep
  const { vuetify } = await import('@/plugins/vuetify')
  vuetify.locale.current.value = lang
}

export default function (app) {
  app.use(gettext)
}
```

The `loaded` Set prevents re-fetching and re-registering already-loaded translations. English is pre-added because it needs no file.

- [ ] **Step 2: Verify dev server starts without errors**

```bash
npm run dev
```
Expected: No errors in terminal. Browser console shows no vue3-gettext errors.

- [ ] **Step 3: Commit**

```bash
git add src/plugins/gettext.js
git commit -m "feat(i18n): rewrite gettext plugin with lazy locale loading"
```

---

## Task 2: Export Vuetify instance and add RTL locale config

**Files:**
- Modify: `src/plugins/vuetify/index.js`

- [ ] **Step 1: Export `vuetify` instance and add RTL**

Replace `src/plugins/vuetify/index.js` with:

```javascript
import { deepMerge } from '@antfu/utils'
import relativeTime from 'dayjs/plugin/relativeTime'
import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import defaults from './defaults'
import { icons } from './icons'
import { staticPrimaryColor, themes } from './theme'
import { cookieRef } from '@/@layouts/stores/config'
import '@core/scss/template/libs/vuetify/index.scss'
import DayJsAdapter from "@date-io/dayjs"
import 'vuetify/styles'

const dayJsAdapter = new DayJsAdapter({ locale: 'en' })
dayJsAdapter.rawDayJsInstance.extend(relativeTime)
export const dateAdapter = dayJsAdapter

export let vuetify = null

export default function (app) {
  const cookieThemeValues = {
    defaultTheme: resolveVuetifyTheme(),
    themes: {
      light: {
        colors: {
          primary: cookieRef('lightThemePrimaryColor', staticPrimaryColor).value,
        },
      },
      dark: {
        colors: {
          primary: cookieRef('darkThemePrimaryColor', staticPrimaryColor).value,
        },
      },
    },
  }

  const optionTheme = deepMerge({ themes }, cookieThemeValues)

  vuetify = createVuetify({
    aliases: {
      IconBtn: VBtn,
    },
    defaults,
    icons,
    theme: optionTheme,
    date: {
      adapter: dayJsAdapter,
    },
    locale: {
      locale: 'en',
      rtl: { ar: true },
    },
  })

  app.use(vuetify)
}
```

Key changes from original:
1. `export let vuetify = null` — so `loadLocale` can import it
2. `vuetify = createVuetify(...)` — assigns to the exported variable
3. `locale: { locale: 'en', rtl: { ar: true } }` — declares Arabic as RTL
4. DayJS adapter locale changed from hardcoded `'vi'` to `'en'` (default; `loadLocale` updates it dynamically)

- [ ] **Step 2: Verify dev server still starts**

```bash
npm run dev
```
Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add src/plugins/vuetify/index.js
git commit -m "feat(i18n): export vuetify instance and add RTL locale config for Arabic"
```

---

## Task 3: Add npm scripts and config files

**Files:**
- Modify: `package.json`
- Create: `gettext.config.js`
- Create: `scripts/i18n-merge.js`

- [ ] **Step 1: Add scripts to `package.json`**

In the `"scripts"` section of `package.json`, add these three entries (keep all existing entries):

```json
"i18n:extract": "vue-gettext-extract",
"i18n:compile": "vue-gettext-compile",
"i18n:merge":   "node scripts/i18n-merge.js"
```

- [ ] **Step 2: Create `gettext.config.js`** (project root)

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

- [ ] **Step 3: Create `scripts/i18n-merge.js`**

```javascript
const { execSync } = require('child_process')
const langs = ['zh_CN', 'vi', 'ja', 'ko', 'de', 'es', 'ar']
for (const lang of langs) {
  execSync(
    `msgmerge --update src/locales/${lang}.po src/locales/messages.pot`,
    { stdio: 'inherit' }
  )
}
```

- [ ] **Step 4: Run extract to generate the `.pot` template**

```bash
npm run i18n:extract
```
Expected: `src/locales/messages.pot` is created, containing all `$gettext()` strings found in `src/`.

- [ ] **Step 5: Commit**

```bash
git add package.json gettext.config.js scripts/i18n-merge.js src/locales/messages.pot
git commit -m "feat(i18n): add gettext extract/compile/merge pipeline"
```

---

## Task 4: Create empty `.po` files for all 7 non-English languages

**Files:**
- Create: `src/locales/zh_CN.po`, `vi.po`, `ja.po`, `ko.po`, `de.po`, `es.po`, `ar.po`

- [ ] **Step 1: Create `src/locales/zh_CN.po`**

```po
# Chinese Simplified translations for make-cargo-client-bo.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: zh_CN\n"
```

- [ ] **Step 2: Create `src/locales/vi.po`**

```po
# Vietnamese translations for make-cargo-client-bo.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: vi\n"
```

- [ ] **Step 3: Create `src/locales/ja.po`**

```po
# Japanese translations for make-cargo-client-bo.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: ja\n"
```

- [ ] **Step 4: Create `src/locales/ko.po`**

```po
# Korean translations for make-cargo-client-bo.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: ko\n"
```

- [ ] **Step 5: Create `src/locales/de.po`**

```po
# German translations for make-cargo-client-bo.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: de\n"
```

- [ ] **Step 6: Create `src/locales/es.po`**

```po
# Spanish translations for make-cargo-client-bo.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: es\n"
```

- [ ] **Step 7: Create `src/locales/ar.po`**

```po
# Arabic translations for make-cargo-client-bo.
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: ar\n"
```

- [ ] **Step 8: Run compile to generate `public/locales/*.json`**

```bash
npm run i18n:compile
```
Expected: `public/locales/zh_CN.json`, `vi.json`, `ja.json`, `ko.json`, `de.json`, `es.json`, `ar.json` are created (all with empty translation objects `{}`).

- [ ] **Step 9: Add `public/locales/*.json` to `.gitignore`**

Add this line to `.gitignore`:
```
public/locales/
```

These files are build artifacts — they are regenerated by `npm run i18n:compile` during CI/CD.

- [ ] **Step 10: Commit**

```bash
git add src/locales/*.po .gitignore
git commit -m "feat(i18n): add empty .po source files for 7 non-English languages"
```

---

## Task 5: Create `LanguageSwitcher.vue` component

**Files:**
- Create: `src/components/common/LanguageSwitcher.vue`

This component replaces `src/@core/components/I18n.vue`. It uses `useGettext()` from `vue3-gettext` (not `useI18n`).

- [ ] **Step 1: Create the file**

```vue
<script setup>
import { useGettext } from 'vue3-gettext'
import { loadLocale } from '@/plugins/gettext'
import MyProfileService from '@/services/MyProfileService'
import { useAuthStore } from '@/stores/authStore'

const { current, available } = useGettext()
const authStore = useAuthStore()

const languages = [
  { code: 'en',    label: 'English' },
  { code: 'zh_CN', label: '中文（简体）' },
  { code: 'vi',    label: 'Tiếng Việt' },
  { code: 'ja',    label: '日本語' },
  { code: 'ko',    label: '한국어' },
  { code: 'de',    label: 'Deutsch' },
  { code: 'es',    label: 'Español' },
  { code: 'ar',    label: 'العربية' },
]

async function switchLanguage(lang) {
  await loadLocale(lang)
  const updatedUser = { ...authStore.user, language: lang }
  await MyProfileService.profile(updatedUser)
  authStore.setLanguage(lang)   // updates Pinia state + cookie so refresh keeps the language
}
</script>

<template>
  <IconBtn>
    <VIcon size="26" icon="tabler-language" />
    <VMenu activator="parent" location="bottom end" offset="14px">
      <VList :selected="[current]" color="primary" min-width="175px">
        <VListItem
          v-for="lang in languages"
          :key="lang.code"
          :value="lang.code"
          @click="switchLanguage(lang.code)"
        >
          <VListItemTitle>{{ lang.label }}</VListItemTitle>
        </VListItem>
      </VList>
    </VMenu>
  </IconBtn>
</template>
```

- [ ] **Step 2: Verify it renders without errors**

Temporarily add `<LanguageSwitcher />` to any page, open the browser, confirm the dropdown shows all 8 languages without console errors. Remove the temporary usage.

- [ ] **Step 3: Commit**

```bash
git add src/components/common/LanguageSwitcher.vue
git commit -m "feat(i18n): add LanguageSwitcher component using vue3-gettext"
```

---

## Task 6: Wire locale loading into `authStore`

**Files:**
- Modify: `src/stores/authStore.js`

- [ ] **Step 1: Add the import at the top of `authStore.js`**

After the existing imports at the top of `src/stores/authStore.js`, add:

```javascript
import { loadLocale } from '@/plugins/gettext'
```

- [ ] **Step 2: Add a `setLanguage` action**

Add this action to the `actions` object in `authStore.js` (alongside `login`, `logout`, etc.):

```javascript
setLanguage (lang) {
  if (!this.user) return
  this.user.language = lang
  // Write back to the cookie so the preference survives a page refresh
  const cookie = useCookie('user')
  if (cookie.value) {
    cookie.value = { ...cookie.value, language: lang }
  }
},
```

- [ ] **Step 3: Call `loadLocale` in the `login` action**

In the `login` action, after `this.user = userWithoutTableConfig` (around line 24), add:

```javascript
loadLocale(userWithoutTableConfig.language ?? 'en')
```

The `login` action after the change:
```javascript
async login (response, route) {
  const { accessToken, user, userAbilityRules } = response
  const userWithoutTableConfig = JSON.parse(JSON.stringify(user))
  userWithoutTableConfig.tableConfig = null
  console.log('loging in userAbilityRules', userAbilityRules)
  useCookie('userAbilityRules').value = userAbilityRules
  useCookie('user').value = userWithoutTableConfig
  useCookie('accessToken').value = accessToken
  ability.update(useCookie('userAbilityRules').value)
  this.user = userWithoutTableConfig
  this.accessToken = accessToken
  this.userAbilityRules = useCookie('userAbilityRules').value
  loadLocale(userWithoutTableConfig.language ?? 'en')
  router.push(route.query.to ? String(route.query.to) : {name: 'first-route'})
},
```

- [ ] **Step 4: Call `loadLocale` in `setTableConfig` — BEFORE the early return**

The `setTableConfig` action currently has `if(!!this.user.tableConfig) return` as its first line. The `loadLocale` call must be placed BEFORE this guard, otherwise page refreshes where tableConfig is cached in the cookie skip locale loading entirely.

Replace the start of `setTableConfig`:
```javascript
async setTableConfig (to = null) {
  if(!!this.user.tableConfig) return
```
With:
```javascript
async setTableConfig (to = null) {
  loadLocale(this.user?.language ?? 'en')
  if(!!this.user.tableConfig) return
```

- [ ] **Step 5: Verify locale loads on login and page refresh**

1. Open browser DevTools Network tab
2. Log in as a user whose `language` DB column is `'de'`
3. Confirm: `GET /locales/de.json` appears in the network tab immediately after login
4. Confirm: `document.documentElement.lang === 'de'`
5. Refresh the page — language is still German (loaded from cookie, before early return)

- [ ] **Step 6: Commit**

```bash
git add src/stores/authStore.js
git commit -m "feat(i18n): load user locale on login and page refresh via authStore"
```

---

## Task 7: Replace `I18n.vue` usage with `LanguageSwitcher`

**Files:**
- Find and update: the layout file(s) that import `I18n.vue`

- [ ] **Step 1: Find usages of `I18n.vue`**

```bash
grep -r "I18n" src/ --include="*.vue" --include="*.js" -l
```
Expected: one or more layout files (likely in `src/@layouts/` or `src/@core/`).

- [ ] **Step 2: In each file found, replace the import**

Replace:
```javascript
import I18n from '@/@core/components/I18n.vue'
```
With:
```javascript
import LanguageSwitcher from '@/components/common/LanguageSwitcher.vue'
```

- [ ] **Step 3: Replace the template usage**

Replace every occurrence of `<I18n` with `<LanguageSwitcher` and `</I18n>` with `</LanguageSwitcher>`. Remove any props that were being passed (`:languages`, `:location`) since `LanguageSwitcher` manages its own language list.

- [ ] **Step 4: Verify the switcher appears and works**

1. `npm run dev`
2. Log in, locate the language icon (tabler-language) in the layout
3. Click it — all 8 languages appear in the dropdown
4. Select `de` (German) — the UI language switches (strings wrapped in `$gettext()` now show German once translations are filled in), the network tab shows `GET /locales/de.json`
5. Refresh the page — same language is still active
6. Select `ar` (Arabic) — `document.dir` becomes `rtl`, layout mirrors

- [ ] **Step 5: Commit**

```bash
git add src/
git commit -m "feat(i18n): replace I18n.vue with LanguageSwitcher in layout"
```

---

## Developer workflow for adding new translations

After this plan is complete, the workflow for translators/developers is:

```bash
# 1. After adding new $gettext('Some string') calls, update the .pot template:
npm run i18n:extract

# 2. Merge new strings into all existing .po files (preserves existing translations):
npm run i18n:merge

# 3. Open each src/locales/{lang}.po and fill in msgstr for new msgid entries

# 4. Compile .po files to .json for the browser:
npm run i18n:compile

# 5. Test in the browser by switching to each language
```
