<?php

namespace App\Engine\Nodes\Flow;

use App\Engine\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\Execution\NodePayload;
use App\Enums\ExecutionNodeStatus;

/**
 * Try/Catch Node
 * 
 * Error handling node that wraps execution and handles failures gracefully.
 * Note: This node defines error handling strategy; actual execution
 * is handled by the workflow engine.
 */
class TryCatchNode implements NodeHandler
{
    public function handle(NodePayload $payload): NodeResult
    {
        $startTime = hrtime(true);

        // Configuration
        $catchErrors = $payload->config['catch_errors'] ?? true;
        $catchTypes = $payload->config['catch_types'] ?? ['all']; // all | specific error codes
        $onError = $payload->config['on_error'] ?? 'continue'; // continue | stop | retry
        $retryCount = (int) ($payload->config['retry_count'] ?? 0);
        $retryDelay = (int) ($payload->config['retry_delay_ms'] ?? 1000);
        $propagateErrors = (bool) ($payload->config['propagate_errors'] ?? false);
        $logErrors = (bool) ($payload->config['log_errors'] ?? true);
        $fallbackValue = $payload->config['fallback_value'] ?? null;

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        // Return error handling configuration
        // The workflow engine will use this to handle errors in child nodes
        return new NodeResult(
            status: ExecutionNodeStatus::Completed,
            output: [
                'error_handling' => [
                    'enabled' => $catchErrors,
                    'catch_types' => $catchTypes,
                    'on_error' => $onError,
                    'retry_count' => $retryCount,
                    'retry_delay_ms' => $retryDelay,
                    'propagate' => $propagateErrors,
                    'log_errors' => $logErrors,
                    'fallback_value' => $fallbackValue,
                ],
                'try_block_ready' => true,
            ],
            durationMs: $durationMs,
        );
    }
}
