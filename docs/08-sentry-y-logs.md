# 08 — Logs de Actividad y Canales Personalizados

## Qué se construyó

Un middleware `LogActivity` que intercepta cada request a la API y registra dos destinos simultáneamente, más dos canales de log dedicados en `config/logging.php`.

---

## Canales de log personalizados

En `config/logging.php` se agregaron dos canales con driver `daily` (rotan un archivo por día):

| Canal | Archivo | Nivel | Retención |
|---|---|---|---|
| `api_activity` | `storage/logs/api-activity-YYYY-MM-DD.log` | `info` | 30 días |
| `api_errors` | `storage/logs/api-errors-YYYY-MM-DD.log` | `error` | 60 días |

El canal `stack` (por defecto) ahora incluye `api_errors`, por lo que todo error crítico aparece tanto en `laravel.log` como en su archivo dedicado.

---

## Middleware `LogActivity`

Ubicación: `app/Http/Middleware/LogActivity.php`  
Registro: `bootstrap/app.php` → `appendToGroup('api', LogActivity::class)`

### Qué hace en cada request

```
Request entrante
    │
    ▼
 handle() — registra hrtime(true) de inicio
    │
    ▼
 $next($request) — ejecuta el resto del pipeline
    │
    ▼
 logToFile()      → siempre, todo request → api_activity channel
    │
 shouldLogToDatabase()?
    │  Sí: auth events + GETs autenticados
    ▼
 logToDatabase()  → inserta en tabla activity_logs
    │
    ▼
 Devuelve Response
```

### Destino 1 — Archivo (`api_activity`)

Registra **todos** los requests con:

```json
{
  "method": "GET",
  "path": "api/tickets",
  "status": 200,
  "user_id": 1,
  "ip": "172.19.0.1",
  "duration_ms": 39,
  "user_agent": "curl/8.5.0"
}
```

### Destino 2 — Base de datos (`activity_logs`)

Solo cuando `shouldLogToDatabase()` devuelve `true`:

- **Auth events** (`/register`, `/login`, `/logout`): los servicios no los registraban
- **GETs autenticados** (`/me`, `/tickets`, `/devices`, `/tickets/{id}`): los servicios solo logean writes

Los writes (POST tickets, PATCH, DELETE, POST devices/assign) **no se duplican** porque ya los registran `TicketService` y `DeviceService` con más detalle.

### Mapa de acciones registradas

| Path | Acción en DB |
|---|---|
| POST /api/register | `auth_register` |
| POST /api/login | `auth_login` |
| POST /api/logout | `auth_logout` |
| GET /api/me | `auth_profile_viewed` |
| GET /api/tickets | `ticket_list_viewed` |
| GET /api/tickets/{id} | `ticket_viewed` |
| GET /api/devices | `device_list_viewed` |

---

## Arquitectura de logs completa

```
Request
  │
  ├── Middleware LogActivity ──► api_activity.log  (access log)
  │
  ├── Exception handler ────────► api_errors.log   (errores 4xx/5xx)
  │                               Discord webhook  (500 + 429)
  │                               Sentry           (excepciones)
  │
  └── Services (TicketService, DeviceService)
           └──► tabla activity_logs  (eventos de negocio con detalle)
```

---

## Archivos involucrados

| Archivo | Cambio |
|---|---|
| `config/logging.php` | Canales `api_activity` y `api_errors`; `stack` incluye `api_errors` |
| `app/Http/Middleware/LogActivity.php` | Nuevo middleware |
| `bootstrap/app.php` | `appendToGroup('api', LogActivity::class)` |

---

## Ver logs en vivo

```bash
# Archivo de actividad (dentro del contenedor)
docker compose exec app tail -f storage/logs/api-activity-$(date +%Y-%m-%d).log

# Últimas entradas en la tabla activity_logs
docker compose exec app php artisan tinker --execute="
App\Models\ActivityLog::latest()->take(10)->get(['action','description','ip_address','created_at'])->each(fn(\$l) => dump(\$l->toArray()));
"
```
