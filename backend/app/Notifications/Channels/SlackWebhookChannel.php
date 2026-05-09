<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends notifications to one or more Slack channels via incoming webhooks.
 *
 * The notification class must implement toSlack(object $notifiable): array
 * returning a Slack Block Kit payload array. At minimum:
 *   ['text' => '...', 'blocks' => [...]]
 *
 * All Slack webhooks configured by the notifiable user will receive the message.
 */
class SlackWebhookChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSlack')) {
            return;
        }

        $webhooks = NotificationChannel::activeOfType(
            $notifiable->id,
            NotificationChannelType::Slack,
        )->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $payload = $notification->toSlack($notifiable);

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
                Log::warning('Slack notification delivery failed.', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
