<?php

namespace App\Engine;

use App\Contracts\Suspendable;
use App\Engine\Execution\ExecutionWriter;
use App\Engine\Execution\NodeRunner;
use App\Engine\Graph\WorkflowGraph;
use App\Engine\Sse\SsePublisher;
use App\Enums\ExecutionMode;
use App\Enums\ExecutionNodeStatus;
use App\Enums\OnErrorBehavior;
use App\Events\ExecutionNodeFailed;
use App\Exceptions\NodeFailedException;
use App\Jobs\ResumeWorkflowJob;
use App\Models\Execution;
use App\Models\ExecutionCheckpoint;
use App\Models\PinnedNodeData;
use App\Services\CreditMeterService;
use App\Services\ExecutionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Core workflow execution engine.
 *
 * Merges WorkflowEngine + ExecutionFinalizer. Checkpoint persistence is handled
 * by private methods (was CheckpointStore).
 *
 * Frontier-based scheduler:
 *  - Sync nodes execute inline
 *  - Async nodes run concurrently via Laravel Concurrency
 *  - Blocking nodes checkpoint state and requeue via delayed jobs
 */
class WorkflowRunner
{
    /** @var array<string, PinnedNodeData> nodeId → PinnedNodeData (loaded once per run) */
    private array $pinnedData = [];

    public function __construct(
        private readonly NodeRunner $nodeRunner,
        private readonly ExecutionWriter $writer,
        private readonly SsePublisher $ssePublisher,
        private readonly CreditMeterService $creditMeter,
    ) {}

    /**
     * Execute a workflow from the beginning.
     */
    public function run(Execution $execution): void
    {
        $workflow = $execution->workflow;
        $version = $workflow->currentVersion;

        if (! $version) {
            $execution->fail(['message' => 'Workflow has no published version.']);

            return;
        }

        $graph = $this->compileGraph($version);

        if ($graph->nodeCount() === 0) {
            $execution->fail(['message' => 'Workflow has no nodes.']);

            return;
        }

        $variables = $this->loadVariables($execution);
        $variables['__trigger_data'] = $execution->trigger_data ?? [];

        $credentials = $this->loadCredentials($execution);

        $outputBuffer = new \App\Engine\Execution\OutputBuffer(
            executionId: $execution->id,
            downstreamConsumers: $graph->downstreamConsumers,
        );

        $context = new WorkflowContext(
            graph: $graph,
            outputs: $outputBuffer,
            executionId: $execution->id,
            variables: $variables,
            credentials: $credentials,
        );

        $this->pinnedData = $this->loadPinnedData($execution);

        $execution->start();
        $this->ssePublisher->event($execution->id, 'execution.started');

        $this->executeLoop($execution, $graph, $context);
    }

    /**
     * Resume a suspended execution from its checkpoint.
     */
    public function resume(Execution $execution): void
    {
        $checkpoint = $this->loadCheckpoint($execution->id);

        if (! $checkpoint) {
            $execution->fail(['message' => 'No checkpoint found for resumption.']);

            return;
        }

        $workflow = $execution->workflow;
        $version = $workflow->currentVersion;

        if (! $version) {
            $execution->fail(['message' => 'Workflow has no published version.']);

            return;
        }

        $graph = $this->compileGraph($version);
        $credentials = $this->loadCredentials($execution);

        $context = WorkflowContext::fromCheckpoint(
            graph: $graph,
            executionId: $execution->id,
            frontierState: array_merge(
                $checkpoint->frontier_state,
                [
                    'frame_stack' => $checkpoint->frame_stack ?? [],
                    'next_sequence' => $checkpoint->next_sequence ?? 1,
                ],
            ),
            outputSnapshot: $checkpoint->output_refs ?? [],
            credentials: $credentials,
        );

        if ($checkpoint->resume_payload !== null) {
            $suspendedNodeId = $execution->result_data['suspended_node'] ?? null;
            if ($suspendedNodeId) {
                $context->outputs->store($suspendedNodeId, $checkpoint->resume_payload);
            }
        }

        if (! $execution->resume()) {
            Log::info("Execution {$execution->id} was already resumed or cancelled — skipping.", [
                'status' => $execution->status->value,
            ]);

            return;
        }

        $this->ssePublisher->event($execution->id, 'execution.resumed');

        $this->deleteCheckpoint($execution->id);

        $this->executeLoop($execution, $graph, $context);
    }

    private function compileGraph(\App\Models\WorkflowVersion $version): WorkflowGraph
    {
        $cacheKey = "engine:graph:{$version->id}";

        $cached = cache()->get($cacheKey);
        if ($cached instanceof WorkflowGraph) {
            return $cached;
        }

        $graph = WorkflowGraph::compile(
            nodes: $version->nodes ?? [],
            edges: $version->edges ?? [],
        );

        cache()->put($cacheKey, $graph, now()->addHours(6));

        return $graph;
    }

    private function executeLoop(Execution $execution, WorkflowGraph $graph, WorkflowContext $context): void
    {
        try {
            while ($context->hasReadyNodes()) {
                $readyNodes = $context->getReadyNodes();

                [$syncNodes, $asyncNodes, $blockingNodes] = $this->nodeRunner->partition($readyNodes, $graph);

                foreach ($syncNodes as $nodeId) {
                    $this->executeNode($nodeId, $graph, $context, $execution);
                }

                if (! empty($asyncNodes)) {
                    $asyncResults = $this->nodeRunner->runAsyncBatch($asyncNodes, $graph, $context);

                    foreach ($asyncResults as $nodeId => $result) {
                        $this->commitNodeResult($nodeId, $result, $graph, $context, $execution);
                    }
                }

                if (! empty($blockingNodes)) {
                    $suspended = $this->handleSuspension($blockingNodes[0], $graph, $context, $execution);

                    if ($suspended !== null) {
                        return;
                    }

                    // Handler doesn't implement Suspendable — run inline
                    foreach ($blockingNodes as $nodeId) {
                        $this->executeNode($nodeId, $graph, $context, $execution);
                    }
                }

                $this->writer->flushIfNeeded($context);

                $memoryCapBytes = (int) config('workflow.output_memory_cap_bytes', 512 * 1024 * 1024);
                if ($context->outputs->memoryUsage() > $memoryCapBytes) {
                    throw new \RuntimeException(
                        'Execution output memory limit exceeded (' . round($memoryCapBytes / 1024 / 1024) . ' MB). '
                        . 'Use batch processing nodes for large datasets.'
                    );
                }

                if ($this->isCancelled($execution->id)) {
                    $this->writer->flush();
                    $execution->cancel();
                    $this->ssePublisher->event($execution->id, 'execution.cancelled');

                    return;
                }
            }

            $this->writer->flush();

            $hasFailures = $this->hasFailedNodes($context);

            if ($hasFailures && ! $context->isFinished()) {
                $this->finalizeFail($execution, $context);
            } else {
                $this->finalizeSuccess($execution, $context);
            }
        } catch (NodeFailedException $e) {
            $this->writer->flush();
            $this->finalizeFail($execution, $context, $e);
        } catch (\Throwable $e) {
            $this->writer->flush();

            Log::error('Workflow runner error', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $execution->fail([
                'message' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            $this->ssePublisher->event($execution->id, 'execution.failed');
        } finally {
            $context->outputs->cleanup();
        }
    }

    private function executeNode(
        string $nodeId,
        WorkflowGraph $graph,
        WorkflowContext $context,
        Execution $execution,
    ): void {
        $this->ssePublisher->nodeStarted($execution->id, [
            'node_id' => $nodeId,
            'node_type' => $graph->getNode($nodeId)['type'] ?? 'unknown',
        ]);

        $pinned = $this->pinnedData[$nodeId] ?? null;
        if ($pinned && $pinned->is_active && $execution->mode === ExecutionMode::Manual) {
            $originalResult = NodeResult::completed($pinned->data ?? []);
        } else {
            $nodeDef = $graph->getNode($nodeId);
            $retryConfig = $nodeDef['config']['retry'] ?? $nodeDef['data']['retry'] ?? [];
            $maxAttempts = max(1, (int) ($retryConfig['max_attempts'] ?? 1));
            $retryDelayMs = max(0, (int) ($retryConfig['retry_delay_ms'] ?? 0));

            $originalResult = null;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $result = $this->nodeRunner->runSync($nodeId, $graph, $context);
                } catch (NodeFailedException $e) {
                    $result = NodeResult::failed($e->getMessage(), 'NODE_EXECUTION_ERROR');
                }

                if ($result->status !== ExecutionNodeStatus::Failed || $attempt >= $maxAttempts) {
                    $originalResult = $result;
                    break;
                }

                if ($retryDelayMs > 0) {
                    usleep($retryDelayMs * 1000);
                }
            }
        }

        $sequence = $context->nextSequence();

        $this->writer->record(
            executionId: $execution->id,
            nodeId: $nodeId,
            nodeRunKey: $nodeId,
            graph: $graph,
            result: $originalResult,
            sequence: $sequence,
        );

        $routingResult = $originalResult->status === ExecutionNodeStatus::Failed
            ? $this->applyOnErrorBehavior($nodeId, $originalResult, $graph, $context, $execution)
            : $originalResult;

        $context->complete(
            nodeId: $nodeId,
            result: $routingResult,
            activeBranches: $routingResult->activeBranches,
        );

        $this->ssePublisher->nodeCompleted($execution->id, [
            'node_id' => $nodeId,
            'status' => $originalResult->status->value,
            'duration_ms' => $originalResult->durationMs,
            'on_error' => $originalResult->status === ExecutionNodeStatus::Failed
                ? OnErrorBehavior::fromNode($graph->getNode($nodeId) ?? [])->value
                : null,
            'progress' => $graph->nodeCount() > 0
                ? (int) round(($context->completedCount() / $graph->nodeCount()) * 100)
                : 100,
        ]);
    }

    private function commitNodeResult(
        string $nodeId,
        NodeResult $result,
        WorkflowGraph $graph,
        WorkflowContext $context,
        Execution $execution,
    ): void {
        $this->ssePublisher->nodeStarted($execution->id, [
            'node_id' => $nodeId,
            'node_type' => $graph->getNode($nodeId)['type'] ?? 'unknown',
        ]);

        $sequence = $context->nextSequence();

        $this->writer->record(
            executionId: $execution->id,
            nodeId: $nodeId,
            nodeRunKey: $nodeId,
            graph: $graph,
            result: $result,
            sequence: $sequence,
        );

        $routingResult = $result->status === ExecutionNodeStatus::Failed
            ? $this->applyOnErrorBehavior($nodeId, $result, $graph, $context, $execution)
            : $result;

        $context->complete(
            nodeId: $nodeId,
            result: $routingResult,
            activeBranches: $routingResult->activeBranches,
        );

        $this->ssePublisher->nodeCompleted($execution->id, [
            'node_id' => $nodeId,
            'status' => $result->status->value,
            'duration_ms' => $result->durationMs,
            'on_error' => $result->status === ExecutionNodeStatus::Failed
                ? OnErrorBehavior::fromNode($graph->getNode($nodeId) ?? [])->value
                : null,
            'progress' => $graph->nodeCount() > 0
                ? (int) round(($context->completedCount() / $graph->nodeCount()) * 100)
                : 100,
        ]);
    }

    private function handleSuspension(
        string $nodeId,
        WorkflowGraph $graph,
        WorkflowContext $context,
        Execution $execution,
    ): ?ExecutionPause {
        $node = $graph->getNode($nodeId);
        $type = $node['type'] ?? '';
        $handler = NodeCatalog::handler($type);

        if (! $handler instanceof Suspendable) {
            return null;
        }

        $nodeInput = NodeInput::build($nodeId, $graph, $context);
        $pause = $handler->suspend($nodeInput);

        $result = NodeResult::completed($pause->nodeOutput);
        $sequence = $context->nextSequence();

        $this->writer->record(
            executionId: $execution->id,
            nodeId: $nodeId,
            nodeRunKey: $nodeId,
            graph: $graph,
            result: $result,
            sequence: $sequence,
        );

        $context->complete(
            nodeId: $nodeId,
            result: $result,
            activeBranches: $result->activeBranches,
        );

        $this->writer->flush();

        $this->saveCheckpoint($execution, $context, $pause);

        $execution->markWaiting($pause->resumeAt, [
            'suspend_reason' => $pause->reason,
            'suspended_node' => $nodeId,
        ]);

        $this->ssePublisher->event($execution->id, 'execution.suspended', [
            'node_id' => $nodeId,
            'reason' => $pause->reason,
            'resume_at' => $pause->resumeAt->toIso8601String(),
        ]);

        $delaySeconds = max(0, (int) now()->diffInSeconds($pause->resumeAt, false));
        ResumeWorkflowJob::dispatch($execution)->delay(now()->addSeconds($delaySeconds));

        return $pause;
    }

    /**
     * @throws NodeFailedException  when behavior is `stop`
     */
    private function applyOnErrorBehavior(
        string $nodeId,
        NodeResult $result,
        WorkflowGraph $graph,
        WorkflowContext $context,
        Execution $execution,
    ): NodeResult {
        $node = $graph->getNode($nodeId) ?? [];
        $behavior = OnErrorBehavior::fromNode($node);
        $nodeType = $node['type'] ?? 'unknown';
        $errorMessage = $result->error['message'] ?? 'Node execution failed.';

        return match ($behavior) {
            OnErrorBehavior::Stop => (function () use (
                $nodeId, $nodeType, $errorMessage, $result, $node, $graph, $context, $execution,
            ): never {
                try {
                    $payload = NodeInput::build($nodeId, $graph, $context);
                    $config = $payload->config;
                    $inputData = $payload->inputData;
                } catch (\Throwable) {
                    $config = $node['config'] ?? [];
                    $inputData = [];
                }

                ExecutionNodeFailed::dispatch(
                    $execution, $nodeId, $nodeType, $errorMessage, $config, $inputData,
                );

                throw new NodeFailedException(
                    nodeId: $nodeId,
                    nodeType: $nodeType,
                    reason: $errorMessage,
                    errorData: $result->error,
                );
            })(),

            OnErrorBehavior::Continue => (function () use ($nodeId, $result, $execution): NodeResult {
                Log::warning("Node [{$nodeId}] failed — continuing (on_error=continue).", [
                    'execution_id' => $execution->id,
                    'error' => $result->error,
                ]);

                return NodeResult::completed(output: [], durationMs: $result->durationMs ?? 0);
            })(),

            OnErrorBehavior::ContinueErrorOutput => (function () use ($nodeId, $result, $execution): NodeResult {
                Log::warning("Node [{$nodeId}] failed — routing to error output.", [
                    'execution_id' => $execution->id,
                    'error' => $result->error,
                ]);

                return NodeResult::errorOutput(
                    errorData: $result->error ?? ['message' => 'Unknown error'],
                    durationMs: $result->durationMs ?? 0,
                );
            })(),
        };
    }

    // ── Terminal state handlers (was ExecutionFinalizer) ─────────────────────

    private function finalizeSuccess(Execution $execution, WorkflowContext $context): void
    {
        $durationMs = $context->elapsedMs();

        $execution->complete(
            resultData: ['completed_nodes' => $context->completedCount()],
            durationMs: $durationMs,
        );

        try {
            $nodes = $execution->nodes()->get()->all();
            $this->creditMeter->consume($execution, $nodes);
        } catch (\Throwable $e) {
            Log::error('Failed to consume credits for execution.', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
            ]);
        }

        $execution->workflow->increment('execution_count', 1, ['last_executed_at' => now()]);

        $this->ssePublisher->event($execution->id, 'execution.completed', [
            'duration_ms' => $durationMs,
            'node_count' => $context->completedCount(),
        ]);
    }

    private function finalizeFail(Execution $execution, WorkflowContext $context, ?\Throwable $exception = null): void
    {
        $durationMs = $context->elapsedMs();

        $error = $exception
            ? ['message' => $exception->getMessage(), 'type' => get_class($exception)]
            : ['message' => 'One or more nodes failed.'];

        $execution->fail($error, $durationMs);

        $this->scheduleAutoRetry($execution);
        $this->triggerErrorWorkflow($execution, $error);

        $this->ssePublisher->event($execution->id, 'execution.failed', [
            'error' => $error['message'],
            'duration_ms' => $durationMs,
        ]);
    }

    /** @param  array<string, mixed>  $error */
    private function triggerErrorWorkflow(Execution $execution, array $error): void
    {
        $workflow = $execution->workflow;
        $errorWorkflowId = $workflow->error_workflow_id;

        if (! $errorWorkflowId) {
            $workflow->loadMissing('workspace.setting');
            $errorWorkflowId = $workflow->workspace?->setting?->error_workflow_id;
        }

        if (! $errorWorkflowId || $errorWorkflowId === $workflow->id) {
            return;
        }

        $currentDepth = (int) ($execution->trigger_data['__error_depth'] ?? 0);
        if ($currentDepth >= 3) {
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
                        '__error_depth' => $currentDepth + 1,
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

    // ── Checkpoint persistence (was CheckpointStore) ─────────────────────────

    private function saveCheckpoint(Execution $execution, WorkflowContext $context, ExecutionPause $pause): void
    {
        $frontierSnapshot = $context->snapshot();
        $outputSnapshot = $context->outputs->snapshot();

        ExecutionCheckpoint::updateOrCreate(
            ['execution_id' => $execution->id],
            [
                'frontier_state' => [
                    'ready_queue' => $frontierSnapshot['ready_queue'],
                    'remaining_in_degree' => $frontierSnapshot['remaining_in_degree'],
                    'completed_nodes' => $frontierSnapshot['completed_nodes'],
                    'variables' => $frontierSnapshot['variables'],
                ],
                'output_refs' => $outputSnapshot,
                'frame_stack' => $frontierSnapshot['frame_stack'],
                'next_sequence' => $frontierSnapshot['next_sequence'],
                'suspend_reason' => $pause->reason,
                'resume_at' => $pause->resumeAt,
                'webhook_wait_uuid' => $pause->webhookWaitUuid,
                'checkpoint_version' => 1,
            ],
        );
    }

    private function loadCheckpoint(int $executionId): ?ExecutionCheckpoint
    {
        return ExecutionCheckpoint::where('execution_id', $executionId)->first();
    }

    private function deleteCheckpoint(int $executionId): void
    {
        ExecutionCheckpoint::where('execution_id', $executionId)->delete();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isCancelled(int $executionId): bool
    {
        try {
            return (bool) Redis::get("engine:cancel:{$executionId}");
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasFailedNodes(WorkflowContext $context): bool
    {
        foreach ($context->getCompletedNodes() as $result) {
            if ($result->status === ExecutionNodeStatus::Failed) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, PinnedNodeData> */
    private function loadPinnedData(Execution $execution): array
    {
        return PinnedNodeData::where('workflow_id', $execution->workflow_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('node_id')
            ->all();
    }

    /** @return array<string, mixed> */
    private function loadVariables(Execution $execution): array
    {
        $workspace = $execution->workspace;
        $variables = [];

        foreach ($workspace->variables()->get() as $variable) {
            $variables[$variable->key] = $variable->is_secret
                ? decrypt($variable->value)
                : $variable->value;
        }

        return $variables;
    }

    /** @return array<string, \App\Models\Credential> */
    private function loadCredentials(Execution $execution): array
    {
        $execution->load('workflow.credentials');

        $credentials = [];

        foreach ($execution->workflow->credentials as $credential) {
            $nodeId = $credential->pivot->node_id;
            if ($nodeId) {
                $credentials[$nodeId] = $credential;
            }
        }

        return $credentials;
    }
}
