<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?string $failureReason = null,
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
            'type'           => 'billing.payment_failed',
            'title'          => 'Payment Failed',
            'body'           => "A payment of {$this->amount} {$this->currency} failed." . ($this->failureReason ? " Reason: {$this->failureReason}" : ''),
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'failure_reason' => $this->failureReason,
            'icon'           => 'error',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $base = config('app.frontend_url', config('app.url'));

        $mail = (new MailMessage)
            ->error()
            ->subject('Payment Failed — Action Required')
            ->greeting('Hi ' . ($notifiable->name ?? 'there') . ',')
            ->line("We were unable to process your payment of **{$this->amount} {$this->currency}**.");

        if ($this->failureReason) {
            $mail->line("**Reason:** {$this->failureReason}");
        }

        return $mail
            ->line('Please update your payment method to keep your subscription active.')
            ->action('Update Payment Method', "{$base}/billing")
            ->line('If you believe this is an error, please contact our support team.');
    }

    public function toSlack(object $notifiable): array
    {
        return [
            'text'   => ":x: Payment of {$this->amount} {$this->currency} failed",
            'blocks' => [[
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => ":x: *Payment Failed*\nAmount: {$this->amount} {$this->currency}" . ($this->failureReason ? "\nReason: {$this->failureReason}" : ''),
                ],
            ]],
        ];
    }

    public function toDiscord(object $notifiable): array
    {
        return [
            'embeds' => [[
                'title'       => '❌ Payment Failed',
                'color'       => 0xED4245,
                'description' => "**Amount:** {$this->amount} {$this->currency}" . ($this->failureReason ? "\n**Reason:** {$this->failureReason}" : ''),
                'timestamp'   => now()->toIso8601String(),
            ]],
        ];
    }

    public function toWebhook(object $notifiable): array
    {
        return array_merge($this->toArray($notifiable), [
            'event'       => 'billing.payment_failed',
            'occurred_at' => now()->toIso8601String(),
        ]);
    }

    public function toSms(object $notifiable): string
    {
        return "[LinkFlow] Payment of {$this->amount} {$this->currency} failed. Update at linkflow.app/billing";
    }
}
