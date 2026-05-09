<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when the number of concurrent active executions exceeds the configured threshold.
 */
class ExecutionOverflowAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $activeCount,
        public readonly int $threshold,
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
            'type'          => 'admin.execution_overflow',
            'title'         => '[Admin] Execution Overflow',
            'body'          => "{$this->activeCount} concurrent executions detected (threshold: {$this->threshold}).",
            'active_count'  => $this->activeCount,
            'threshold'     => $this->threshold,
            'icon'          => 'error',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("[LinkFlow Admin] Execution Overflow — {$this->activeCount} active")
            ->greeting('Platform Alert')
            ->line("**{$this->activeCount} concurrent executions** are active, exceeding the threshold of {$this->threshold}.")
            ->line('This may indicate a runaway loop, a sudden traffic spike, or a misconfigured polling trigger.')
            ->line('Recommended actions:')
            ->line('• Check the Executions dashboard for stuck or looping workflows.')
            ->line('• Review polling trigger intervals.')
            ->line('• Scale queue workers if legitimate load.')
            ->action('View Executions', config('app.url') . '/admin/executions');
    }

    public function toSlack(object $notifiable): array
    {
        return [
            'text'   => ":rotating_light: [Admin] Execution overflow — {$this->activeCount} active (threshold: {$this->threshold})",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => '🚨 Execution Overflow'],
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Active Executions*\n{$this->activeCount}"],
                        ['type' => 'mrkdwn', 'text' => "*Threshold*\n{$this->threshold}"],
                    ],
                ],
            ],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => '🚨 [Admin] Execution Overflow',
                'color'       => 0xED4245,
                'description' => "**Active executions:** {$this->activeCount}\n**Threshold:** {$this->threshold}",
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'admin.execution_overflow',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}
