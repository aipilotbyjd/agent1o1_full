<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $planName,
        public readonly \Carbon\Carbon $expiresAt,
        public readonly int $daysRemaining,
        private readonly array $channels = ['database', 'mail'],
    ) {
        $this->onQueue(config('notifications.queue', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'           => 'billing.subscription_expiring',
            'title'          => 'Subscription Expiring Soon',
            'body'           => "Your {$this->planName} plan expires in {$this->daysRemaining} day(s) on {$this->expiresAt->toFormattedDateString()}.",
            'plan_name'      => $this->planName,
            'expires_at'     => $this->expiresAt->toIso8601String(),
            'days_remaining' => $this->daysRemaining,
            'icon'           => 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = config('app.frontend_url', config('app.url'));

        return (new MailMessage)
            ->subject("Your subscription expires in {$this->daysRemaining} day(s)")
            ->greeting('Hi ' . ($notifiable->name ?? 'there') . ',')
            ->line("Your **{$this->planName}** subscription will expire on **{$this->expiresAt->toFormattedDateString()}** ({$this->daysRemaining} days remaining).")
            ->line('Renew now to avoid any interruption to your workflows.')
            ->action('Renew Subscription', "{$base}/billing")
            ->line('Thank you for using LinkFlow!');
    }

    public function toSlack(object $notifiable): array
    {
        return [
            'text'   => ":calendar: Subscription expires in {$this->daysRemaining} day(s)",
            'blocks' => [[
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":calendar: *Subscription Expiring*\nYour *{$this->planName}* plan expires in {$this->daysRemaining} day(s) on {$this->expiresAt->toFormattedDateString()}.",
                ],
            ]],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => '📅 Subscription Expiring Soon',
                'color'       => 0xFEE75C,
                'description' => "**Plan:** {$this->planName}\n**Expires:** {$this->expiresAt->toFormattedDateString()} ({$this->daysRemaining} days)",
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'billing.subscription_expiring',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public function toSms(object $notifiable): string
    {
        return "[LinkFlow] Your {$this->planName} subscription expires in {$this->daysRemaining} day(s). Renew: linkflow.app/billing";
    }
}
