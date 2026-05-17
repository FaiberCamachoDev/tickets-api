# 09 — Dashboard de Métricas

## Qué se construyó

Una página web en `/dashboard` que muestra métricas en tiempo real del sistema: resumen de tickets y dispositivos, distribución por estado y prioridad, tendencia de creación de tickets en los últimos 7 días, y el log de actividad reciente.

---

## Arquitectura

```
GET /dashboard
    │
    ▼
DashboardController::index()
    │  inyecta MetricsService via constructor
    ▼
MetricsService          — consultas a la BD, devuelve colecciones listas
    │
    ▼
resources/views/dashboard/index.blade.php
    │  Tailwind CDN (sin Vite build)
    ▼
HTML renderizado en el navegador
```

---

## `MetricsService`

Ubicación: `app/Services/MetricsService.php`

| Método | Qué devuelve |
|---|---|
| `summary()` | Array con 6 contadores globales |
| `ticketsByStatus()` | Colección con label, count, percent por status |
| `ticketsByPriority()` | Colección keyed por prioridad |
| `devicesByStatus()` | Colección keyed por status |
| `ticketTrend()` | Colección de 7 días: `{date, count}` |
| `recentActivity(12)` | Últimos N registros de `activity_logs` con user eager-loaded |

### Compatibilidad SQL Server

- `selectRaw('status, COUNT(*) as count')` + `groupBy('status')` funciona igual en SQL Server
- `whereDate('created_at', $date)` en el trend evita `GROUP BY CAST(...)` que requiere ajustes en SQL Server
- `COUNT(*)` devuelve `string` en `pdo_sqlsrv` → se castea a `(int)` en el map
- Los enums de Eloquent se acceden con `->value` antes de pasarlos a la vista

---

## Vista Blade

Archivo: `resources/views/dashboard/index.blade.php`

No usa el layout de Jetstream ni `@vite()` porque no hay un build de assets dentro del contenedor Docker. En su lugar usa **Tailwind Play CDN**:

```html
<script src="https://cdn.tailwindcss.com"></script>
```

Esto es correcto para un dashboard de visualización interno — sin interactividad JS compleja y sin necesidad de pipeline de build.

### Secciones de la vista

1. **Nav** — nombre de la app, breadcrumb, timestamp de actualización
2. **Stat cards** — 6 tarjetas de color con los totales principales
3. **Tickets por estado** — barras de progreso con porcentaje por color (amber=open, blue=in_progress, emerald=resolved, gray=closed)
4. **Dispositivos por estado** — 3 tarjetas (available, assigned, maintenance) + tickets por prioridad (high/medium/low)
5. **Trend 7 días** — gráfico de barras CSS puro (sin JS), altura proporcional al máximo del período
6. **Actividad reciente** — tabla con action badge, descripción, usuario, IP y timestamp

---

## Archivos involucrados

| Archivo | Rol |
|---|---|
| `app/Services/MetricsService.php` | Todas las consultas y transformaciones |
| `app/Http/Controllers/DashboardController.php` | Recibe MetricsService, pasa datos a la vista |
| `resources/views/dashboard/index.blade.php` | Vista Blade + Tailwind CDN |
| `routes/web.php` | `GET /dashboard` |

---

## Acceder al dashboard

```
http://localhost:8000/dashboard
```

No requiere autenticación (acceso de desarrollo). Los datos son en tiempo real: cada recarga consulta la BD.

---

## Por qué Tailwind CDN en lugar de Vite

El contenedor Docker no tiene el build de assets generado (`public/build/`). Opciones:

- **`npm run build` dentro del contenedor** — requiere Node en la imagen, añade peso al Dockerfile
- **Tailwind CDN** — una sola línea, sin dependencias de build, funciona igual para un dashboard interno

Para un frontend público con assets optimizados, se usaría el pipeline de Vite. Para métricas internas, el CDN es la elección pragmática.
