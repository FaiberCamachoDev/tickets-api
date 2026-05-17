# Actividad Técnica – API REST con Laravel

## Objetivo

Desarrollar una API REST usando Laravel conectada a SQL Server, implementando buenas prácticas de desarrollo backend enfocadas en:

- Diseño de endpoints HTTP
- Manejo de errores
- Rate limiting
- Responses estructuradas
- Monitoreo y observabilidad
- Integración con Discord mediante Webhooks
- Monitoreo de excepciones usando Sentry
- Contenerización usando Docker

---

# Contexto del Proyecto

La empresa necesita una API para administrar tickets relacionados con:

- Asignación de dispositivos
- Control de dispositivos
- Reporte de incidentes técnicos

Los dispositivos pueden incluir:

- PCs
- Laptops
- Dispositivos móviles
- Tablets
- Otros activos tecnológicos

La API debe permitir gestionar incidencias y asignaciones de dispositivos dentro de una organización.

---

# Funcionalidades Esperadas

La API debe permitir:

- Registrar usuarios
- Crear tickets de incidentes
- Consultar tickets
- Actualizar estados de tickets
- Eliminar tickets
- Registrar asignaciones de dispositivos
- Consultar historial de incidentes
- Registrar logs de actividad

---

# Requisitos Técnicos Obligatorios

## 1. Framework

La solución debe desarrollarse usando:

- PHP 8+
- Laravel

---

# 2. Base de Datos

Debe utilizarse:

- SQL Server

La aplicación debe incluir:

- Migraciones
- Seeders
- Relaciones entre tablas

---

# 3. Docker

El proyecto debe ejecutarse obligatoriamente usando Docker.

Debe incluir como mínimo:

- Dockerfile
- docker-compose.yml

---

# 4. Endpoints HTTP

La API debe contener mínimo los siguientes endpoints:

| Método | Endpoint | Descripción |
|---|---|---|
| POST | /api/register | Registrar usuario |
| POST | /api/login | Login |
| GET | /api/tickets | Obtener tickets |
| GET | /api/tickets/{id} | Obtener ticket |
| POST | /api/tickets | Crear ticket |
| PUT | /api/tickets/{id} | Actualizar ticket |
| DELETE | /api/tickets/{id} | Eliminar ticket |
| POST | /api/devices/assign | Asignar dispositivo |
| GET | /api/devices | Consultar dispositivos |

---

# 5. Estructura de Responses

Todos los endpoints deben responder en formato JSON estructurado.

---

# 6. Manejo de Errores

Deben implementar:

- try/catch
- Manejo de excepciones
- Respuestas HTTP correctas (HTTP codes)

## Debe evaluarse:

- Claridad de errores
- Evitar errores genéricos
- Logging de excepciones

---

# 7. Rate Limiting

Todos los endpoints deben tener protección contra abuso usando Rate Limiter de Laravel.

## Ejemplo

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(10)
        ->by($request->ip());
});
```

Aplicar middleware:

```php
Route::middleware('throttle:api')
```

---

# 8. Notificaciones Automáticas a Discord

## Escenario 1 — Error interno

Cuando ocurra una excepción o error 500:

Debe enviarse un mensaje automático a Discord notificando:

- endpoint
- método HTTP
- mensaje de error
- fecha
- IP del cliente

---

## Escenario 2 — Rate Limit excedido

Cuando un usuario exceda el límite permitido:

Debe enviarse una alerta automática a Discord indicando:

- endpoint
- IP
- timestamp
- cantidad de intentos

---

# 9. Integración Obligatoria con Sentry

El proyecto debe integrar Sentry para monitoreo y trazabilidad de errores.

Debe configurarse para:

- Capturar excepciones
- Registrar errores HTTP 500
- Registrar errores inesperados
- Evidenciar eventos enviados a Sentry

---

# 10. Webhooks Obligatorios

El proyecto debe usar Webhooks de Discord para enviar alertas automáticas relacionadas con:

- Excepciones
- Rate limiting
- Fallos críticos

---

# 11. Autenticación

Implementar autenticación usando:

- Laravel Sanctum

o

- JWT

---

# Requisitos Arquitectónicos

La solución debe estar organizada correctamente utilizando:

- Controllers
- Services
- Requests
- Models
- Migrations
- Middleware

## Importante

La lógica de negocio no debe quedar directamente dentro de los controllers.

Se espera que el código esté separado en funciones y métodos reutilizables para mantener una arquitectura limpia y fácil de mantener.

---

# Extras (Puntos Bonus)

## Bonus 1

Implementar logs personalizados.

---

## Bonus 2

Agregar dashboard básico de métricas o logs.

---

# Entregables

El estudiante debe entregar:

1. Repositorio Git
2. Archivo README.md con:
    - instrucciones de instalación
    - variables de entorno
    - colección Postman
    - instrucciones para Docker
3. Script SQL o migraciones
4. Evidencia de notificaciones en Discord
5. Evidencia de rate limit funcionando
6. Evidencia de integración funcionando con Sentry
7. Archivos Docker configurados correctamente
