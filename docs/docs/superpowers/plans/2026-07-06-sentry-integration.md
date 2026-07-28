# Sentry Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Sentry error tracking to production so unhandled exceptions are captured with user context and release info.

**Architecture:** Install `sentry/sentry-symfony` bundle via Composer (Symfony Flex auto-registers it). Wrap all config in `when@prod` so Sentry is completely inactive in dev/test. Filter out expected 4xx exceptions. Enable user context via `send_default_pii`. Tag errors with git SHA via `SENTRY_RELEASE` env var.

**Tech Stack:** `sentry/sentry-symfony` ~4.0, Symfony 7.1, PHP 8.2

---

### Task 1: Install the bundle

**Files:**
- Modify: `composer.json` / `composer.lock` (via composer)
- Auto-created by Flex: `config/packages/sentry.yaml`, entry in `config/bundles.php`
- Auto-modified by Flex: `.env`

- [ ] **Step 1: Run composer require**

```bash
composer require sentry/sentry-symfony
```

Expected output includes lines like:
```
- Configuring sentry/sentry-symfony
  - Added config/packages/sentry.yaml
```

- [ ] **Step 2: Verify bundle is registered**

Open `config/bundles.php` and confirm this line exists:

```php
Sentry\SentryBundle\SentryBundle::class => ['all' => true],
```

- [ ] **Step 3: Inspect what Flex generated**

Run:
```bash
cat config/packages/sentry.yaml
cat .env | grep SENTRY
```

Flex typically creates a minimal `sentry.yaml` with just a DSN placeholder and adds `SENTRY_DSN=` to `.env`. Note the exact structure — you'll replace the file contents entirely in Task 2.

- [ ] **Step 4: Verify container still compiles**

```bash
php bin/console debug:container --env=prod 2>&1 | head -5
```

Expected: no errors (the bundle initialises cleanly even with an empty DSN in prod when the env var is present).

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/bundles.php
git commit -m "feat: install sentry/sentry-symfony bundle"
```

---

### Task 2: Configure the bundle

**Files:**
- Modify: `config/packages/sentry.yaml` (replace Flex-generated content entirely)

The bundle must only activate in `prod`. Dev and test get no Sentry config at all — if `SENTRY_DSN` is empty in dev, the bundle would still try to initialise and log warnings. Wrapping everything in `when@prod` is the cleanest solution.

- [ ] **Step 1: Check the exact config schema**

```bash
php bin/console config:dump-reference sentry --env=prod
```

Confirm that `ignored_exceptions` is a valid top-level key under `sentry:`. The output will show all available keys and their types. If `ignored_exceptions` does not appear, check the bundle version with `composer show sentry/sentry-symfony` and consult the bundle's README for the correct key name.

- [ ] **Step 2: Replace config/packages/sentry.yaml with production-only config**

Replace the entire file with:

```yaml
when@prod:
    sentry:
        dsn: '%env(SENTRY_DSN)%'
        release: '%env(default::SENTRY_RELEASE)%'
        options:
            environment: '%kernel.environment%'
            send_default_pii: true
        ignored_exceptions:
            - App\Misc\Exception\BadRequestException
            - App\Misc\Exception\AccessDeniedException
            - App\Misc\Exception\CapacityExceededException
            - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
            - Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
            - Symfony\Component\Security\Core\Exception\AuthenticationException
```

**What each key does:**
- `dsn` — Sentry project DSN, read from env (empty in dev = Sentry disabled)
- `release` — git SHA passed from deployer; `default::SENTRY_RELEASE` means empty string if env var not set (Sentry silently ignores empty release)
- `environment` — tags every error with `prod`
- `send_default_pii: true` — enables the bundle's `LoginListener` which reads the authenticated user from Symfony's security token and attaches `id`/`email` to each error report automatically
- `ignored_exceptions` — these classes are never sent to Sentry; they are expected business/HTTP errors, not bugs

- [ ] **Step 3: Verify container compiles with the new config**

```bash
php bin/console cache:clear --env=prod --no-debug
php bin/console debug:container sentry --env=prod 2>&1 | head -20
```

Expected: you see `sentry.client` and `Sentry\ClientInterface` in the container listing (no errors).

- [ ] **Step 4: Verify dev environment is unaffected**

```bash
php bin/console debug:container sentry --env=dev 2>&1
```

Expected: `No services found` or `[ERROR] No services match` — confirming Sentry is completely absent in dev.

- [ ] **Step 5: Commit**

```bash
git add config/packages/sentry.yaml
git commit -m "feat: configure sentry for production-only error tracking"
```

---

### Task 3: Add env vars to .env

**Files:**
- Modify: `.env`

Flex already added `SENTRY_DSN=`. We need to add `SENTRY_RELEASE=` so `.env` documents the variable and the deployer knows to set it.

- [ ] **Step 1: Check what Flex already added to .env**

```bash
grep -n SENTRY .env
```

If `SENTRY_DSN=` is already present, skip adding it. If not, add it.

- [ ] **Step 2: Add SENTRY_RELEASE to .env**

Add after the existing `SENTRY_DSN=` line:

```dotenv
SENTRY_DSN=
SENTRY_RELEASE=
```

Both are empty by default — the app works without them in dev. On the production server, `SENTRY_DSN` is set to the DSN from Sentry's project settings page (Settings → Client Keys). `SENTRY_RELEASE` is set by the deployer to the current git SHA.

- [ ] **Step 3: Commit**

```bash
git add .env
git commit -m "feat: add SENTRY_DSN and SENTRY_RELEASE env var placeholders"
```

---

### Task 4: Update the deployer to set SENTRY_RELEASE

**Files:**
- Modify: `D:\Projects\make-cargo-deployer\deploy.php` (separate repo)

The deployer needs to export `SENTRY_RELEASE` before running `cache:warmup` so the compiled container picks up the release tag.

- [ ] **Step 1: Open deploy.php and find where cache:warmup runs**

Look for the task that runs `cache:warmup` or `cache:clear` (likely the `deploy` task or a `deployCode`-style task).

- [ ] **Step 2: Set SENTRY_RELEASE before cache:warmup**

In the deployer task that runs on the remote server during deployment, add this line immediately before the `cache:warmup` command:

```php
run('echo "SENTRY_RELEASE=$(cd {{release_path}} && git rev-parse HEAD)" >> {{release_path}}/.env.local');
```

This writes the current commit SHA into `.env.local` on the release directory, which Symfony picks up at runtime. The `>>` appends so existing `.env.local` content is preserved.

- [ ] **Step 3: Verify the approach**

Check that `.env.local` on the server does not already define `SENTRY_RELEASE` (which would cause a duplicate). If it does, use a `sed` replace instead:

```php
run('cd {{release_path}} && RELEASE=$(git rev-parse HEAD) && (grep -q SENTRY_RELEASE .env.local && sed -i "s|SENTRY_RELEASE=.*|SENTRY_RELEASE=$RELEASE|" .env.local || echo "SENTRY_RELEASE=$RELEASE" >> .env.local)');
```

- [ ] **Step 4: Commit the deployer change**

```bash
# in the deployer repo
git add deploy.php
git commit -m "feat: set SENTRY_RELEASE env var to git SHA on deployment"
```

---

## Verification After Deployment

After deploying to production with a real `SENTRY_DSN` set on the server:

1. Trigger a test error:
```bash
# On the server, run a command that will throw a generic exception
cd /var/www/makeCargoDemo/api/current
/usr/bin/php8.2 bin/console sentry:test --env=prod
```
The `sentry:test` command is provided by `sentry/sentry-symfony` and sends a test event directly to Sentry. Check the Sentry project dashboard — the test event should appear within seconds.

2. Confirm ignored exceptions are NOT captured:
   - Make a request to a non-existent route (404) — should not appear in Sentry
   - Make an unauthenticated request to a protected endpoint — should not appear in Sentry

3. Confirm user context appears:
   - Trigger an error while authenticated — the Sentry issue should show the user's ID and email in the "User" tab
