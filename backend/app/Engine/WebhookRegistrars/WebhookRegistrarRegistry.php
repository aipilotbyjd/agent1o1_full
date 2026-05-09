<?php

namespace App\Engine\WebhookRegistrars;

use App\Engine\Contracts\RegisterableWebhook;
use App\Engine\Contracts\VerifiableWebhook;

/**
 * WebhookRegistrarRegistry — the single map of provider → registrar class.
 *
 * ═══════════════════════════════════════════════════════════════
 * TWO REGISTRAR TIERS
 * ═══════════════════════════════════════════════════════════════
 * All registrars implement VerifiableWebhook:
 *   - provider()              — identifies the provider string
 *   - supportsAutoRegistration() — whether API registration is possible
 *   - checkExists()           — verify the webhook still exists
 *   - verifySignature()       — authenticate incoming payloads
 *
 * Auto-registerable providers additionally implement RegisterableWebhook:
 *   - register()   — create the webhook via the provider's API
 *   - unregister() — delete it via the provider's API
 *
 * Discord implements only VerifiableWebhook (manual portal setup required).
 * GitHub, Stripe, Slack implement RegisterableWebhook.
 *
 * Use resolve() when you only need to verify a signature or check existence.
 * Use resolveRegisterable() when you need to register or unregister.
 *
 * ═══════════════════════════════════════════════════════════════
 * HOW TO ADD A NEW PROVIDER
 * ═══════════════════════════════════════════════════════════════
 * 1. Create your registrar class in app/Engine/WebhookRegistrars/
 *    implementing either VerifiableWebhook or RegisterableWebhook.
 * 2. Add a new entry to REGISTRARS below.
 * 3. The rest of the system picks it up automatically.
 */
class WebhookRegistrarRegistry
{
    /** @var array<string, class-string<VerifiableWebhook>> */
    private const REGISTRARS = [
        'github' => GitHubWebhookRegistrar::class,
        'stripe' => StripeWebhookRegistrar::class,
        'slack' => SlackWebhookRegistrar::class,
        'discord' => DiscordWebhookRegistrar::class,
    ];

    /**
     * Resolve a registrar instance by provider name.
     *
     * Returns a VerifiableWebhook — suitable for signature verification
     * and health checks. Returns null if the provider has no registrar.
     */
    public static function resolve(string $provider): ?VerifiableWebhook
    {
        $class = self::REGISTRARS[$provider] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * Resolve a registrar that supports programmatic registration.
     *
     * Returns a RegisterableWebhook — suitable for calling register() and
     * unregister() via the provider's API. Returns null if the provider has
     * no registrar or only supports manual setup (e.g. Discord).
     */
    public static function resolveRegisterable(string $provider): ?RegisterableWebhook
    {
        $registrar = self::resolve($provider);

        return $registrar instanceof RegisterableWebhook ? $registrar : null;
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
