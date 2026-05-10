<?php

namespace App\Services;

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Exceptions\ApiException;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Execution;
use App\Models\ExecutionReplayPack;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Support\Str;

class ExecutionService
{
    /**
     * Trigger a new workflow execution.
     */
    public function trigger(
        Workflow $workflow,
        User $user,
        ?array $triggerData = null,
        ExecutionMode $mode = ExecutionMode::Manual,
    ): Execution {
        if (! $workflow->is_active) {
            throw ApiException::unprocessable('Workflow is not active.');
        }

        if (! $workflow->current_version_id) {
            throw ApiException::unprocessable('Workflow has no published version.');
        }

        $workflow->loadMissing('currentVersion');

        // Per-workflow concurrency limit
        $maxConcurrent = $workflow->max_concurrent_executions ?? 0;
        if ($maxConcurrent > 0) {
            $activeCount = $workflow->executions()
                ->whereIn('status', [
                    ExecutionStatus::Pending->value,
                    ExecutionStatus::Running->value,
                    ExecutionStatus::Waiting->value,
                ])
                ->count();

            if ($activeCount >= $maxConcurrent) {
                throw ApiException::unprocessable(
                    "Workflow has reached its concurrent execution limit ({$maxConcurrent}). Try again later."
                );
            }
        }

        $retrySettings = $workflow->currentVersion->settings['retry'] ?? [];

        $execution = Execution::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $workflow->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => $mode,
            'triggered_by' => $user->id,
            'trigger_data' => $triggerData,
            'attempt' => 1,
            'max_attempts' => max(1, (int) ($retrySettings['max_attempts'] ?? 1)),
            'retry_delay_seconds' => max(1, (int) ($retrySettings['retry_wait'] ?? 60)),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        $this->captureReplayPack($execution, $workflow);

        // Route to the appropriate queue based on trigger mode so high-priority
        // webhook executions are never blocked by slow scheduled or retry jobs.
        $queue = match ($mode) {
            ExecutionMode::Webhook => 'workflows-realtime',
            ExecutionMode::Schedule, ExecutionMode::Polling => 'workflows-schedule',
            ExecutionMode::Retry => 'workflows-low',
            default => 'workflows-default',
        };

        ExecuteWorkflowJob::dispatch($execution)->onQueue($queue);

        return $execution;
    }

    /**
     * Retry a failed execution by creating a child execution.
     */
    public function retry(Execution $execution, User $user): Execution
    {
        if ($execution->status !== ExecutionStatus::Failed) {
            throw ApiException::unprocessable('Only failed executions can be retried.');
        }

        if ($execution->attempt >= $execution->max_attempts) {
            throw ApiException::unprocessable('Maximum retry attempts reached.');
        }

        $workflow = $execution->workflow;

        $childExecution = Execution::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $execution->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => ExecutionMode::Retry,
            'triggered_by' => $user->id,
            'trigger_data' => $execution->trigger_data,
            'attempt' => $execution->attempt + 1,
            'max_attempts' => $execution->max_attempts,
            'retry_delay_seconds' => $execution->retry_delay_seconds,
            'parent_execution_id' => $execution->id,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        $this->captureReplayPack($childExecution, $workflow);

        // Manual retries run immediately on the default queue (user is waiting for result).
        ExecuteWorkflowJob::dispatch($childExecution)->onQueue('workflows-default');

        return $childExecution;
    }

    /**
     * Automatically schedule a retry for a failed execution using exponential backoff.
     *
     * Called by the execution engine immediately after marking an execution as failed.
     * Uses the retry settings that were snapshotted onto the execution at creation time,
     * so the delay is always consistent with the workflow version that originally ran.
     *
     * Delay formula: retry_delay_seconds × 2^(attempt − 1)
     *   attempt 1 failed → delay = retry_delay_seconds × 1   (2^0)
     *   attempt 2 failed → delay = retry_delay_seconds × 2   (2^1)
     *   attempt 3 failed → delay = retry_delay_seconds × 4   (2^2)
     *
     * Auto-retries are dispatched on the low-priority queue so they do not
     * compete with new or manual executions.
     *
     * @return Execution|null  The newly queued child execution, or null when no retries remain.
     */
    public function autoRetry(Execution $execution): ?Execution
    {
        if (! $execution->canRetry()) {
            return null;
        }

        $delaySeconds = $execution->retry_delay_seconds * (2 ** ($execution->attempt - 1));

        $child = Execution::create([
            'workflow_id' => $execution->workflow_id,
            'workspace_id' => $execution->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => ExecutionMode::Retry,
            'triggered_by' => $execution->triggered_by,
            'trigger_data' => $execution->trigger_data,
            'attempt' => $execution->attempt + 1,
            'max_attempts' => $execution->max_attempts,
            'retry_delay_seconds' => $execution->retry_delay_seconds,
            'parent_execution_id' => $execution->id,
        ]);

        ExecuteWorkflowJob::dispatch($child)
            ->onQueue('workflows-low')
            ->delay($delaySeconds);

        return $child;
    }

    /**
     * Cancel an active execution.
     */
    public function cancel(Execution $execution): Execution
    {
        if (! $execution->canCancel()) {
            throw ApiException::unprocessable('This execution cannot be cancelled.');
        }

        $execution->cancel();

        return $execution->refresh();
    }

    public function delete(Execution $execution): void
    {
        $execution->delete();
    }

    /**
     * Get aggregated execution stats for a workspace.
     *
     * @return array<string, mixed>
     */
    public function stats(Workspace $workspace, ?int $workflowId = null): array
    {
        $query = $workspace->executions();

        if ($workflowId) {
            $query->where('workflow_id', $workflowId);
        }

        $total = $query->count();
        $completed = (clone $query)->where('status', ExecutionStatus::Completed)->count();
        $failed = (clone $query)->where('status', ExecutionStatus::Failed)->count();
        $running = (clone $query)->where('status', ExecutionStatus::Running)->count();
        $pending = (clone $query)->where('status', ExecutionStatus::Pending)->count();
        $cancelled = (clone $query)->where('status', ExecutionStatus::Cancelled)->count();
        $avgDuration = (clone $query)->whereNotNull('duration_ms')->avg('duration_ms');

        return [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
            'running' => $running,
            'pending' => $pending,
            'cancelled' => $cancelled,
            'success_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'avg_duration_ms' => $avgDuration ? (int) round($avgDuration) : null,
        ];
    }

    /**
     * Replay a completed execution using its captured snapshot.
     */
    public function replay(Execution $execution, User $user): Execution
    {
        $replayPack = $execution->replayPack;

        if (! $replayPack) {
            throw ApiException::unprocessable('No replay pack found for this execution.');
        }

        if ($execution->status->isActive()) {
            throw ApiException::unprocessable('Cannot replay an active execution.');
        }

        $workflow = $execution->workflow;

        $replayExecution = Execution::create([
            'workflow_id' => $workflow->id,
            'workspace_id' => $execution->workspace_id,
            'status' => ExecutionStatus::Pending,
            'mode' => ExecutionMode::Replay,
            'triggered_by' => $user->id,
            'trigger_data' => $replayPack->trigger_snapshot,
            'replay_of_execution_id' => $execution->id,
            'is_deterministic_replay' => true,
            'attempt' => 1,
            'max_attempts' => 1,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);

        $this->captureReplayPack($replayExecution, $workflow);

        ExecuteWorkflowJob::dispatch($replayExecution)
            ->onQueue('workflows-default');

        return $replayExecution;
    }

    private function captureReplayPack(Execution $execution, Workflow $workflow): void
    {
        $version = $workflow->currentVersion;

        ExecutionReplayPack::create([
            'execution_id' => $execution->id,
            'workspace_id' => $execution->workspace_id,
            'workflow_id' => $workflow->id,
            'source_execution_id' => null,
            'mode' => 'capture',
            'deterministic_seed' => (string) Str::uuid(),
            'workflow_snapshot' => [
                'id' => $workflow->id,
                'name' => $workflow->name,
                'nodes' => $version->nodes ?? [],
                'edges' => $version->edges ?? [],
                'settings' => $version->settings ?? [],
            ],
            'trigger_snapshot' => $execution->trigger_data,
            'fixtures' => [],
            'environment_snapshot' => [
                'app_env' => config('app.env'),
                'app_url' => config('app.url'),
                'captured_by' => 'api-dispatch',
            ],
            'captured_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
