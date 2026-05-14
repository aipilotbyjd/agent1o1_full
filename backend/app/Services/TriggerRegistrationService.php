<?php

namespace App\Services;

use App\Contracts\WebhookRegistrar;
use App\Engine\Webhook\WebhookRegistry;
use App\Models\Trigger;
use Illuminate\Support\Facades\Log;

class TriggerRegistrationService
{

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
            $events = $trigger->triggerType->webhook_events ?? [];

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

    private function getRegistrar(string $service): ?WebhookRegistrar
    {
        return WebhookRegistry::resolveRegisterable($service);
    }

    private function getCallbackUrl(Trigger $trigger): string
    {
        return config('app.url')."/api/v1/webhooks/{$trigger->webhook_uuid}";
    }
}
