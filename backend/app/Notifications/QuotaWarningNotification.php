<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuotaWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Workspace $workspace,
        public readonly int $usedCredits,
        public readonly int $totalCredits,
        private readonly array $channels = ['database', 'mail'],
    ) {
        $this->onQueue(config('notifications.queue', 'notifications'));
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function percentUsed(): int
    {
        if ($this->totalCredits === 0) {
            return 100;
        }

        return (int) round(($this->usedCredits / $this->totalCredits) * 100);
    }

    public function toArray(object $notifiable): array
    {
        $pct = $this->percentUsed();

        return [
            'type'           => 'quota.warning',
            'title'          => 'Credit Quota Warning',
            'body'           => "You've used {$pct}% of your credits in the \"{$this->workspace->name}\" workspace.",
            'workspace_id'   => $this->workspace->id,
            'workspace_name' => $this->workspace->name,
            'used_credits'   => $this->usedCredits,
            'total_credits'  => $this->totalCredits,
            'percent_used'   => $pct,
            'icon'           => 'warning',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pct = $this->percentUsed();
        $remaining = $this->totalCredits - $this->usedCredits;
        $base = config('app.frontend_url', config('app.url'));

        return (new MailMessage)
            ->subject("⚠️ {$pct}% of credits used — {$this->workspace->name}")
            ->greeting('Heads up, ' . ($notifiable->name ?? 'there') . '!')
            ->line("Your workspace **{$this->workspace->name}** has used **{$pct}%** of its credits.")
            ->line("Remaining: **{$remaining} credits** out of {$this->totalCredits}.")
            ->line('Top up your credits before they run out to keep your workflows running smoothly.')
            ->action('Buy More Credits', "{$base}/billing")
            ->line('You will receive another alert when credits are fully exhausted.');
    }

    public function toSlack(object $notifiable): array
    {
        $pct = $this->percentUsed();

        return [
            'text'   => ":warning: Credit quota at {$pct}% for workspace *{$this->workspace->name}*",
            'blocks' => [[
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":warning: *Credit Quota Warning*\nWorkspace *{$this->workspace->name}* has used {$pct}% of its credits. Remaining: " . ($this->totalCredits - $this->usedCredits) . '.',
                ],
            ]],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        $pct = $this->percentUsed();

        return [
            'embeds' => [[
                'title'       => '⚠️ Credit Quota Warning',
                'color'       => 0xFEE75C,
                'description' => "**Workspace:** {$this->workspace->name}\n**Used:** {$pct}%\n**Remaining:** " . ($this->totalCredits - $this->usedCredits) . ' credits',
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'quota.warning',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public function toSms(object $notifiable): string
    {
        $pct = $this->percentUsed();

        return "[LinkFlow] Warning: {$pct}% of credits used in {$this->workspace->name}. Top up at linkflow.app/billing";
    }
}
