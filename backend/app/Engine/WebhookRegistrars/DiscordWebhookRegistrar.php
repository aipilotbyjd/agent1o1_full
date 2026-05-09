<?php

namespace App\Engine\WebhookRegistrars;

use App\Engine\Contracts\VerifiableWebhook;
use Illuminate\Support\Facades\Log;

/**
 * DiscordWebhookRegistrar — handles Discord Interactions endpoint verification.
 *
 * ═══════════════════════════════════════════════════════════════
 * HOW DISCORD WEBHOOKS WORK (very different from others)
 * ═══════════════════════════════════════════════════════════════
 *
 * Discord does NOT use traditional webhooks for receiving events.
 * Instead, it uses an "Interactions Endpoint URL" set in the Discord
 * Developer Portal. There is no API to set this URL programmatically
 * — it must be done manually in the portal.
 *
 * supportsAutoRegistration() returns FALSE for this reason.
 * Discord implements only VerifiableWebhook, not RegisterableWebhook:
 *   - The engine calls verifySignature() on every incoming request ✓
 *   - The engine calls checkExists() during health checks ✓
 *   - register() and unregister() are never called — no dead stubs needed ✓
 *
 * The user sees a notice in the UI: "Set your Interactions Endpoint URL
 * in the Discord Developer Portal to: {your_callback_url}"
 *
 * SIGNATURE VERIFICATION (Ed25519 — different from HMAC)
 * -------------------------------------------------------
 * Discord uses Ed25519 public key cryptography, not HMAC-SHA256.
 * Every request carries:
 *   X-Signature-Ed25519   — hex-encoded Ed25519 signature
 *   X-Signature-Timestamp — Unix timestamp string
 *
 * The signed message is: timestamp + raw_body
 * Verified against the application's PUBLIC KEY (not a secret).
 *
 * Discord REQUIRES that the PING verification (type=1) is handled
 * synchronously. WebhookReceiverController handles this before
 * dispatching to the queue.
 *
 * REQUIREMENT
 * -----------
 * Requires the PHP sodium extension (libsodium), included in PHP 7.2+.
 * Verify with: php -m | grep sodium
 *
 * The 'public_key' credential field must contain the Discord application's
 * public key from the Discord Developer Portal.
 */
class DiscordWebhookRegistrar implements VerifiableWebhook
{
    public function provider(): string
    {
        return 'discord';
    }

    /**
     * Discord does not support API-based webhook URL registration.
     * The user must set the Interactions Endpoint URL manually in the portal.
     */
    public function supportsAutoRegistration(): bool
    {
        return false;
    }

    /**
     * Discord doesn't expose a "check webhook exists" API.
     * We verify the application credentials are valid by checking
     * that a public_key is stored — we can't make an API call without auth.
     */
    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool
    {
        return ! empty($credentials['public_key']);
    }

    /**
     * Verify Discord's Ed25519 signature.
     *
     * Discord signs: timestamp + raw_body using the application's Ed25519 private key.
     * We verify using the application's PUBLIC key.
     *
     * The $signature parameter is "{timestamp}|{X-Signature-Ed25519 header value}",
     * a convention set by WebhookReceiverController.
     *
     * The $secret parameter is the Discord application public key (hex-encoded).
     *
     * @param  string $payload    Raw request body.
     * @param  string $signature  "{timestamp}|{hex-encoded Ed25519 signature}"
     * @param  string $secret     Discord application public key (hex-encoded).
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        if (! extension_loaded('sodium')) {
            Log::error('DiscordWebhookRegistrar: sodium PHP extension is required for Discord signature verification.');

            return false;
        }

        [$timestamp, $sigHex] = explode('|', $signature, 2) + ['', ''];

        if (empty($timestamp) || empty($sigHex)) {
            return false;
        }

        try {
            $publicKey = sodium_hex2bin($secret);
            $sigBin = sodium_hex2bin($sigHex);
            $message = $timestamp.$payload;

            return sodium_crypto_sign_verify_detached($sigBin, $message, $publicKey);
        } catch (\SodiumException $e) {
            Log::warning('DiscordWebhookRegistrar: signature verification threw SodiumException', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
