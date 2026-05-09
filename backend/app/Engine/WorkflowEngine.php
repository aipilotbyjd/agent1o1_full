<?php

namespace App\Engine;

use App\Engine\Contracts\SuspendsExecution;
use App\Engine\Enums\OnErrorBehavior;
use App\Engine\Exceptions\NodeFailedException;
use App\Engine\Execution\AsyncExecutor;
use App\Engine\Execution\ExecutionFinalizer;
use App\Engine\Execution\ExecutionScheduler;
use App\Engine\Execution\NodePayload;
use App\Engine\Execution\Suspension;
use App\Engine\Execution\SyncExecutor;
use App\Engine\Graph\GraphCompiler;
use App\Engine\Graph\WorkflowGraph;
use App\Engine\Persistence\BatchWriter;
use App\Engine\Persistence\CheckpointStore;
use App\Engine\Sse\SsePublisher;
use App\Enums\ExecutionNodeStatus;
use App\Events\ExecutionNodeFailed;
use App\Jobs\ResumeWorkflowJob;
use App\Models\Execution;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * The core workflow execution engine.
 *
 * Runs a compiled WorkflowGraph using a frontier-based scheduler:
 *  - Sync nodes execute instantly (transforms, conditions, triggers)
 *  - Async nodes execute concurrently via Laravel Concurrency
 *  - Blocking nodes checkpoint state and requeue via delayed jobs
 *
 * Persistence is batched — node results accumulate in memory and flush
 * to the database periodically or on completion/failure.
 *
 * Extracted responsibilities:
 *  - SsePublisher     → publishes real-time Redis SSE events
 *  - ExecutionScheduler → partitions nodes by execution mode
 *  - ExecutionFinalizer → handles success/failure terminal states
 */
class WorkflowEngine
{
    public function __construct(
        private readonly GraphCompiler $compiler,
        private readonly SyncExecutor $syncExecutor,
        private readonly AsyncExecutor $asyncExecutor,
        private readonly BatchWriter $batchWriter,
        private readonly CheckpointStore $checkpointStore,
        private readonly SsePublisher $ssePublisher,
        private readonly ExecutionScheduler $executionScheduler,
        private readonly ExecutionFinalizer $executionFinalizer,
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

        $context = new RunContext(
            graph: $graph,
            outputs: $outputBuffer,
            executionId: $execution->id,
            variables: $variables,
            credentials: $credentials,
        );

        $execution->start();
        $this->ssePublisher->event($execution->id, 'execution.started');

        $this->executeLoop($execution, $graph, $context);
    }

    /**
     * Resume a suspended execution from its checkpoint.
     */
    public function resume(Execution $execution): void
    {
        $checkpoint = $this->checkpointStore->load($execution->id);

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

        $context = RunContext::fromCheckpoint(
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

        // Inject webhook resume payload as the suspended WaitNode's output so
        // downstream nodes can read the incoming webhook body, headers, etc.
        if ($checkpoint->resume_payload !== null) {
            $suspendedNodeId = $execution->result_data['suspended_node'] ?? null;
            if ($suspendedNodeId) {
                $context->outputs->store($suspendedNodeId, $checkpoint->resume_payload);
            }
        }

        $execution->resume();
        $this->ssePublisher->event($execution->id, 'execution.resumed');

        $this->checkpointStore->delete($execution->id);

        $this->executeLoop($execution, $graph, $context);
    }

    /**
     * Compile workflow version into a WorkflowGraph with caching.
     */
    private function compileGraph(\App\Models\WorkflowVersion $version): WorkflowGraph
    {
        $cacheKey = "engine:graph:{$version->id}";

        $cached = cache()->get($cacheKey);
        if ($cached instanceof WorkflowGraph) {
            return $cached;
        }

        $graph = $this->compiler->compile(
            nodes: $version->nodes ?? [],
            edges: $version->edges ?? [],
        );

        cache()->put($cacheKey, $graph, now()->addHours(6));

        return $graph;
    }

    /**
     * The main execution loop — frontier-based scheduler.
     */
    private function executeLoop(Execution $execution, WorkflowGraph $graph, RunContext $context): void
    {
        try {
            while ($context->hasReadyNodes()) {
                $readyNodes = $context->getReadyNodes();

                // Partition nodes by execution mode
                [$syncNodes, $asyncNodes, $blockingNodes] = $this->executionScheduler->partition($readyNodes, $graph);

                // Execute sync nodes instantly
                foreach ($syncNodes as $nodeId) {
                    $this->executeNode($nodeId, $graph, $context, $execution);
                }

                // Execute async nodes concurrently via Laravel Concurrency
                if (! empty($asyncNodes)) {
                    $asyncResults = $this->asyncExecutor->runBatch($asyncNodes, $graph, $context);

                    foreach ($asyncResults as $nodeId => $result) {
                        $this->commitNodeResult($nodeId, $result, $graph, $context, $execution);
                    }
                }

                // Blocking nodes — checkpoint + requeue via delayed job
                if (! empty($blockingNodes)) {
                    $suspension = $this->handleSuspension($blockingNodes[0], $graph, $context, $execution);

                    if ($suspension !== null) {
                        return;
                    }

                    // Fallback: handler doesn't implement SuspendsExecution, run inline
                    foreach ($blockingNodes as $nodeId) {
                        $this->executeNode($nodeId, $graph, $context, $execution);
                    }
                }

                // Flush to DB if threshold reached
                $this->batchWriter->flushIfNeeded($context);

                // Check for cancellation
                if ($this->isCancelled($execution->id)) {
                    $this->batchWriter->flush();
                    $execution->cancel();
                    $this->ssePublisher->event($execution->id, 'execution.cancelled');

                    return;
                }
            }

            // Final flush — write any remaining node results
            $this->batchWriter->flush();

            // Determine final status
            $hasFailures = $this->hasFailedNodes($context);

            if ($hasFailures && ! $context->isFinished()) {
                $this->executionFinalizer->fail($execution, $context);
            } else {
                $this->executionFinalizer->succeed($execution, $context);
            }
        } catch (NodeFailedException $e) {
            $this->batchWriter->flush();
            $this->executionFinalizer->fail($execution, $context, $e);
        } catch (\Throwable $e) {
            $this->batchWriter->flush();

            Log::error('Workflow engine error', [
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

    /**
     * Execute a single node: resolve handler, run, record result, advance frontier.
     *
     * Error handling order (critical — must happen before context->complete()):
     *   1. Run the node handler → get the raw result.
     *   2. If the result is Failed, persist the true status to the batch writer.
     *   3. Resolve on_error behaviour and convert the raw result to a routing
     *      result that carries the correct activeBranches for frontier advancement.
     *      For `stop` this step throws NodeFailedException, aborting the execution.
     *   4. Pass the routing result to context->complete() so the frontier advances
     *      along the correct edges (success handles, error handle, or none at all).
     */
    private function executeNode(
        string $nodeId,
        WorkflowGraph $graph,
        RunContext $context,
        Execution $execution,
    ): void {
        $this->ssePublisher->nodeStarted($execution->id, [
            'node_id' => $nodeId,
            'node_type' => $graph->getNode($nodeId)['type'] ?? 'unknown',
        ]);

        try {
            $originalResult = $this->syncExecutor->run($nodeId, $graph, $context);
        } catch (NodeFailedException $e) {
            $originalResult = NodeResult::failed($e->getMessage(), 'NODE_EXECUTION_ERROR');
        }

        $sequence = $context->nextSequence();

        // Always persist the true result status (Failed, Completed, etc.) to DB.
        $this->batchWriter->record(
            executionId: $execution->id,
            nodeId: $nodeId,
            nodeRunKey: $nodeId,
            graph: $graph,
            result: $originalResult,
            sequence: $sequence,
        );

        // Derive the routing result: may be identical to originalResult, or a
        // modified version with different activeBranches / status. For `stop`
        // this throws NodeFailedException before we reach context->complete().
        $routingResult = $originalResult->status === ExecutionNodeStatus::Failed
            ? $this->applyOnErrorBehavior($nodeId, $originalResult, $graph, $context, $execution)
            : $originalResult;

        // Advance frontier using the routing result's activeBranches.
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

    /**
     * Commit a pre-computed result (from AsyncExecutor) into the execution state.
     *
     * Applies the same on_error routing logic as executeNode().
     */
    private function commitNodeResult(
        string $nodeId,
        NodeResult $result,
        WorkflowGraph $graph,
        RunContext $context,
        Execution $execution,
    ): void {
        $this->ssePublisher->nodeStarted($execution->id, [
            'node_id' => $nodeId,
            'node_type' => $graph->getNode($nodeId)['type'] ?? 'unknown',
        ]);

        $sequence = $context->nextSequence();

        $this->batchWriter->record(
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

    /**
     * Handle a suspendable node: checkpoint state and dispatch a delayed resume job.
     *
     * Returns the Suspension if the execution was suspended, or null if the handler
     * doesn't implement SuspendsExecution (caller should fall back to inline execution).
     */
    private function handleSuspension(
        string $nodeId,
        WorkflowGraph $graph,
        RunContext $context,
        Execution $execution,
    ): ?Suspension {
        $node = $graph->getNode($nodeId);
        $type = $node['type'] ?? '';
        $handler = NodeRegistry::handler($type);

        if (! $handler instanceof SuspendsExecution) {
            return null;
        }

        // Build payload and get suspension details (do NOT execute the node)
        $nodePayloadFactory = app(\App\Engine\Execution\NodePayloadFactory::class);
        $nodePayload = $nodePayloadFactory->build($nodeId, $graph, $context);
        $suspension = $handler->suspend($nodePayload);

        // Record the node as completed with the suspension output
        $result = NodeResult::completed($suspension->nodeOutput);
        $sequence = $context->nextSequence();

        $this->batchWriter->record(
            executionId: $execution->id,
            nodeId: $nodeId,
            nodeRunKey: $nodeId,
            graph: $graph,
            result: $result,
            sequence: $sequence,
        );

        // Advance frontier past the suspending node
        $context->complete(
            nodeId: $nodeId,
            result: $result,
            activeBranches: $result->activeBranches,
        );

        // Flush all pending rows before suspending
        $this->batchWriter->flush();

        // Save checkpoint
        $this->checkpointStore->save($execution, $context, $suspension);

        // Transition execution to waiting
        $execution->markWaiting($suspension->resumeAt, [
            'suspend_reason' => $suspension->reason,
            'suspended_node' => $nodeId,
        ]);

        $this->ssePublisher->event($execution->id, 'execution.suspended', [
            'node_id' => $nodeId,
            'reason' => $suspension->reason,
            'resume_at' => $suspension->resumeAt->toIso8601String(),
        ]);

        // Dispatch delayed resume job
        $delaySeconds = max(0, (int) now()->diffInSeconds($suspension->resumeAt, false));

        ResumeWorkflowJob::dispatch($execution)
            ->delay(now()->addSeconds($delaySeconds));

        return $suspension;
    }

    /**
     * Apply the per-node on_error strategy to a failed NodeResult.
     *
     * Returns a routing NodeResult whose activeBranches drive frontier advancement:
     *
     *  stop                 – Fires ExecutionNodeFailed, then throws NodeFailedException.
     *                         The execution loop catches this and halts the workflow.
     *                         Never returns.
     *
     *  continue             – Returns NodeResult::completed([]) so context->complete()
     *                         advances all downstream success-path nodes with empty input.
     *                         The original failure is already persisted to DB by the caller.
     *
     *  continue_error_output – Returns NodeResult::errorOutput(errorData) which carries
     *                          activeBranches=['error']. context->complete() will only
     *                          advance edges whose sourceHandle === 'error', letting the
     *                          user's error sub-flow handle the failure.
     *
     * @throws NodeFailedException  when behavior is `stop`
     */
    private function applyOnErrorBehavior(
        string $nodeId,
        NodeResult $result,
        WorkflowGraph $graph,
        RunContext $context,
        Execution $execution,
    ): NodeResult {
        $node = $graph->getNode($nodeId) ?? [];
        $behavior = OnErrorBehavior::fromNode($node);
        $nodeType = $node['type'] ?? 'unknown';
        $errorMessage = $result->error['message'] ?? 'Node execution failed.';

        return match ($behavior) {

            // ── stop ─────────────────────────────────────────────────────────
            OnErrorBehavior::Stop => (function () use (
                $nodeId, $nodeType, $errorMessage, $result, $node, $graph, $context, $execution,
            ): never {
                // Collect payload for the event (best-effort — may fail for broken nodes)
                try {
                    $nodePayloadFactory = app(\App\Engine\Execution\NodePayloadFactory::class);
                    $payload = $nodePayloadFactory->build($nodeId, $graph, $context);
                    $config = $payload->config;
                    $inputData = $payload->inputData;
                } catch (\Throwable) {
                    $config = $node['config'] ?? [];
                    $inputData = [];
                }

                ExecutionNodeFailed::dispatch(
                    $execution,
                    $nodeId,
                    $nodeType,
                    $errorMessage,
                    $config,
                    $inputData,
                );

                throw new NodeFailedException(
                    nodeId: $nodeId,
                    nodeType: $nodeType,
                    reason: $errorMessage,
                    errorData: $result->error,
                );
            })(),

            // ── continue ─────────────────────────────────────────────────────
            // Treat the node as if it produced empty output; all success-path
            // downstream nodes still run (with empty input from this node).
            OnErrorBehavior::Continue => (function () use (
                $nodeId, $result, $execution,
            ): NodeResult {
                Log::warning("Node [{$nodeId}] failed — continuing (on_error=continue).", [
                    'execution_id' => $execution->id,
                    'error' => $result->error,
                ]);

                // activeBranches = null → resolveSuccessors returns all successors.
                // Empty output so downstream nodes receive nothing from this node.
                return NodeResult::completed(
                    output: [],
                    durationMs: $result->durationMs ?? 0,
                );
            })(),

            // ── continue_error_output ─────────────────────────────────────────
            // Route error data only to edges whose sourceHandle === 'error'.
            // Success-path edges are NOT activated.
            OnErrorBehavior::ContinueErrorOutput => (function () use (
                $nodeId, $result, $execution,
            ): NodeResult {
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

    /**
     * Check if the execution has been cancelled via a Redis flag.
     */
    private function isCancelled(int $executionId): bool
    {
        try {
            return (bool) Redis::get("engine:cancel:{$executionId}");
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if any completed nodes have failed status.
     */
    private function hasFailedNodes(RunContext $context): bool
    {
        foreach ($context->getCompletedNodes() as $result) {
            if ($result->status === ExecutionNodeStatus::Failed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Load workspace variables for the execution.
     *
     * @return array<string, mixed>
     */
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

    /**
     * Load credentials for the execution and perform auto-refresh if necessary.
     *
     * @return array<string, \App\Models\Credential>
     */
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
