<?php

namespace App\Http\Controllers\Api\V1;

use App\Engine\WebhookRegistrars\WebhookRegistrarRegistry;
use App\Jobs\WebhookProcessingJob;
use App\Models\Webhook;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * WebhookReceiverController — the single entry point for ALL incoming webhooks.
 *
 * ═══════════════════════════════════════════════════════════════
 * ARCHITECTURE: WHY THIS CONTROLLER IS DELIBERATELY THIN
 * ═══════════════════════════════════════════════════════════════
 *
 * This controller does as little as possible, as fast as possible.
 * The goal: respond to the external service (GitHub, Stripe, Slack)
 * in under 50ms, always with HTTP 200.
 *
 * If we did heavy processing here (loading the workflow, starting the
 * engine, waiting for DB writes), two things break:
 *
 *   1. DUPLICATE EXECUTIONS — External services retry after ~10s if
 *      they don't get a 200. Slow processing = retry = two executions
 *      for one event.
 *
 *   2. THROUGHPUT COLLAPSE — PHP-FPM has a fixed worker pool. If each
 *      worker is held for 5 seconds processing a webhook, you can only
 *      handle N concurrent webhooks where N = worker count. With async
 *      dispatch, workers are free in <50ms and throughput is N * 100+.
 *
 * ═══════════════════════════════════════════════════════════════
 * THE THREE THINGS THIS CONTROLLER DOES
 * ═══════════════════════════════════════════════════════════════
 *
 *   1. SYNCHRONOUS HANDSHAKES — Some providers require an immediate
 *      synchronous response before any queuing:
 *        - Slack URL verification: responds with the challenge token
 *        - Discord PING: responds with {"type":1}
 *      These CANNOT go to a queue — the provider rejects the URL if
 *      the response is delayed.
 *
 *   2. SIGNATURE VERIFICATION — A fast HMAC or Ed25519 check to confirm
 *      the request is genuinely from the provider. Rejected immediately
 *      with 401 if invalid. Done here (not in the job) so that:
 *        a) Fake/malicious requests never touch the queue
 *        b) The response is fast enough that the provider doesn't retry
 *
 *   3. JOB DISPATCH — For all other requests, pack the payload into
 *      WebhookProcessingJob and dispatch to the high-priority queue.
 *      Return 200 immediately. The job handles the rest.
 *
 *      EXCEPTION: 'wait' response mode. When the webhook caller needs
 *      the workflow result back (e.g. a form that shows the response),
 *      we process synchronously via WebhookService. This is an explicit
 *      trade-off: 'wait' mode is inherently slower, and the caller knows it.
 *
 * ═══════════════════════════════════════════════════════════════
 * FLOW DIAGRAM
 * ═══════════════════════════════════════════════════════════════
 *
 *  Incoming POST /api/v1/webhook/{uuid}
 *          │
 *          ▼
 *  [1] Load webhook by UUID (404 if not found)
 *          │
 *          ▼
 *  [2] Synchronous handshake? ──YES──► Return handshake response immediately
 *          │ NO
 *          ▼
 *  [3] Externally managed? ──YES──► Verify provider signature ──FAIL──► 401
 *          │ NO (or pass)
 *          ▼
 *  [4] response_mode = 'wait'? ──YES──► WebhookService::handleIncoming (sync)
 *          │ NO
 *          ▼
 *  [5] Dispatch WebhookProcessingJob to 'workflows-high' queue
 *          │
 *          ▼
 *  [6] Return 200 immediately
 */
class WebhookReceiverController
{
    public function __construct(private WebhookService $webhookService) {}

    public function handle(Request $request, string $uuid): JsonResponse
    {
        $webhook = Webhook::query()
            ->with('workflow')
            ->where('uuid', $uuid)
            ->first();

        if (! $webhook) {
            return response()->json(['error' => 'Webhook not found.'], 404);
        }

        // ── Step 1: Handle synchronous provider handshakes ──────────────
        // These MUST be handled before any queue dispatch.
        // The provider will reject the URL if we don't respond immediately.

        if ($handshake = $this->handleProviderHandshake($request, $webhook)) {
            return $handshake;
        }

        // ── Step 2: Verify provider signature (fast — just HMAC/Ed25519) ─
        if ($webhook->isExternallyManaged() && ! $this->verifyProviderSignature($request, $webhook)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        // ── Step 3: 'wait' mode — process synchronously ─────────────────
        // The caller needs the workflow result in the same HTTP connection.
        // This is a deliberate trade-off: slower, but the caller asked for it.
        if ($webhook->response_mode === 'wait') {
            $headers = $this->normalizeHeaders($request);
            $result = $this->webhookService->handleIncoming(
                $webhook,
                $request->method(),
                $request->all(),
                $headers,
            );

            return response()->json($result['response_body'], $result['response_status']);
        }

        // ── Step 4: 'immediate' mode — dispatch to queue and return ──────
        // The actual execution happens in WebhookProcessingJob (async).
        // We return 200 here before the job even starts.
        $headers = $this->normalizeHeaders($request);
        $payload = $request->except(array_keys($request->allFiles()));
        $binaryFiles = $this->storeBinaryFiles($request);

        WebhookProcessingJob::dispatch(
            $webhook->id,
            $request->method(),
            array_merge($payload, $binaryFiles ? ['__binary_files' => $binaryFiles] : []),
            $headers,
        );

        return response()->json([
            'success' => true,
            'message' => 'Webhook received.',
        ], $webhook->response_status ?? 200);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SYNCHRONOUS HANDSHAKES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Handle provider-specific synchronous handshake requests.
     *
     * Returns a JsonResponse if a handshake was detected, null otherwise.
     * Handshakes must be responded to within 3 seconds — never queued.
     */
    private function handleProviderHandshake(Request $request, Webhook $webhook): ?JsonResponse
    {
        // Slack URL verification challenge
        // Slack sends this when you first save a Request URL in the App settings.
        // We detect it before checking the webhook's provider because the challenge
        // arrives before the webhook is fully registered.
        if ($request->input('type') === 'url_verification' && $request->has('challenge')) {
            return response()->json(['challenge' => $request->input('challenge')]);
        }

        // Discord PING interaction
        // Discord sends type=1 to verify the endpoint URL is valid.
        // We must respond with {"type":1} synchronously.
        if ($webhook->provider === 'discord' && (int) $request->input('type') === 1) {
            if ($this->verifyProviderSignature($request, $webhook)) {
                return response()->json(['type' => 1]);
            }

            return response()->json(['error' => 'Invalid Discord signature.'], 401);
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // SIGNATURE VERIFICATION
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Verify the webhook payload signature using the provider's registrar.
     *
     * Each provider uses a different header and algorithm:
     *   GitHub  → X-Hub-Signature-256
     *   Stripe  → Stripe-Signature
     *   Slack   → X-Slack-Signature + X-Slack-Request-Timestamp
     *   Discord → X-Signature-Ed25519 + X-Signature-Timestamp
     *
     * For Slack and Discord we pack both the timestamp and signature into a
     * single string so the registrar can access both without needing the Request.
     */
    private function verifyProviderSignature(Request $request, Webhook $webhook): bool
    {
        $registrar = WebhookRegistrarRegistry::resolve($webhook->provider);

        if (! $registrar) {
            return true;
        }

        $secret = $webhook->external_webhook_secret;

        if (! $secret) {
            return true;
        }

        $payload = $request->getContent();

        $signature = match ($webhook->provider) {
            'github' => $request->header('X-Hub-Signature-256', ''),
            'stripe' => $request->header('Stripe-Signature', ''),

            // Slack: pack timestamp|signature so registrar can use both
            'slack' => $request->header('X-Slack-Request-Timestamp', '')
                .'|'
                .$request->header('X-Slack-Signature', ''),

            // Discord: pack timestamp|signature so registrar can use both
            'discord' => $request->header('X-Signature-Timestamp', '')
                .'|'
                .$request->header('X-Signature-Ed25519', ''),

            default => '',
        };

        return $registrar->verifySignature($payload, $signature, $secret);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Store any uploaded binary files from a multipart/form-data webhook payload.
     *
     * Files are written to storage/app/webhook-binaries/{batch-uuid}/{field}.{ext}
     * so they persist until the execution completes and the engine cleans them up.
     *
     * Returns metadata keyed by form field name:
     * [
     *   'avatar' => [
     *     'field'          => 'avatar',
     *     'original_name'  => 'photo.jpg',
     *     'storage_disk'   => 'local',
     *     'storage_path'   => 'webhook-binaries/abc123/avatar.jpg',
     *     'mime_type'      => 'image/jpeg',
     *     'size_bytes'     => 204800,
     *   ],
     *   ...
     * ]
     *
     * Returns an empty array when the request contains no uploaded files.
     *
     * @return array<string, array<string, mixed>>
     */
    private function storeBinaryFiles(Request $request): array
    {
        if (empty($request->allFiles())) {
            return [];
        }

        $batchUuid = (string) Str::uuid();
        $basePath = "webhook-binaries/{$batchUuid}";
        $metadata = [];

        foreach ($request->allFiles() as $fieldName => $file) {
            // allFiles() can return arrays for multi-file fields; normalise to a flat list.
            $files = is_array($file) ? array_values($file) : [$file];

            foreach ($files as $index => $uploadedFile) {
                if (! ($uploadedFile instanceof \Illuminate\Http\UploadedFile)) {
                    continue;
                }

                $extension = $uploadedFile->getClientOriginalExtension();
                $key = is_array($file) ? "{$fieldName}_{$index}" : $fieldName;
                $storageName = $extension ? "{$key}.{$extension}" : $key;
                $storagePath = "{$basePath}/{$storageName}";

                Storage::disk('local')->put(
                    $storagePath,
                    file_get_contents($uploadedFile->getRealPath()),
                );

                $metadata[$key] = [
                    'field' => $fieldName,
                    'original_name' => $uploadedFile->getClientOriginalName(),
                    'storage_disk' => 'local',
                    'storage_path' => $storagePath,
                    'mime_type' => $uploadedFile->getMimeType() ?? $uploadedFile->getClientMimeType(),
                    'size_bytes' => $uploadedFile->getSize(),
                ];
            }
        }

        return $metadata;
    }

    /**
     * Flatten multi-value headers into single strings.
     * Symfony's HeaderBag stores each header as an array of values.
     */
    private function normalizeHeaders(Request $request): array
    {
        return array_map(
            fn ($values) => is_array($values) ? ($values[0] ?? '') : $values,
            $request->headers->all(),
        );
    }
}
