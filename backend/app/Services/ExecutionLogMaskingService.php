<?php

namespace App\Services;

use App\Models\Variable;
use Illuminate\Support\Facades\Cache;

class ExecutionLogMaskingService
{
    private const MASK_VALUE = '***REDACTED***';

    /**
     * Mask sensitive data in execution logs.
     * Replaces secret variable values with a mask placeholder.
     */
    public function maskData(mixed $data, string $workspaceId): mixed
    {
        if ($data === null) {
            return null;
        }

        $secretValues = $this->getSecretValues($workspaceId);

        if (empty($secretValues)) {
            return $data;
        }

        return $this->recursiveMask($data, $secretValues);
    }

    /**
     * Get all secret variable values for a workspace.
     *
     * @return array<string>
     */
    private function getSecretValues(string $workspaceId): array
    {
        $cacheKey = "workspace.{$workspaceId}.secret_values";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($workspaceId) {
            return Variable::query()
                ->where('workspace_id', $workspaceId)
                ->where('is_secret', true)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->pluck('value')
                ->filter()
                ->toArray();
        });
    }

    /**
     * Recursively mask secret values in data structures.
     *
     * @param  array<string>  $secretValues
     */
    private function recursiveMask(mixed $data, array $secretValues): mixed
    {
        if (is_string($data)) {
            return $this->maskString($data, $secretValues);
        }

        if (is_array($data)) {
            return array_map(
                fn ($value) => $this->recursiveMask($value, $secretValues),
                $data
            );
        }

        return $data;
    }

    /**
     * Mask secret values in a string.
     */
    private function maskString(string $data, array $secretValues): string
    {
        foreach ($secretValues as $secret) {
            if ($secret && str_contains($data, $secret)) {
                $data = str_replace($secret, self::MASK_VALUE, $data);
            }
        }

        return $data;
    }

    /**
     * Clear cached secret values for a workspace.
     */
    public function clearCache(string $workspaceId): void
    {
        Cache::forget("workspace.{$workspaceId}.secret_values");
    }
}
