<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications to any HTTP endpoint via POST.
 *
 * The notification class must implement toWebhook(object $notifiable): array
 * returning the JSON body to POST.
 *
 * If the user configured a `secret` on the channel, this driver will add an
 * HMAC-SHA256 signature header (X-LinkFlow-Signature) so the receiving endpoint
 * can verify authenticity:
 *   X-LinkFlow-Signature: sha256=<hex_digest>
 */
class GenericWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebhook')) {
            return;
        }

        $webhooks = NotificationChannel::activeOfType(
            $notifiable->id,
            NotificationChannelType::Webhook,
        )->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $notification->toWebhook($notifiable);
        $body = json_encode($payload);

        $timeout = config('notifications.webhook_timeout', 10);

        foreach ($webhooks as $channel) {
            $config = $channel->getDecryptedConfig();
            $url = $config['url'] ?? null;

            if (! $url) {
                continue;
            }

            try {
                $headers = ['Content-Type' => 'application/json'];

                if ($secret = ($config['secret'] ?? null)) {
                    $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);
                    $headers['X-LinkFlow-Signature'] = $sig;
                }

                Http::timeout($timeout)
                    ->withHeaders($headers)
                    ->post($url, $payload);
            } catch (\Throwable $e) {
                Log::warning('Webhook notification delivery failed.', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
