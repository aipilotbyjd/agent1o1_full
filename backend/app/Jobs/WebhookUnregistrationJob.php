<?php

namespace App\Jobs;

use App\Models\Workflow;
use App\Services\WebhookAutoRegistrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * WebhookUnregistrationJob — paired with WebhookRegistrationJob.
 *
 * When a workflow is deactivated, we need to tell GitHub/Stripe to STOP
 * sending events to our webhook URL. Without this, GitHub keeps POSTing
 * to your server for events on deactivated workflows — wasted processing.
 *
 * Like registration, this is done async so the user's deactivation request
 * returns immediately while cleanup happens in the background.
 *
 *   User clicks "Deactivate"
 *   → workflow.is_active = false
 *   → workflow.webhook_status = 'deregistering'
 *   → dispatch this job
 *   → return response immediately
 *         ↓ (async)
 *   WebhookUnregistrationJob
 *   → call GitHub/Stripe DELETE API
 *   → clear external_webhook_id from DB
 *   → webhook_status = null
 *         ↓ (on permanent failure after all retries)
 *   failed()
 *   → webhook_status = 'failed'
 *   → store error message for user visibility
 */
class WebhookUnregistrationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 5 attempts: 30s → 2m → 5m → 10m → 30m.
     * Unregistration is best-effort — a permanent failure just means
     * the provider keeps sending events to our dead endpoint (safe,
     * we verify signatures and discard inactive workflow events).
     *
     * @return array<int, int>
     */
    public int $tries = 5;

    public function backoff(): array
    {
        return [30, 120, 300, 600, 1800];
    }

    public function __construct(private readonly string $workflowId)
    {
        $this->onQueue('maintenance');
    }

    public function handle(WebhookAutoRegistrationService $service): void
    {
        $workflow = Workflow::find($this->workflowId);

        if (! $workflow) {
            return;
        }

        try {
            $service->unregisterForWorkflow($workflow);

            $workflow->update([
                'webhook_status' => null,
                'webhook_status_message' => null,
            ]);

            Log::info('WebhookUnregistrationJob: unregistration complete', ['workflow_id' => $this->workflowId]);
        } catch (\Throwable $e) {
            Log::error('WebhookUnregistrationJob: failed to unregister', [
                'workflow_id' => $this->workflowId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Called by the queue after all retry attempts are exhausted.
     *
     * Marks the workflow's webhook_status as 'failed' so the user can see
     * that unregistration did not complete and the provider may still be
     * sending events to the (now inactive) callback URL.
     */
    public function failed(\Throwable $exception): void
    {
        $workflow = Workflow::find($this->workflowId);

        $workflow?->update([
            'webhook_status' => 'failed',
            'webhook_status_message' => 'Webhook unregistration failed after multiple attempts: '.$exception->getMessage(),
        ]);

        Log::error('WebhookUnregistrationJob: permanently failed after all retries', [
            'workflow_id' => $this->workflowId,
            'error' => $exception->getMessage(),
        ]);
    }
}
