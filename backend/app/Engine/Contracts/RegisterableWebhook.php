<?php

namespace App\Engine\Contracts;

/**
 * RegisterableWebhook — extends VerifiableWebhook for providers that support
 * programmatic webhook creation and deletion via their API.
 *
 * Implement this interface when the provider exposes an API that lets you:
 *   - Create a webhook subscription pointing to your callback URL
 *   - Delete it when no longer needed
 *   - Check whether a previously created webhook still exists
 *
 * Current implementations: GitHub, Stripe, Slack.
 *
 * Providers that require the developer to paste the URL into a dashboard
 * (e.g. Discord) implement only VerifiableWebhook and must NOT implement
 * this interface — that is the whole point of the split.
 *
 * ═══════════════════════════════════════════════════════════════
 * ADDING A NEW AUTO-REGISTERABLE PROVIDER (3 steps)
 * ═══════════════════════════════════════════════════════════════
 * 1. Create  app/Engine/WebhookRegistrars/YourProviderWebhookRegistrar.php
 *    implementing this interface.
 * 2. Add it to WebhookRegistrarRegistry::REGISTRARS.
 * 3. Add a case to WebhookAutoRegistrationService if custom provider_config
 *    fields are required (e.g. repo owner/name for GitHub).
 */
interface RegisterableWebhook extends VerifiableWebhook
{
    /**
     * Call the provider API to create a new webhook subscription.
     *
     * Must return exactly:
     *   'external_id' — ID assigned by the provider (stored in webhooks.external_webhook_id)
     *   'secret'      — signing secret for verifySignature (stored encrypted in the DB)
     *
     * @param  string               $callbackUrl    URL the provider should POST events to.
     * @param  array<string>        $events         Event types to subscribe to (e.g. ['push']).
     * @param  array<string, mixed> $credentials    Decrypted credential data.
     * @param  array<string, mixed> $providerConfig Extra provider-specific config.
     * @return array{external_id: string, secret: string}
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array;

    /**
     * Call the provider API to delete the webhook subscription.
     *
     * MUST be idempotent — if the webhook no longer exists (404 from provider),
     * do not throw. This handles the case where a user manually deleted it
     * from the provider's dashboard between our last check and now.
     *
     * @param  string               $externalId     ID of the webhook to delete.
     * @param  array<string, mixed> $credentials    Decrypted credential data.
     * @param  array<string, mixed> $providerConfig Extra provider-specific config.
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void;
}
