<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when suspicious activity is detected on the platform.
 *
 * Suspicious activity includes:
 *   - Brute force login attempts
 *   - Unusual API request rates from a single IP
 *   - Multiple failed auth attempts for the same account
 *   - Mass webhook triggering
 */
class SuspiciousActivityAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $activityType,
        public readonly string $description,
        public readonly array $metadata = [],
        private readonly array $channels = ['mail'],
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
            'type'          => 'admin.suspicious_activity',
            'title'         => '[Admin] Suspicious Activity Detected',
            'body'          => "[{$this->activityType}] {$this->description}",
            'activity_type' => $this->activityType,
            'description'   => $this->description,
            'metadata'      => $this->metadata,
            'icon'          => 'error',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject("[LinkFlow Admin] Suspicious Activity: {$this->activityType}")
            ->greeting('Security Alert')
            ->line("**Type:** {$this->activityType}")
            ->line("**Details:** {$this->description}");

        if (! empty($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                $mail->line("**{$key}:** {$value}");
            }
        }

        return $mail
            ->line('Please review your admin dashboard and take appropriate action if needed.')
            ->action('View Activity Log', config('app.url') . '/admin/activity');
    }

    public function toSlack(object $notifiable): array
    {
        $fields = [
            ['type' => 'mrkdwn', 'text' => "*Activity Type*\n{$this->activityType}"],
            ['type' => 'mrkdwn', 'text' => "*Details*\n{$this->description}"],
        ];

        foreach (array_slice($this->metadata, 0, 4) as $key => $value) {
            $fields[] = ['type' => 'mrkdwn', 'text' => "*{$key}*\n{$value}"];
        }

        return [
            'text'   => ":shield: [Admin] Suspicious activity: {$this->activityType}",
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => '🛡️ Suspicious Activity Detected']],
                ['type' => 'section', 'fields' => $fields],
            ],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $meta = collect($this->metadata)
            ->map(fn ($v, $k) => "**{$k}:** {$v}")
            ->implode("\n");

        return [
            'embeds' => [[
                'title'       => '🛡️ [Admin] Suspicious Activity',
                'color'       => 0xEB459E,
                'description' => "**Type:** {$this->activityType}\n**Details:** {$this->description}" . ($meta ? "\n{$meta}" : ''),
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'admin.suspicious_activity',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}
