# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# All artisan/composer commands MUST run inside Docker (pdo_sqlsrv not available on host PHP)
docker compose exec app php artisan <cmd>
docker compose exec app composer <cmd>

# First-time setup
composer run setup

# Start all dev services (server + queue + logs + vite)
composer run dev

# Run all tests
composer run test

# Run a single test file
php artisan test tests/Feature/ExampleTest.php

# Lint / format code
./vendor/bin/pint

# Migrate and seed
docker compose exec app php artisan migrate:fresh --seed

# Start with Docker
docker compose up --build

# Push edited files to container (overlayfs bind-mount issue — edits to existing files don't sync)
docker cp <local-file> tickets-api-app-1:/var/www/<file>
# New files DO appear automatically; only edits to existing files need docker cp
```

## Architecture

**Stack:** Laravel 13.7 · PHP 8.3 · Jetstream + Sanctum (API token auth) · SQL Server 2022 (Docker, port 1433) · Sentry (error monitoring)

**Database:** Always test against SQL Server inside Docker — `pdo_sqlsrv` is not available on the host. `DB_CONNECTION=sqlsrv` with credentials from `docker-compose.yml`.

**Critical SQL Server quirk:** `pdo_sqlsrv` returns `bigint` columns as PHP strings. All models must cast `id` and foreign key columns to `'integer'` in `casts()`, otherwise strict comparisons (`!==`) between `user_id` (string `"6"`) and `$user->id` (int `6`) fail silently with wrong 403 errors.

**Intentional convention — no DB-level enums:** Status and type columns are `string(20)` in migrations. Validation is delegated to PHP backed enums in `app/Enums/`. When adding a new status or type, add the case to the enum — no migration needed.

**Enums:**
- `TicketStatus`: `open`, `in_progress`, `closed`, `resolved`
- `TicketPriority`: `low`, `medium`, `high`
- `DeviceStatus`: `available`, `assigned`, `maintenance`
- `DeviceType`: `pc`, `laptop`, `mobile`, `tablet`, `other`

**Core domain tables (all complete):**
- `tickets` — belongs to `users`, optionally to `devices`; soft-deleted; casts: `id/user_id/device_id` → integer, `status` → TicketStatus, `priority` → TicketPriority
- `devices` — soft-deleted; casts: `type` → DeviceType, `status` → DeviceStatus
- `device_assignments` — `device_id`, `user_id`, `assigned_at`, `returned_at` (nullable), `notes`; soft-deleted
- `activity_logs` — `user_id` (nullable FK), `action`, `loggable_type/id` (polymorphic), `description`, `ip_address`, `user_agent`, `metadata` (json)

**HTTP layer pattern:** Controller → Service → Model. Controllers only receive, delegate, return. Services hold all business logic and record `ActivityLog` entries. FormRequests hold validation rules.

**`ApiResponse` trait** (`app/Traits/ApiResponse.php`): `success()`, `error()`, `notFound()`, `paginated()`. The `paginated()` method accepts a `?callable $transform` to apply API Resources: always pass `fn($items) => SomeResource::collection($items)->resolve()` — skipping this exposes raw Eloquent models with `deleted_at`, etc.

**Exception handler** (`bootstrap/app.php` `withExceptions()`): Handles 401, 404, 422, 429, 500 with structured JSON. Sentry's `Integration::handles($exceptions)` is called FIRST so it captures the full stacktrace before the handler converts to JSON.

**Rate limiting** (`AppServiceProvider::boot()`): `RateLimiter::for('api')` — 60 req/min per authenticated user (`user_id`) or per IP for anonymous. Applied via `throttle:api` middleware wrapping all `/api/*` routes.

**API routes** (`routes/api.php`): Registered in `bootstrap/app.php` via `api: __DIR__.'/../routes/api.php'`. Current routes (11 total):
- Public (under `throttle:api`): `POST /api/register`, `POST /api/login`
- Auth-protected (under `auth:sanctum`): `POST /logout`, `GET /me`, `GET|POST /tickets`, `GET|PATCH|DELETE /tickets/{id}`, `GET /devices`, `POST /devices/assign`

**Business logic conventions:**
- `DeviceService::assign()` throws `\DomainException` if device is not `available` → caught in controller → 409 Conflict
- `DB::transaction()` wraps all multi-table operations (assign device: update status + create assignment + log)
- `TicketService::create()` explicitly sets `status => TicketStatus::OPEN` (Eloquent doesn't auto-load DB defaults)

**Seeders:** `DatabaseSeeder` creates 5 users → `DeviceSeeder` (15 available + 3 assigned + 2 maintenance) → `TicketSeeder` (30 tickets with `recycle($users)` and `recycle($devices)`).

**Error monitoring:** Sentry integrated via `Integration::handles($exceptions)` in `bootstrap/app.php`. Configure `SENTRY_LARAVEL_DSN` in `.env`.

## Pending (per plan)

- **Fase 7:** Discord webhooks — `DiscordChannel`, `ServerExceptionAlert`, `RateLimitExceededAlert` Notifications; trigger on 500 errors and 429 rate limit; `config/services.php` discord key; `docs/07-discord-webhooks.md`
- **Fase 8:** Custom log channels in `config/logging.php` + `LogActivity` middleware → inserts into `activity_logs`; `docs/08-sentry-y-logs.md`
- **Fase 9:** `/dashboard` (Blade + Tailwind) with `MetricsService`; `docs/09-dashboard-metricas.md`
- **Fase 10:** `README.md`, Postman collection at `docs/postman/`, `docs/README.md` index

## Educational docs

Spanish-language docs in `docs/` explain each phase (what was built, how it works, where it lives, why that approach):
- `docs/01-modelos-y-base-de-datos.md`
- `docs/02-arquitectura-http.md`
- `docs/03-autenticacion-sanctum.md`
- `docs/04-crud-tickets.md`
- `docs/05-devices-y-asignaciones.md`
- `docs/06-rate-limiting-y-excepciones.md`
