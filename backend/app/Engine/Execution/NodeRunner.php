<?php

namespace App\Engine\Execution;

use App\Engine\Graph\WorkflowGraph;
use App\Engine\NodeCatalog;
use App\Engine\NodeInput;
use App\Engine\NodeResult;
use App\Engine\WorkflowContext;
use App\Enums\NodeType;
use App\Exceptions\NodeFailedException;
use Illuminate\Support\Facades\Concurrency;

/**
 * Classifies and executes nodes: sync (inline), async (concurrent), or blocking (suspendable).
 *
 * Merges ExecutionScheduler, SyncExecutor, and AsyncExecutor into one class.
 */
class NodeRunner
{
    private int $maxConcurrency;

    public function __construct(?int $maxConcurrency = null)
    {
        $this->maxConcurrency = $maxConcurrency ?? (int) config('workflow.async_max_concurrency', 4);
    }

    /**
     * Partition a list of ready node IDs into three groups.
     *
     * @param  list<string>  $nodeIds
     * @return array{list<string>, list<string>, list<string>}  [sync, async, blocking]
     */
    public function partition(array $nodeIds, WorkflowGraph $graph): array
    {
        $sync = [];
        $async = [];
        $blocking = [];

        foreach ($nodeIds as $nodeId) {
            $node = $graph->getNode($nodeId);
            $type = $node['type'] ?? '';
            $nodeType = NodeType::tryFrom($type);

            if ($nodeType === null) {
                if (NodeCatalog::isAppNode($type)) {
                    $async[] = $nodeId;
                } else {
                    $sync[] = $nodeId;
                }

                continue;
            }

            if ($nodeType->isSuspendable()) {
                $blocking[] = $nodeId;
            } elseif ($nodeType->isSync()) {
                $sync[] = $nodeId;
            } else {
                $async[] = $nodeId;
            }
        }

        return [$sync, $async, $blocking];
    }

    /**
     * Execute a single sync node inline.
     */
    public function runSync(string $nodeId, WorkflowGraph $graph, WorkflowContext $context): NodeResult
    {
        $node = $graph->getNode($nodeId);
        $type = $node['type'] ?? '';

        $handler = NodeCatalog::handler($type);

        if ($handler === null) {
            return NodeResult::failed("Unknown node type [{$type}] for node [{$nodeId}].", 'UNKNOWN_TYPE');
        }

        $input = NodeInput::build($nodeId, $graph, $context);

        try {
            return $handler->handle($input);
        } catch (\Throwable $e) {
            throw new NodeFailedException(
                nodeId: $nodeId,
                nodeType: $node['type'] ?? 'unknown',
                reason: $e->getMessage(),
                errorData: ['exception' => get_class($e), 'trace' => $e->getTraceAsString()],
                previous: $e,
            );
        }
    }

    /**
     * Execute a batch of sync nodes sequentially.
     *
     * @param  list<string>  $nodeIds
     * @return array<string, NodeResult>
     */
    public function runSyncBatch(array $nodeIds, WorkflowGraph $graph, WorkflowContext $context): array
    {
        $results = [];

        foreach ($nodeIds as $nodeId) {
            try {
                $results[$nodeId] = $this->runSync($nodeId, $graph, $context);
            } catch (NodeFailedException $e) {
                $results[$nodeId] = NodeResult::failed($e->getMessage(), 'NODE_EXECUTION_ERROR');
            }
        }

        return $results;
    }

    /**
     * Run a batch of async nodes concurrently via Laravel Concurrency.
     *
     * @param  list<string>  $nodeIds
     * @return array<string, NodeResult>
     */
    public function runAsyncBatch(array $nodeIds, WorkflowGraph $graph, WorkflowContext $context): array
    {
        if (empty($nodeIds)) {
            return [];
        }

        // Small batches run inline — avoids child-process overhead
        if (count($nodeIds) <= 3) {
            return $this->runSequentialInline($nodeIds, $graph, $context);
        }

        // Build all inputs BEFORE launching concurrent work (reads from mutable context)
        $inputs = [];
        foreach ($nodeIds as $nodeId) {
            $inputs[$nodeId] = NodeInput::build($nodeId, $graph, $context);
        }

        $chunks = array_chunk($nodeIds, $this->maxConcurrency, true);
        $allResults = [];

        foreach ($chunks as $chunk) {
            $chunkResults = $this->executeChunk(array_values($chunk), $inputs);
            $allResults = array_merge($allResults, $chunkResults);
        }

        return $allResults;
    }

    /**
     * @param  list<string>  $nodeIds
     * @return array<string, NodeResult>
     */
    private function runSequentialInline(array $nodeIds, WorkflowGraph $graph, WorkflowContext $context): array
    {
        $results = [];

        foreach ($nodeIds as $nodeId) {
            $input = NodeInput::build($nodeId, $graph, $context);

            try {
                $handler = NodeCatalog::handler($input->nodeType);

                if ($handler === null) {
                    $results[$nodeId] = NodeResult::failed("Unknown node type [{$input->nodeType}].", 'UNKNOWN_TYPE');

                    continue;
                }

                $results[$nodeId] = $handler->handle($input);
            } catch (\Throwable $e) {
                $results[$nodeId] = NodeResult::failed($e->getMessage(), 'NODE_EXECUTION_ERROR');
            }
        }

        return $results;
    }

    /**
     * Execute a chunk of nodes concurrently via child processes.
     *
     * @param  list<string>  $chunk
     * @param  array<string, NodeInput>  $inputs
     * @return array<string, NodeResult>
     */
    private function executeChunk(array $chunk, array $inputs): array
    {
        $workerCount = min($this->maxConcurrency, count($chunk));
        $batches = array_chunk($chunk, (int) ceil(count($chunk) / $workerCount));

        $tasks = [];

        foreach ($batches as $batch) {
            $serializedBatch = [];

            foreach ($batch as $nodeId) {
                $input = $inputs[$nodeId];
                $serializedBatch[] = [
                    'node_id' => $input->nodeId,
                    'node_type' => $input->nodeType,
                    'node_name' => $input->nodeName,
                    'config' => $input->config,
                    'input_data' => $input->inputData,
                    'credentials' => $input->credentials,
                    'variables' => $input->variables,
                    'execution_meta' => $input->executionMeta,
                    'node_run_key' => $input->nodeRunKey,
                ];
            }

            $tasks[] = function () use ($serializedBatch) {
                $results = [];

                foreach ($serializedBatch as $raw) {
                    $input = new \App\Engine\NodeInput(
                        nodeId: $raw['node_id'],
                        nodeType: $raw['node_type'],
                        nodeName: $raw['node_name'],
                        config: $raw['config'],
                        inputData: $raw['input_data'],
                        credentials: $raw['credentials'],
                        variables: $raw['variables'],
                        executionMeta: $raw['execution_meta'],
                        nodeRunKey: $raw['node_run_key'],
                    );

                    $handler = \App\Engine\NodeCatalog::handler($input->nodeType);

                    if ($handler === null) {
                        $results[$input->nodeId] = [
                            'status' => 'failed',
                            'output' => null,
                            'error' => ['message' => "Unknown node type [{$input->nodeType}].", 'code' => 'UNKNOWN_TYPE'],
                            'duration_ms' => 0,
                            'active_branches' => null,
                            'loop_items' => null,
                        ];

                        continue;
                    }

                    try {
                        $result = $handler->handle($input);
                        $results[$input->nodeId] = $result->toArray();
                    } catch (\Throwable $e) {
                        $results[$input->nodeId] = [
                            'status' => 'failed',
                            'output' => null,
                            'error' => ['message' => $e->getMessage(), 'code' => 'NODE_EXECUTION_ERROR'],
                            'duration_ms' => 0,
                            'active_branches' => null,
                            'loop_items' => null,
                        ];
                    }
                }

                return $results;
            };
        }

        try {
            $rawBatches = Concurrency::driver('process')->run($tasks);
        } catch (\Throwable) {
            return $this->fallbackSequential($chunk, $inputs);
        }

        $results = [];

        foreach ($rawBatches as $batchResults) {
            foreach ($batchResults as $nodeId => $rawResult) {
                $results[$nodeId] = NodeResult::fromArray($rawResult);
            }
        }

        return $results;
    }

    /**
     * @param  list<string>  $chunk
     * @param  array<string, NodeInput>  $inputs
     * @return array<string, NodeResult>
     */
    private function fallbackSequential(array $chunk, array $inputs): array
    {
        $results = [];

        foreach ($chunk as $nodeId) {
            $input = $inputs[$nodeId];

            try {
                $handler = NodeCatalog::handler($input->nodeType);

                if ($handler === null) {
                    $results[$nodeId] = NodeResult::failed("Unknown node type [{$input->nodeType}].", 'UNKNOWN_TYPE');

                    continue;
                }

                $results[$nodeId] = $handler->handle($input);
            } catch (\Throwable $e) {
                $results[$nodeId] = NodeResult::failed($e->getMessage(), 'NODE_EXECUTION_ERROR');
            }
        }

        return $results;
    }
}
