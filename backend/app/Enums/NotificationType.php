<?php

namespace App\Enums;

/**
 * All notification types in the system.
 *
 * Grouped into:
 *   USER  — events triggered by a user's own workflow activity or billing state.
 *   ADMIN — system-level events that platform administrators need to know about.
 *
 * The string values are stable identifiers used as keys in notification_preferences
 * and persisted in the notifications table. Never rename them without a migration.
 */
enum NotificationType: string
{
    // ── User: Execution ──────────────────────────────────────────────────
    case ExecutionFailed      = 'execution.failed';
    case ExecutionSucceeded   = 'execution.succeeded';
    case ExecutionRetrying    = 'execution.retrying';
    case ExecutionTimedOut    = 'execution.timed_out';

    // ── User: Quota & Credits ────────────────────────────────────────────
    case QuotaWarning         = 'quota.warning';      // at 80% credit usage
    case QuotaExceeded        = 'quota.exceeded';     // at 100% — executions paused

    // ── User: Workspace ──────────────────────────────────────────────────
    case WorkspaceInvitation  = 'workspace.invitation';
    case MemberJoined         = 'workspace.member_joined';
    case MemberRemoved        = 'workspace.member_removed';

    // ── User: Billing ────────────────────────────────────────────────────
    case PaymentFailed        = 'billing.payment_failed';
    case PaymentSucceeded     = 'billing.payment_succeeded';
    case SubscriptionExpiring = 'billing.subscription_expiring';
    case SubscriptionCancelled = 'billing.subscription_cancelled';
    case TrialEnding          = 'billing.trial_ending';

    // ── Admin: Execution Health ──────────────────────────────────────────
    case AdminExecutionOverflow    = 'admin.execution_overflow';
    case AdminHighErrorRate        = 'admin.high_error_rate';
    case AdminExecutionQueueBacklog = 'admin.execution_queue_backlog';

    // ── Admin: Security & Activity ───────────────────────────────────────
    case AdminSuspiciousActivity  = 'admin.suspicious_activity';
    case AdminBruteForceAttempt   = 'admin.brute_force_attempt';

    // ── Admin: Platform ──────────────────────────────────────────────────
    case AdminNewSignup           = 'admin.new_signup';
    case AdminSystemHealth        = 'admin.system_health';
    case AdminDiskUsageHigh       = 'admin.disk_usage_high';
    case AdminQueueWorkerDown     = 'admin.queue_worker_down';

    /**
     * Human-readable label for this notification type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ExecutionFailed        => 'Execution Failed',
            self::ExecutionSucceeded     => 'Execution Succeeded',
            self::ExecutionRetrying      => 'Execution Retrying',
            self::ExecutionTimedOut      => 'Execution Timed Out',
            self::QuotaWarning           => 'Credit Quota Warning',
            self::QuotaExceeded          => 'Credit Quota Exceeded',
            self::WorkspaceInvitation    => 'Workspace Invitation',
            self::MemberJoined           => 'Member Joined Workspace',
            self::MemberRemoved          => 'Member Removed from Workspace',
            self::PaymentFailed          => 'Payment Failed',
            self::PaymentSucceeded       => 'Payment Succeeded',
            self::SubscriptionExpiring   => 'Subscription Expiring Soon',
            self::SubscriptionCancelled  => 'Subscription Cancelled',
            self::TrialEnding            => 'Trial Period Ending',
            self::AdminExecutionOverflow      => '[Admin] Execution Overflow',
            self::AdminHighErrorRate          => '[Admin] High Error Rate',
            self::AdminExecutionQueueBacklog  => '[Admin] Execution Queue Backlog',
            self::AdminSuspiciousActivity     => '[Admin] Suspicious Activity',
            self::AdminBruteForceAttempt      => '[Admin] Brute Force Attempt',
            self::AdminNewSignup              => '[Admin] New User Signup',
            self::AdminSystemHealth           => '[Admin] System Health Alert',
            self::AdminDiskUsageHigh          => '[Admin] Disk Usage High',
            self::AdminQueueWorkerDown        => '[Admin] Queue Worker Down',
        };
    }

    /**
     * Whether this notification is only for platform administrators.
     */
    public function isAdminOnly(): bool
    {
        return str_starts_with($this->value, 'admin.');
    }

    /**
     * Default channels for this notification type if the user has no saved preference.
     *
     * @return list<string>
     */
    public function defaultChannels(): array
    {
        return match ($this) {
            self::ExecutionFailed,
            self::QuotaExceeded,
            self::PaymentFailed          => ['database', 'mail'],
            self::ExecutionSucceeded,
            self::ExecutionRetrying,
            self::ExecutionTimedOut      => ['database'],
            self::QuotaWarning           => ['database', 'mail'],
            self::WorkspaceInvitation    => ['database', 'mail'],
            self::MemberJoined,
            self::MemberRemoved          => ['database'],
            self::PaymentSucceeded       => ['database', 'mail'],
            self::SubscriptionExpiring,
            self::TrialEnding            => ['database', 'mail'],
            self::SubscriptionCancelled  => ['database', 'mail'],
            default                      => ['mail'],
        };
    }

    /**
     * All user-facing (non-admin) types, grouped for preference UI rendering.
     *
     * @return array<string, list<self>>
     */
    public static function userGroups(): array
    {
        return [
            'Executions' => [
                self::ExecutionFailed,
                self::ExecutionSucceeded,
                self::ExecutionRetrying,
                self::ExecutionTimedOut,
            ],
            'Credits & Quota' => [
                self::QuotaWarning,
                self::QuotaExceeded,
            ],
            'Workspace' => [
                self::WorkspaceInvitation,
                self::MemberJoined,
                self::MemberRemoved,
            ],
            'Billing' => [
                self::PaymentFailed,
                self::PaymentSucceeded,
                self::SubscriptionExpiring,
                self::SubscriptionCancelled,
                self::TrialEnding,
            ],
        ];
    }
}
