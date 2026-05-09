<?php

namespace App\Jobs;

use App\Models\PollingTrigger;
use App\Services\PollingTriggerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PollTriggerJob — polls a single PollingTrigger in isolation.
 *
 * ═══════════════════════════════════════════════════════════════
 * WHY THIS JOB EXISTS
 * ═══════════════════════════════════════════════════════════════
 *
 * Previously, PollTriggersCommand looped through ALL due triggers
 * sequentially in a single PHP process:
 *
 *   foreach ($triggers as $trigger) {
 *       $pollingTriggerService->poll($trigger); // 2-5s API call each
 *   }
 *
 * With 50 triggers, that's 100-250 seconds in a single process. The
 * next invocation starts before the first finishes → overlapping runs
 * → double-triggers → duplicate executions.
 *
 * Now the command dispatches one job per trigger. Each job runs
 * independently on a queue worker, so 50 triggers run in PARALLEL
 * (bounded by worker count), each taking only 2-5 seconds.
 *
 * ═══════════════════════════════════════════════════════════════
 * ATOMIC LOCK — PREVENTS DOUBLE-POLLING
 * ═══════════════════════════════════════════════════════════════
 *
 * If the scheduler fires before the previous job completes (e.g. the
 * trigger's interval is shorter than the API response time), we'd
 * dispatch a duplicate job. The atomic lock prevents this:
 *
 *   - Lock key:    polling-trigger:{id}
 *   - Lock owner:  this job's unique ID
 *   - Lock TTL:    job timeout + 30s buffer
 *
 * If the lock can't be acquired, the job releases itself immediately.
 * The previous job still holds the lock and will complete normally.
 *
 * ═══════════════════════════════════════════════════════════════
 * QUEUE: maintenance
 * ═══════════════════════════════════════════════════════════════
 * Low priority. Polling misses a beat occasionally is acceptable.
 * Never competes with webhook processing or workflow execution.
 */
class PollTriggerJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(private readonly int $pollingTriggerId)
    {
        $this->onQueue('maintenance');
    }

    public function handle(PollingTriggerService $pollingTriggerService): void
    {
        $lockKey = "polling-trigger:{$this->pollingTriggerId}";
        $lockTtl = $this->timeout + 30;

        $lock = Cache::lock($lockKey, $lockTtl);

        if (! $lock->get()) {
            Log::info("PollTriggerJob: another worker is already polling trigger {$this->pollingTriggerId}, skipping.");

            return;
        }

        try {
            $trigger = PollingTrigger::find($this->pollingTriggerId);

            if (! $trigger) {
                return;
            }

            if (! $trigger->is_active) {
                Log::info("PollTriggerJob: trigger {$this->pollingTriggerId} is inactive, skipping.");

                return;
            }

            $triggered = $pollingTriggerService->poll($trigger);

            Log::info("PollTriggerJob: trigger {$this->pollingTriggerId} polled", [
                'new_executions' => $triggered,
                'next_poll_at'   => $trigger->fresh()?->next_poll_at,
            ]);
        } finally {
            $lock->release();
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("PollTriggerJob: permanently failed for trigger {$this->pollingTriggerId}", [
            'error' => $exception->getMessage(),
        ]);

        PollingTrigger::where('id', $this->pollingTriggerId)->update([
            'last_error' => "Job failed: {$exception->getMessage()}",
        ]);
    }
}
