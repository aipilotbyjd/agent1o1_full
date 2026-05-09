<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotaExceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Workspace $workspace,
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
            'type'           => 'quota.exceeded',
            'title'          => 'Credit Quota Exceeded — Executions Paused',
            'body'           => "Your workspace \"{$this->workspace->name}\" has run out of credits. All workflow executions are paused.",
            'workspace_id'   => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'icon'           => 'error',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = config('app.frontend_url', config('app.url'));

        return (new MailMessage)
            ->error()
            ->subject("🚨 Credits exhausted — {$this->workspace->name}")
            ->greeting('Action required, ' . ($notifiable->name ?? 'there') . '!')
            ->line("Your workspace **{$this->workspace->name}** has run out of credits.")
            ->line('**All workflow executions are now paused** until you add more credits.')
            ->action('Top Up Credits Now', "{$base}/billing")
            ->line('Workflows will resume automatically once credits are added.');
    }

    public function toSlack(object $notifiable): array
    {
        return [
            'text'   => ":rotating_light: Credits exhausted — *{$this->workspace->name}* — executions paused",
            'blocks' => [[
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":rotating_light: *Credits Exhausted*\nWorkspace *{$this->workspace->name}* is out of credits. All executions are paused.",
                ],
            ]],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => '🚨 Credits Exhausted — Executions Paused',
                'color'       => 0xED4245,
                'description' => "**Workspace:** {$this->workspace->name}\nAll workflow executions are paused. Please top up credits.",
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'quota.exceeded',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public function toSms(object $notifiable): string
    {
        return "[LinkFlow] URGENT: {$this->workspace->name} credits exhausted. Executions paused. Top up: linkflow.app/billing";
    }
}
