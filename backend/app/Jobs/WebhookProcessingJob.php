<?php

namespace App\Jobs;

use App\Enums\ExecutionMode;
use App\Models\Webhook;
use App\Services\ExecutionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * WebhookProcessingJob — Layer 1 of the async webhook architecture.
 *
 * WHY THIS JOB EXISTS
 * -------------------
 * Without this job, the WebhookReceiverController processes everything
 * synchronously inside the HTTP request:
 *   GitHub POST → verify → find workflow → start engine → return response
 *
 * This causes two problems:
 *   1. If the queue is backed up and execution is slow, GitHub waits.
 *      After ~10 seconds it retries — causing DUPLICATE workflow executions.
 *   2. The server can only handle as many concurrent webhooks as it has
 *      PHP workers. Under load, webhooks start timing out.
 *
 * WITH THIS JOB
 * -------------
 * The controller does only two things: verify the signature + dispatch this job.
 * It returns HTTP 200 in under 10ms. GitHub never retries. No duplicates.
 * Throughput is now limited by queue workers, not PHP-FPM workers.
 *
 *   GitHub POST → verify → dispatch job → return 200 immediately
 *                                  ↓ (async, on queue)
 *                          WebhookProcessingJob
 *                            → find workflow
 *                            → start execution
 *
 * QUEUE CHOICE
 * ------------
 * Uses 'workflows-high' queue so webhooks are processed before
 * lower-priority background tasks like billing snapshots.
 */
class WebhookProcessingJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        private readonly int $webhookId,
        private readonly string $method,
        private readonly array $payload,
        private readonly array $headers,
    ) {
        $this->onQueue('workflows-high');
    }

    public function handle(ExecutionService $executionService): void
    {
        $webhook = Webhook::with(['workflow', 'workspace'])->find($this->webhookId);

        if (! $webhook) {
            Log::warning('WebhookProcessingJob: webhook not found', ['webhook_id' => $this->webhookId]);

            return;
        }

        if (! $webhook->is_active) {
            Log::info('WebhookProcessingJob: webhook is inactive, skipping', ['webhook_id' => $this->webhookId]);

            return;
        }

        $workflow = $webhook->workflow;

        if (! $workflow || ! $workflow->is_active) {
            Log::info('WebhookProcessingJob: workflow is inactive, skipping', ['webhook_id' => $this->webhookId]);

            return;
        }

        $triggerData = [
            'webhook_uuid' => $webhook->uuid,
            'method' => $this->method,
            'headers' => $this->headers,
            'body' => $this->payload,
        ];

        $triggerUser = $workflow->creator ?? $webhook->workspace?->owner;

        if (! $triggerUser) {
            Log::error('WebhookProcessingJob: could not resolve trigger user', [
                'webhook_id' => $this->webhookId,
                'workflow_id' => $workflow->id,
            ]);

            return;
        }

        $executionService->trigger(
            $workflow,
            $triggerUser,
            $triggerData,
            ExecutionMode::Webhook,
        );

        $webhook->incrementQuietly('call_count', 1, ['last_called_at' => now()]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('WebhookProcessingJob failed permanently', [
            'webhook_id' => $this->webhookId,
            'error' => $exception->getMessage(),
        ]);
    }
}
