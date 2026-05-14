<?php

namespace App\Engine\Nodes\Apps\Debug;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;
use Illuminate\Support\Facades\Log;

/**
 * Logger/Debug Node
 * 
 * Log messages and debug workflow execution.
 */
class LoggerNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'LOGGER_ERROR';
    }

    protected function operations(): array
    {
        return [
            'log' => $this->log(...),
            'debug' => $this->debug(...),
            'inspect' => $this->inspect(...),
        ];
    }

    /**
     * Log message
     */
    private function log(NodeInput $payload): array
    {
        $message = $payload->config['message'] ?? '';
        $level = $payload->config['level'] ?? 'info'; // debug | info | warning | error
        $data = $payload->config['data'] ?? $payload->inputData ?? [];
        $channel = $payload->config['channel'] ?? 'workflow';
        $includeContext = (bool) ($payload->config['include_context'] ?? true);

        if (empty($message)) {
            throw new \InvalidArgumentException('Log message is required');
        }

        // Build context
        $context = [];
        if ($includeContext) {
            $context = [
                'workflow_id' => $payload->executionMeta['workflow_id'] ?? null,
                'execution_id' => $payload->executionMeta['execution_id'] ?? null,
                'node_id' => $payload->executionMeta['node_id'] ?? null,
                'timestamp' => now()->toIso8601String(),
            ];
        }

        // Add data to context
        if (! empty($data)) {
            $context['data'] = $data;
        }

        // Log with appropriate level
        match ($level) {
            'debug' => Log::channel($channel)->debug($message, $context),
            'info' => Log::channel($channel)->info($message, $context),
            'warning', 'warn' => Log::channel($channel)->warning($message, $context),
            'error' => Log::channel($channel)->error($message, $context),
            default => Log::channel($channel)->info($message, $context),
        };

        return [
            'logged' => true,
            'message' => $message,
            'level' => $level,
            'channel' => $channel,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Debug - Log variable values
     */
    private function debug(NodeInput $payload): array
    {
        $variables = $payload->config['variables'] ?? $payload->inputData ?? [];
        $label = $payload->config['label'] ?? 'Debug';
        $channel = $payload->config['channel'] ?? 'workflow';

        $message = "🔍 {$label}";

        Log::channel($channel)->debug($message, [
            'variables' => $variables,
            'workflow_id' => $payload->executionMeta['workflow_id'] ?? null,
            'execution_id' => $payload->executionMeta['execution_id'] ?? null,
            'node_id' => $payload->executionMeta['node_id'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);

        return [
            'logged' => true,
            'label' => $label,
            'variable_count' => is_array($variables) ? count($variables) : 1,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Inspect - Detailed variable inspection
     */
    private function inspect(NodeInput $payload): array
    {
        $value = $payload->config['value'] ?? $payload->inputData['value'] ?? null;
        $label = $payload->config['label'] ?? 'Inspect';
        $showType = (bool) ($payload->config['show_type'] ?? true);
        $showSize = (bool) ($payload->config['show_size'] ?? true);

        $inspection = [
            'value' => $value,
        ];

        if ($showType) {
            $inspection['type'] = gettype($value);

            if (is_object($value)) {
                $inspection['class'] = get_class($value);
            }
        }

        if ($showSize) {
            if (is_array($value)) {
                $inspection['count'] = count($value);
            } elseif (is_string($value)) {
                $inspection['length'] = strlen($value);
            }
        }

        // Additional inspection info
        if (is_array($value)) {
            $inspection['keys'] = array_keys($value);
            $inspection['is_associative'] = $this->isAssociative($value);
        }

        Log::channel('workflow')->debug("🔍 {$label}", [
            'inspection' => $inspection,
            'workflow_id' => $payload->executionMeta['workflow_id'] ?? null,
            'execution_id' => $payload->executionMeta['execution_id'] ?? null,
            'timestamp' => now()->toIso8601String(),
        ]);

        return [
            'label' => $label,
            'inspection' => $inspection,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Check if array is associative
     */
    private function isAssociative(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
