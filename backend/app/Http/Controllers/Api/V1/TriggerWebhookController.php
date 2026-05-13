<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Trigger;
use App\Services\TriggerExecutionService;
use App\Services\TriggerRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TriggerWebhookController extends Controller
{
    public function __construct(
        private TriggerExecutionService $executionService,
        private TriggerRegistrationService $registrationService,
    ) {}

    /**
     * Handle incoming webhook from any service
     * Route: POST /api/v1/webhooks/{webhook_uuid}
     */
    public function receive(string $webhookUuid, Request $request): JsonResponse
    {
        // Find trigger by webhook UUID
        $trigger = Trigger::where('webhook_uuid', $webhookUuid)
            ->where('is_published', true)
            ->first();

        if (!$trigger) {
            Log::warning('Webhook received for unknown trigger', ['uuid' => $webhookUuid]);
            return response()->json(['error' => 'Webhook not found'], 404);
        }

        // Handle Slack challenge (special case - must respond immediately)
        if ($this->isSlackChallenge($request)) {
            return response()->json(['challenge' => $request->json('challenge')]);
        }

        // Verify webhook signature
        if (!$this->verifySignature($trigger, $request)) {
            Log::warning('Webhook signature verification failed', [
                'trigger_id' => $trigger->id,
                'service' => $trigger->triggerCategory->slug,
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Extract payload based on service
        $payload = $this->extractPayload($trigger, $request);

        // Queue the execution (don't block the webhook response)
        try {
            $this->executionService->handleWebhookEvent($trigger, $payload);

            Log::info('Webhook processed successfully', [
                'trigger_id' => $trigger->id,
                'service' => $trigger->triggerCategory->slug,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Webhook received and queued for processing',
            ], 202);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Webhook processing failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check endpoint for webhook services
     * Some services (Slack, etc.) require periodic health checks
     */
    public function healthCheck(string $webhookUuid): JsonResponse
    {
        $trigger = Trigger::where('webhook_uuid', $webhookUuid)
            ->where('is_published', true)
            ->first();

        if (!$trigger) {
            return response()->json(['error' => 'Webhook not found'], 404);
        }

        return response()->json([
            'status' => 'healthy',
            'webhook_uuid' => $webhookUuid,
            'service' => $trigger->triggerCategory->slug,
            'last_triggered_at' => $trigger->last_triggered_at,
        ]);
    }

    /**
     * Verify webhook signature using service-specific verification
     */
    private function verifySignature(Trigger $trigger, Request $request): bool
    {
        $signature = $this->extractSignature($trigger, $request);

        if (!$signature) {
            // Some services don't require signatures
            // Check service-specific requirements
            if (in_array($trigger->triggerCategory->slug, ['github', 'slack', 'stripe'])) {
                return false;
            }
            return true;
        }

        return $this->registrationService->verifyWebhookSignature(
            $trigger,
            $request->getContent(),
            $signature
        );
    }

    /**
     * Extract signature from request based on service
     */
    private function extractSignature(Trigger $trigger, Request $request): ?string
    {
        $service = $trigger->triggerCategory->slug;

        return match ($service) {
            'github' => $request->header('X-Hub-Signature-256'),
            'slack' => $request->header('X-Slack-Request-Timestamp').'|'.$request->header('X-Slack-Signature'),
            'stripe' => $request->header('Stripe-Signature'),
            default => null,
        };
    }

    /**
     * Extract payload from request based on service
     */
    private function extractPayload(Trigger $trigger, Request $request): array
    {
        $service = $trigger->triggerCategory->slug;

        return match ($service) {
            'slack' => $this->extractSlackPayload($request),
            'github', 'stripe' => $request->json() ?? [],
            default => $request->all(),
        };
    }

    /**
     * Extract Slack-specific payload
     */
    private function extractSlackPayload(Request $request): array
    {
        $json = $request->json();

        // Slack sends events inside an 'event' wrapper
        if (isset($json['event'])) {
            return $json['event'];
        }

        return $json ?? [];
    }

    /**
     * Check if this is a Slack URL verification challenge
     */
    private function isSlackChallenge(Request $request): bool
    {
        return $request->json('type') === 'url_verification';
    }
}
