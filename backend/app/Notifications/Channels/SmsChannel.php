<?php

namespace App\Notifications\Channels;

use App\Enums\NotificationChannelType;
use App\Models\NotificationChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends SMS notifications via Twilio.
 *
 * The notification class must implement toSms(object $notifiable): string
 * returning the plain-text message body (max 160 chars for a single SMS segment).
 *
 * Required environment variables:
 *   TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM_NUMBER
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $accountSid = config('services.twilio.account_sid');
        $authToken  = config('services.twilio.auth_token');
        $from       = config('services.twilio.from');

        if (! $accountSid || ! $authToken || ! $from) {
            Log::warning('SMS channel: Twilio credentials not configured.');

            return;
        }

        $phoneNumbers = NotificationChannel::activeOfType(
            $notifiable->id,
            NotificationChannelType::Sms,
        )->get();

        if ($phoneNumbers->isEmpty()) {
            return;
        }

        $body = $notification->toSms($notifiable);

        foreach ($phoneNumbers as $channel) {
            $config = $channel->getDecryptedConfig();
            $to = $config['phone'] ?? null;

            if (! $to) {
                continue;
            }

            try {
                Http::withBasicAuth($accountSid, $authToken)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                        'To'   => $to,
                        'From' => $from,
                        'Body' => $body,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('SMS notification delivery failed.', [
                    'channel_id' => $channel->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }
}
