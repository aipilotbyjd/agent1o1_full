<?php

namespace App\Console\Commands;

use App\Models\Execution;
use App\Services\AdminAlertService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runs every 5 minutes via the scheduler.
 * Checks for platform problems and fires admin alerts automatically.
 *
 * Checks performed:
 *  1. Stuck executions (running > N minutes)
 *  2. Failed queue jobs
 *  3. High queue backlog
 *  4. Platform-wide error rate (last 15 min window)
 *  5. Too many concurrent active executions
 *  6. Disk usage
 *  7. Per-workspace failure spikes
 */
class AdminPlatformHealthCommand extends Command
{
    protected $signature   = 'admin:health-check {--force : Skip cooldown caches and always alert}';
    protected $description = 'Check platform health and send admin alerts for any problems found.';

    // How long to suppress the same alert type before firing again (minutes).
    private const COOLDOWN_MINUTES = 30;

    public function __construct(private readonly AdminAlertService $alerts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('[health-check] Starting platform health check...');
        $force = (bool) $this->option('force');

        $this->checkStuckExecutions($force);
        $this->checkFailedJobs($force);
        $this->checkErrorRate($force);
        $this->checkConcurrentExecutions($force);
        $this->checkDiskUsage($force);
        $this->checkWorkspaceFailureSpikes($force);

        $this->info('[health-check] Done.');
        return self::SUCCESS;
    }

    // ── 1. Stuck Executions ───────────────────────────────────────────────

    private function checkStuckExecutions(bool $force): void
    {
        $thresholdMinutes = (int) config('admin_alerts.stuck_execution_minutes', 30);

        $stuck = Execution::where('status', 'running')
            ->where('started_at', '<=', now()->subMinutes($thresholdMinutes))
            ->with(['workflow:id,name', 'workspace:id,name,slug'])
            ->orderBy('started_at')
            ->limit(50)
            ->get();

        if ($stuck->isEmpty()) {
            $this->line('[health-check] ✓ No stuck executions.');
            return;
        }

        $count = $stuck->count();
        $this->warn("[health-check] ✗ {$count} stuck execution(s) found.");

        if (!$force && $this->isCoolingDown('stuck_executions')) {
            $this->line('[health-check]   → Skipped (cooldown active).');
            return;
        }

        $examples = $stuck->take(5)->map(fn ($e) => [
            'id'        => $e->id,
            'workflow'  => $e->workflow?->name ?? 'Unknown',
            'workspace' => $e->workspace?->name ?? 'Unknown',
            'minutes'   => (int) $e->started_at->diffInMinutes(now()),
        ])->toArray();

        $this->alerts->stuckExecutions($count, $thresholdMinutes, $examples);
        $this->setCooldown('stuck_executions');
    }

    // ── 2. Failed Queue Jobs ──────────────────────────────────────────────

    private function checkFailedJobs(bool $force): void
    {
        $failedCount = DB::table('failed_jobs')->count();
        $backlog     = DB::table('jobs')->count();
        $maxFailed   = (int) config('admin_alerts.max_failed_jobs', 5);

        if ($failedCount < $maxFailed) {
            $this->line("[health-check] ✓ Failed jobs: {$failedCount} (threshold: {$maxFailed}).");
            return;
        }

        $this->warn("[health-check] ✗ {$failedCount} failed job(s) in the queue.");

        if (!$force && $this->isCoolingDown('failed_jobs')) {
            $this->line('[health-check]   → Skipped (cooldown active).');
            return;
        }

        $examples = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get()
            ->map(fn ($j) => [
                'job'       => class_basename(json_decode($j->payload, true)['displayName'] ?? $j->queue),
                'failed_at' => $j->failed_at,
                'error'     => substr($j->exception, 0, 120) . '...',
            ])
            ->toArray();

        $this->alerts->failedJobs($failedCount, $backlog, $examples);
        $this->setCooldown('failed_jobs');
    }

    // ── 3. Platform Error Rate ────────────────────────────────────────────

    private function checkErrorRate(bool $force): void
    {
        $windowMinutes = 15;
        $threshold     = (float) config('admin_alerts.error_rate_percent', 20);

        $total  = Execution::where('created_at', '>=', now()->subMinutes($windowMinutes))->count();
        $failed = Execution::where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->where('status', 'failed')
            ->count();

        if ($total < 10) {
            $this->line('[health-check] ✓ Error rate: insufficient data (< 10 executions).');
            return;
        }

        $rate = round(($failed / $total) * 100, 1);

        if ($rate < $threshold) {
            $this->line("[health-check] ✓ Error rate: {$rate}% (threshold: {$threshold}%).");
            return;
        }

        $this->warn("[health-check] ✗ Error rate: {$rate}% over last {$windowMinutes} minutes.");

        if (!$force && $this->isCoolingDown('error_rate')) {
            $this->line('[health-check]   → Skipped (cooldown active).');
            return;
        }

        $this->alerts->highErrorRate($rate, $failed, $total, $windowMinutes);
        $this->setCooldown('error_rate');
    }

    // ── 4. Concurrent Executions ──────────────────────────────────────────

    private function checkConcurrentExecutions(bool $force): void
    {
        $max    = (int) config('admin_alerts.max_concurrent_executions', 100);
        $active = Execution::whereIn('status', ['running', 'pending'])->count();

        if ($active < $max) {
            $this->line("[health-check] ✓ Active executions: {$active} (max: {$max}).");
            return;
        }

        $this->warn("[health-check] ✗ Concurrent executions: {$active} (threshold: {$max}).");

        if (!$force && $this->isCoolingDown('execution_overflow')) {
            $this->line('[health-check]   → Skipped (cooldown active).');
            return;
        }

        $this->alerts->executionOverflow($active, $max);
        $this->setCooldown('execution_overflow');
    }

    // ── 5. Disk Usage ─────────────────────────────────────────────────────

    private function checkDiskUsage(bool $force): void
    {
        $path    = base_path();
        $total   = disk_total_space($path);
        $free    = disk_free_space($path);

        if ($total === false || $free === false) {
            $this->line('[health-check] ✓ Disk check: unable to determine (skipped).');
            return;
        }

        $usedPercent = round((($total - $free) / $total) * 100, 1);
        $warnAt      = (float) config('admin_alerts.disk_warn_percent', 80);
        $criticalAt  = (float) config('admin_alerts.disk_critical_percent', 90);

        if ($usedPercent < $warnAt) {
            $this->line("[health-check] ✓ Disk: {$usedPercent}% used.");
            return;
        }

        $this->warn("[health-check] ✗ Disk: {$usedPercent}% used.");

        if (!$force && $this->isCoolingDown('disk_usage')) {
            $this->line('[health-check]   → Skipped (cooldown active).');
            return;
        }

        $this->alerts->diskUsageHigh($usedPercent, $path);
        $this->setCooldown('disk_usage');

        if ($usedPercent >= $criticalAt) {
            $this->alerts->systemHealth(
                component: 'Disk Storage',
                severity: 'critical',
                message: "CRITICAL: Disk at {$usedPercent}%. Platform may become unavailable soon.",
                metrics: ['percent_used' => $usedPercent, 'path' => $path],
            );
        }
    }

    // ── 6. Per-Workspace Failure Spikes ───────────────────────────────────

    private function checkWorkspaceFailureSpikes(bool $force): void
    {
        $windowMinutes  = 15;
        $spikeThreshold = (int) config('admin_alerts.workspace_failure_spike_count', 10);

        $spikes = Execution::select('workspace_id', DB::raw('COUNT(*) as fail_count'))
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->groupBy('workspace_id')
            ->havingRaw('COUNT(*) >= ?', [$spikeThreshold])
            ->with('workspace:id,name,slug')
            ->get();

        if ($spikes->isEmpty()) {
            $this->line('[health-check] ✓ No workspace failure spikes.');
            return;
        }

        foreach ($spikes as $spike) {
            $wsName = $spike->workspace?->name ?? 'Unknown';
            $wsSlug = $spike->workspace?->slug ?? (string) $spike->workspace_id;
            $count  = $spike->fail_count;

            $this->warn("[health-check] ✗ Workspace \"{$wsName}\": {$count} failures in {$windowMinutes}min.");

            $cacheKey = "workspace_failure_spike_{$spike->workspace_id}";

            if (!$force && $this->isCoolingDown($cacheKey)) {
                $this->line('[health-check]   → Skipped (cooldown active).');
                continue;
            }

            $this->alerts->workspaceFailureSpike($wsName, $wsSlug, $count, $windowMinutes);
            $this->setCooldown($cacheKey);
        }
    }

    // ── Cooldown Helpers ──────────────────────────────────────────────────

    private function isCoolingDown(string $key): bool
    {
        return Cache::has("admin_health_cooldown:{$key}");
    }

    private function setCooldown(string $key): void
    {
        Cache::put("admin_health_cooldown:{$key}", true, now()->addMinutes(self::COOLDOWN_MINUTES));
    }
}
