<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;

/**
 * JSON Node
 * 
 * Comprehensive JSON operations for workflow data manipulation.
 */
class JsonNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'JSON_ERROR';
    }

    protected function operations(): array
    {
        return [
            'parse' => $this->parse(...),
            'stringify' => $this->stringify(...),
            'extract' => $this->extract(...),
            'merge' => $this->merge(...),
            'validate' => $this->validate(...),
        ];
    }

    /**
     * Parse JSON string to object/array
     */
    private function parse(NodePayload $payload): array
    {
        $jsonString = $payload->config['json_string'] ?? $payload->inputData['json_string'] ?? '';
        $assoc = (bool) ($payload->config['return_array'] ?? true);

        if (empty($jsonString)) {
            throw new \InvalidArgumentException('JSON string is required');
        }

        try {
            $decoded = json_decode($jsonString, $assoc, 512, JSON_THROW_ON_ERROR);

            return [
                'data' => $decoded,
                'success' => true,
            ];
        } catch (\JsonException $e) {
            throw new \RuntimeException('Invalid JSON: '.$e->getMessage());
        }
    }

    /**
     * Convert data to JSON string
     */
    private function stringify(NodePayload $payload): array
    {
        $data = $payload->config['data'] ?? $payload->inputData['data'] ?? null;
        $pretty = (bool) ($payload->config['pretty_print'] ?? false);
        $escapeUnicode = (bool) ($payload->config['escape_unicode'] ?? false);

        if ($data === null) {
            throw new \InvalidArgumentException('Data is required');
        }

        $options = JSON_THROW_ON_ERROR;

        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }

        if (! $escapeUnicode) {
            $options |= JSON_UNESCAPED_UNICODE;
        }

        try {
            $jsonString = json_encode($data, $options);

            return [
                'json_string' => $jsonString,
                'length' => strlen($jsonString),
                'success' => true,
            ];
        } catch (\JsonException $e) {
            throw new \RuntimeException('Failed to encode JSON: '.$e->getMessage());
        }
    }

    /**
     * Extract value from JSON using JSONPath-like syntax
     */
    private function extract(NodePayload $payload): array
    {
        $data = $payload->config['data'] ?? $payload->inputData['data'] ?? null;
        $path = $payload->config['path'] ?? '';
        $defaultValue = $payload->config['default'] ?? null;

        if ($data === null) {
            throw new \InvalidArgumentException('Data is required');
        }

        if (empty($path)) {
            throw new \InvalidArgumentException('Path is required');
        }

        // Use Laravel's data_get helper which supports dot notation
        $value = data_get($data, $path, $defaultValue);

        return [
            'value' => $value,
            'path' => $path,
            'found' => $value !== $defaultValue,
        ];
    }

    /**
     * Merge multiple JSON objects
     */
    private function merge(NodePayload $payload): array
    {
        $objects = $payload->config['objects'] ?? $payload->inputData['objects'] ?? [];
        $mode = $payload->config['mode'] ?? 'deep'; // deep | shallow

        if (empty($objects) || ! is_array($objects)) {
            throw new \InvalidArgumentException('Array of objects is required');
        }

        if ($mode === 'deep') {
            $merged = $this->deepMerge($objects);
        } else {
            $merged = array_merge(...$objects);
        }

        return [
            'merged' => $merged,
            'count' => count($objects),
        ];
    }

    /**
     * Validate JSON against a schema
     */
    private function validate(NodePayload $payload): array
    {
        $data = $payload->config['data'] ?? $payload->inputData['data'] ?? null;
        $schema = $payload->config['schema'] ?? [];

        if ($data === null) {
            throw new \InvalidArgumentException('Data is required');
        }

        if (empty($schema)) {
            throw new \InvalidArgumentException('Schema is required');
        }

        $errors = [];
        $valid = $this->validateAgainstSchema($data, $schema, '', $errors);

        return [
            'valid' => $valid,
            'errors' => $errors,
            'error_count' => count($errors),
        ];
    }

    /**
     * Deep merge arrays recursively
     */
    private function deepMerge(array $arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    $result[$key] = $this->deepMerge([$result[$key], $value]);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Simple schema validation
     */
    private function validateAgainstSchema($data, array $schema, string $path, array &$errors): bool
    {
        $valid = true;

        // Check required fields
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $field) {
                if (! isset($data[$field])) {
                    $errors[] = "Missing required field: {$path}.{$field}";
                    $valid = false;
                }
            }
        }

        // Check type
        if (isset($schema['type'])) {
            $actualType = gettype($data);
            $expectedType = $schema['type'];

            $typeMap = [
                'object' => 'array',
                'array' => 'array',
                'string' => 'string',
                'number' => ['integer', 'double'],
                'integer' => 'integer',
                'boolean' => 'boolean',
            ];

            $expected = $typeMap[$expectedType] ?? $expectedType;

            if (is_array($expected)) {
                if (! in_array($actualType, $expected)) {
                    $errors[] = "Type mismatch at {$path}: expected ".implode('|', $expected).", got {$actualType}";
                    $valid = false;
                }
            } elseif ($actualType !== $expected) {
                $errors[] = "Type mismatch at {$path}: expected {$expected}, got {$actualType}";
                $valid = false;
            }
        }

        // Check properties for objects
        if (isset($schema['properties']) && is_array($data)) {
            foreach ($schema['properties'] as $prop => $propSchema) {
                if (isset($data[$prop])) {
                    $newPath = $path ? "{$path}.{$prop}" : $prop;
                    $valid = $this->validateAgainstSchema($data[$prop], $propSchema, $newPath, $errors) && $valid;
                }
            }
        }

        return $valid;
    }
}
