<?php

namespace App\Notifications;

use App\Channels\DiscordChannel;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class RateLimitExceededAlert extends Notification
{
    public function __construct(
        private readonly TooManyRequestsHttpException $exception,
        private readonly Request $request,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(mixed $notifiable): array
    {
        $retryAfter = $this->exception->getHeaders()['Retry-After'] ?? 'N/A';
        $identifier = $this->request->user()?->email ?? $this->request->ip();

        return [
            'username' => config('app.name').' Bot',
            'embeds'   => [[
                'title'       => '⚠️ Rate Limit Excedido (429)',
                'color'       => 16776960, // yellow
                'description' => 'Un cliente alcanzó el límite de solicitudes.',
                'fields'      => [
                    ['name' => 'URL',         'value' => $this->request->method().' '.$this->request->path(), 'inline' => true],
                    ['name' => 'Identificador','value' => $identifier,                                         'inline' => true],
                    ['name' => 'Retry-After', 'value' => $retryAfter.'s',                                      'inline' => true],
                ],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }
}
