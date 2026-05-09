<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic system health alert for platform issues.
 *
 * Covers:
 *   - Queue worker down / backlog spike
 *   - Database response time degradation
 *   - Disk usage high
 *   - External API integration outages
 */
class SystemHealthAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $component,
        public readonly string $severity,
        public readonly string $message,
        public readonly array $metrics = [],
        private readonly array $channels = ['mail'],
    ) {
        $this->onQueue(config('notifications.queue', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function severityEmoji(): string
    {
        return match ($this->severity) {
            'critical' => '🔴',
            'warning'  => '🟡',
            'info'     => '🔵',
            default    => '⚪',
        };
    }

    public function severityColor(): int
    {
        return match ($this->severity) {
            'critical' => 0xED4245,
            'warning'  => 0xFEE75C,
            'info'     => 0x5865F2,
            default    => 0x99AAB5,
        };
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'admin.system_health',
            'title'     => "[Admin] System Health: {$this->component}",
            'body'      => "[{$this->severity}] {$this->component}: {$this->message}",
            'component' => $this->component,
            'severity'  => $this->severity,
            'message'   => $this->message,
            'metrics'   => $this->metrics,
            'icon'      => $this->severity === 'critical' ? 'error' : 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $emoji = $this->severityEmoji();
        $mail = (new MailMessage)
            ->error()
            ->subject("[LinkFlow Admin] {$emoji} {$this->severity}: {$this->component}")
            ->greeting('System Health Alert')
            ->line("**Component:** {$this->component}")
            ->line("**Severity:** {$this->severity}")
            ->line("**Message:** {$this->message}");

        if (! empty($this->metrics)) {
            foreach ($this->metrics as $key => $value) {
                $mail->line("**{$key}:** {$value}");
            }
        }

        return $mail->action('Admin Dashboard', config('app.url') . '/admin');
    }

    public function toSlack(object $notifiable): array
    {
        $emoji = $this->severityEmoji();
        $fields = [
            ['type' => 'mrkdwn', 'text' => "*Component*\n{$this->component}"],
            ['type' => 'mrkdwn', 'text' => "*Severity*\n{$this->severity}"],
            ['type' => 'mrkdwn', 'text' => "*Message*\n{$this->message}"],
        ];

        foreach (array_slice($this->metrics, 0, 3) as $key => $value) {
            $fields[] = ['type' => 'mrkdwn', 'text' => "*{$key}*\n{$value}"];
        }

        return [
            'text'   => "{$emoji} [Admin] {$this->severity}: {$this->component} — {$this->message}",
            'blocks' => [
                ['type' => 'header', 'text' => ['type' => 'plain_text', 'text' => "{$emoji} System Health Alert"]],
                ['type' => 'section', 'fields' => $fields],
            ],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $emoji = $this->severityEmoji();
        $meta = collect($this->metrics)->map(fn ($v, $k) => "**{$k}:** {$v}")->implode("\n");

        return [
            'embeds' => [[
                'title'       => "{$emoji} [Admin] System Health: {$this->component}",
                'color'       => $this->severityColor(),
                'description' => "**Severity:** {$this->severity}\n**Message:** {$this->message}" . ($meta ? "\n{$meta}" : ''),
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'admin.system_health',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}
