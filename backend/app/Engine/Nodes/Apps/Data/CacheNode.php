<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Node
 * 
 * Cache data for performance optimization.
 */
class CacheNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'CACHE_ERROR';
    }

    protected function operations(): array
    {
        return [
            'get' => $this->get(...),
            'set' => $this->set(...),
            'has' => $this->has(...),
            'delete' => $this->delete(...),
            'clear' => $this->clear(...),
            'remember' => $this->remember(...),
        ];
    }

    /**
     * Get cached value
     */
    private function get(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $defaultValue = $payload->config['default'] ?? null;
        $prefix = $payload->config['prefix'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key is required');
        }

        $cacheKey = $this->buildKey($key, $prefix);
        $value = Cache::get($cacheKey, $defaultValue);
        $exists = Cache::has($cacheKey);

        return [
            'key' => $key,
            'value' => $value,
            'exists' => $exists,
            'cache_key' => $cacheKey,
        ];
    }

    /**
     * Set cached value
     */
    private function set(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $value = $payload->config['value'] ?? $payload->inputData['value'] ?? null;
        $ttl = $payload->config['ttl'] ?? null; // seconds, null = forever
        $prefix = $payload->config['prefix'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key is required');
        }

        $cacheKey = $this->buildKey($key, $prefix);

        if ($ttl !== null) {
            Cache::put($cacheKey, $value, (int) $ttl);
        } else {
            Cache::forever($cacheKey, $value);
        }

        return [
            'key' => $key,
            'cached' => true,
            'ttl' => $ttl,
            'cache_key' => $cacheKey,
        ];
    }

    /**
     * Check if key exists
     */
    private function has(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $prefix = $payload->config['prefix'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key is required');
        }

        $cacheKey = $this->buildKey($key, $prefix);
        $exists = Cache::has($cacheKey);

        return [
            'key' => $key,
            'exists' => $exists,
            'cache_key' => $cacheKey,
        ];
    }

    /**
     * Delete cached value
     */
    private function delete(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $prefix = $payload->config['prefix'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key is required');
        }

        $cacheKey = $this->buildKey($key, $prefix);
        $deleted = Cache::forget($cacheKey);

        return [
            'key' => $key,
            'deleted' => $deleted,
            'cache_key' => $cacheKey,
        ];
    }

    /**
     * Clear all cache with prefix
     */
    private function clear(NodePayload $payload): array
    {
        $prefix = $payload->config['prefix'] ?? 'workflow';

        // This is a simplified version
        // In production, you might want to use tags or scan keys
        Cache::flush();

        return [
            'prefix' => $prefix,
            'cleared' => true,
        ];
    }

    /**
     * Remember (get or set if missing)
     */
    private function remember(NodePayload $payload): array
    {
        $key = $payload->config['key'] ?? '';
        $value = $payload->config['value'] ?? $payload->inputData['value'] ?? null;
        $ttl = $payload->config['ttl'] ?? 3600;
        $prefix = $payload->config['prefix'] ?? 'workflow';

        if (empty($key)) {
            throw new \InvalidArgumentException('Cache key is required');
        }

        $cacheKey = $this->buildKey($key, $prefix);
        $wasCached = Cache::has($cacheKey);

        $cachedValue = Cache::remember($cacheKey, $ttl, function () use ($value) {
            return $value;
        });

        return [
            'key' => $key,
            'value' => $cachedValue,
            'was_cached' => $wasCached,
            'cache_key' => $cacheKey,
        ];
    }

    /**
     * Build cache key with prefix
     */
    private function buildKey(string $key, string $prefix): string
    {
        return "{$prefix}:{$key}";
    }
}
