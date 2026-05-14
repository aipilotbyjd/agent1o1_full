<?php

namespace App\Engine\Execution;

use App\Engine\NodeResult;
use App\Engine\WorkflowContext;
use App\Engine\Graph\WorkflowGraph;
use Illuminate\Support\Facades\DB;

class ExecutionWriter
{
    /** @var list<array<string, mixed>> */
    private array $pendingRows = [];

    public function record(
        int $executionId,
        string $nodeId,
        string $nodeRunKey,
        WorkflowGraph $graph,
        NodeResult $result,
        int $sequence,
        ?int $loopIndex = null,
        ?string $parentFrame = null,
    ): void {
        $node = $graph->getNode($nodeId);

        $this->pendingRows[] = [
            'execution_id' => $executionId,
            'node_id' => $nodeId,
            'node_run_key' => $nodeRunKey,
            'node_type' => $node['type'] ?? 'unknown',
            'node_name' => $node['name'] ?? $node['data']['name'] ?? $nodeId,
            'status' => $result->status->value,
            'started_at' => now()->subMilliseconds($result->durationMs ?? 0),
            'finished_at' => now(),
            'duration_ms' => $result->durationMs,
            'input_data' => null,
            'output_data' => $result->output ? json_encode($result->output) : null,
            'error' => $result->error ? json_encode($result->error) : null,
            'sequence' => $sequence,
            'loop_index' => $loopIndex,
            'parent_frame' => $parentFrame,
        ];
    }

    public function flush(): int
    {
        if (empty($this->pendingRows)) {
            return 0;
        }

        $rows = $this->pendingRows;
        $this->pendingRows = [];

        DB::table('execution_nodes')->upsert(
            $rows,
            ['execution_id', 'node_run_key'],
            [
                'node_type', 'node_name', 'status',
                'started_at', 'finished_at', 'duration_ms',
                'output_data', 'error', 'sequence',
                'loop_index', 'parent_frame',
            ],
        );

        return count($rows);
    }

    public function flushIfNeeded(WorkflowContext $context): int
    {
        if (! $context->shouldFlush()) {
            return 0;
        }

        $flushed = $this->flush();
        $context->markFlushed();

        return $flushed;
    }

    public function pendingCount(): int
    {
        return count($this->pendingRows);
    }

    public function hasPending(): bool
    {
        return ! empty($this->pendingRows);
    }
}
