<?php

namespace App\Engine\Nodes\Flow;

use App\Engine\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\Execution\NodePayload;
use App\Enums\ExecutionNodeStatus;

/**
 * Batch Processor Node
 * 
 * Process items in configurable batches with commits and error handling.
 */
class BatchProcessorNode implements NodeHandler
{
    public function handle(NodePayload $payload): NodeResult
    {
        $startTime = hrtime(true);

        // Configuration
        $items = $payload->inputData['items'] ?? [];
        $batchSize = (int) ($payload->config['batch_size'] ?? 100);
        $pauseBetweenBatches = (int) ($payload->config['pause_ms'] ?? 0);
        $commitEachBatch = (bool) ($payload->config['commit_each_batch'] ?? true);
        $stopOnError = (bool) ($payload->config['stop_on_error'] ?? false);
        $maxRetries = (int) ($payload->config['max_retries'] ?? 0);

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        if ($batchSize < 1) {
            throw new \InvalidArgumentException('Batch size must be at least 1');
        }

        // Split items into batches
        $batches = array_chunk($items, $batchSize);
        $totalBatches = count($batches);

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        // Return batch processing configuration
        return new NodeResult(
            status: ExecutionNodeStatus::Completed,
            output: [
                'total_items' => count($items),
                'batch_size' => $batchSize,
                'total_batches' => $totalBatches,
                'pause_ms' => $pauseBetweenBatches,
                'commit_each_batch' => $commitEachBatch,
                'stop_on_error' => $stopOnError,
                'max_retries' => $maxRetries,
                'batches' => $batches,
            ],
            durationMs: $durationMs,
        );
    }
}
