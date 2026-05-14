<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Contracts\Suspendable;
use App\Engine\ExecutionPause;
use App\Engine\NodeResult;
use App\Engine\NodeInput;

/**
 * Pauses execution for a configured duration before continuing.
 *
 * Instead of blocking with sleep(), implements Suspendable to
 * checkpoint state and resume via a delayed queue job.
 */
class DelayNode implements NodeHandler, Suspendable
{
    public function handle(NodeInput $payload): NodeResult
    {
        $delaySeconds = (int) ($payload->config['delay_seconds'] ?? $payload->config['seconds'] ?? 0);

        return NodeResult::completed([
            'delayed_seconds' => $delaySeconds,
            'scheduled_at' => now()->toIso8601String(),
        ]);
    }

    public function suspend(NodeInput $payload): ExecutionPause
    {
        $delaySeconds = (int) ($payload->config['delay_seconds'] ?? $payload->config['seconds'] ?? 0);

        return new ExecutionPause(
            reason: 'delay',
            resumeAt: now()->addSeconds(max($delaySeconds, 0)),
            nodeOutput: [
                'delayed_seconds' => $delaySeconds,
                'scheduled_at' => now()->toIso8601String(),
            ],
        );
    }
}
