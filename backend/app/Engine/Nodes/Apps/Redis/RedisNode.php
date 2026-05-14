<?php

namespace App\Engine\Nodes\Apps\Redis;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Redis;

/**
 * Redis node — key/value operations against a Redis instance.
 *
 * Credentials:
 *   host        — Redis host (default: 127.0.0.1)
 *   port        — Redis port (default: 6379)
 *   password    — Auth password (optional)
 *   database    — Database index (default: 0)
 *
 * Uses Laravel's Redis facade with a dynamic connection resolved at runtime.
 * The default "default" connection is used unless credentials specify a different host,
 * in which case a temporary connection config is pushed.
 */
class RedisNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'REDIS_ERROR';
    }

    protected function operations(): array
    {
        return [
            'get' => $this->get(...),
            'set' => $this->set(...),
            'delete' => $this->delete(...),
            'exists' => $this->exists(...),
            'incr' => $this->incr(...),
            'decr' => $this->decr(...),
            'lpush' => $this->lpush(...),
            'rpush' => $this->rpush(...),
            'lrange' => $this->lrange(...),
            'hset' => $this->hset(...),
            'hget' => $this->hget(...),
            'hgetall' => $this->hgetall(...),
            'expire' => $this->expire(...),
            'ttl' => $this->ttl(...),
            'keys' => $this->keys(...),
            'flush_db' => $this->flushDb(...),
        ];
    }

    private function redis(NodeInput $payload): \Illuminate\Redis\Connections\Connection
    {
        $credentials = $payload->credentials ?? [];
        $host = (string) ($credentials['host'] ?? '');

        if ($host && $host !== '127.0.0.1' && $host !== 'localhost') {
            $name = 'workflow_redis_node_'.md5(json_encode($credentials));

            config([
                "database.redis.{$name}" => [
                    'host' => $host,
                    'port' => (int) ($credentials['port'] ?? 6379),
                    'password' => $credentials['password'] ?? null,
                    'database' => (int) ($credentials['database'] ?? 0),
                ],
            ]);

            return Redis::connection($name);
        }

        $db = (int) ($credentials['database'] ?? 0);
        $connection = Redis::connection('default');
        $connection->select($db);

        return $connection;
    }

    /**
     * @return array<string, mixed>
     */
    private function get(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $value = $this->redis($payload)->get($key);

        return ['key' => $key, 'value' => $value, 'exists' => $value !== null];
    }

    /**
     * @return array<string, mixed>
     */
    private function set(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $value = $payload->inputData['value'] ?? $payload->config['value'] ?? null;
        $ttl = isset($payload->config['ttl']) ? (int) $payload->config['ttl'] : null;

        $serialized = is_array($value) ? json_encode($value) : (string) $value;

        if ($ttl !== null && $ttl > 0) {
            $this->redis($payload)->setex($key, $ttl, $serialized);
        } else {
            $this->redis($payload)->set($key, $serialized);
        }

        return ['key' => $key, 'set' => true, 'ttl' => $ttl];
    }

    /**
     * @return array<string, mixed>
     */
    private function delete(NodeInput $payload): array
    {
        $keys = array_filter((array) ($payload->inputData['keys'] ?? [$payload->inputData['key'] ?? $payload->config['key'] ?? '']));
        $count = $this->redis($payload)->del($keys);

        return ['deleted' => $count, 'keys' => $keys];
    }

    /**
     * @return array<string, mixed>
     */
    private function exists(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');

        return ['key' => $key, 'exists' => (bool) $this->redis($payload)->exists($key)];
    }

    /**
     * @return array<string, mixed>
     */
    private function incr(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $by = (int) ($payload->config['by'] ?? 1);

        $value = $by > 1 ? $this->redis($payload)->incrby($key, $by) : $this->redis($payload)->incr($key);

        return ['key' => $key, 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private function decr(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $by = (int) ($payload->config['by'] ?? 1);

        $value = $by > 1 ? $this->redis($payload)->decrby($key, $by) : $this->redis($payload)->decr($key);

        return ['key' => $key, 'value' => $value];
    }

    /**
     * @return array<string, mixed>
     */
    private function lpush(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $values = (array) ($payload->inputData['values'] ?? [$payload->inputData['value'] ?? $payload->config['value'] ?? '']);

        $length = $this->redis($payload)->lpush($key, ...$values);

        return ['key' => $key, 'length' => $length];
    }

    /**
     * @return array<string, mixed>
     */
    private function rpush(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $values = (array) ($payload->inputData['values'] ?? [$payload->inputData['value'] ?? $payload->config['value'] ?? '']);

        $length = $this->redis($payload)->rpush($key, ...$values);

        return ['key' => $key, 'length' => $length];
    }

    /**
     * @return array<string, mixed>
     */
    private function lrange(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $start = (int) ($payload->config['start'] ?? 0);
        $stop = (int) ($payload->config['stop'] ?? -1);

        return ['key' => $key, 'items' => $this->redis($payload)->lrange($key, $start, $stop)];
    }

    /**
     * @return array<string, mixed>
     */
    private function hset(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $fields = (array) ($payload->inputData['fields'] ?? $payload->config['fields'] ?? []);

        $this->redis($payload)->hmset($key, $fields);

        return ['key' => $key, 'set' => true, 'fields' => array_keys($fields)];
    }

    /**
     * @return array<string, mixed>
     */
    private function hget(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $field = (string) ($payload->inputData['field'] ?? $payload->config['field'] ?? '');

        return ['key' => $key, 'field' => $field, 'value' => $this->redis($payload)->hget($key, $field)];
    }

    /**
     * @return array<string, mixed>
     */
    private function hgetall(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');

        return ['key' => $key, 'fields' => $this->redis($payload)->hgetall($key)];
    }

    /**
     * @return array<string, mixed>
     */
    private function expire(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $seconds = (int) ($payload->inputData['seconds'] ?? $payload->config['seconds'] ?? 3600);

        return ['key' => $key, 'set' => (bool) $this->redis($payload)->expire($key, $seconds)];
    }

    /**
     * @return array<string, mixed>
     */
    private function ttl(NodeInput $payload): array
    {
        $key = (string) ($payload->inputData['key'] ?? $payload->config['key'] ?? '');
        $ttl = $this->redis($payload)->ttl($key);

        return ['key' => $key, 'ttl' => $ttl, 'persists' => $ttl === -1];
    }

    /**
     * @return array<string, mixed>
     */
    private function keys(NodeInput $payload): array
    {
        $pattern = (string) ($payload->inputData['pattern'] ?? $payload->config['pattern'] ?? '*');

        return ['keys' => $this->redis($payload)->keys($pattern)];
    }

    /**
     * @return array<string, mixed>
     */
    private function flushDb(NodeInput $payload): array
    {
        $this->redis($payload)->flushdb();

        return ['flushed' => true];
    }
}
