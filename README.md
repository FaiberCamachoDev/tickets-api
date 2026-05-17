# Tickets API

REST API for technical support ticket management and device assignment. Built with Laravel 13, PHP 8.3, SQL Server 2022, and token-based authentication (Sanctum).

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 13.7 |
| Language | PHP 8.3 |
| Database | SQL Server 2022 (Docker) |
| Authentication | Laravel Sanctum (API tokens) |
| Error monitoring | Sentry |
| Alerts | Discord webhooks |
| Frontend | Blade + Tailwind CSS (dashboard) |

---

## Getting started

### Requirements

- Docker + Docker Compose

### First-time setup

```bash
docker compose up --build -d
docker compose exec app php artisan migrate:fresh --seed
```

The server will be available at `http://localhost:8000`.

### Daily usage

```bash
docker compose up -d        # start
docker compose down         # stop
docker compose logs -f app  # stream logs
```

> **Note:** `pdo_sqlsrv` is only available inside the container. Never run `php artisan` directly on the host — always prefix with `docker compose exec app`.

---

## API Endpoints

Base URL: `http://localhost:8000/api`

All resource endpoints require the following headers:
```
Authorization: Bearer <token>
Accept: application/json
```

### Authentication

| Method | Route | Description |
|---|---|---|
| `POST` | `/register` | Create a new account |
| `POST` | `/login` | Obtain an API token |
| `POST` | `/logout` | Revoke current token |
| `GET` | `/me` | Get authenticated user profile |

### Tickets

| Method | Route | Description |
|---|---|---|
| `GET` | `/tickets` | List tickets (paginated; filters: `status`, `priority`) |
| `POST` | `/tickets` | Create a ticket |
| `GET` | `/tickets/{id}` | Get a ticket |
| `PATCH` | `/tickets/{id}` | Update a ticket |
| `DELETE` | `/tickets/{id}` | Delete a ticket (soft delete) |

**Valid values:**
- `status`: `open` · `in_progress` · `resolved` · `closed`
- `priority`: `low` · `medium` · `high`
- `category`: `incident` · `device_assignment` · `control`

### Devices

| Method | Route | Description |
|---|---|---|
| `GET` | `/devices` | List devices (filters: `status`, `type`) |
| `POST` | `/devices/assign` | Assign a device to a user |

**Valid values:**
- `status`: `available` · `assigned` · `maintenance`
- `type`: `pc` · `laptop` · `mobile` · `tablet` · `other`

---

## Testing with Postman

Import `docs/postman/tickets-api.postman_collection.json` into Postman.

The collection includes all 11 routes with example request bodies. The **Login** and **Register** requests include a test script that automatically saves the token to `{{token}}` — no manual copy-pasting required.

Seeded test user credentials:
```
email:    test@example.com
password: password
```

---

## Dashboard

Metrics view available in the browser:

```
http://localhost:8000/dashboard
```

Shows: ticket and device totals, distribution by status and priority, 7-day creation trend, and recent activity log.

---

## Useful commands

```bash
# Reset database with fresh seed data
docker compose exec app php artisan migrate:fresh --seed

# Interactive console
docker compose exec app php artisan tinker

# Push an edited file to the container (edits to existing files don't sync automatically)
docker cp <local-file> tickets-api-app-1:/var/www/<path-in-container>

# Query recent activity
docker compose exec app php artisan tinker --execute="
App\Models\ActivityLog::latest()->take(5)->get(['action','description'])->each(fn(\$l) => dump(\$l->toArray()));
"

# Stream activity log
docker compose exec app tail -f storage/logs/api-activity-$(date +%Y-%m-%d).log
```

---

## Environment variables

| Variable | Description |
|---|---|
| `DB_HOST` | SQL Server host (default: `sqlserver` inside Docker) |
| `DB_DATABASE` | Database name (`tickets_db`) |
| `SENTRY_LARAVEL_DSN` | Sentry DSN for exception tracking |
| `DISCORD_WEBHOOK_URL` | Discord webhook for 500 and 429 alerts |

Copy `.env.example` to `.env` and fill in the values before starting the project.

---

## Documentation

Each project phase has its own documentation in `docs/`. See the [full index](docs/README.md).

---

## Observability architecture

```
Request
  │
  ├── LogActivity middleware ──► api-activity.log  (access log)
  │
  ├── Exception handler ────────► api-errors.log   (errors)
  │                               Discord webhook  (500 + 429)
  │                               Sentry           (full stacktrace)
  │
  └── Services ─────────────────► activity_logs    (business events)
```

---

## License

MIT
