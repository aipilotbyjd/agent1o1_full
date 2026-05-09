<?php

namespace App\Notifications;

use App\Models\Execution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExecutionFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Execution $execution,
        public readonly string $errorMessage,
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
            'type'          => 'execution.failed',
            'title'         => 'Workflow Execution Failed',
            'body'          => "The workflow \"{$this->execution->workflow?->name}\" failed: {$this->errorMessage}",
            'execution_id'  => $this->execution->id,
            'workflow_id'   => $this->execution->workflow_id,
            'workflow_name' => $this->execution->workflow?->name,
            'error'         => $this->errorMessage,
            'icon'          => 'error',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workflowName = $this->execution->workflow?->name ?? 'Unknown Workflow';
        $workspaceSlug = $this->execution->workspace?->slug ?? '';

        return (new MailMessage)
            ->error()
            ->subject("Execution Failed — {$workflowName}")
            ->greeting('Hi ' . ($notifiable->name ?? 'there') . ',')
            ->line("Your workflow **{$workflowName}** failed to complete.")
            ->line("**Error:** {$this->errorMessage}")
            ->line('Execution ID: `' . $this->execution->id . '`')
            ->action('View Execution', $this->executionUrl($workspaceSlug))
            ->line('You can review the full execution log and retry from the LinkFlow dashboard.');
    }

    public function toSlack(object $notifiable): array
    {
        $workflowName = $this->execution->workflow?->name ?? 'Unknown Workflow';

        return [
            'text'   => ":x: Workflow *{$workflowName}* failed",
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => ['type' => 'plain_text', 'text' => '❌ Workflow Execution Failed'],
                ],
                [
                    'type'   => 'section',
                    'fields' => [
                        ['type' => 'mrkdwn', 'text' => "*Workflow*\n{$workflowName}"],
                        ['type' => 'mrkdwn', 'text' => "*Error*\n{$this->errorMessage}"],
                    ],
                ],
                [
                    'type'     => 'actions',
                    'elements' => [[
                        'type'  => 'button',
                        'style' => 'danger',
                        'text'  => ['type' => 'plain_text', 'text' => 'View Execution'],
                        'url'   => $this->executionUrl($this->execution->workspace?->slug ?? ''),
                    ]],
                ],
            ],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $workflowName = $this->execution->workflow?->name ?? 'Unknown Workflow';

        return [
            'embeds' => [[
                'title'       => '❌ Workflow Execution Failed',
                'color'       => 0xED4245,
                'description' => "**Workflow:** {$workflowName}\n**Error:** {$this->errorMessage}",
                'footer'      => ['text' => 'Execution ID: ' . $this->execution->id],
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'      => 'execution.failed',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public function toSms(object $notifiable): string
    {
        $name = $this->execution->workflow?->name ?? 'A workflow';

        return "[LinkFlow] {$name} execution failed: {$this->errorMessage}";
    }

    private function executionUrl(string $workspaceSlug): string
    {
        $base = config('app.frontend_url', config('app.url'));

        return "{$base}/workspaces/{$workspaceSlug}/executions/{$this->execution->id}";
    }
}
