<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceFailureSpikeAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $workspaceName,
        private readonly string $workspaceSlug,
        private readonly int $failedCount,
        private readonly int $windowMinutes,
        private readonly array $channels,
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = config('app.frontend_url', config('app.url'));

        return (new MailMessage)
            ->error()
            ->subject("[ADMIN] Workspace \"{$this->workspaceName}\" — {$this->failedCount} Failures in {$this->windowMinutes} min")
            ->greeting('Platform Alert')
            ->line("Workspace **{$this->workspaceName}** has had {$this->failedCount} failed executions in the last {$this->windowMinutes} minutes.")
            ->line('This may indicate a misconfigured workflow, an external service outage, or a bug in a recently edited workflow.')
            ->action('View Workspace', "{$base}/admin/workspaces/{$this->workspaceSlug}");
    }

    public function toSlack(object $notifiable): array
    {
        return [
            'text' => ":rotating_light: *Workspace failure spike* — _{$this->workspaceName}_ had {$this->failedCount} failed executions in {$this->windowMinutes}min",
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => "🚨 Failure Spike — {$this->workspaceName}",
                'description' => "{$this->failedCount} failed executions in the last {$this->windowMinutes} minutes.",
                'color'       => 0xFF4444,
                'footer'      => ['text' => "Workspace: {$this->workspaceSlug}"],
            ]],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'            => 'admin.workspace_failure_spike',
            'workspace_name'  => $this->workspaceName,
            'workspace_slug'  => $this->workspaceSlug,
            'failed_count'    => $this->failedCount,
            'window_minutes'  => $this->windowMinutes,
        ];
    }
}
