<?php

namespace App\Engine\Webhook;

use App\Contracts\WebhookRegistrar;

/**
 * WebhookRegistry — the single map of provider → registrar class.
 *
 * ═══════════════════════════════════════════════════════════════
 * TWO REGISTRAR TIERS
 * ═══════════════════════════════════════════════════════════════
 * All registrars implement WebhookRegistrar:
 *   - provider()              — identifies the provider string
 *   - supportsAutoRegistration() — whether API registration is possible
 *   - checkExists()           — verify the webhook still exists
 *   - verifySignature()       — authenticate incoming payloads
 *
 * Auto-registerable providers support register() and unregister().
 * Discord requires manual portal setup — supportsAutoRegistration() returns false.
 *
 * Use resolve() when you only need to verify a signature or check existence.
 * Use resolveRegisterable() when you need to register or unregister.
 *
 * ═══════════════════════════════════════════════════════════════
 * HOW TO ADD A NEW PROVIDER
 * ═══════════════════════════════════════════════════════════════
 * 1. Create your registrar class in app/Engine/Webhook/
 *    implementing WebhookRegistrar.
 * 2. Add a new entry to REGISTRARS below.
 * 3. The rest of the system picks it up automatically.
 */
class WebhookRegistry
{
    /** @var array<string, class-string<WebhookRegistrar>> */
    private const REGISTRARS = [
        'github' => GitHubRegistrar::class,
        'stripe' => StripeRegistrar::class,
        'slack' => SlackRegistrar::class,
        'discord' => DiscordRegistrar::class,
    ];

    /**
     * Resolve a registrar instance by provider name.
     *
     * Returns a WebhookRegistrar — suitable for signature verification
     * and health checks. Returns null if the provider has no registrar.
     */
    public static function resolve(string $provider): ?WebhookRegistrar
    {
        $class = self::REGISTRARS[$provider] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * Resolve a registrar that supports programmatic registration.
     *
     * Returns a WebhookRegistrar — suitable for calling register() and
     * unregister() via the provider's API. Returns null if the provider has
     * no registrar or only supports manual setup (e.g. Discord).
     */
    public static function resolveRegisterable(string $provider): ?WebhookRegistrar
    {
        $registrar = self::resolve($provider);

        return ($registrar !== null && $registrar->supportsAutoRegistration()) ? $registrar : null;
    }

    /**
     * Check if a provider has a registered webhook handler.
     */
    public static function supports(string $provider): bool
    {
        return isset(self::REGISTRARS[$provider]);
    }

    /**
     * Check if a provider supports automatic webhook registration via API.
     * Returns false for providers requiring manual setup (e.g. Discord).
     */
    public static function supportsAutoRegistration(string $provider): bool
    {
        $registrar = self::resolve($provider);

        return $registrar !== null && $registrar->supportsAutoRegistration();
    }

    /**
     * Get all supported provider names.
     *
     * @return list<string>
     */
    public static function providers(): array
    {
        return array_keys(self::REGISTRARS);
    }

    /**
     * Get all provider names that support automatic registration.
     *
     * @return list<string>
     */
    public static function autoRegistrationProviders(): array
    {
        return array_filter(
            self::providers(),
            fn (string $p) => self::supportsAutoRegistration($p)
        );
    }
}
