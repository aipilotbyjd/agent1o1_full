<?php

namespace App\Engine\Nodes\Apps\Communication;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Mail;

/**
 * Email Node
 * 
 * Send and receive emails via SMTP/IMAP.
 */
class EmailNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'EMAIL_ERROR';
    }

    protected function operations(): array
    {
        return [
            'send' => $this->send(...),
            'send_bulk' => $this->sendBulk(...),
        ];
    }

    /**
     * Send single email
     */
    private function send(NodeInput $payload): array
    {
        $to = $payload->config['to'] ?? '';
        $subject = $payload->config['subject'] ?? '';
        $body = $payload->config['body'] ?? '';
        $from = $payload->config['from'] ?? config('mail.from.address');
        $fromName = $payload->config['from_name'] ?? config('mail.from.name');
        $cc = $payload->config['cc'] ?? [];
        $bcc = $payload->config['bcc'] ?? [];
        $replyTo = $payload->config['reply_to'] ?? null;
        $isHtml = (bool) ($payload->config['is_html'] ?? true);
        $attachments = $payload->config['attachments'] ?? [];

        // Validate
        if (empty($to)) {
            throw new \InvalidArgumentException('Recipient email is required');
        }

        if (empty($subject)) {
            throw new \InvalidArgumentException('Subject is required');
        }

        if (empty($body)) {
            throw new \InvalidArgumentException('Email body is required');
        }

        // Validate email format
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid recipient email format');
        }

        try {
            Mail::send([], [], function ($message) use (
                $to,
                $subject,
                $body,
                $from,
                $fromName,
                $cc,
                $bcc,
                $replyTo,
                $isHtml,
                $attachments
            ) {
                $message->to($to)
                    ->subject($subject)
                    ->from($from, $fromName);

                if ($isHtml) {
                    $message->html($body);
                } else {
                    $message->text($body);
                }

                // CC
                if (! empty($cc)) {
                    $ccList = is_array($cc) ? $cc : [$cc];
                    foreach ($ccList as $ccEmail) {
                        if (filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
                            $message->cc($ccEmail);
                        }
                    }
                }

                // BCC
                if (! empty($bcc)) {
                    $bccList = is_array($bcc) ? $bcc : [$bcc];
                    foreach ($bccList as $bccEmail) {
                        if (filter_var($bccEmail, FILTER_VALIDATE_EMAIL)) {
                            $message->bcc($bccEmail);
                        }
                    }
                }

                // Reply-To
                if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                    $message->replyTo($replyTo);
                }

                // Attachments
                if (! empty($attachments) && is_array($attachments)) {
                    foreach ($attachments as $attachment) {
                        if (isset($attachment['path']) && file_exists($attachment['path'])) {
                            $message->attach(
                                $attachment['path'],
                                [
                                    'as' => $attachment['name'] ?? basename($attachment['path']),
                                    'mime' => $attachment['mime'] ?? null,
                                ]
                            );
                        }
                    }
                }
            });

            return [
                'success' => true,
                'to' => $to,
                'subject' => $subject,
                'sent_at' => now()->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to send email: '.$e->getMessage());
        }
    }

    /**
     * Send bulk emails
     */
    private function sendBulk(NodeInput $payload): array
    {
        $recipients = $payload->config['recipients'] ?? [];
        $subject = $payload->config['subject'] ?? '';
        $body = $payload->config['body'] ?? '';
        $from = $payload->config['from'] ?? config('mail.from.address');
        $fromName = $payload->config['from_name'] ?? config('mail.from.name');
        $isHtml = (bool) ($payload->config['is_html'] ?? true);
        $delayMs = (int) ($payload->config['delay_ms'] ?? 100); // Rate limiting

        if (empty($recipients) || ! is_array($recipients)) {
            throw new \InvalidArgumentException('Recipients array is required');
        }

        if (empty($subject)) {
            throw new \InvalidArgumentException('Subject is required');
        }

        if (empty($body)) {
            throw new \InvalidArgumentException('Email body is required');
        }

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($recipients as $index => $recipient) {
            $email = is_array($recipient) ? ($recipient['email'] ?? '') : $recipient;

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                $errors[] = "Invalid email at index {$index}: {$email}";
                continue;
            }

            try {
                // Personalize body if variables provided
                $personalizedBody = $body;
                if (is_array($recipient) && isset($recipient['variables'])) {
                    foreach ($recipient['variables'] as $key => $value) {
                        $personalizedBody = str_replace("{{{$key}}}", $value, $personalizedBody);
                    }
                }

                Mail::send([], [], function ($message) use (
                    $email,
                    $subject,
                    $personalizedBody,
                    $from,
                    $fromName,
                    $isHtml
                ) {
                    $message->to($email)
                        ->subject($subject)
                        ->from($from, $fromName);

                    if ($isHtml) {
                        $message->html($personalizedBody);
                    } else {
                        $message->text($personalizedBody);
                    }
                });

                $sent++;

                // Rate limiting delay
                if ($delayMs > 0 && $index < count($recipients) - 1) {
                    usleep($delayMs * 1000);
                }
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Failed to send to {$email}: {$e->getMessage()}";
            }
        }

        return [
            'total' => count($recipients),
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
            'success_rate' => count($recipients) > 0 ? round(($sent / count($recipients)) * 100, 2) : 0,
        ];
    }
}
