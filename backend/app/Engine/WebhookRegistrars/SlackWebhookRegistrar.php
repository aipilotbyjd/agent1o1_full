<?php

namespace App\Engine\WebhookRegistrars;

use App\Engine\Contracts\RegisterableWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SlackWebhookRegistrar — manages Slack event subscriptions.
 *
 * ═══════════════════════════════════════════════════════════════
 * HOW SLACK WEBHOOKS WORK (different from GitHub/Stripe)
 * ═══════════════════════════════════════════════════════════════
 *
 * Slack uses Event Subscriptions, not classic webhooks.
 * The flow is:
 *
 *   1. You set a "Request URL" in the Slack App settings
 *   2. Slack sends a one-time URL verification challenge (handled in
 *      WebhookReceiverController BEFORE this registrar is involved)
 *   3. After verification, Slack sends events to your URL
 *
 * AUTO-REGISTRATION
 * -----------------
 * Slack's API allows setting the event subscription URL programmatically
 * via the `apps.manifest.update` API (requires a Slack App manifest).
 * This requires a special "app-level token" (xapp-...) not a bot token.
 *
 * This registrar implements that flow. If the workspace has only a bot
 * token, supportsAutoRegistration() returns true but register() will
 * log a warning — the user must set the URL manually in the Slack
 * App dashboard.
 *
 * SIGNATURE VERIFICATION
 * ----------------------
 * Slack signs every request with HMAC-SHA256 using a "Signing Secret"
 * (different from the OAuth bot token). The signature covers:
 *   "v0:" + timestamp + ":" + raw_body
 *
 * The signature is in the X-Slack-Signature header as "v0=<hex>".
 * The timestamp is in X-Slack-Request-Timestamp.
 * Timestamps older than 5 minutes are rejected to prevent replay attacks.
 *
 * CHALLENGE HANDLING
 * ------------------
 * When Slack first verifies your URL, it sends:
 *   POST { "type": "url_verification", "challenge": "abc123" }
 * You must respond immediately with:
 *   { "challenge": "abc123" }
 *
 * This is handled in WebhookReceiverController::handleSlackChallenge()
 * BEFORE the queue dispatch, because Slack expects a response within 3 seconds.
 */
class SlackWebhookRegistrar implements RegisterableWebhook
{
    private const BASE_URL = 'https://slack.com/api';

    private const TIMESTAMP_TOLERANCE = 300;

    public function provider(): string
    {
        return 'slack';
    }

    public function supportsAutoRegistration(): bool
    {
        return true;
    }

    /**
     * Slack does not provide a "list webhooks" or "get webhook by ID" API.
     * We verify the app still has event subscriptions by calling auth.test.
     *
     * @param  array  $credentials  Must contain 'bot_token' (xoxb-...).
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool
    {
        $token = $credentials['bot_token'] ?? $credentials['access_token'] ?? '';

        $response = Http::baseUrl(self::BASE_URL)
            ->withToken($token)
            ->get('/auth.test');

        return $response->successful() && ($response->json('ok') === true);
    }

    /**
     * Register a Slack event subscription URL via the manifest API.
     *
     * Requires an app-level token (xapp-...) in credentials['app_token'].
     * If not present, logs a warning and returns a placeholder — the user
     * must manually set the Request URL in the Slack App dashboard.
     *
     * @param  array  $events      Slack event types (e.g. ['message', 'app_mention']).
     * @param  array  $credentials Must contain 'signing_secret' and optionally 'app_token'.
     * @return array{external_id: string, secret: string}
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array
    {
        $signingSecret = $credentials['signing_secret'] ?? '';

        if (empty($signingSecret)) {
            throw new \RuntimeException(
                'Slack registration requires a signing_secret in the credential. '.
                'Find it in your Slack App settings under "Basic Information > App Credentials".'
            );
        }

        $appToken = $credentials['app_token'] ?? null;

        if (! $appToken) {
            Log::warning('SlackWebhookRegistrar: no app_token provided. Slack event subscription URL must be set manually.', [
                'callback_url' => $callbackUrl,
            ]);

            return [
                'external_id' => 'manual-'.$providerConfig['app_id'] ?? 'unknown',
                'secret' => $signingSecret,
            ];
        }

        $appId = $providerConfig['app_id'] ?? null;

        if (! $appId) {
            throw new \RuntimeException('Slack registration requires app_id in provider_config.');
        }

        $response = Http::baseUrl(self::BASE_URL)
            ->withToken($appToken)
            ->post('/apps.manifest.update', [
                'app_id' => $appId,
                'manifest' => json_encode([
                    'event_subscriptions' => [
                        'request_url' => $callbackUrl,
                        'bot_events' => $events,
                    ],
                ]),
            ]);

        if (! $response->successful() || ! $response->json('ok')) {
            throw new \RuntimeException('Slack manifest update failed: '.($response->json('error') ?? 'unknown error'));
        }

        return [
            'external_id' => $appId,
            'secret' => $signingSecret,
        ];
    }

    /**
     * Slack does not support deleting event subscriptions via API.
     * The URL must be cleared manually in the Slack App dashboard.
     * We simply log a notice and return.
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void
    {
        Log::info('SlackWebhookRegistrar: Slack event subscriptions cannot be removed via API. Please clear the Request URL in your Slack App dashboard.', [
            'external_id' => $externalId,
        ]);
    }

    /**
     * Verify the X-Slack-Signature header.
     *
     * Slack signs requests as: HMAC-SHA256("v0:{timestamp}:{body}", signing_secret)
     * Compared to X-Slack-Signature: "v0={hex_hash}"
     *
     * The timestamp comes from the X-Slack-Request-Timestamp header and is
     * included in $signature as a pipe-separated prefix: "{timestamp}|{sig_header}".
     * This is a convention set by WebhookReceiverController before calling this method.
     *
     * @param  string  $payload    Raw request body.
     * @param  string  $signature  "{timestamp}|{X-Slack-Signature header value}"
     * @param  string  $secret     The Slack signing secret.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        [$timestamp, $sigHeader] = explode('|', $signature, 2) + ['', ''];

        if (empty($timestamp) || empty($sigHeader)) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            return false;
        }

        $baseString = "v0:{$timestamp}:{$payload}";
        $computed = 'v0='.hash_hmac('sha256', $baseString, $secret);

        return hash_equals($computed, $sigHeader);
    }
}
