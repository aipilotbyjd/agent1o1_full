<?php

namespace App\Engine\Execution;

use App\Engine\RunContext;
use App\Engine\Sse\SsePublisher;
use App\Models\Execution;
use App\Services\ExecutionService;
use Illuminate\Support\Facades\Log;

/**
 * Handles the terminal phase of a workflow execution.
 *
 * Responsible for marking the execution completed or failed, updating
 * workflow statistics, publishing the final SSE event, triggering error
 * workflows, and scheduling auto-retries.
 */
class ExecutionFinalizer
{
    public function __construct(private readonly SsePublisher $ssePublisher) {}

    /**
     * Mark the execution as successfully completed.
     */
    public function succeed(Execution $execution, RunContext $context): void
    {
        $durationMs = $context->elapsedMs();

        $execution->complete(
            resultData: ['completed_nodes' => $context->completedCount()],
            durationMs: $durationMs,
        );

        // Single query: atomic counter increment + timestamp update
        $execution->workflow->increment('execution_count', 1, ['last_executed_at' => now()]);

        $this->ssePublisher->event($execution->id, 'execution.completed', [
            'duration_ms' => $durationMs,
            'node_count' => $context->completedCount(),
        ]);
    }

    /**
     * Mark the execution as failed, trigger error workflow, schedule auto-retry.
     */
    public function fail(Execution $execution, RunContext $context, ?\Throwable $exception = null): void
    {
        $durationMs = $context->elapsedMs();

        $error = $exception
            ? ['message' => $exception->getMessage(), 'type' => get_class($exception)]
            : ['message' => 'One or more nodes failed.'];

        $execution->fail($error, $durationMs);

        // Schedule an automatic retry if the workflow settings allow it.
        $this->scheduleAutoRetry($execution);

        // Trigger error workflow if configured
        $this->triggerErrorWorkflow($execution, $error);

        $this->ssePublisher->event($execution->id, 'execution.failed', [
            'error' => $error['message'],
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Trigger the error workflow if one is configured.
     *
     * Resolution order:
     *   1. Per-workflow `error_workflow_id` — fine-grained override per workflow.
     *   2. Workspace-level `error_workflow_id` — applies to every workflow in the
     *      workspace that doesn't have its own error workflow set.
     *
     * The triggered error workflow receives the failure details as trigger data
     * so the customer can route, alert, or retry based on the error context.
     *
     * @param  array<string, mixed>  $error
     */
    private function triggerErrorWorkflow(Execution $execution, array $error): void
    {
        $workflow = $execution->workflow;

        // 1. Per-workflow override takes priority
        $errorWorkflowId = $workflow->error_workflow_id;

        // 2. Fall back to workspace-level default
        if (! $errorWorkflowId) {
            $workflow->loadMissing('workspace.setting');
            $errorWorkflowId = $workflow->workspace?->setting?->error_workflow_id;
        }

        if (! $errorWorkflowId) {
            return;
        }

        // Guard: never let a workflow trigger itself as its own error handler
        if ($errorWorkflowId === $workflow->id) {
            Log::warning('Error workflow is the same as the failed workflow — skipping.', [
                'execution_id' => $execution->id,
                'workflow_id' => $workflow->id,
            ]);

            return;
        }

        try {
            $errorWorkflow = \App\Models\Workflow::find($errorWorkflowId);

            if ($errorWorkflow && $errorWorkflow->is_active) {
                app(ExecutionService::class)->trigger(
                    workflow: $errorWorkflow,
                    user: $execution->triggeredBy ?? $workflow->creator,
                    triggerData: [
                        'source_execution_id' => $execution->id,
                        'source_workflow_id' => $workflow->id,
                        'source_workflow_name' => $workflow->name,
                        'error' => $error,
                    ],
                );
            }
        } catch (\Throwable $e) {
            Log::error('Failed to trigger error workflow.', [
                'execution_id' => $execution->id,
                'error_workflow_id' => $errorWorkflowId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Schedule an automatic retry for a failed execution.
     *
     * Delegates entirely to ExecutionService::autoRetry() which:
     *  - guards against exceeding max_attempts (no-op if limit reached)
     *  - computes the exponential delay from the snapshotted retry_delay_seconds
     *  - creates the child Execution record and dispatches the job with ->delay()
     *
     * Resolved lazily via app() to avoid a circular dependency:
     *   ExecutionService → ExecuteWorkflowJob → WorkflowEngine
     */
    private function scheduleAutoRetry(Execution $execution): void
    {
        try {
            app(ExecutionService::class)->autoRetry($execution);
        } catch (\Throwable $e) {
            Log::error('Failed to schedule auto-retry.', [
                'execution_id' => $execution->id,
                'attempt' => $execution->attempt,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
