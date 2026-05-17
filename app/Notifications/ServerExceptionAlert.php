<?php

namespace App\Notifications;

use App\Channels\DiscordChannel;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;

class ServerExceptionAlert extends Notification
{
    public function __construct(
        private readonly \Throwable $exception,
        private readonly Request $request,
    ) {}

    public function via(mixed $notifiable): array
    {
        return [DiscordChannel::class];
    }

    public function toDiscord(mixed $notifiable): array
    {
        $message = mb_strimwidth($this->exception->getMessage(), 0, 1024, '…');
        $file    = str_replace(base_path().'/', '', $this->exception->getFile());

        return [
            'username' => config('app.name').' Bot',
            'embeds'   => [[
                'title'       => '🚨 Error 500 — Internal Server Error',
                'color'       => 15158332, // red
                'description' => sprintf('`%s`', get_class($this->exception)),
                'fields'      => [
                    ['name' => 'URL',     'value' => $this->request->method().' '.$this->request->path(), 'inline' => true],
                    ['name' => 'IP',      'value' => $this->request->ip(),                                 'inline' => true],
                    ['name' => 'Mensaje', 'value' => $message,                                             'inline' => false],
                    ['name' => 'Archivo', 'value' => $file.' :'.$this->exception->getLine(),              'inline' => false],
                ],
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }
}
