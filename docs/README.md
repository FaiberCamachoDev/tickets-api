# Documentación — Tickets API

Documentación educativa del proyecto, organizada por fases de construcción. Cada doc explica qué se construyó, cómo funciona, dónde vive en el código y por qué se tomaron esas decisiones.

---

## Índice

| Fase | Documento | Contenido |
|---|---|---|
| 01 | [Modelos y Base de Datos](01-modelos-y-base-de-datos.md) | Migraciones, modelos Eloquent, relaciones, enums, seeders |
| 02 | [Arquitectura HTTP](02-arquitectura-http.md) | Patrón Controller → Service → Model, FormRequests, API Resources, trait `ApiResponse` |
| 03 | [Autenticación Sanctum](03-autenticacion-sanctum.md) | Registro, login, logout, tokens de API, middleware `auth:sanctum` |
| 04 | [CRUD de Tickets](04-crud-tickets.md) | Endpoints de tickets, soft delete, filtros, paginación, autorización por ownership |
| 05 | [Devices y Asignaciones](05-devices-y-asignaciones.md) | Listado de dispositivos, asignación con transacción DB, `DomainException` → 409 |
| 06 | [Rate Limiting y Excepciones](06-rate-limiting-y-excepciones.md) | `RateLimiter::for('api')`, handler de excepciones JSON, Sentry |
| 07 | [Discord Webhooks](07-discord-webhooks.md) | Canal personalizado `DiscordChannel`, notificaciones 500 y 429, `rescue()` |
| 08 | [Logs de Actividad](08-sentry-y-logs.md) | Middleware `LogActivity`, canales `api_activity` / `api_errors`, tabla `activity_logs` |
| 09 | [Dashboard de Métricas](09-dashboard-metricas.md) | `MetricsService`, vista Blade + Tailwind CDN, gráficos CSS puro |

---

## Colección Postman

El archivo `postman/tickets-api.postman_collection.json` contiene las 11 rutas de la API listas para importar.

> La carpeta `postman/` está en `.gitignore` — no sube al repositorio.

**Instrucciones de uso:**
1. Abre Postman → **Import** → selecciona el archivo JSON
2. Ejecuta **Login** o **Register** — el token se guarda automáticamente en `{{token}}`
3. Las demás rutas usan `{{token}}` y `{{base_url}}` sin configuración adicional

---

## Flujo de datos completo

```
Cliente HTTP
    │
    ▼
throttle:api (60 req/min)
    │
    ▼
LogActivity middleware ──────────────────────────────────► api-activity.log
    │
    ▼
auth:sanctum (rutas protegidas)
    │
    ▼
Controller  (recibe, delega, retorna)
    │
    ▼
FormRequest (valida)     Service (lógica de negocio)
                              │
                     ┌────────┴────────┐
                     ▼                 ▼
                   Model          ActivityLog
               (Eloquent)        (tabla DB)
```

### Manejo de errores

```
Excepción
    │
    ├── Sentry::capture()        (siempre, con stacktrace)
    │
    ├── Si 500 o 429 ──────────► Discord webhook (embed con contexto)
    │
    └── JSON response al cliente (sin stacktrace expuesto)
```
