<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\NodeInput;
use App\Enums\ExecutionNodeStatus;

/**
 * Retry Node
 * 
 * Retry failed operations with configurable backoff strategies.
 */
class RetryNode implements NodeHandler
{
    public function handle(NodeInput $payload): NodeResult
    {
        $startTime = hrtime(true);

        // Configuration
        $maxAttempts = (int) ($payload->config['max_attempts'] ?? 3);
        $initialDelay = (int) ($payload->config['initial_delay_ms'] ?? 1000);
        $maxDelay = (int) ($payload->config['max_delay_ms'] ?? 60000);
        $backoffStrategy = $payload->config['backoff_strategy'] ?? 'exponential'; // exponential | linear | fixed
        $backoffMultiplier = (float) ($payload->config['backoff_multiplier'] ?? 2.0);
        $jitter = (bool) ($payload->config['jitter'] ?? true);
        $retryOnErrors = $payload->config['retry_on_errors'] ?? ['all']; // Error codes to retry on
        $abortOnErrors = $payload->config['abort_on_errors'] ?? []; // Error codes to abort immediately

        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('Max attempts must be at least 1');
        }

        // Calculate delays for each retry attempt
        $delays = $this->calculateDelays(
            $maxAttempts,
            $initialDelay,
            $maxDelay,
            $backoffStrategy,
            $backoffMultiplier,
            $jitter
        );

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return new NodeResult(
            status: ExecutionNodeStatus::Completed,
            output: [
                'retry_config' => [
                    'max_attempts' => $maxAttempts,
                    'backoff_strategy' => $backoffStrategy,
                    'delays' => $delays,
                    'retry_on_errors' => $retryOnErrors,
                    'abort_on_errors' => $abortOnErrors,
                ],
                'total_max_delay' => array_sum($delays),
            ],
            durationMs: $durationMs,
        );
    }

    /**
     * Calculate delay for each retry attempt
     */
    private function calculateDelays(
        int $maxAttempts,
        int $initialDelay,
        int $maxDelay,
        string $strategy,
        float $multiplier,
        bool $jitter
    ): array {
        $delays = [];

        for ($attempt = 1; $attempt < $maxAttempts; $attempt++) {
            $delay = match ($strategy) {
                'exponential' => min($initialDelay * pow($multiplier, $attempt - 1), $maxDelay),
                'linear' => min($initialDelay * $attempt, $maxDelay),
                'fixed' => $initialDelay,
                default => $initialDelay,
            };

            // Add jitter to prevent thundering herd
            if ($jitter) {
                $delay = (int) ($delay * (0.5 + (mt_rand() / mt_getrandmax()) * 0.5));
            }

            $delays[] = (int) $delay;
        }

        return $delays;
    }
}
