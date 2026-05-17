# 07 — Discord Webhooks (Notificaciones de Alerta)

## Qué se construyó

Dos notificaciones automáticas que se envían a Discord cuando ocurren eventos críticos en la API:

- **`ServerExceptionAlert`** — se dispara en cualquier error 500 no controlado
- **`RateLimitExceededAlert`** — se dispara cuando un cliente supera el rate limit (429)

---

## Cómo funciona

### Canal personalizado (`DiscordChannel`)

Laravel permite crear canales de notificación propios. `DiscordChannel` implementa el método `send()` que:

1. Obtiene la URL del webhook desde `$notifiable->routeNotificationFor('discord')`
2. Llama a `$notification->toDiscord()` para obtener el payload
3. Hace `Http::post($url, $payload)` con timeout de 5 segundos

```
Excepción →  bootstrap/app.php  →  Notification::route('discord', url)
                                       →  DiscordChannel::send()
                                            →  HTTP POST al webhook
```

### Formato del mensaje en Discord

Los mensajes usan **embeds** de Discord:

- **500** → embed rojo con clase de excepción, mensaje, URL y archivo
- **429** → embed amarillo con URL, identificador (email o IP) y tiempo de espera

### Integración en el handler de excepciones

En `bootstrap/app.php`, los renders de 429 y 500 disparan la notificación antes de retornar la respuesta JSON, envueltos en `rescue()` para que si Discord falla no afecte la respuesta al cliente:

```php
rescue(fn () => Notification::route('discord', config('services.discord.webhook_url'))
    ->notify(new ServerExceptionAlert($e, $r)));
```

---

## Configuración

### 1. Crear un webhook en Discord

1. En tu servidor Discord: **Configuración del canal → Integraciones → Webhooks → Nuevo Webhook**
2. Copia la URL del webhook

### 2. Agregar al `.env`

```env
DISCORD_WEBHOOK_URL=https://discordapp.com/api/webhooks/TU_ID/TU_TOKEN
```

La clave en `config/services.php`:

```php
'discord' => [
    'webhook_url' => env('DISCORD_WEBHOOK_URL'),
],
```

---

## Archivos involucrados

| Archivo | Rol |
|---|---|
| `app/Channels/DiscordChannel.php` | Canal que hace el HTTP POST |
| `app/Notifications/ServerExceptionAlert.php` | Payload del embed para errores 500 |
| `app/Notifications/RateLimitExceededAlert.php` | Payload del embed para errores 429 |
| `bootstrap/app.php` | Dispara las notificaciones en los handlers |
| `config/services.php` | Llave `discord.webhook_url` |

---

## Probar manualmente

```bash
docker compose exec app php artisan tinker
```

```php
use App\Notifications\ServerExceptionAlert;
use Illuminate\Support\Facades\Notification;

$e = new \RuntimeException('Prueba manual', 500);
$r = Illuminate\Http\Request::create('/api/tickets', 'GET');

Notification::route('discord', config('services.discord.webhook_url'))
    ->notify(new ServerExceptionAlert($e, $r));
```

---

## Por qué este enfoque

- **`rescue()`** asegura que si Discord está caído, el cliente sigue recibiendo su respuesta JSON sin errores adicionales.
- **Webhook HTTP** sin OAuth ni bot: cero dependencias externas extra, sin paquetes adicionales.
- **`Http::timeout(5)`** evita que una llamada lenta a Discord bloquee el proceso.
- El canal personalizado sigue el contrato de notificaciones de Laravel, por lo que es extensible a email, Slack, etc. sin tocar los handlers.
