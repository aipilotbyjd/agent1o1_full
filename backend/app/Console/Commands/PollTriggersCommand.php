<?php

namespace App\Console\Commands;

use App\Jobs\PollTriggerJob;
use App\Models\PollingTrigger;
use Illuminate\Console\Command;

/**
 * PollTriggersCommand — finds due polling triggers and dispatches jobs.
 *
 * This command runs every minute via the scheduler. Its only job is to
 * find triggers that are due (next_poll_at <= now) and dispatch one
 * PollTriggerJob per trigger. It does NOT poll anything inline.
 *
 * Previously this command looped through all triggers synchronously,
 * making sequential API calls. With 50 triggers at 2s each = 100 seconds
 * of blocking work in a single process, causing overlapping runs.
 *
 * Now each trigger gets its own queue job:
 *   - 50 triggers → 50 jobs dispatched in < 100ms
 *   - Workers run them in parallel (bounded by worker count)
 *   - Each job acquires an atomic lock to prevent double-polling
 *   - Slow API responses never affect other triggers
 *
 * This command itself is fast and stateless — safe to run every minute.
 */
class PollTriggersCommand extends Command
{
    protected $signature = 'workflows:poll';

    protected $description = 'Dispatch polling jobs for all active polling triggers whose interval has elapsed';

    public function handle(): int
    {
        $triggers = PollingTrigger::where('is_active', true)
            ->where('next_poll_at', '<=', now())
            ->pluck('id');

        if ($triggers->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($triggers as $triggerId) {
            PollTriggerJob::dispatch($triggerId);
        }

        $this->info("Dispatched {$triggers->count()} polling job(s).");

        return self::SUCCESS;
    }
}
