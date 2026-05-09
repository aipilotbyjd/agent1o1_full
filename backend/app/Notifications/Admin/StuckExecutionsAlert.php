<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StuckExecutionsAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $stuckCount,
        private readonly int $thresholdMinutes,
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
            ->subject("[ADMIN] {$this->stuckCount} Stuck Execution(s) Detected")
            ->greeting('Platform Alert')
            ->line("{$this->stuckCount} execution(s) have been in `running` state for more than {$this->thresholdMinutes} minutes without progressing.");

        foreach ($this->examples as $ex) {
            $msg->line("• Execution #{$ex['id']} — Workflow: {$ex['workflow']} — Workspace: {$ex['workspace']} — Running for {$ex['minutes']} min");
        }

        return $msg
            ->line('These executions may be deadlocked or have a silent failure in a long-running node.')
            ->line('**Action:** Review the execution logs and consider cancelling them manually if they are not progressing.');
    }

    public function toSlack(object $notifiable): array
    {
        $lines = array_map(
            fn ($ex) => "• #{$ex['id']} — {$ex['workflow']} ({$ex['workspace']}) — {$ex['minutes']}min",
            $this->examples,
        );

        return [
            'text' => ":warning: *{$this->stuckCount} stuck execution(s)* — running > {$this->thresholdMinutes}min\n" . implode("\n", $lines),
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $lines = array_map(
            fn ($ex) => "• #{$ex['id']} — {$ex['workflow']} ({$ex['workspace']}) — {$ex['minutes']}min",
            $this->examples,
        );

        return [
            'embeds' => [[
                'title'       => "⚠️ {$this->stuckCount} Stuck Execution(s)",
                'description' => implode("\n", $lines),
                'color'       => 0xFFA500,
                'footer'      => ['text' => "Running for >{$this->thresholdMinutes}min without progress"],
            ]],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'              => 'admin.stuck_executions',
            'stuck_count'       => $this->stuckCount,
            'threshold_minutes' => $this->thresholdMinutes,
            'examples'          => $this->examples,
        ];
    }
}
