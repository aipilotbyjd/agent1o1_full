<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when the execution error rate exceeds the configured threshold
 * within a sliding time window.
 */
class HighErrorRateAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly float $errorRatePercent,
        public readonly int $failedCount,
        public readonly int $totalCount,
        public readonly int $windowMinutes,
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
            'type'               => 'admin.high_error_rate',
            'title'              => '[Admin] High Execution Error Rate',
            'body'               => "Error rate is {$this->errorRatePercent}% ({$this->failedCount}/{$this->totalCount}) in the last {$this->windowMinutes} minutes.",
            'error_rate_percent' => $this->errorRatePercent,
            'failed_count'       => $this->failedCount,
            'total_count'        => $this->totalCount,
            'window_minutes'     => $this->windowMinutes,
            'icon'               => 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("[LinkFlow Admin] High Error Rate — {$this->errorRatePercent}%")
            ->greeting('Platform Alert')
            ->line("Execution error rate has risen to **{$this->errorRatePercent}%** in the last {$this->windowMinutes} minutes.")
            ->line("{$this->failedCount} out of {$this->totalCount} executions failed.")
            ->line('This may indicate a platform issue, a broken external API, or a user workflow problem.')
            ->action('View Failed Executions', config('app.url') . '/admin/executions?status=failed');
    }

    public function toSlack(object $notifiable): array
    {
        return [
            'text'   => ":warning: [Admin] Error rate spike — {$this->errorRatePercent}% ({$this->failedCount}/{$this->totalCount}) in {$this->windowMinutes}m",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => '⚠️ High Execution Error Rate'],
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Error Rate*\n{$this->errorRatePercent}%"],
                        ['type' => 'mrkdwn', 'text' => "*Failed / Total*\n{$this->failedCount} / {$this->totalCount}"],
                        ['type' => 'mrkdwn', 'text' => "*Window*\nLast {$this->windowMinutes} min"],
                    ],
                ],
            ],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => '⚠️ [Admin] High Execution Error Rate',
                'color'       => 0xFEE75C,
                'description' => "**Error Rate:** {$this->errorRatePercent}%\n**Failed:** {$this->failedCount} / {$this->totalCount}\n**Window:** Last {$this->windowMinutes} minutes",
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'admin.high_error_rate',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}
