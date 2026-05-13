<?php

namespace App\Jobs;

use App\Models\Trigger;
use App\Services\TriggerExecutionService;
use App\Services\TriggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lock;
use Illuminate\Support\Facades\Log;

class PollTriggersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(TriggerService $triggerService, TriggerExecutionService $executionService): void
    {
        Log::info('PollTriggersJob: Starting poll check');

        // Get triggers that are due for polling
        $triggers = $triggerService->getPollingDueTriggers();

        if ($triggers->isEmpty()) {
            Log::info('PollTriggersJob: No triggers due for polling');
            return;
        }

        Log::info('PollTriggersJob: Found triggers to poll', ['count' => $triggers->count()]);

        foreach ($triggers as $trigger) {
            $this->pollTrigger($trigger, $triggerService, $executionService);
        }

        Log::info('PollTriggersJob: Completed poll check');
    }

    /**
     * Poll a single trigger with distributed locking
     */
    private function pollTrigger(
        Trigger $trigger,
        TriggerService $triggerService,
        TriggerExecutionService $executionService
    ): void {
        // Use atomic lock to prevent multiple concurrent polls of same trigger
        $lockKey = "trigger-poll:{$trigger->id}";

        if (!Lock::get($lockKey, 30)) {
            Log::warning('PollTriggersJob: Could not acquire lock', ['trigger_id' => $trigger->id]);
            return;
        }

        try {
            Log::debug('PollTriggersJob: Polling trigger', ['trigger_id' => $trigger->id]);

            $executionService->handlePollingCheck($trigger);

            Log::info('PollTriggersJob: Trigger polled successfully', ['trigger_id' => $trigger->id]);
        } catch (\Exception $e) {
            Log::error('PollTriggersJob: Polling failed', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            Lock::forceRelease($lockKey);
        }
    }
}
