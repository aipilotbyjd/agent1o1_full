<?php

namespace App\Engine\Contracts;

/**
 * VerifiableWebhook — the minimal contract every webhook provider must satisfy.
 *
 * ALL providers implement this interface, including those that require manual
 * setup (e.g. Discord) and cannot be programmatically registered.
 *
 * It covers the two responsibilities shared by every provider:
 *   1. Identity  — which provider string this registrar handles
 *   2. Verification — whether an incoming request is genuine
 *
 * Providers that additionally support programmatic registration/unregistration
 * via an API must also implement RegisterableWebhook.
 */
interface VerifiableWebhook
{
    /**
     * The provider identifier string (e.g. 'github', 'stripe', 'discord').
     * Must match the key in WebhookRegistrarRegistry::REGISTRARS.
     */
    public function provider(): string;

    /**
     * Whether this provider supports automatic webhook registration via API.
     *
     * true  — GitHub, Stripe, Slack (we call their API to create the webhook)
     * false — Discord (URL must be set manually in the Discord Developer Portal)
     *
     * Used by WebhookAutoRegistrationService to decide whether to attempt
     * programmatic registration. Providers returning false must implement
     * only this interface, not RegisterableWebhook.
     */
    public function supportsAutoRegistration(): bool;

    /**
     * Call the provider API (or perform a local check) to verify that the
     * webhook subscription is still active and healthy.
     *
     * Used by:
     *   - WebhookHealthCheckCommand to detect silently deleted webhooks
     *   - WebhookAutoRegistrationService before re-registering (prevents duplicates)
     *
     * Must return false rather than throw when the webhook does not exist.
     *
     * @param  string               $externalId     ID returned when the webhook was created.
     * @param  array<string, mixed> $credentials    Decrypted credential data.
     * @param  array<string, mixed> $providerConfig Extra provider-specific config.
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool;

    /**
     * Verify that an incoming HTTP request genuinely came from the provider.
     *
     * Each provider uses a different signing scheme:
     *   GitHub  — HMAC-SHA256 of body      → X-Hub-Signature-256
     *   Stripe  — Timestamp + HMAC-SHA256   → Stripe-Signature
     *   Slack   — HMAC-SHA256 of ts+body    → X-Slack-Signature
     *   Discord — Ed25519 public key sig    → X-Signature-Ed25519
     *
     * @param  string $payload    Raw request body (not JSON decoded).
     * @param  string $signature  Full signature header value from the provider.
     * @param  string $secret     Shared secret from webhooks.external_webhook_secret.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool;
}
