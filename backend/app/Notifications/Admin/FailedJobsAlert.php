<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FailedJobsAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $failedCount,
        private readonly int $queueBacklog,
        private readonly array $examples,
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
        $msg = (new MailMessage)
            ->error()
            ->subject("[ADMIN] {$this->failedCount} Failed Job(s) in Queue")
            ->greeting('Platform Alert')
            ->line("{$this->failedCount} background job(s) have permanently failed (exhausted all retries). Queue backlog: {$this->queueBacklog} pending jobs.");

        foreach ($this->examples as $ex) {
            $msg->line("• {$ex['job']} — failed at {$ex['failed_at']} — {$ex['error']}");
        }

        return $msg
            ->line('Failed jobs stop background processing for things like sending emails, running executions, and delivering webhooks.')
            ->line('**Action:** Run `php artisan queue:retry all` to retry, or inspect via `php artisan queue:failed`.');
    }

    public function toSlack(object $notifiable): array
    {
        $lines = array_map(
            fn ($ex) => "• {$ex['job']} — {$ex['error']}",
            $this->examples,
        );

        return [
            'text' => ":red_circle: *{$this->failedCount} failed job(s)* in the queue (backlog: {$this->queueBacklog})\n" . implode("\n", $lines),
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $lines = array_map(
            fn ($ex) => "• `{$ex['job']}` — {$ex['error']}",
            $this->examples,
        );

        return [
            'embeds' => [[
                'title'       => "🔴 {$this->failedCount} Failed Queue Job(s)",
                'description' => implode("\n", $lines),
                'color'       => 0xFF0000,
                'footer'      => ['text' => "Queue backlog: {$this->queueBacklog} pending jobs"],
            ]],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'admin.failed_jobs',
            'failed_count'  => $this->failedCount,
            'queue_backlog' => $this->queueBacklog,
            'examples'      => $this->examples,
        ];
    }
}
