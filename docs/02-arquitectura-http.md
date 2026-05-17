# 02 — Arquitectura HTTP de la API

## ¿Qué se construyó?

En esta fase conectamos las rutas API al sistema y definimos la arquitectura de capas que usará toda la API: **Controllers → Services → (Models)**. También creamos el trait `ApiResponse` que estandariza todas las respuestas JSON.

---

## ¿Dónde vive?

```
bootstrap/app.php                          ← registra routes/api.php
routes/api.php                             ← punto de entrada de todas las rutas API
app/Traits/ApiResponse.php                 ← formato estándar de respuesta JSON
app/Http/Controllers/Api/ApiController.php ← base de todos los controllers de la API
app/Http/Controllers/Api/              ← controllers específicos (se añaden por fase)
app/Http/Requests/Api/                 ← validaciones por endpoint
app/Http/Resources/                    ← transformadores de salida JSON
app/Services/                          ← lógica de negocio
```

---

## ¿Cómo funciona?

### Cómo Laravel 11 registra las rutas

En versiones anteriores de Laravel existía un `RouteServiceProvider.php`. En Laravel 11+ ese archivo desapareció — las rutas se registran directamente en `bootstrap/app.php`:

```php
// bootstrap/app.php
Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   // ← esto agrega /api/* con middleware 'api'
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
```

Al especificar `api:`, Laravel automáticamente aplica el grupo de middleware `api` (que incluye `throttle`, `json`, etc.) con el prefijo `/api` para todas esas rutas.

### Las 4 capas de la API

```
Request HTTP
    │
    ▼
┌─────────────────────────────┐
│  Form Request               │  ← valida los datos de entrada (rules)
│  (app/Http/Requests/Api/)   │
└─────────────┬───────────────┘
              │ datos validados
              ▼
┌─────────────────────────────┐
│  Controller                 │  ← orquesta: recibe, llama service, devuelve respuesta
│  (app/Http/Controllers/Api/)│
└─────────────┬───────────────┘
              │ llama al service
              ▼
┌─────────────────────────────┐
│  Service                    │  ← lógica de negocio: crea, actualiza, valida reglas
│  (app/Services/)            │
└─────────────┬───────────────┘
              │ usa Eloquent
              ▼
┌─────────────────────────────┐
│  Model / DB                 │  ← persiste y consulta datos
└─────────────────────────────┘
              │
              ▼
┌─────────────────────────────┐
│  API Resource               │  ← transforma el modelo a JSON de salida
│  (app/Http/Resources/)      │
└─────────────────────────────┘
```

**¿Por qué este nivel de separación?**

| Si pusieras todo en el Controller... | Con la separación... |
|---|---|
| El controller tiene 200+ líneas | Cada clase tiene una responsabilidad |
| No puedes reusar la lógica desde otro endpoint | El Service puede ser llamado desde Controller, Artisan, Queue |
| Los tests son complicados (testear HTTP para probar reglas de negocio) | Puedes testear el Service aislado sin HTTP |
| Cambiar el formato de respuesta requiere tocar toda la lógica | Solo cambias el Resource |

### El trait ApiResponse

Todas las respuestas de la API tienen el mismo formato:

```json
// Éxito
{
  "status": "success",
  "message": "OK",
  "data": { ... }
}

// Error
{
  "status": "error",
  "message": "Resource not found"
}

// Paginado
{
  "status": "success",
  "message": "OK",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 30,
    "last_page": 2
  }
}
```

El trait evita repetir esa estructura en cada controller:

```php
// Sin trait:
return response()->json(['status' => 'success', 'message' => '...', 'data' => $ticket], 200);

// Con trait:
return $this->success($ticket, 'Ticket creado');
return $this->error('No encontrado', 404);
return $this->paginated($tickets);
```

### El Rate Limiter 'api'

Definido en `AppServiceProvider::boot()`:

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)
        ->by($request->user()?->id ?: $request->ip());
});
```

Y aplicado en `routes/api.php`:

```php
Route::middleware('throttle:api')->group(function () {
    // todas las rutas API van aquí
});
```

`by()` distingue dos tipos de cliente:
- Usuario **autenticado** → el límite se aplica por `user_id` (cada usuario tiene su cuota individual)
- Usuario **anónimo** → el límite se aplica por IP (más restrictivo naturalmente)

Esto evita que un usuario malicioso cree múltiples cuentas para evadir el rate limit con IPs distintas.

---

## ¿Por qué este enfoque?

- **`ApiController` base** con `ApiResponse`: evitar duplicar el `use ApiResponse` en cada controller y tener un lugar para inyectar servicios comunes en el futuro.
- **Trait en lugar de clase helper**: los traits en PHP se "mezclan" en la clase, por lo que `$this->success()` se siente nativo y el IDE puede autocompletar sin inyección de dependencias.
- **`routes/api.php` con subarchivos incluidos por fase**: mantiene el archivo principal limpio mientras el proyecto crece — cada dominio tendrá su propio archivo de rutas.

---

## Verificación manual

```bash
# Verificar que las rutas están registradas
docker compose exec app php artisan route:list

# Cuando tengamos al menos 1 ruta en api.php:
docker compose exec app php artisan route:list --path=api
```

---

## Glosario

| Término | Significado |
|---|---|
| **Middleware** | Capa que intercepta el request antes/después del controller (auth, rate limit, JSON, etc.) |
| **Trait** | Mecanismo de PHP para reutilizar métodos en múltiples clases sin herencia |
| **Rate Limiter** | Control de cuántas requests puede hacer un cliente en un período |
| **Form Request** | Clase de Laravel que encapsula las reglas de validación fuera del controller |
| **API Resource** | Clase de Laravel que transforma un modelo (o colección) en JSON de salida |
| **Service** | Clase PHP pura que contiene la lógica de negocio, sin depender de HTTP |
