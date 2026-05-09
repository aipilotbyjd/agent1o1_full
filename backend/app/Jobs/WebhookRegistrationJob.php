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
 * WebhookRegistrationJob — Layer 2 of the async webhook architecture.
 *
 * WHY THIS JOB EXISTS
 * -------------------
 * When Workflow::activate() is called, it needs to register webhooks with
 * external services (GitHub, Stripe, etc.). These are outbound API calls
 * to third-party servers that can be slow (200-2000ms) or fail.
 *
 * Without this job, those API calls happen INLINE inside the user's
 * HTTP request to activate the workflow:
 *   User clicks "Activate" → request hangs while GitHub API is called → GitHub slow → user sees timeout
 *
 * WITH THIS JOB
 * -------------
 * Activation is split into two phases:
 *
 *   Phase 1 (synchronous, fast):
 *     User clicks "Activate"
 *     → workflow.is_active = true
 *     → workflow.webhook_status = 'pending'
 *     → dispatch this job
 *     → return response immediately (<50ms)
 *
 *   Phase 2 (async, on queue):
 *     WebhookRegistrationJob::handle()
 *     → call GitHub/Stripe API to register webhook URL
 *     → on success: webhook_status = 'active'
 *     → on failure: webhook_status = 'failed', store error message
 *
 * The user sees the workflow as active immediately. If registration fails,
 * the status message explains why and they can retry.
 *
 * RETRIES
 * -------
 * 3 attempts with 30-second backoff handles transient GitHub/Stripe outages.
 */
class WebhookRegistrationJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 5 attempts total with exponential backoff:
     *   30s → 2m → 5m → 10m → 30m
     *
     * This covers transient GitHub/Stripe/Slack 503s (30s retry) through
     * extended outages (30m retry). After 5 failures the job goes to
     * failed_jobs and webhook_status is set to 'failed' by failed().
     */
    public int $tries = 5;

    /**
     * @return array<int, int>
     */
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

        if (! $workflow->is_active) {
            Log::info('WebhookRegistrationJob: workflow deactivated before registration could complete', [
                'workflow_id' => $this->workflowId,
            ]);

            return;
        }

        try {
            $service->registerForWorkflow($workflow);

            $workflow->update([
                'webhook_status' => 'active',
                'webhook_status_message' => null,
            ]);

            Log::info('WebhookRegistrationJob: registration complete', ['workflow_id' => $this->workflowId]);
        } catch (\Throwable $e) {
            $workflow->update([
                'webhook_status' => 'failed',
                'webhook_status_message' => $e->getMessage(),
            ]);

            Log::error('WebhookRegistrationJob: registration failed', [
                'workflow_id' => $this->workflowId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $workflow = Workflow::find($this->workflowId);

        $workflow?->update([
            'webhook_status' => 'failed',
            'webhook_status_message' => 'Webhook registration failed after multiple attempts: '.$exception->getMessage(),
        ]);
    }
}
