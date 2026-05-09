<?php

namespace App\Engine\Nodes\Flow;

use App\Engine\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\Execution\NodePayload;
use App\Enums\ExecutionNodeStatus;

/**
 * Advanced Loop/Iterator Node
 * 
 * Iterates over a collection with support for:
 * - Multiple execution modes (serial, parallel, batched)
 * - Rate limiting and throttling
 * - Per-iteration error handling
 * - Break conditions
 * - Max concurrency control
 */
class LoopNode implements NodeHandler
{
    public function handle(NodePayload $payload): NodeResult
    {
        $startTime = hrtime(true);

        // Extract configuration
        $sourceField = $payload->config['source'] ?? 'items';
        $mode = $payload->config['mode'] ?? 'serial'; // serial, parallel, batched
        $batchSize = (int) ($payload->config['batch_size'] ?? 10);
        $maxIterations = (int) ($payload->config['max_iterations'] ?? null);
        $delayMs = (int) ($payload->config['delay_ms'] ?? 0);
        $maxConcurrency = (int) ($payload->config['max_concurrency'] ?? 5);
        $onError = $payload->config['on_error'] ?? 'stop'; // stop, continue, fail_after_n
        $failAfterErrors = (int) ($payload->config['fail_after_errors'] ?? 3);
        $breakCondition = $payload->config['break_condition'] ?? null; // expression

        // Get items to iterate
        $items = data_get($payload->inputData, $sourceField, []);

        if (! is_array($items)) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed(
                "Loop source '{$sourceField}' is not an array.",
                'LOOP_INVALID_SOURCE',
                $durationMs,
            );
        }

        // Apply max iterations limit
        if ($maxIterations && count($items) > $maxIterations) {
            $items = array_slice($items, 0, $maxIterations);
        }

        $itemCount = count($items);

        // Validate configuration
        if ($mode === 'batched' && $batchSize < 1) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed(
                'Batch size must be at least 1.',
                'LOOP_INVALID_BATCH_SIZE',
                $durationMs,
            );
        }

        if ($mode === 'parallel' && $maxConcurrency < 1) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed(
                'Max concurrency must be at least 1.',
                'LOOP_INVALID_CONCURRENCY',
                $durationMs,
            );
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        // Return loop configuration for the engine to process
        // The actual loop execution is handled by the workflow engine
        // This node just configures how the loop should behave
        return new NodeResult(
            status: ExecutionNodeStatus::Completed,
            output: [
                'item_count' => $itemCount,
                'mode' => $mode,
                'batch_size' => $batchSize,
                'max_concurrency' => $maxConcurrency,
                'delay_ms' => $delayMs,
                'error_handling' => $onError,
                'break_condition' => $breakCondition,
            ],
            durationMs: $durationMs,
            loopItems: array_values($items),
        );
    }

    /**
     * Process items in serial mode (one at a time, in order)
     * This is a helper method that could be used if the engine delegates execution
     */
    private function processSerial(array $items, int $delayMs, callable $processor): array
    {
        $results = [];
        $errorCount = 0;

        foreach ($items as $index => $item) {
            try {
                $result = $processor($item, $index);
                $results[] = ['success' => true, 'index' => $index, 'result' => $result];
            } catch (\Throwable $e) {
                $errorCount++;
                $results[] = [
                    'success' => false,
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }

            // Apply delay between iterations (rate limiting)
            if ($delayMs > 0 && $index < count($items) - 1) {
                usleep($delayMs * 1000);
            }
        }

        return $results;
    }

    /**
     * Process items in parallel mode (up to max_concurrency at once)
     */
    private function processParallel(array $items, int $maxConcurrency, callable $processor): array
    {
        // In a real implementation, this would use process pools or async workers
        // For now, this is a placeholder showing the intent
        $results = [];
        $chunks = array_chunk($items, $maxConcurrency);

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $itemIndex => $item) {
                $globalIndex = ($chunkIndex * $maxConcurrency) + $itemIndex;
                try {
                    $result = $processor($item, $globalIndex);
                    $results[] = ['success' => true, 'index' => $globalIndex, 'result' => $result];
                } catch (\Throwable $e) {
                    $results[] = [
                        'success' => false,
                        'index' => $globalIndex,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Process items in batched mode (process N items as a batch)
     */
    private function processBatched(array $items, int $batchSize, int $delayMs, callable $batchProcessor): array
    {
        $results = [];
        $batches = array_chunk($items, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            try {
                $result = $batchProcessor($batch, $batchIndex);
                $results[] = [
                    'success' => true,
                    'batch_index' => $batchIndex,
                    'batch_size' => count($batch),
                    'result' => $result,
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'success' => false,
                    'batch_index' => $batchIndex,
                    'error' => $e->getMessage(),
                ];
            }

            // Apply delay between batches
            if ($delayMs > 0 && $batchIndex < count($batches) - 1) {
                usleep($delayMs * 1000);
            }
        }

        return $results;
    }

    /**
     * Evaluate break condition using simple expression evaluation
     */
    private function shouldBreak(?string $condition, $item, int $index, array $context): bool
    {
        if (! $condition) {
            return false;
        }

        // Simple expression evaluation
        // In production, this would use the ExpressionParser
        // For now, support basic comparisons like "index > 10" or "item.value == 'stop'"
        
        try {
            // This is a placeholder - real implementation would use the expression engine
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
