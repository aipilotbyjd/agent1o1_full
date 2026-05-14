<?php

namespace App\Contracts;

interface WebhookRegistrar
{
    public function provider(): string;

    public function supportsAutoRegistration(): bool;

    public function checkExists(string $externalId, array $credentials, array $providerConfig = []): bool;

    public function verifySignature(string $payload, string $signature, string $secret): bool;

    /**
     * Register a webhook with the provider.
     * Only called when supportsAutoRegistration() returns true.
     *
     * @return array{external_id: string, secret: string}
     */
    public function register(string $callbackUrl, array $events, array $credentials, array $providerConfig = []): array;

    /**
     * Unregister a webhook from the provider. Must be idempotent.
     * Only called when supportsAutoRegistration() returns true.
     */
    public function unregister(string $externalId, array $credentials, array $providerConfig = []): void;
}
