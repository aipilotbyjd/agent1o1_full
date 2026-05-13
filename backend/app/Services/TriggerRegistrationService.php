<?php

namespace App\Services;

use App\Engine\WebhookRegistrars\GitHubWebhookRegistrar;
use App\Engine\WebhookRegistrars\SlackWebhookRegistrar;
use App\Engine\WebhookRegistrars\StripeWebhookRegistrar;
use App\Models\Trigger;
use Illuminate\Support\Facades\Log;

class TriggerRegistrationService
{
    /**
     * Map of service slugs to registrar classes
     */
    private const REGISTRARS = [
        'github' => GitHubWebhookRegistrar::class,
        'slack' => SlackWebhookRegistrar::class,
        'stripe' => StripeWebhookRegistrar::class,
        // Add more service registrars as needed
    ];

    /**
     * Register a webhook trigger with its external service
     */
    public function registerWebhookTrigger(Trigger $trigger): void
    {
        if (!$trigger->isWebhookBased()) {
            throw new \InvalidArgumentException('Cannot register non-webhook trigger');
        }

        $category = $trigger->triggerCategory->slug;
        $registrar = $this->getRegistrar($category);

        if (!$registrar) {
            Log::warning('No registrar found for service', ['service' => $category]);
            $trigger->update(['webhook_status' => 'failed', 'webhook_status_message' => "No registrar for $category"]);
            return;
        }

        try {
            // Get credentials
            $credential = $trigger->credential;
            if (!$credential) {
                throw new \Exception('No credential provided');
            }

            $credentials = $credential->data ?? [];
            $providerConfig = $trigger->getFieldValues();

            // Get events for this trigger type
            $events = $this->getEventsForTriggerType($trigger->triggerType->slug);

            // Generate callback URL
            $callbackUrl = $this->getCallbackUrl($trigger);

            // Register with external service
            $result = $registrar->register($callbackUrl, $events, $credentials, $providerConfig);

            // Store external webhook info
            $trigger->update([
                'webhook_provider' => $category,
                'webhook_external_id' => $result['external_id'] ?? null,
                'webhook_secret' => $result['secret'] ?? null,
                'webhook_registered_url' => $callbackUrl,
                'webhook_status' => 'active',
                'webhook_status_message' => null,
            ]);

            Log::info('Webhook registered successfully', [
                'trigger_id' => $trigger->id,
                'service' => $category,
                'external_id' => $result['external_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook registration failed', [
                'trigger_id' => $trigger->id,
                'service' => $category,
                'error' => $e->getMessage(),
            ]);

            $trigger->update([
                'webhook_status' => 'failed',
                'webhook_status_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Unregister a webhook trigger from its external service
     */
    public function unregisterWebhookTrigger(Trigger $trigger): void
    {
        if (!$trigger->isWebhookBased() || !$trigger->webhook_external_id) {
            return;
        }

        $category = $trigger->triggerCategory->slug;
        $registrar = $this->getRegistrar($category);

        if (!$registrar) {
            Log::warning('No registrar found for unregistration', ['service' => $category]);
            return;
        }

        try {
            $credential = $trigger->credential;
            if (!$credential) {
                return;
            }

            $credentials = $credential->data ?? [];
            $providerConfig = $trigger->getFieldValues();

            $registrar->unregister($trigger->webhook_external_id, $credentials, $providerConfig);

            Log::info('Webhook unregistered successfully', [
                'trigger_id' => $trigger->id,
                'service' => $category,
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook unregistration failed', [
                'trigger_id' => $trigger->id,
                'service' => $category,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(Trigger $trigger, string $payload, string $signature): bool
    {
        if (!$trigger->isWebhookBased()) {
            return false;
        }

        $category = $trigger->triggerCategory->slug;
        $registrar = $this->getRegistrar($category);

        if (!$registrar) {
            return false;
        }

        try {
            return $registrar->verifySignature($payload, $signature, $trigger->webhook_secret);
        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed', [
                'trigger_id' => $trigger->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get registrar instance for a service
     */
    private function getRegistrar(string $service): ?object
    {
        if (!isset(self::REGISTRARS[$service])) {
            return null;
        }

        $class = self::REGISTRARS[$service];
        return new $class();
    }

    /**
     * Get webhook callback URL for a trigger
     */
    private function getCallbackUrl(Trigger $trigger): string
    {
        $baseUrl = config('app.url');
        return "{$baseUrl}/api/v1/webhooks/{$trigger->webhook_uuid}";
    }

    /**
     * Map trigger type slugs to event names for the external service
     */
    private function getEventsForTriggerType(string $triggerSlug): array
    {
        return match ($triggerSlug) {
            // GitHub events
            'github_push' => ['push'],
            'github_pull_request' => ['pull_request'],
            'github_issue' => ['issues'],
            'github_release' => ['release'],

            // Slack events
            'slack_message' => ['message.channels', 'message.groups', 'message.im'],
            'slack_mention' => ['app_mention'],
            'slack_reaction' => ['reaction_added'],

            // Stripe events
            'stripe_charge_succeeded' => ['charge.succeeded'],
            'stripe_invoice_created' => ['invoice.created'],
            'stripe_customer_created' => ['customer.created'],

            // Airtable events
            'airtable_new_record' => ['records.created'],
            'airtable_updated_record' => ['records.updated'],

            // Discord events
            'discord_message' => ['message'],
            'discord_reaction' => ['reaction'],

            default => [],
        };
    }
}
