<?php

namespace App\Services;

use App\Engine\Webhook\WebhookRegistry;
use App\Models\Webhook;
use App\Models\Workflow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles automatic webhook registration/unregistration with external
 * services (GitHub, Stripe, Slack, etc.) when workflows are activated or deactivated.
 *
 * ═══════════════════════════════════════════════════════════════
 * HOW THIS SERVICE FITS IN THE ARCHITECTURE
 * ═══════════════════════════════════════════════════════════════
 *
 * This service is called by WebhookRegistrationJob and WebhookUnregistrationJob
 * (both queue jobs) — never directly from a controller or model.
 *
 * Registration flow:
 *   WorkflowRegistrationJob::handle()
 *     → registerForWorkflow($workflow)
 *       → extractTriggerNodes() — find trigger nodes needing webhooks
 *       → for each: registerExternalWebhook()
 *         → checkExists() — skip if already registered (prevents duplicates)
 *         → URL stability check — re-register if APP_URL has changed
 *         → register() — call GitHub/Stripe API
 *         → store result (external_id, secret, registered_url) in DB
 *
 * ═══════════════════════════════════════════════════════════════
 * URL STABILITY
 * ═══════════════════════════════════════════════════════════════
 *
 * We store the URL used at registration time in webhooks.registered_url.
 * On every activation, we compare the current callback URL against the stored one.
 * If they differ (e.g. domain changed, new deployment), we automatically
 * re-register with the new URL so events keep arriving.
 */
class WebhookAutoRegistrationService
{
    public function __construct(private CredentialResolverService $credentialResolver) {}

    /**
     * Register external webhooks for all trigger nodes that support it.
     *
     * Only providers where supportsAutoRegistration() = true are processed.
     * Providers like Discord (manual setup only) are skipped here but still
     * support signature verification when events arrive.
     */
    public function registerForWorkflow(Workflow $workflow): void
    {
        $triggerNodes = $this->extractTriggerNodes($workflow);

        foreach ($triggerNodes as $node) {
            $provider = $node['provider'] ?? null;

            if (! $provider || ! WebhookRegistry::supports($provider)) {
                continue;
            }

            // Skip providers that require manual setup (e.g. Discord)
            if (! WebhookRegistry::supportsAutoRegistration($provider)) {
                Log::info('WebhookAutoRegistrationService: provider requires manual setup, skipping auto-registration', [
                    'workflow_id' => $workflow->id,
                    'provider' => $provider,
                ]);
                continue;
            }

            $this->registerExternalWebhook($workflow, $node, $provider);
        }
    }

    /**
     * Unregister all external webhooks for a workflow.
     *
     * Called when a workflow is deactivated. Finds all webhooks with an
     * external provider and calls the provider API to delete them.
     */
    public function unregisterForWorkflow(Workflow $workflow): void
    {
        $webhooks = $workflow->webhooks()
            ->whereNotNull('provider')
            ->whereNotNull('external_webhook_id')
            ->get();

        foreach ($webhooks as $webhook) {
            $this->unregisterExternalWebhook($webhook);
        }
    }

    /**
     * Register a single external webhook for a trigger node.
     *
     * @param  array<string, mixed>  $node
     */
    private function registerExternalWebhook(Workflow $workflow, array $node, string $provider): void
    {
        $registrar = WebhookRegistry::resolveRegisterable($provider);

        if (! $registrar) {
            return;
        }

        $credentials = $this->credentialResolver->forNode($workflow, $node);
        $nodeId = $node['id'] ?? null;

        if (! $credentials) {
            Log::warning('No credentials found for webhook auto-registration', [
                'workflow_id' => $workflow->id,
                'provider' => $provider,
                'node_id' => $nodeId,
            ]);

            return;
        }

        $existingWebhook = $workflow->webhooks()
            ->where('node_id', $nodeId)
            ->where('provider', $provider)
            ->first();

        $uuid = $existingWebhook?->uuid ?? (string) Str::uuid();
        $callbackUrl = $this->buildCallbackUrl($uuid);
        $events = $node['events'] ?? $node['config']['events'] ?? ['*'];
        $providerConfig = $this->buildProviderConfig($node, $provider);

        if ($existingWebhook && $existingWebhook->external_webhook_id) {
            $stillExists = $registrar->checkExists(
                $existingWebhook->external_webhook_id,
                $credentials,
                $existingWebhook->provider_config ?? [],
            );

            // URL stability check: if our app URL changed since last registration
            // (e.g. new deployment, domain change), the old webhook points to a
            // dead URL. We must unregister the old one and register the new one.
            $urlChanged = $existingWebhook->registered_url !== null
                && $existingWebhook->registered_url !== $callbackUrl;

            if ($stillExists && ! $urlChanged) {
                // Webhook exists on the provider and URL is correct — nothing to do.
                Log::info('External webhook already registered and URL is current, skipping', [
                    'workflow_id' => $workflow->id,
                    'provider' => $provider,
                    'node_id' => $nodeId,
                ]);

                return;
            }

            if ($stillExists && $urlChanged) {
                // URL changed — unregister old webhook so the provider stops
                // sending events to our old (now dead) URL.
                Log::info('App URL has changed — unregistering old webhook before re-registering', [
                    'workflow_id' => $workflow->id,
                    'provider' => $provider,
                    'old_url' => $existingWebhook->registered_url,
                    'new_url' => $callbackUrl,
                ]);

                try {
                    $registrar->unregister($existingWebhook->external_webhook_id, $credentials, $existingWebhook->provider_config ?? []);
                } catch (\Throwable $e) {
                    Log::warning('Failed to unregister old webhook during URL migration', ['error' => $e->getMessage()]);
                }
            }

            // Clear the stale external ID so we re-register below.
            $existingWebhook->update([
                'external_webhook_id' => null,
                'external_webhook_secret' => null,
                'registered_url' => null,
            ]);
        }

        try {
            $result = $registrar->register($callbackUrl, $events, $credentials, $providerConfig);

            if ($existingWebhook) {
                $existingWebhook->update([
                    'external_webhook_id' => $result['external_id'],
                    'external_webhook_secret' => $result['secret'],
                    'provider_config' => $providerConfig,
                    'registered_url' => $callbackUrl,
                ]);
            } else {
                Webhook::create([
                    'workflow_id' => $workflow->id,
                    'workspace_id' => $workflow->workspace_id,
                    'uuid' => $uuid,
                    'node_id' => $nodeId,
                    'provider' => $provider,
                    'external_webhook_id' => $result['external_id'],
                    'external_webhook_secret' => $result['secret'],
                    'provider_config' => $providerConfig,
                    'registered_url' => $callbackUrl,
                    'methods' => ['POST'],
                    'is_active' => true,
                    'auth_type' => 'none',
                    'response_mode' => 'immediate',
                    'response_status' => 200,
                ]);
            }

            Log::info('External webhook registered', [
                'workflow_id' => $workflow->id,
                'provider' => $provider,
                'node_id' => $nodeId,
                'external_id' => $result['external_id'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to register external webhook', [
                'workflow_id' => $workflow->id,
                'provider' => $provider,
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Unregister a single external webhook.
     */
    private function unregisterExternalWebhook(Webhook $webhook): void
    {
        $registrar = WebhookRegistry::resolveRegisterable($webhook->provider);

        if (! $registrar) {
            return;
        }

        $credentials = $this->credentialResolver->forWebhook($webhook);

        if (! $credentials) {
            Log::warning('No credentials found for webhook unregistration', [
                'webhook_id' => $webhook->id,
                'provider' => $webhook->provider,
            ]);

            return;
        }

        try {
            $registrar->unregister(
                $webhook->external_webhook_id,
                $credentials,
                $webhook->provider_config ?? [],
            );

            $webhook->update([
                'external_webhook_id' => null,
                'external_webhook_secret' => null,
            ]);

            Log::info('External webhook unregistered', [
                'webhook_id' => $webhook->id,
                'provider' => $webhook->provider,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to unregister external webhook', [
                'webhook_id' => $webhook->id,
                'provider' => $webhook->provider,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract trigger nodes from the workflow's current version that have a provider.
     *
     * @return list<array<string, mixed>>
     */
    private function extractTriggerNodes(Workflow $workflow): array
    {
        $workflow->loadMissing('currentVersion');

        $version = $workflow->currentVersion;

        if (! $version) {
            return [];
        }

        $nodes = $version->nodes ?? [];
        $triggers = [];

        foreach ($nodes as $node) {
            $type = $node['type'] ?? '';
            $config = $node['config'] ?? [];
            $triggerType = $config['trigger_type'] ?? null;

            if ($type !== 'trigger' || ! $triggerType) {
                continue;
            }

            if (WebhookRegistry::supports($triggerType)) {
                $triggers[] = [
                    'id' => $node['id'] ?? null,
                    'provider' => $triggerType,
                    'events' => $config['events'] ?? ['*'],
                    'config' => $config,
                    'credential_id' => $node['credential_id'] ?? $config['credential_id'] ?? null,
                ];
            }
        }

        return $triggers;
    }

    /**
     * Build the callback URL for the webhook receiver endpoint.
     */
    private function buildCallbackUrl(string $uuid): string
    {
        $baseUrl = config('app.url');

        return rtrim($baseUrl, '/').'/api/v1/webhook/'.$uuid;
    }

    /**
     * Build provider-specific config from the trigger node.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function buildProviderConfig(array $node, string $provider): array
    {
        $config = $node['config'] ?? [];
        $base = ['node_id' => $node['id'] ?? null];

        return match ($provider) {
            'github' => [
                ...$base,
                'owner' => $config['owner'] ?? '',
                'repository' => $config['repository'] ?? '',
            ],
            'stripe' => [
                ...$base,
                'events' => $config['events'] ?? ['*'],
            ],
            'slack' => [
                ...$base,
                'app_id' => $config['app_id'] ?? '',
                'events' => $config['events'] ?? [],
            ],
            'discord' => [
                ...$base,
                'application_id' => $config['application_id'] ?? '',
            ],
            default => $base,
        };
    }
}
