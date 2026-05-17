# 06 — Rate Limiting y Manejo de Excepciones

## ¿Qué se construyó?

**Rate Limiting:** un limitador `api` que permite 60 requests/minuto por usuario (o por IP si es anónimo), aplicado a todas las rutas `/api/*`.

**Exception Handler:** un sistema global que convierte cualquier excepción en respuestas JSON estructuradas y limpias, sin exponer stacktraces al cliente.

---

## ¿Dónde vive?

```
app/Providers/AppServiceProvider.php  ← define RateLimiter::for('api')
routes/api.php                        ← aplica middleware 'throttle:api'
bootstrap/app.php                     ← exception handler global
```

---

## ¿Cómo funciona?

### Rate Limiting

```php
// AppServiceProvider::boot()
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)
        ->by($request->user()?->id ?: $request->ip());
});
```

`->by(key)` determina **quién** comparte la cuota:
- Usuario autenticado → cuota individual por `user_id` (cada usuario tiene sus propias 60 req/min)
- Anónimo → cuota por IP (más restrictivo; un atacante con una IP no puede abusar)

```php
// routes/api.php
Route::middleware('throttle:api')->group(function () {
    // todas las rutas API van aquí
});
```

Cuando se excede el límite, Laravel retorna 429 con headers informativos:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
Retry-After: 58
```

Y gracias al exception handler, la respuesta JSON es:
```json
{
  "status": "error",
  "message": "Demasiadas solicitudes. Espera un momento e intenta de nuevo.",
  "retry_after": 58
}
```

### Exception Handler en Laravel 11+

En versiones anteriores había un `app/Exceptions/Handler.php` que sobreescribías. En Laravel 11+ el manejo de excepciones se hace en `bootstrap/app.php` usando closures:

```php
->withExceptions(function (Exceptions $exceptions) {
    Integration::handles($exceptions);   // Sentry sigue capturando TODO

    // Forzar JSON para rutas /api/*
    $exceptions->shouldRenderJsonWhen(fn(Request $r) => $r->is('api/*'));

    // Manejo específico por tipo de excepción
    $exceptions->render(function (ValidationException $e, Request $r) {
        if ($r->is('api/*')) {
            return response()->json([...], 422);
        }
    });
    // ...
})
```

### Tipos de excepción manejados

| Excepción | HTTP | Cuándo ocurre |
|---|---|---|
| `ValidationException` | 422 | FormRequest falla sus reglas |
| `ModelNotFoundException` / `NotFoundHttpException` | 404 | `findOrFail()` o ruta inexistente |
| `AuthenticationException` | 401 | Request sin token o token inválido |
| `TooManyRequestsHttpException` | 429 | Rate limit excedido |
| `\Throwable` (fallback) | 500 | Cualquier otra excepción |

### El orden importa: Sentry antes que el handler

```php
Integration::handles($exceptions);  // ← PRIMERO: Sentry se engancha a TODOS los errores

$exceptions->render(function (...) { ... });  // ← DESPUÉS: formateamos para el cliente
```

Sentry captura la excepción antes de que nuestro handler la convierta en JSON. Si lo pusiéramos al final, Sentry solo vería los errores que se "escapan" del handler. Así, Sentry recibe el stacktrace completo mientras el cliente recibe solo el mensaje limpio.

### Fallback genérico — Seguridad por omisión

```php
$exceptions->render(function (\Throwable $e, Request $r) {
    if ($r->is('api/*')) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Error interno del servidor. El equipo ha sido notificado.',
        ], 500);
    }
});
```

**Nunca expones stacktraces al cliente.** Un stacktrace puede revelar:
- Rutas de archivos del servidor
- Nombres de variables internas
- Queries SQL con datos sensibles
- Versiones de librerías (explotables si tienen CVEs)

Sentry ya tiene el stacktrace completo para debugging. El cliente no lo necesita.

---

## Verificación manual

```bash
# 429 — exceder rate limit (requiere bajar el límite temporalmente a 3/min)
for i in {1..5}; do
  curl -s http://localhost:8000/api/tickets \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json" | grep status
done

# 401 — sin token
curl http://localhost:8000/api/tickets -H "Accept: application/json"

# 404 — modelo inexistente
curl http://localhost:8000/api/tickets/99999 \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"

# 422 — validación fallida
curl -X POST http://localhost:8000/api/tickets \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"","priority":"invalido"}'
```

---

## Glosario

| Término | Significado |
|---|---|
| **Rate Limiting** | Restricción de cuántas requests puede hacer un cliente en un período |
| **`throttle:api`** | Middleware que aplica el limiter nombrado 'api' |
| **`Retry-After`** | Header HTTP que indica cuántos segundos esperar antes de reintentar |
| **429 Too Many Requests** | Código HTTP para rate limit excedido |
| **Exception Handler** | Punto centralizado que intercepta todas las excepciones no capturadas |
| **Stacktrace** | Traza de llamadas que llevaron al error; útil para debugging, peligroso si se expone |
| **`shouldRenderJsonWhen`** | Indica a Laravel cuándo responder con JSON vs HTML en errores |
