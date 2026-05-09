<?php

namespace App\Jobs;

use App\Models\Credential;
use App\Services\OAuthCredentialFlowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Proactively refreshes OAuth credentials expiring within the next 7 days.
 *
 * Runs daily via the scheduler. Finds all credentials with a non-null
 * expires_at within the refresh window and attempts to refresh each one.
 * If refresh fails, logs a warning (a notification to the credential owner
 * will be added in C-03 when the notification system is built).
 *
 * Only credentials with expires_at set are OAuth-backed; API key and
 * HTTP Basic credentials never have an expiry date.
 */
class RefreshOAuthTokenJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(OAuthCredentialFlowService $oauthService): void
    {
        // Fetch credentials expiring within 7 days OR already expired.
        // We attempt to refresh already-expired ones too so they're usable again.
        $credentials = Credential::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now()->addDays(7))
            ->with('workspace')
            ->get();

        if ($credentials->isEmpty()) {
            Log::info('RefreshOAuthTokenJob: no credentials need refreshing.');

            return;
        }

        Log::info("RefreshOAuthTokenJob: refreshing {$credentials->count()} credential(s).");

        $refreshed = 0;
        $failed = 0;

        foreach ($credentials as $credential) {
            try {
                $result = $oauthService->refreshToken($credential);

                if ($result !== null) {
                    $refreshed++;
                    Log::info("RefreshOAuthTokenJob: refreshed credential {$credential->id}.", [
                        'workspace_id' => $credential->workspace_id,
                        'credential_type' => $credential->type,
                        'new_expires_at' => $result->expires_at?->toIso8601String(),
                    ]);
                } else {
                    $failed++;
                    Log::warning("RefreshOAuthTokenJob: refresh returned null for credential {$credential->id}.", [
                        'workspace_id' => $credential->workspace_id,
                        'credential_type' => $credential->type,
                        'expires_at' => $credential->expires_at?->toIso8601String(),
                    ]);
                    // TODO C-03: dispatch OAuthTokenExpiringNotification to credential creator
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::error("RefreshOAuthTokenJob: exception refreshing credential {$credential->id}.", [
                    'error' => $e->getMessage(),
                    'credential_type' => $credential->type,
                ]);
            }
        }

        Log::info("RefreshOAuthTokenJob: complete. refreshed={$refreshed} failed={$failed}.");
    }
}
