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

class CheckScheduledTriggersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(TriggerService $triggerService, TriggerExecutionService $executionService): void
    {
        Log::info('CheckScheduledTriggersJob: Checking scheduled triggers');

        // Get triggers that are due
        $triggers = $triggerService->getScheduledDueTriggers();

        if ($triggers->isEmpty()) {
            Log::debug('CheckScheduledTriggersJob: No scheduled triggers due');
            return;
        }

        Log::info('CheckScheduledTriggersJob: Found scheduled triggers to run', ['count' => $triggers->count()]);

        foreach ($triggers as $trigger) {
            $this->executeScheduledTrigger($trigger, $executionService);
        }

        Log::info('CheckScheduledTriggersJob: Completed check');
    }

    /**
     * Execute a scheduled trigger with distributed locking
     */
    private function executeScheduledTrigger(
        Trigger $trigger,
        TriggerExecutionService $executionService
    ): void {
        // Use atomic lock to prevent duplicate executions
        $lockKey = "trigger-schedule:{$trigger->id}";

        if (!Lock::get($lockKey, 10)) {
            Log::warning('CheckScheduledTriggersJob: Could not acquire lock', ['trigger_id' => $trigger->id]);
            return;
        }

        try {
            Log::debug('CheckScheduledTriggersJob: Executing scheduled trigger', ['trigger_id' => $trigger->id]);

            $executionService->handleScheduledTrigger($trigger);

            Log::info('CheckScheduledTriggersJob: Trigger executed successfully', ['trigger_id' => $trigger->id]);
        } catch (\Exception $e) {
            Log::error('CheckScheduledTriggersJob: Execution failed', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            Lock::forceRelease($lockKey);
        }
    }
}
