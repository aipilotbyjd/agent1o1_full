<?php

namespace App\Engine\Sse;

use Illuminate\Support\Facades\Redis;

/**
 * Publishes real-time SSE events to Redis streams and PubSub channels.
 *
 * All per-node events are throttled: once the node event count for an
 * execution exceeds the configured threshold, individual node events are
 * suppressed. Execution-level events (started, completed, failed, etc.)
 * are always published regardless of threshold.
 */
class SsePublisher
{
    /** @var array<int, int> executionId → last EXPIRE refresh timestamp */
    private array $lastExpireRefresh = [];

    /** @var array<int, int> executionId → number of node SSE events published */
    private array $nodeSseCount = [];

    /**
     * Publish a node_started event if under the threshold.
     *
     * @param  array<string, mixed>  $data
     */
    public function nodeStarted(int $executionId, array $data = []): void
    {
        if (! $this->underThreshold($executionId)) {
            return;
        }

        $this->nodeSseCount[$executionId] = ($this->nodeSseCount[$executionId] ?? 0) + 1;
        $this->publish($executionId, 'execution.node_started', $data);
    }

    /**
     * Publish a node_completed event if under the threshold.
     *
     * @param  array<string, mixed>  $data
     */
    public function nodeCompleted(int $executionId, array $data = []): void
    {
        if (! $this->underThreshold($executionId)) {
            return;
        }

        $this->nodeSseCount[$executionId] = ($this->nodeSseCount[$executionId] ?? 0) + 1;
        $this->publish($executionId, 'execution.node_completed', $data);
    }

    /**
     * Publish an execution-level event (always sent, no threshold check).
     *
     * @param  array<string, mixed>  $data
     */
    public function event(int $executionId, string $event, array $data = []): void
    {
        $this->publish($executionId, $event, $data);
    }

    /**
     * Whether per-node SSE events should still be published for this execution.
     *
     * For very large workflows, publishing an event for every node start/complete
     * generates hundreds of Redis calls and slows execution. Once the node event
     * count exceeds the configured threshold we suppress individual node events.
     */
    private function underThreshold(int $executionId): bool
    {
        $threshold = (int) config('workflow.sse_node_event_threshold', 200);

        if ($threshold <= 0) {
            return false;
        }

        return ($this->nodeSseCount[$executionId] ?? 0) < $threshold;
    }

    /**
     * Publish a raw SSE event via Redis pipeline (XADD + PUBLISH).
     *
     * @param  array<string, mixed>  $data
     */
    private function publish(int $executionId, string $event, array $data): void
    {
        $payload = json_encode([
            'event' => $event,
            'execution_id' => $executionId,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            $streamKey = "execution:{$executionId}:events";
            $pubsubChannel = "linkflow:execution:{$executionId}:live";
            $client = Redis::connection()->client();

            $client->pipeline(function ($pipe) use ($streamKey, $pubsubChannel, $payload) {
                $pipe->xadd($streamKey, '*', ['payload' => $payload]);
                $pipe->publish($pubsubChannel, $payload);
            });

            if (($this->lastExpireRefresh[$executionId] ?? 0) < time() - 30) {
                $client->expire($streamKey, 300);
                $this->lastExpireRefresh[$executionId] = time();
            }
        } catch (\Throwable) {
            // SSE publishing is best-effort
        }
    }
}
