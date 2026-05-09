<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications to Discord channels via incoming webhooks.
 *
 * The notification class must implement toDiscord(object $notifiable): array
 * returning a Discord webhook payload. Example:
 *   ['content' => '...', 'embeds' => [...]]
 *
 * Discord embed color codes:
 *   Red    (failure) : 0xED4245
 *   Green  (success) : 0x57F287
 *   Yellow (warning) : 0xFEE75C
 *   Blue   (info)    : 0x5865F2
 */
class DiscordWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toDiscord')) {
            return;
        }

        $webhooks = NotificationChannel::activeOfType(
            $notifiable->id,
            NotificationChannelType::Discord,
        )->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $notification->toDiscord($notifiable);

        $timeout = config('notifications.webhook_timeout', 10);

        foreach ($webhooks as $channel) {
            $config = $channel->getDecryptedConfig();
            $url = $config['url'] ?? null;

            if (! $url) {
                continue;
            }

            try {
                Http::timeout($timeout)->post($url, $payload);
            } catch (\Throwable $e) {
                Log::warning('Discord notification delivery failed.', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
