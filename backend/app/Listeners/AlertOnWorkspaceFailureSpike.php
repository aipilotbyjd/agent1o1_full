<?php

namespace App\Listeners;

use App\Events\ExecutionNodeFailed;
use App\Services\AdminAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fires in real-time after every execution node failure.
 * If a single workspace racks up N failures in a short window,
 * alert admin immediately (before the scheduled health check runs).
 */
class AlertOnWorkspaceFailureSpike implements ShouldQueue
{
    public string $queue = 'notifications';

    public function __construct(private readonly AdminAlertService $alerts) {}

    public function handle(ExecutionNodeFailed $event): void
    {
        $workspaceId = $event->execution->workspace_id;
        if (!$workspaceId) {
            return;
        }

        $windowMinutes  = 10;
        $spikeThreshold = (int) config('admin_alerts.realtime_spike_threshold', 5);
        $cooldownKey    = "realtime_spike_alert:{$workspaceId}";

        // Already alerted recently — skip.
        if (Cache::has($cooldownKey)) {
            return;
        }

        $recentFailures = DB::table('executions')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if ($recentFailures < $spikeThreshold) {
            return;
        }

        $workspace = $event->execution->workspace;
        $wsName    = $workspace?->name ?? 'Unknown';
        $wsSlug    = $workspace?->slug ?? (string) $workspaceId;

        $this->alerts->workspaceFailureSpike($wsName, $wsSlug, $recentFailures, $windowMinutes);

        // Suppress re-alerting for this workspace for 30 min.
        Cache::put($cooldownKey, true, now()->addMinutes(30));
    }
}
