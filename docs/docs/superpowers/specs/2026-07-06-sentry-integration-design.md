# Sentry Integration Design

**Goal:** Add error tracking to production so broken requests and unhandled exceptions are captured in Sentry with full context (user, release, stack trace).

**Architecture:** Install `sentry/sentry-symfony` bundle. The bundle hooks into Symfony's kernel exception event and captures unhandled exceptions automatically before `KernelExceptionSubscriber` returns the JSON response. No changes to existing exception handling code.

**Tech Stack:** `sentry/sentry-symfony` ~4.0, Symfony 7.1, PHP 8.2

---

## Package

Add `sentry/sentry-symfony` via Composer. The bundle auto-registers its listeners via Symfony Flex. No manual bundle registration needed (Symfony 7 with Flex handles it).

```
composer require sentry/sentry-symfony
```

---

## Configuration

New file `config/packages/sentry.yaml`, wrapped entirely in `when@prod` so Sentry is completely inactive in dev and test.

```yaml
when@prod:
    sentry:
        dsn: '%env(SENTRY_DSN)%'
        release: '%env(default::SENTRY_RELEASE)%'
        options:
            environment: '%kernel.environment%'
            send_default_pii: true
            ignore_exceptions:
                - App\Misc\Exception\BadRequestException
                - App\Misc\Exception\AccessDeniedException
                - App\Misc\Exception\CapacityExceededException
                - Symfony\Component\HttpKernel\Exception\NotFoundHttpException
                - Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
                - Symfony\Component\Security\Core\Exception\AuthenticationException
```

`send_default_pii: true` enables the bundle's `LoginListener` which attaches the authenticated user's `id` and `email` to every error report.

`default::SENTRY_RELEASE` uses Symfony's `default:` env processor — if `SENTRY_RELEASE` is not set, it sends no release tag rather than erroring.

---

## Environment Variables

Add to `.env` (empty defaults, so the app works without Sentry in dev):

```dotenv
SENTRY_DSN=
SENTRY_RELEASE=
```

On the production server, `SENTRY_DSN` is set to the project DSN from Sentry's project settings. `SENTRY_RELEASE` is set to the git SHA during deployment (e.g. `SENTRY_RELEASE=$(git rev-parse HEAD)` in the deployer).

---

## What Gets Captured

**Captured:** Any exception that reaches the Symfony kernel and is not in `ignored_exceptions` — unhandled PHP errors, unexpected service failures, database errors, 5xx responses, Messenger worker failures.

**Not captured:**
- `BadRequestException` — user input validation failures (400)
- `AccessDeniedException` — custom auth refusals (403)
- `CapacityExceededException` — business rule limits (4xx)
- `NotFoundHttpException` — route not found (404)
- `AccessDeniedHttpException` — Symfony security layer (403)
- `AuthenticationException` — unauthenticated requests (401)

---

## User Context

The bundle's `LoginListener` reads the authenticated user from Symfony's security token and attaches:
- `id` — user identifier
- `email` — from `getUserIdentifier()` or email field

This happens automatically when `send_default_pii: true` is set. No extra code needed.

---

## Release Tracking

Each error in Sentry is tagged with the git SHA from `SENTRY_RELEASE`. This lets you:
- See which release first introduced a bug
- Filter errors by release in Sentry's UI
- Mark releases as resolved

The deployer sets this variable before running `cache:warmup` during deployment.

---

## Files Changed

| File | Action |
|------|--------|
| `composer.json` / `composer.lock` | Modified — adds `sentry/sentry-symfony` |
| `config/packages/sentry.yaml` | Created — bundle configuration |
| `.env` | Modified — adds `SENTRY_DSN=` and `SENTRY_RELEASE=` |

No changes to `KernelExceptionSubscriber`, `services.yaml`, or any existing exception handling code.
