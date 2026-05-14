<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\NodeInput;
use App\Enums\ExecutionNodeStatus;

/**
 * Wait for Event Node
 * 
 * Pauses workflow execution until an external event is received.
 * Supports webhooks, signals, and timeouts.
 */
class WaitForEventNode implements NodeHandler
{
    public function handle(NodeInput $payload): NodeResult
    {
        $startTime = hrtime(true);

        // Configuration
        $eventType = $payload->config['event_type'] ?? 'webhook'; // webhook | signal | timeout
        $eventId = $payload->config['event_id'] ?? null;
        $timeout = (int) ($payload->config['timeout_seconds'] ?? 3600); // 1 hour default
        $timeoutAction = $payload->config['timeout_action'] ?? 'fail'; // fail | continue | retry

        if (empty($eventId)) {
            $eventId = uniqid('event_', true);
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        // Return event waiting configuration
        // The workflow engine will pause execution here and wait for the event
        return new NodeResult(
            status: ExecutionNodeStatus::Completed,
            output: [
                'event_type' => $eventType,
                'event_id' => $eventId,
                'timeout_seconds' => $timeout,
                'timeout_action' => $timeoutAction,
                'waiting_for_event' => true,
                'webhook_url' => $eventType === 'webhook' ? url("/api/v1/events/{$eventId}") : null,
            ],
            durationMs: $durationMs,
        );
    }
}
