# 04 — CRUD de Tickets

## ¿Qué se construyó?

El conjunto completo de operaciones sobre tickets: crear, listar, ver, actualizar y eliminar (soft delete), con lógica de negocio separada en un Service y registro automático de actividad en `activity_logs`.

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/tickets` | Lista paginada de MIS tickets (filtrable) |
| POST | `/api/tickets` | Crea un ticket nuevo |
| GET | `/api/tickets/{id}` | Ver un ticket específico |
| PATCH | `/api/tickets/{id}` | Actualizar (campos parciales) |
| DELETE | `/api/tickets/{id}` | Eliminar (soft delete) |

---

## ¿Dónde vive?

```
app/Http/Requests/Api/Ticket/StoreTicketRequest.php   ← validación de creación
app/Http/Requests/Api/Ticket/UpdateTicketRequest.php  ← validación de actualización
app/Services/TicketService.php                        ← lógica de negocio
app/Http/Controllers/Api/TicketController.php         ← orquestación
app/Http/Resources/TicketResource.php                 ← formato JSON de salida
```

---

## ¿Cómo funciona?

### Patrón Service — Por qué la lógica no va en el Controller

```
TicketController::store()
  │
  ├─► StoreTicketRequest::rules() valida datos
  │
  └─► TicketService::create(User, array): Ticket
        ├─ $user->tickets()->create([...])          ← relación Eloquent asigna user_id
        ├─ ActivityLog::create([...])                ← registra la acción
        └─ $ticket->load(['user', 'device'])         ← eager loading para el Resource
```

El **controller** solo hace tres cosas: recibir, delegar al service, retornar. Si mañana necesitas crear un ticket desde un comando Artisan o desde un Queue job, solo llamas a `TicketService::create()` — no hay nada que duplicar.

### Rule::enum — Validación de enums en FormRequest

```php
// StoreTicketRequest.php
'priority' => ['required', Rule::enum(TicketPriority::class)],
```

`Rule::enum(TicketPriority::class)` valida que el valor recibido (`'low'`, `'medium'`, `'high'`) sea uno de los `->value` del enum. Si no, devuelve 422. Esto evita guardar strings arbitrarios en la DB.

### `sometimes` vs `required` en UpdateTicketRequest

```php
// UpdateTicketRequest.php
'title'  => ['sometimes', 'string'],  // solo valida SI está presente
'status' => ['sometimes', Rule::enum(TicketStatus::class)],
```

`sometimes` permite actualizaciones parciales (PATCH): puedes enviar solo `{"status": "in_progress"}` sin el título. El campo se valida únicamente si viene en el request.

### Paginación con filtros

```php
// TicketService::list()
Ticket::with(['user', 'device'])
    ->where('user_id', $user->id)   // ← un usuario solo ve SUS tickets
    ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
    ->latest()
    ->paginate(15);
```

`->when($condition, $callback)` aplica el filtro solo si la condición es verdadera. Evita escribir `if/else` por cada filtro posible.

La paginación con `->paginate(15)` devuelve un `LengthAwarePaginator` que contiene los datos Y los metadatos de páginas:

```json
{
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 30,
    "last_page": 2
  }
}
```

Usa `?page=2` en la URL para navegar: `GET /api/tickets?page=2&status=open`.

### Eager Loading — Evitar N+1

```php
Ticket::with(['user', 'device'])->paginate(15)
// → 1 query para tickets + 1 para users + 1 para devices = 3 queries total
```

Sin `with()`:
```php
Ticket::paginate(15)
// Por cada ticket: una query para ->user, otra para ->device = 1 + N*2 queries
// Con 15 tickets = 31 queries
```

El `TicketResource` usa `$this->whenLoaded('device')` — si el device no fue eager-loaded, el campo aparece como nulo en la respuesta sin hacer una query extra.

### SQL Server bigint como string

SQL Server retorna columnas `bigint` como strings en PHP via `pdo_sqlsrv`. Sin un cast, `$ticket->user_id` sería `"6"` (string) y la comparación estricta `!== $request->user()->id` (int 6) fallaría, dando 403.

La solución es declarar los casts en el modelo:

```php
// Ticket.php
protected function casts(): array
{
    return [
        'id'        => 'integer',
        'user_id'   => 'integer',
        'device_id' => 'integer',
        // ...
    ];
}
```

**Aplica esto a TODOS los modelos** cuando uses SQL Server.

### Soft Delete

Al llamar `$ticket->delete()`, Eloquent NO hace `DELETE FROM tickets`. En cambio:

```sql
UPDATE tickets SET deleted_at = '2026-05-14 21:58:19' WHERE id = 36
```

Las queries normales (`Ticket::all()`, `Ticket::find()`) automáticamente agregan `WHERE deleted_at IS NULL`. El ticket "desaparece" sin perder el historial. La table `activity_logs` sigue teniendo el registro de la creación, actualización y eliminación.

---

## Verificación manual

```bash
TOKEN=<tu_token>

# Crear
curl -X POST http://localhost:8000/api/tickets \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"title":"PC sin sonido","description":"Audio no funciona","priority":"low","category":"incident"}'

# Listar con filtro
curl "http://localhost:8000/api/tickets?status=open&priority=low" \
  -H "Authorization: Bearer $TOKEN"

# Actualizar status
curl -X PATCH http://localhost:8000/api/tickets/36 \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"status":"in_progress"}'

# Eliminar (soft delete)
curl -X DELETE http://localhost:8000/api/tickets/36 \
  -H "Authorization: Bearer $TOKEN"
```

---

## Glosario

| Término | Significado |
|---|---|
| **`Route::apiResource`** | Registra las 5 rutas CRUD estándar en una sola línea |
| **`sometimes`** | Regla de validación: solo valida el campo SI está presente en el request |
| **`Rule::enum`** | Valida que el valor sea uno de los `->value` del backed enum |
| **`->when($cond, $fn)`** | Aplica un scope/filtro de Eloquent condicionalmente |
| **`->paginate(n)`** | Divide resultados en páginas de n elementos con metadatos |
| **Eager loading** | Cargar relaciones por adelantado para evitar N+1 queries |
| **Soft Delete** | Marcar como borrado sin eliminar físicamente (columna `deleted_at`) |
| **N+1 problem** | Problema donde cada elemento de una lista dispara una query extra |
