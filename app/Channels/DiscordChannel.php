<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class DiscordChannel
{
    public function send(mixed $notifiable, Notification $notification): void
    {
        $url = $notifiable->routeNotificationFor('discord');

        if (empty($url)) {
            return;
        }

        $payload = $notification->toDiscord($notifiable);

        Http::timeout(5)->post($url, $payload);
    }
}
