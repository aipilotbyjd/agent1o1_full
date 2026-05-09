<?php

namespace App\Notifications;

use App\Models\Execution;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExecutionSucceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Execution $execution,
        private readonly array $channels = ['database'],
    ) {
        $this->onQueue(config('notifications.queue', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toArray(object $notifiable): array
    {
        $durationMs = $this->execution->duration_ms;
        $duration = $durationMs ? round($durationMs / 1000, 2) . 's' : 'N/A';

        return [
            'type'          => 'execution.succeeded',
            'title'         => 'Workflow Execution Completed',
            'body'          => "The workflow \"{$this->execution->workflow?->name}\" completed successfully in {$duration}.",
            'execution_id'  => $this->execution->id,
            'workflow_id'   => $this->execution->workflow_id,
            'workflow_name' => $this->execution->workflow?->name,
            'duration_ms'   => $durationMs,
            'icon'          => 'success',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workflowName = $this->execution->workflow?->name ?? 'Unknown Workflow';
        $workspaceSlug = $this->execution->workspace?->slug ?? '';
        $durationMs = $this->execution->duration_ms;
        $duration = $durationMs ? round($durationMs / 1000, 2) . 's' : 'N/A';

        $base = config('app.frontend_url', config('app.url'));

        return (new MailMessage)
            ->success()
            ->subject("Execution Succeeded — {$workflowName}")
            ->greeting('Hi ' . ($notifiable->name ?? 'there') . ',')
            ->line("Your workflow **{$workflowName}** completed successfully in {$duration}.")
            ->action('View Execution', "{$base}/workspaces/{$workspaceSlug}/executions/{$this->execution->id}");
    }

    public function toSlack(object $notifiable): array
    {
        $workflowName = $this->execution->workflow?->name ?? 'Unknown Workflow';

        return [
            'text'   => ":white_check_mark: Workflow *{$workflowName}* completed successfully",
            'blocks' => [[
                'type' => 'section',
                'text' => ['type' => 'mrkdwn', 'text' => ":white_check_mark: *{$workflowName}* executed successfully."],
            ]],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $workflowName = $this->execution->workflow?->name ?? 'Unknown Workflow';

        return [
            'embeds' => [[
                'title'     => '✅ Workflow Execution Succeeded',
                'color'     => 0x57F287,
                'description' => "**Workflow:** {$workflowName}",
                'timestamp' => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'execution.succeeded',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public function toSms(object $notifiable): string
    {
        $name = $this->execution->workflow?->name ?? 'Workflow';

        return "[LinkFlow] {$name} completed successfully.";
    }
}
