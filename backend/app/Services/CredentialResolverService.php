<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\Workflow;

/**
 * Centralised credential resolver.
 *
 * Resolves the decrypted credential data array for:
 *   - a workflow node (by explicit credential_id or pivot node_id)
 *   - an existing Webhook record (by provider_config node_id or provider type match)
 *
 * Previously this logic was duplicated in WebhookAutoRegistrationService and
 * WebhookHealthCheckCommand. A single injectable service eliminates that
 * duplication and makes credential resolution independently testable.
 */
class CredentialResolverService
{
    /**
     * Resolve credentials for a specific trigger node inside a workflow.
     *
     * Looks up by explicit `credential_id` field on the node first, then
     * falls back to the pivot-table `node_id` relationship.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    public function forNode(Workflow $workflow, array $node): ?array
    {
        $credentialId = $node['credential_id'] ?? null;

        if ($credentialId) {
            $credential = $workflow->credentials()->find($credentialId);
        } else {
            $credential = $workflow->credentials()
                ->wherePivot('node_id', $node['id'] ?? '')
                ->first();
        }

        if (! $credential) {
            return null;
        }

        return $this->decode($credential->data);
    }

    /**
     * Resolve credentials for an existing registered webhook.
     *
     * Prefers the explicit `node_id` stored in provider_config; falls back
     * to matching a credential whose type contains the provider name.
     *
     * @return array<string, mixed>|null
     */
    public function forWebhook(Webhook $webhook): ?array
    {
        $webhook->loadMissing('workflow.credentials');

        $workflow = $webhook->workflow;

        if (! $workflow) {
            return null;
        }

        $nodeId = $webhook->provider_config['node_id'] ?? null;

        if ($nodeId) {
            $credential = $workflow->credentials()
                ->wherePivot('node_id', $nodeId)
                ->first();
        } else {
            $credential = $workflow->credentials
                ->first(fn ($c) => str_contains(strtolower($c->type ?? ''), $webhook->provider));
        }

        if (! $credential) {
            return null;
        }

        return $this->decode($credential->data);
    }

    /**
     * Decode credential data that may be stored as a JSON string or already
     * decoded to an array by Laravel's encrypted cast.
     *
     * @return array<string, mixed>
     */
    private function decode(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        return json_decode((string) $data, true) ?? [];
    }
}
