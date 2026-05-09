<?php

namespace App\Enums;

/**
 * Delivery channel types for notifications.
 *
 *  database       — In-app bell / notification centre (stored in the notifications table).
 *  mail           — Email via Laravel Mail (uses the user's primary email address).
 *  slack          — Slack incoming webhook (URL stored in notification_channels).
 *  discord        — Discord channel webhook (URL stored in notification_channels).
 *  webhook        — Generic HTTP POST to any URL (stored in notification_channels).
 *  sms            — SMS via Twilio (phone number stored in notification_channels).
 *
 * New channels can be added here without touching the notification classes —
 * just implement a new custom Laravel channel driver and register it.
 */
enum NotificationChannelType: string
{
    case Database = 'database';
    case Mail     = 'mail';
    case Slack    = 'slack';
    case Discord  = 'discord';
    case Webhook  = 'webhook';
    case Sms      = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Database => 'In-App',
            self::Mail     => 'Email',
            self::Slack    => 'Slack',
            self::Discord  => 'Discord',
            self::Webhook  => 'Webhook',
            self::Sms      => 'SMS',
        };
    }

    /**
     * Whether this channel requires a user-configured endpoint stored
     * in notification_channels (i.e., not handled automatically by Laravel).
     */
    public function requiresStoredConfig(): bool
    {
        return match ($this) {
            self::Slack, self::Discord, self::Webhook, self::Sms => true,
            default => false,
        };
    }

    /**
     * The Laravel notification channel driver name passed to via().
     */
    public function driver(): string
    {
        return match ($this) {
            self::Database => 'database',
            self::Mail     => 'mail',
            self::Slack    => \App\Notifications\Channels\SlackWebhookChannel::class,
            self::Discord  => \App\Notifications\Channels\DiscordWebhookChannel::class,
            self::Webhook  => \App\Notifications\Channels\GenericWebhookChannel::class,
            self::Sms      => \App\Notifications\Channels\SmsChannel::class,
        };
    }

    /**
     * @return list<self>
     */
    public static function userConfigurable(): array
    {
        return [
            self::Slack,
            self::Discord,
            self::Webhook,
            self::Sms,
        ];
    }
}
