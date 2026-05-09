<?php

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Fired when a new user registers on the platform.
 * Useful for tracking growth and spotting spam registrations.
 */
class NewSignupAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $newUser,
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
            'type'       => 'admin.new_signup',
            'title'      => '[Admin] New User Signup',
            'body'       => "New user registered: {$this->newUser->name} ({$this->newUser->email})",
            'user_id'    => $this->newUser->id,
            'user_name'  => $this->newUser->name,
            'user_email' => $this->newUser->email,
            'icon'       => 'info',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[LinkFlow] New User Signup')
            ->greeting('Growth Alert')
            ->line("A new user just signed up:")
            ->line("**Name:** {$this->newUser->name}")
            ->line("**Email:** {$this->newUser->email}")
            ->line("**Registered:** {$this->newUser->created_at->toFormattedDateString()}")
            ->action('View in Admin', config('app.url') . '/admin/users/' . $this->newUser->id);
    }

    public function toSlack(object $notifiable): array
    {
        return [
            'text'   => ":tada: New signup: {$this->newUser->name} ({$this->newUser->email})",
            'blocks' => [[
                'type'   => 'section',
                'fields' => [
                    ['type' => 'mrkdwn', 'text' => "*Name*\n{$this->newUser->name}"],
                    ['type' => 'mrkdwn', 'text' => "*Email*\n{$this->newUser->email}"],
                ],
            ]],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => '🎉 New User Signup',
                'color'       => 0x57F287,
                'description' => "**Name:** {$this->newUser->name}\n**Email:** {$this->newUser->email}",
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'admin.new_signup',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }
}
