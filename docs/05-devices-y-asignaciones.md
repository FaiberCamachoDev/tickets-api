# 05 — Devices y Asignaciones

## ¿Qué se construyó?

Dos endpoints para gestionar dispositivos y su asignación a usuarios:

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/devices` | Lista todos los dispositivos (paginada) |
| POST | `/api/devices/assign` | Asigna un dispositivo a un usuario |

La asignación es una operación multi-tabla que usa una **transacción de base de datos** para garantizar consistencia.

---

## ¿Dónde vive?

```
app/Http/Requests/Api/Device/AssignDeviceRequest.php ← validación
app/Services/DeviceService.php                        ← lógica + transacción
app/Http/Controllers/Api/DeviceController.php         ← orquestación
app/Http/Resources/DeviceResource.php                 ← JSON de dispositivo
app/Http/Resources/DeviceAssignmentResource.php       ← JSON de asignación
```

---

## ¿Cómo funciona?

### La transacción DB en DeviceService::assign()

Asignar un dispositivo implica **dos operaciones en la DB que deben ser atómicas**:
1. Cambiar `Device::status` de `available` → `assigned`
2. Crear una fila en `device_assignments`

Si la operación 1 falla a mitad, no queremos un `DeviceAssignment` huérfano. Si la operación 2 falla, no queremos un device marcado como `assigned` sin un registro de a quién.

```php
// DeviceService.php
return DB::transaction(function () use ($device, $assignedUser, $data) {
    $device->update(['status' => DeviceStatus::ASSIGNED]);      // Op 1

    $assignment = DeviceAssignment::create([...]);               // Op 2

    ActivityLog::create([...]);                                  // Op 3

    return $assignment->load(['device', 'user']);
});
```

Si cualquiera de las 3 operaciones lanza una excepción, `DB::transaction()` hace automáticamente `ROLLBACK` — ningún cambio queda parcialmente aplicado.

### DomainException para reglas de negocio

Antes de iniciar la transacción, validamos que el device esté disponible:

```php
if ($device->status !== DeviceStatus::AVAILABLE) {
    throw new \DomainException(
        "El dispositivo 'voluptatem officia' no está disponible (estado: assigned)"
    );
}
```

`DomainException` es una excepción de PHP estándar que usamos para **reglas de negocio** (no errores técnicos). El controller la captura y retorna 409 Conflict:

```php
// DeviceController.php
try {
    $assignment = $this->deviceService->assign($device, $assignedUser, $request->validated());
} catch (\DomainException $e) {
    return $this->error($e->getMessage(), 409);
}
```

**¿Por qué 409 Conflict?** Porque el estado del recurso (el device ya está assigned) impide completar la operación. No es un error del cliente (400) ni un 404 — el device existe pero está en conflicto con la acción solicitada.

### Enum en transición de estado

El service usa el enum `DeviceStatus` para comparar y actualizar:

```php
if ($device->status !== DeviceStatus::AVAILABLE) { ... }  // comparación de enum
$device->update(['status' => DeviceStatus::ASSIGNED]);     // Eloquent usa ->value al guardar
```

Gracias al cast en el modelo Device:
```php
'status' => DeviceStatus::class,
```
Eloquent convierte automáticamente `'assigned'` → `DeviceStatus::ASSIGNED` al leer, y `DeviceStatus::ASSIGNED` → `'assigned'` al escribir.

### `findOrFail` vs `exists` en validación

En el controller:
```php
$device = Device::findOrFail($request->device_id);
$assignedUser = User::findOrFail($request->user_id);
```

Aunque `AssignDeviceRequest` ya valida que `device_id` existe en la tabla (`'exists:devices,id'`), usamos `findOrFail` porque necesitamos el modelo para pasarlo al service. Sin `findOrFail`, haríamos dos queries (la de la validación + la de la búsqueda). Con `findOrFail`, la excepción `ModelNotFoundException` es capturada por el exception handler → 404.

---

## Verificación manual

```bash
TOKEN=<tu_token>

# Listar todos los dispositivos
curl http://localhost:8000/api/devices \
  -H "Authorization: Bearer $TOKEN"

# Asignar dispositivo id=2 a user id=1
curl -X POST http://localhost:8000/api/devices/assign \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"device_id":2,"user_id":1,"notes":"Para trabajo en campo"}'

# Intentar asignar el mismo dispositivo de nuevo → 409
curl -X POST http://localhost:8000/api/devices/assign \
  -H "Content-Type: application/json" -H "Authorization: Bearer $TOKEN" \
  -d '{"device_id":2,"user_id":3}'
```

Respuesta 409:
```json
{
  "status": "error",
  "message": "El dispositivo 'ex ab' no está disponible para asignación (estado actual: assigned)"
}
```

---

## Glosario

| Término | Significado |
|---|---|
| **`DB::transaction()`** | Envuelve operaciones en una transacción SQL: si algo falla, hace ROLLBACK automático |
| **ROLLBACK** | Deshacer todas las operaciones de una transacción fallida, dejando la DB intacta |
| **409 Conflict** | Código HTTP que indica que el estado actual del recurso impide la operación |
| **`DomainException`** | Excepción PHP para violaciones de reglas de negocio (no errores técnicos) |
| **`findOrFail`** | Busca un modelo por ID; si no existe lanza `ModelNotFoundException` → 404 automático |
| **Transición de estado** | Cambio controlado del `status` de un modelo, validando que la transición sea válida |
