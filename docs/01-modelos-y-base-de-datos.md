# 01 — Modelos y Base de Datos

## ¿Qué se construyó?

En esta fase completamos los cimientos de la base de datos: las migraciones de todas las tablas, los modelos Eloquent con sus relaciones, y los factories/seeders para poblar el sistema con datos de prueba.

---

## ¿Dónde vive?

```
database/
  migrations/
    0001_01_01_000000_create_users_table.php       ← Laravel default
    2026_05_13_002916_create_devices_table.php      ← nuestra
    2026_05_13_002916_create_tickets_table.php      ← nuestra
    2026_05_13_002917_create_device_assignments_table.php  ← nuestra
    2026_05_13_002917_create_activity_logs_table.php       ← nuestra
  factories/
    UserFactory.php · DeviceFactory.php · TicketFactory.php · DeviceAssignmentFactory.php
  seeders/
    DatabaseSeeder.php · DeviceSeeder.php · TicketSeeder.php

app/Models/
  User.php · Device.php · Ticket.php · DeviceAssignment.php · ActivityLog.php

app/Enums/
  TicketStatus.php · TicketPriority.php · DeviceType.php · DeviceStatus.php
```

---

## ¿Cómo funciona?

### Las migraciones

Una migración es un archivo PHP que describe la estructura de una tabla. Laravel las ejecuta en orden cronológico (el timestamp del nombre de archivo marca el orden).

```bash
php artisan migrate          # aplica las pendientes
php artisan migrate:fresh    # borra todo y recrea desde cero
php artisan migrate:rollback # deshace la última migración
```

Cada migración tiene dos métodos:
- `up()` → crea/modifica la tabla
- `down()` → revierte ese cambio (para rollbacks)

### Diagrama de relaciones

```
users ──────────────────────────────────────────────────┐
  │                                                       │
  │ hasMany                                         hasMany
  ▼                                                       ▼
tickets ──── belongsTo ───► devices        device_assignments
  │                              │
  │ morphMany                    │ morphMany
  └──────────────────────────────┘
                    ▼
             activity_logs (loggable_type + loggable_id)
```

### Strings en lugar de ENUMs de DB

Las columnas de estado y tipo se definen como `string(20)`:
```php
$table->string('status', 20)->default('open');
$table->string('priority', 20)->default('medium');
```

**¿Por qué no `DB::enum`?** Porque agregar un valor a un enum de base de datos requiere una migración (ALTER TABLE), que en SQL Server es especialmente costosa y requiere downtime. Con strings, el único cambio al agregar un estado nuevo es añadir un `case` en el enum de PHP:

```php
// app/Enums/TicketStatus.php
enum TicketStatus: string {
    case OPEN        = 'open';
    case IN_PROGRESS = 'in_progress';
    case CLOSED      = 'closed';
    case RESOLVED    = 'resolved';
    // Agregar aquí. Sin migración. ✅
}
```

Eloquent convierte automáticamente entre el string de la DB y el enum de PHP mediante `casts()`:

```php
// En el modelo Ticket:
protected function casts(): array {
    return [
        'status'   => TicketStatus::class,   // 'open' ↔ TicketStatus::OPEN
        'priority' => TicketPriority::class,
    ];
}

// Al leer:
$ticket->status === TicketStatus::OPEN  // true (comparación de enums, no strings)
$ticket->status->value                  // 'open' (el string si lo necesitas)

// Al escribir (Eloquent llama ->value automáticamente):
$ticket->status = TicketStatus::IN_PROGRESS;
$ticket->save();  // guarda 'in_progress' en la DB
```

### Soft Deletes

En lugar de borrar el registro de la DB (`DELETE`), Eloquent marca una columna `deleted_at` con la fecha del borrado. Los registros borrados no aparecen en queries normales, pero el historial se preserva.

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model {
    use SoftDeletes;  // activa el comportamiento
}
```

En la migración:
```php
$table->softDeletes();  // agrega la columna deleted_at NULLABLE
```

**¿Por qué en tickets y devices?** Porque si un técnico borra un ticket accidentalmente, o un dispositivo se pierde, necesitamos preservar el historial de asignaciones e incidentes. Con soft deletes podemos restaurar con `Ticket::withTrashed()->find($id)->restore()`.

### Relación polimórfica (MorphTo) en ActivityLog

`activity_logs` puede estar asociada a cualquier modelo: un `Ticket`, un `Device`, un `DeviceAssignment`. En lugar de tener una foreign key por cada modelo, usamos dos columnas:

```
loggable_type = "App\Models\Ticket"   ← la clase del modelo
loggable_id   = 42                     ← el id del registro
```

En la migración:
```php
$table->nullableMorphs('loggable');
// Equivale a:
// $table->string('loggable_type')->nullable();
// $table->unsignedBigInteger('loggable_id')->nullable();
// + índice compuesto automático
```

En el modelo `ActivityLog`:
```php
public function loggable(): MorphTo
{
    return $this->morphTo();  // Laravel infiere las columnas del nombre del método
}
```

Cuando lo uses:
```php
$log->loggable;  // devuelve el Ticket, Device, o lo que sea, ya hidratado
```

### Factories vs Seeders

| | Factory | Seeder |
|---|---|---|
| **Qué es** | Receta para generar datos falsos de un modelo | Script que orquesta la inserción de datos |
| **Cuándo se usa** | En tests y seeders | Solo en `migrate:fresh --seed` |
| **Datos** | Aleatorios (Faker) | Pueden ser fijos o usar factories |

```php
// Factory — define cómo se ve un Device genérico:
DeviceFactory::definition() → ['name' => 'lorem ipsum', 'type' => 'laptop', ...]

// Seeder — orquesta cuántos y con qué estados:
Device::factory(15)->create();              // 15 disponibles
Device::factory(3)->assigned()->create();   // 3 asignados
```

El método `->assigned()` es un **estado** del factory — una variación sobre la definición base:
```php
public function assigned(): static
{
    return $this->state(['status' => DeviceStatus::ASSIGNED->value]);
}
```

---

## ¿Por qué este enfoque?

- **Migraciones sobre SQL manual:** versionables en Git, ejecutables en cualquier entorno, reversibles.
- **Enums de PHP sobre enums de DB:** flexibilidad sin migraciones, validación en la capa de aplicación.
- **SoftDeletes en tickets y devices:** auditoría y recuperación de errores sin perder historial.
- **MorphTo en activity_logs:** una sola tabla de logs sirve para todos los modelos actuales y futuros.
- **Factories con estados:** permite a los tests y seeders crear escenarios específicos sin repetir lógica.

---

## Verificación manual

```bash
# 1. Correr todas las migraciones + seeders desde cero
php artisan migrate:fresh --seed

# 2. Verificar en tinker que los datos existen y las relaciones funcionan
php artisan tinker

>>> Ticket::with(['user', 'device'])->first()
>>> Device::with('assignments')->where('status', 'assigned')->get()
>>> App\Models\Ticket::count()   # debería ser 30
>>> App\Models\Device::count()   # debería ser 20
```

---

## Glosario

| Término | Significado |
|---|---|
| **Migración** | Archivo PHP que describe cambios en el esquema de la DB, versionable en Git |
| **Backed Enum** | Enum de PHP donde cada case tiene un valor escalar asociado (string o int) |
| **Soft Delete** | Marcar un registro como borrado sin eliminarlo físicamente de la DB |
| **MorphTo / Polymorphic** | Relación donde una tabla puede apuntar a múltiples tipos de modelos distintos |
| **Factory** | Clase que define cómo generar datos falsos para un modelo |
| **Seeder** | Script que usa factories para poblar la DB con un escenario concreto |
| **State (factory)** | Variación de un factory que sobreescribe ciertos campos (`->assigned()`, `->closed()`) |
| **`casts()`** | Método de Eloquent que convierte automáticamente tipos entre PHP y la DB |
