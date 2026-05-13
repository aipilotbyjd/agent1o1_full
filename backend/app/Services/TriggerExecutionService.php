<?php

namespace App\Services;

use App\Models\Trigger;
use App\Models\TriggerExecution;
use Illuminate\Support\Facades\Log;

class TriggerExecutionService
{
    public function __construct(
        private TriggerService $triggerService,
    ) {}

    /**
     * Handle webhook event and execute workflow
     */
    public function handleWebhookEvent(Trigger $trigger, array $payload): ?string
    {
        try {
            // Record execution
            $executionId = $this->executeWorkflow($trigger, $payload, 'webhook');

            // Update stats
            $this->triggerService->recordTriggerExecution($trigger);
            $this->triggerService->clearTriggerErrors($trigger);

            return $executionId;
        } catch (\Exception $e) {
            Log::error('Webhook execution failed', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);

            $this->triggerService->recordTriggerError($trigger, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle polling check and execute workflow if new data
     */
    public function handlePollingCheck(Trigger $trigger): ?string
    {
        try {
            // Get polling handler for this trigger type
            $category = $trigger->triggerCategory->slug;
            $handler = $this->getPollingHandler($category);

            if (!$handler) {
                throw new \Exception("No polling handler for {$category}");
            }

            // Poll the service
            $fieldValues = $trigger->getFieldValues();
            $credential = $trigger->credential;
            $credentials = $credential?->data ?? [];

            $results = $handler->poll(
                $fieldValues,
                $credentials,
                $trigger->polling_last_seen_ids ?? [],
                $trigger->polling_interval_seconds
            );

            if (empty($results['new_items'])) {
                // No new items, just update check time
                $this->triggerService->updatePollingCheckTime($trigger);
                return null;
            }

            // Execute workflow for each new item
            $executionIds = [];
            foreach ($results['new_items'] as $item) {
                $executionId = $this->executeWorkflow($trigger, $item, 'polling');
                if ($executionId) {
                    $executionIds[] = $executionId;
                }
            }

            // Update last seen IDs
            $newIds = array_map(fn ($item) => $item['id'] ?? $item['_id'] ?? null, $results['new_items']);
            $newIds = array_filter($newIds);
            $this->triggerService->updatePollingCheckTime($trigger, $newIds);

            // Update stats
            $this->triggerService->recordTriggerExecution($trigger);
            $this->triggerService->clearTriggerErrors($trigger);

            return $executionIds[0] ?? null;
        } catch (\Exception $e) {
            Log::error('Polling check failed', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);

            $this->triggerService->recordTriggerError($trigger, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Handle scheduled trigger execution
     */
    public function handleScheduledTrigger(Trigger $trigger): ?string
    {
        try {
            $executionId = $this->executeWorkflow($trigger, [], 'schedule');

            // Update next run time
            $this->updateNextScheduleTime($trigger);

            // Update stats
            $this->triggerService->recordTriggerExecution($trigger);
            $this->triggerService->clearTriggerErrors($trigger);

            return $executionId;
        } catch (\Exception $e) {
            Log::error('Scheduled trigger execution failed', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);

            $this->triggerService->recordTriggerError($trigger, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Execute workflow and return execution ID
     */
    private function executeWorkflow(Trigger $trigger, array $payload, string $source): string
    {
        // This is a simplified version - actual implementation would use WorkflowEngine
        // For now, just record the execution

        $execution = TriggerExecution::create([
            'trigger_id' => $trigger->id,
            'source' => $source,
            'triggered_at' => now(),
            'trigger_payload' => $payload,
            'status' => 'success', // Would be set by actual engine
        ]);

        // TODO: Call WorkflowEngine to actually execute the workflow
        // $executionId = $workflowEngine->execute($trigger->workflow, $payload);
        // $execution->workflow_execution_id = $executionId;
        // $execution->save();

        Log::info('Workflow triggered', [
            'trigger_id' => $trigger->id,
            'source' => $source,
            'execution_id' => $execution->id,
        ]);

        return $execution->id;
    }

    /**
     * Calculate and update next schedule time
     */
    private function updateNextScheduleTime(Trigger $trigger): void
    {
        if ($trigger->triggerCategory->category_type !== 'schedule') {
            return;
        }

        $nextRun = $this->calculateNextScheduleTime(
            $trigger->schedule_expression,
            $trigger->schedule_timezone
        );

        $trigger->update([
            'schedule_last_run_at' => now(),
            'schedule_next_run_at' => $nextRun,
        ]);
    }

    /**
     * Calculate next run time for schedule
     */
    private function calculateNextScheduleTime(string $expression, string $timezone): \DateTime
    {
        $tz = new \DateTimeZone($timezone);
        $now = new \DateTime('now', $tz);

        // Simplified version - in production use cron library
        // For daily: parse time and set to next occurrence
        // For cron: use proper cron parser

        return $now->modify('+1 day');
    }

    /**
     * Get polling handler for a service
     */
    private function getPollingHandler(string $service): ?object
    {
        // This would map to actual polling handlers
        // For now, return null - handlers would be implemented per service
        return null;
    }
}
