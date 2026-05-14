<?php

namespace App\Console\Commands;

use App\Engine\Webhook\WebhookRegistry;
use App\Models\Webhook;
use App\Services\CredentialResolverService;
use App\Services\WebhookAutoRegistrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * WebhookHealthCheckCommand — detects and heals silently broken webhooks.
 *
 * Run via: php artisan webhooks:health-check
 * Scheduled: daily at 03:00 in routes/console.php
 *
 * ═══════════════════════════════════════════════════════════════
 * WHY THIS COMMAND EXISTS
 * ═══════════════════════════════════════════════════════════════
 *
 * External webhooks can break silently without your system knowing:
 *
 *   1. User revokes the OAuth token → GitHub deletes the webhook
 *   2. App reaches GitHub's 20-webhook limit → registration silently fails
 *   3. App domain changes → webhook still exists on GitHub but points to dead URL
 *   4. GitHub/Stripe rate-limits your app and deletes the hook
 *
 * In all these cases, workflows appear "active" in your system but
 * receive zero events. Users see nothing happen and file support tickets.
 *
 * This command fixes that by:
 *   1. Finding all active, externally-managed webhooks
 *   2. Calling checkExists() on the provider to verify they still exist
 *   3. If missing → re-registering by triggering WebhookRegistrationJob
 *   4. Logging a summary you can alert on
 *
 * ═══════════════════════════════════════════════════════════════
 * WHAT IT CHECKS
 * ═══════════════════════════════════════════════════════════════
 *
 * - Webhook exists on provider (checkExists)
 * - Workflow is still active (don't waste API calls on inactive workflows)
 * - Provider still has a registrar (handles removed integrations gracefully)
 */
class WebhookHealthCheckCommand extends Command
{
    protected $signature = 'webhooks:health-check
                            {--dry-run : Report issues without fixing them}
                            {--provider= : Only check a specific provider (github, stripe, slack, discord)}';

    protected $description = 'Check that all active external webhooks still exist on their providers and re-register any that are missing.';

    public function handle(WebhookAutoRegistrationService $registrationService, CredentialResolverService $credentialResolver): int
    {
        $isDryRun = $this->option('dry-run');
        $providerFilter = $this->option('provider');

        $this->info('Starting webhook health check'.($isDryRun ? ' (dry run — no changes will be made)' : '').'...');

        $query = Webhook::query()
            ->whereNotNull('provider')
            ->whereNotNull('external_webhook_id')
            ->whereHas('workflow', fn ($q) => $q->where('is_active', true))
            ->with(['workflow', 'workflow.credentials']);

        if ($providerFilter) {
            $query->where('provider', $providerFilter);
        }

        $webhooks = $query->get();

        $this->info("Checking {$webhooks->count()} active external webhook(s)...");
        $this->newLine();

        $healthy = 0;
        $missing = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($webhooks as $webhook) {
            $label = "[{$webhook->provider}] Workflow #{$webhook->workflow_id} / Webhook {$webhook->uuid}";

            $registrar = WebhookRegistry::resolve($webhook->provider);

            if (! $registrar) {
                $this->line("  {$label} — SKIPPED (no registrar for provider '{$webhook->provider}')");
                $skipped++;

                continue;
            }

            $credentials = $credentialResolver->forWebhook($webhook);

            if (! $credentials) {
                $this->warn("  {$label} — SKIPPED (could not resolve credentials)");
                $skipped++;

                continue;
            }

            try {
                $exists = $registrar->checkExists(
                    $webhook->external_webhook_id,
                    $credentials,
                    $webhook->provider_config ?? [],
                );

                if ($exists) {
                    $this->line("  <fg=green>✓</> {$label} — OK");
                    $healthy++;
                } else {
                    $this->warn("  <fg=yellow>✗</> {$label} — MISSING on provider");
                    $missing++;

                    if (! $isDryRun) {
                        $this->reregister($registrationService, $webhook, $label);
                    }
                }
            } catch (\Throwable $e) {
                $this->error("  {$label} — ERROR: {$e->getMessage()}");
                $errors++;

                Log::error('webhooks:health-check encountered an error', [
                    'webhook_id' => $webhook->id,
                    'provider' => $webhook->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('Health check complete:');
        $this->line("  Healthy : {$healthy}");
        $this->line("  Missing : {$missing}".($isDryRun ? ' (not fixed — dry run)' : ' (re-registration triggered)'));
        $this->line("  Skipped : {$skipped}");
        $this->line("  Errors  : {$errors}");

        Log::info('webhooks:health-check completed', compact('healthy', 'missing', 'skipped', 'errors'));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function reregister(WebhookAutoRegistrationService $service, Webhook $webhook, string $label): void
    {
        try {
            // Clear the stale external ID so the service re-registers from scratch
            $webhook->update([
                'external_webhook_id' => null,
                'external_webhook_secret' => null,
                'registered_url' => null,
            ]);

            // Dispatch a registration job for the workflow — same as activation
            \App\Jobs\WebhookRegistrationJob::dispatch($webhook->workflow_id);

            $this->line("    → Re-registration job dispatched for workflow #{$webhook->workflow_id}");
        } catch (\Throwable $e) {
            $this->error("    → Failed to dispatch re-registration: {$e->getMessage()}");
        }
    }

}
