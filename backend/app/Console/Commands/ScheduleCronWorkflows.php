<?php

namespace App\Console\Commands;

use App\Enums\ExecutionMode;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Execution;
use App\Models\Workflow;
use App\Services\ExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * ScheduleCronWorkflows — dispatches due cron workflows with per-workflow locks.
 *
 * Runs every minute. For each workflow whose next_run_at <= now:
 *   1. Acquire an atomic lock keyed on workflow ID (prevents overlapping runs)
 *   2. Trigger an execution via ExecutionService
 *   3. Calculate and save next_run_at
 *   4. Release the lock
 *
 * The lock TTL is 90 seconds. If a previous invocation is still running
 * for a given workflow (e.g. ExecutionService::trigger is slow), the new
 * invocation skips that workflow rather than double-triggering it.
 *
 * Lock key:  cron-workflow:{id}
 * Lock TTL:  90s (longer than scheduler interval, shorter than most cron gaps)
 */
class ScheduleCronWorkflows extends Command
{
    protected $signature = 'workflows:schedule-cron';

    protected $description = 'Dispatch execution jobs for cron-triggered workflows that are due';

    public function handle(ExecutionService $executionService): int
    {
        $workflows = Workflow::query()
            ->where('is_active', true)
            ->where('trigger_type', 'cron')
            ->whereNotNull('cron_expression')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->with(['currentVersion', 'creator', 'workspace.owner'])
            ->get();

        $dispatched = 0;
        $skipped = 0;

        foreach ($workflows as $workflow) {
            if (! $workflow->current_version_id) {
                continue;
            }

            $lock = Cache::lock("cron-workflow:{$workflow->id}", 90);

            if (! $lock->get()) {
                // Previous invocation still holds the lock — skip, don't double-trigger.
                $skipped++;

                continue;
            }

            try {
                $triggeredBy = $workflow->creator ?? $workflow->workspace->owner;

                if (! $triggeredBy) {
                    $this->warn("Skipping workflow {$workflow->id}: no user to trigger as.");

                    continue;
                }

                $executionService->trigger(
                    $workflow,
                    $triggeredBy,
                    ['trigger' => 'cron', 'cron_expression' => $workflow->cron_expression],
                    ExecutionMode::Scheduled,
                );

                $nextRun = $this->calculateNextRun($workflow->cron_expression);

                $workflow->update([
                    'last_cron_run_at' => now(),
                    'next_run_at'      => $nextRun,
                ]);

                $dispatched++;
            } catch (\Throwable $e) {
                $this->error("Failed to dispatch workflow {$workflow->id}: {$e->getMessage()}");
            } finally {
                $lock->release();
            }
        }

        if ($dispatched > 0 || $skipped > 0) {
            $this->info("Dispatched {$dispatched} cron workflow(s)".($skipped > 0 ? ", skipped {$skipped} (lock held)." : '.'));
        }

        return self::SUCCESS;
    }

    private function calculateNextRun(string $cronExpression): ?\Carbon\Carbon
    {
        try {
            $cron = new \Cron\CronExpression($cronExpression);

            return \Carbon\Carbon::instance($cron->getNextRunDate());
        } catch (\Throwable) {
            return null;
        }
    }
}
