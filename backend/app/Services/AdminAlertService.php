<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\Admin\ExecutionOverflowAlert;
use App\Notifications\Admin\FailedJobsAlert;
use App\Notifications\Admin\HighErrorRateAlert;
use App\Notifications\Admin\NewSignupAlert;
use App\Notifications\Admin\StuckExecutionsAlert;
use App\Notifications\Admin\SuspiciousActivityAlert;
use App\Notifications\Admin\SystemHealthAlert;
use App\Notifications\Admin\WorkspaceFailureSpikeAlert;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends platform-level alerts to configured admin destinations.
 *
 * Admin alerts can be delivered to:
 *   - Admin email addresses (ADMIN_ALERT_EMAIL env var, comma-separated)
 *   - Slack workspace via incoming webhook (ADMIN_SLACK_WEBHOOK_URL)
 *   - Discord channel via incoming webhook (ADMIN_DISCORD_WEBHOOK_URL)
 *   - Generic webhook endpoint (ADMIN_WEBHOOK_URL)
 *   - Any User models that have the 'admin' role
 *
 * All methods are safe to call from anywhere — they swallow errors internally
 * and log failures without re-throwing, so they never break the primary flow.
 */
class AdminAlertService
{
    // ── Execution Alerts ────────────────────────────────────────────────────

    public function executionOverflow(int $activeCount, int $threshold): void
    {
        $this->send(new ExecutionOverflowAlert($activeCount, $threshold, $this->resolveAdminChannels()));
    }

    public function highErrorRate(
        float $errorRatePercent,
        int $failedCount,
        int $totalCount,
        int $windowMinutes = 15,
    ): void {
        $this->send(new HighErrorRateAlert(
            $errorRatePercent,
            $failedCount,
            $totalCount,
            $windowMinutes,
            $this->resolveAdminChannels(),
        ));
    }

    // ── Security Alerts ─────────────────────────────────────────────────────

    public function suspiciousActivity(
        string $activityType,
        string $description,
        array $metadata = [],
    ): void {
        $this->send(new SuspiciousActivityAlert(
            $activityType,
            $description,
            $metadata,
            $this->resolveAdminChannels(),
        ));
    }

    public function bruteForceAttempt(string $ip, string $email, int $attemptCount): void
    {
        $this->suspiciousActivity(
            activityType: 'brute_force',
            description: "Brute-force login attempt detected: {$attemptCount} failed logins for {$email}.",
            metadata: ['ip' => $ip, 'email' => $email, 'attempts' => $attemptCount],
        );
    }

    public function stuckExecutions(int $count, int $thresholdMinutes, array $examples): void
    {
        $this->send(new StuckExecutionsAlert($count, $thresholdMinutes, $examples, $this->resolveAdminChannels()));
    }

    public function failedJobs(int $failedCount, int $queueBacklog, array $examples): void
    {
        $this->send(new FailedJobsAlert($failedCount, $queueBacklog, $examples, $this->resolveAdminChannels()));
    }

    public function workspaceFailureSpike(
        string $workspaceName,
        string $workspaceSlug,
        int $failedCount,
        int $windowMinutes,
    ): void {
        $this->send(new WorkspaceFailureSpikeAlert(
            $workspaceName,
            $workspaceSlug,
            $failedCount,
            $windowMinutes,
            $this->resolveAdminChannels(),
        ));
    }

    // ── Growth Alerts ───────────────────────────────────────────────────────

    public function newSignup(User $user): void
    {
        $this->send(new NewSignupAlert($user, $this->resolveAdminChannels()));
    }

    // ── System Health Alerts ────────────────────────────────────────────────

    public function systemHealth(
        string $component,
        string $severity,
        string $message,
        array $metrics = [],
    ): void {
        $this->send(new SystemHealthAlert(
            $component,
            $severity,
            $message,
            $metrics,
            $this->resolveAdminChannels(),
        ));
    }

    public function queueWorkerDown(int $backlogDepth): void
    {
        $this->systemHealth(
            component: 'Queue Worker',
            severity: 'critical',
            message: 'Queue worker appears to be down or severely backlogged.',
            metrics: ['backlog_depth' => $backlogDepth],
        );
    }

    public function diskUsageHigh(float $percentUsed, string $path = '/'): void
    {
        $this->systemHealth(
            component: 'Disk Storage',
            severity: $percentUsed >= 95 ? 'critical' : 'warning',
            message: "Disk usage at {$percentUsed}% on {$path}.",
            metrics: ['path' => $path, 'percent_used' => "{$percentUsed}%"],
        );
    }

    // ── Delivery ────────────────────────────────────────────────────────────

    /**
     * Dispatch a notification to all configured admin destinations.
     *
     * Delivery targets:
     *   1. Admin User models (role=admin) — stored preferences respected
     *   2. Anonymous email / Slack / Discord / webhook from config
     *
     * Errors are caught and logged; this method never throws.
     */
    private function send(\Illuminate\Notifications\Notification $notification): void
    {
        try {
            // 1. Send to admin User models in the database
            $adminUsers = User::where('role', 'admin')->get();
            foreach ($adminUsers as $admin) {
                $admin->notify($notification);
            }

            // 2. Anonymous destinations from config
            $anonymous = Notification::route('mail', $this->adminEmails());

            $slackUrl   = config('notifications.admin_slack_webhook');
            $discordUrl = config('notifications.admin_discord_webhook');
            $webhookUrl = config('notifications.admin_webhook_url');

            if ($slackUrl) {
                $anonymous->route('App\Notifications\Channels\SlackWebhookChannel', $slackUrl);
            }

            if ($discordUrl) {
                $anonymous->route('App\Notifications\Channels\DiscordWebhookChannel', $discordUrl);
            }

            if ($webhookUrl) {
                $anonymous->route('App\Notifications\Channels\GenericWebhookChannel', $webhookUrl);
            }

            if ($this->adminEmails()) {
                $anonymous->notify($notification);
            }
        } catch (\Throwable $e) {
            Log::error('Admin alert delivery failed.', [
                'notification' => get_class($notification),
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Determine which channels are available for admin alerts based on config.
     *
     * @return list<string>
     */
    private function resolveAdminChannels(): array
    {
        $channels = [];

        if ($this->adminEmails()) {
            $channels[] = 'mail';
        }

        if (config('notifications.admin_slack_webhook')) {
            $channels[] = \App\Notifications\Channels\SlackWebhookChannel::class;
        }

        if (config('notifications.admin_discord_webhook')) {
            $channels[] = \App\Notifications\Channels\DiscordWebhookChannel::class;
        }

        if (config('notifications.admin_webhook_url')) {
            $channels[] = \App\Notifications\Channels\GenericWebhookChannel::class;
        }

        // Always include database for admin User models
        $channels[] = 'database';

        return $channels ?: ['database'];
    }

    /**
     * @return list<string>
     */
    private function adminEmails(): array
    {
        return (array) config('notifications.admin_emails', []);
    }
}
