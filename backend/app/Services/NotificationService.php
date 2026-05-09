<?php

namespace App\Services;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;

/**
 * Central orchestrator for all user-facing notifications.
 *
 * Responsibilities:
 *   1. Resolve which channels a user wants for a given notification type.
 *   2. Instantiate the correct Notification class with the right data.
 *   3. Dispatch via Laravel's notification system (which handles queuing).
 *   4. Provide preference management helpers.
 *
 * Usage from anywhere in the app:
 *   app(NotificationService::class)->notifyExecutionFailed($execution, $error);
 *   app(NotificationService::class)->notifyQuotaWarning($workspace, $used, $total);
 */
class NotificationService
{
    // ── Execution ────────────────────────────────────────────────────────────

    public function notifyExecutionFailed(\App\Models\Execution $execution, string $errorMessage): void
    {
        $owner = $execution->triggeredBy ?? $execution->workflow?->creator;

        if (! $owner) {
            return;
        }

        $this->dispatch($owner, NotificationType::ExecutionFailed, function (array $channels) use ($execution, $errorMessage) {
            return new \App\Notifications\ExecutionFailedNotification($execution, $errorMessage, $channels);
        });
    }

    public function notifyExecutionSucceeded(\App\Models\Execution $execution): void
    {
        $owner = $execution->triggeredBy ?? $execution->workflow?->creator;

        if (! $owner) {
            return;
        }

        $this->dispatch($owner, NotificationType::ExecutionSucceeded, function (array $channels) use ($execution) {
            return new \App\Notifications\ExecutionSucceededNotification($execution, $channels);
        });
    }

    // ── Quota & Credits ──────────────────────────────────────────────────────

    /**
     * Notify all admin members of a workspace about a quota warning.
     */
    public function notifyQuotaWarning(Workspace $workspace, int $usedCredits, int $totalCredits): void
    {
        $this->notifyWorkspaceAdmins(
            $workspace,
            NotificationType::QuotaWarning,
            function (array $channels) use ($workspace, $usedCredits, $totalCredits) {
                return new \App\Notifications\QuotaWarningNotification($workspace, $usedCredits, $totalCredits, $channels);
            },
        );
    }

    public function notifyQuotaExceeded(Workspace $workspace): void
    {
        $this->notifyWorkspaceAdmins(
            $workspace,
            NotificationType::QuotaExceeded,
            fn (array $channels) => new \App\Notifications\QuotaExceededNotification($workspace, $channels),
        );
    }

    // ── Billing ──────────────────────────────────────────────────────────────

    public function notifyPaymentFailed(
        User $user,
        string $amount,
        string $currency,
        ?string $failureReason = null,
    ): void {
        $this->dispatch($user, NotificationType::PaymentFailed, function (array $channels) use ($amount, $currency, $failureReason) {
            return new \App\Notifications\PaymentFailedNotification($amount, $currency, $failureReason, $channels);
        });
    }

    public function notifySubscriptionExpiring(
        User $user,
        string $planName,
        \Carbon\Carbon $expiresAt,
        int $daysRemaining,
    ): void {
        $this->dispatch($user, NotificationType::SubscriptionExpiring, function (array $channels) use ($planName, $expiresAt, $daysRemaining) {
            return new \App\Notifications\SubscriptionExpiringNotification($planName, $expiresAt, $daysRemaining, $channels);
        });
    }

    // ── Workspace ────────────────────────────────────────────────────────────

    /**
     * Notify a user about their workspace invitation.
     * Uses the existing WorkspaceInvitationNotification for backward compat,
     * but runs it through preference resolution.
     */
    public function notifyWorkspaceInvitation(\App\Models\Invitation $invitation): void
    {
        if (! $invitation->email) {
            return;
        }

        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            // Guest invite — send directly without preference lookup
            $invitation->notify(new \App\Notifications\WorkspaceInvitationNotification($invitation));

            return;
        }

        $this->dispatch($user, NotificationType::WorkspaceInvitation, function (array $channels) use ($invitation) {
            return new \App\Notifications\WorkspaceInvitationNotification($invitation);
        });
    }

    // ── Preference Management ────────────────────────────────────────────────

    /**
     * Get all notification preferences for a user, filling in defaults for
     * any types that have no stored preference yet.
     *
     * @return array<string, NotificationPreference>  keyed by type value
     */
    public function getPreferences(User $user): array
    {
        $stored = NotificationPreference::where('user_id', $user->id)
            ->get()
            ->keyBy(fn ($p) => $p->type instanceof NotificationType ? $p->type->value : $p->type);

        $preferences = [];

        foreach (NotificationType::cases() as $type) {
            if ($type->isAdminOnly()) {
                continue;
            }

            $preferences[$type->value] = $stored[$type->value]
                ?? NotificationPreference::buildDefault($user, $type);
        }

        return $preferences;
    }

    /**
     * Upsert notification preferences for a user.
     *
     * $updates = ['execution.failed' => ['enabled' => true, 'channels' => ['mail', 'slack']]]
     *
     * @param  array<string, array{enabled: bool, channels: list<string>}>  $updates
     */
    public function updatePreferences(User $user, array $updates): void
    {
        foreach ($updates as $typeValue => $settings) {
            $type = NotificationType::tryFrom($typeValue);

            if (! $type || $type->isAdminOnly()) {
                continue;
            }

            $channels = $this->sanitizeChannels($settings['channels'] ?? []);
            $enabled  = (bool) ($settings['enabled'] ?? true);

            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'type' => $typeValue],
                ['channels' => $channels, 'enabled' => $enabled],
            );
        }
    }

    /**
     * Get the resolved channel driver names for a user + type combination.
     *
     * @return list<string>
     */
    public function resolveChannels(User $user, NotificationType $type): array
    {
        $pref = NotificationPreference::where('user_id', $user->id)
            ->where('type', $type->value)
            ->first();

        if ($pref) {
            return $pref->resolvedDrivers();
        }

        // Fall back to defaults
        return collect($type->defaultChannels())
            ->map(fn (string $ch) => NotificationChannelType::tryFrom($ch)?->driver())
            ->filter()
            ->values()
            ->all();
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Resolve channels for the user + type, then build and send the notification.
     *
     * @param  callable(list<string>): \Illuminate\Notifications\Notification  $factory
     */
    private function dispatch(User $user, NotificationType $type, callable $factory): void
    {
        try {
            $channels = $this->resolveChannels($user, $type);

            if (empty($channels)) {
                return;
            }

            $notification = $factory($channels);
            $user->notify($notification);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch notification.', [
                'user_id' => $user->id,
                'type'    => $type->value,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify all workspace owner/admin members about an event.
     *
     * @param  callable(list<string>): \Illuminate\Notifications\Notification  $factory
     */
    private function notifyWorkspaceAdmins(
        Workspace $workspace,
        NotificationType $type,
        callable $factory,
    ): void {
        $admins = $workspace->members()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();

        foreach ($admins as $admin) {
            $this->dispatch($admin, $type, $factory);
        }
    }

    /**
     * Remove any channel values that are not valid NotificationChannelType entries.
     *
     * @param  list<string>  $channels
     * @return list<string>
     */
    private function sanitizeChannels(array $channels): array
    {
        return collect($channels)
            ->filter(fn (string $ch) => NotificationChannelType::tryFrom($ch) !== null)
            ->values()
            ->all();
    }
}
