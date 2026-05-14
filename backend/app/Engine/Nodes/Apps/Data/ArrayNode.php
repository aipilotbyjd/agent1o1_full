<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;

/**
 * Array Operations Node
 * 
 * Map, filter, reduce, sort, and transform arrays.
 */
class ArrayNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'ARRAY_ERROR';
    }

    protected function operations(): array
    {
        return [
            'map' => $this->map(...),
            'reduce' => $this->reduce(...),
            'sort' => $this->sort(...),
            'group_by' => $this->groupBy(...),
            'unique' => $this->unique(...),
            'flatten' => $this->flatten(...),
            'slice' => $this->slice(...),
            'chunk' => $this->chunk(...),
        ];
    }

    /**
     * Map - Extract/transform specific fields from each item
     */
    private function map(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $fields = $payload->config['fields'] ?? []; // ['new_name' => 'source.path']
        $mode = $payload->config['mode'] ?? 'extract'; // extract | transform

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        if (empty($fields)) {
            throw new \InvalidArgumentException('Fields mapping is required');
        }

        $mapped = array_map(function ($item) use ($fields) {
            $result = [];

            foreach ($fields as $newName => $sourcePath) {
                $result[$newName] = data_get($item, $sourcePath);
            }

            return $result;
        }, $items);

        return [
            'items' => $mapped,
            'count' => count($mapped),
        ];
    }

    /**
     * Reduce - Aggregate array into single value
     */
    private function reduce(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $operation = $payload->config['operation'] ?? 'sum'; // sum | avg | min | max | count | concat
        $field = $payload->config['field'] ?? null;

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        // Extract values if field specified
        $values = $field
            ? array_map(fn ($item) => data_get($item, $field), $items)
            : $items;

        $result = match ($operation) {
            'sum' => array_sum($values),
            'avg', 'average' => count($values) > 0 ? array_sum($values) / count($values) : 0,
            'min' => count($values) > 0 ? min($values) : null,
            'max' => count($values) > 0 ? max($values) : null,
            'count' => count($values),
            'concat', 'join' => implode($payload->config['separator'] ?? ', ', $values),
            'product' => array_product($values),
            default => null,
        };

        return [
            'result' => $result,
            'operation' => $operation,
            'items_processed' => count($values),
        ];
    }

    /**
     * Sort array by field
     */
    private function sort(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $field = $payload->config['field'] ?? null;
        $direction = $payload->config['direction'] ?? 'asc'; // asc | desc
        $type = $payload->config['type'] ?? 'auto'; // auto | string | numeric

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        $sorted = $items;

        usort($sorted, function ($a, $b) use ($field, $direction, $type) {
            $aVal = $field ? data_get($a, $field) : $a;
            $bVal = $field ? data_get($b, $field) : $b;

            // Type-specific comparison
            if ($type === 'numeric' || (is_numeric($aVal) && is_numeric($bVal))) {
                $comparison = $aVal <=> $bVal;
            } elseif ($type === 'string') {
                $comparison = strcasecmp($aVal, $bVal);
            } else {
                $comparison = $aVal <=> $bVal;
            }

            return $direction === 'desc' ? -$comparison : $comparison;
        });

        return [
            'items' => $sorted,
            'count' => count($sorted),
        ];
    }

    /**
     * Group items by field value
     */
    private function groupBy(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $field = $payload->config['field'] ?? '';

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        if (empty($field)) {
            throw new \InvalidArgumentException('Field is required');
        }

        $groups = [];

        foreach ($items as $item) {
            $key = data_get($item, $field, '_null');
            $groups[$key] = $groups[$key] ?? [];
            $groups[$key][] = $item;
        }

        return [
            'groups' => $groups,
            'group_count' => count($groups),
            'total_items' => count($items),
        ];
    }

    /**
     * Get unique values
     */
    private function unique(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $field = $payload->config['field'] ?? null;

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        if ($field) {
            // Get unique values for specific field
            $values = array_map(fn ($item) => data_get($item, $field), $items);
            $unique = array_values(array_unique($values));
        } else {
            // Get unique items
            $unique = array_values(array_unique($items, SORT_REGULAR));
        }

        return [
            'items' => $unique,
            'count' => count($unique),
            'duplicates_removed' => count($items) - count($unique),
        ];
    }

    /**
     * Flatten nested arrays
     */
    private function flatten(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $depth = (int) ($payload->config['depth'] ?? -1); // -1 = infinite

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        $flattened = $this->flattenArray($items, $depth);

        return [
            'items' => $flattened,
            'count' => count($flattened),
        ];
    }

    /**
     * Slice array (get subset)
     */
    private function slice(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $start = (int) ($payload->config['start'] ?? 0);
        $length = $payload->config['length'] ?? null;

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        $sliced = $length !== null
            ? array_slice($items, $start, $length)
            : array_slice($items, $start);

        return [
            'items' => $sliced,
            'count' => count($sliced),
        ];
    }

    /**
     * Chunk array into smaller arrays
     */
    private function chunk(NodeInput $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $size = (int) ($payload->config['size'] ?? 10);

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        if ($size < 1) {
            throw new \InvalidArgumentException('Chunk size must be at least 1');
        }

        $chunks = array_chunk($items, $size);

        return [
            'chunks' => $chunks,
            'chunk_count' => count($chunks),
            'total_items' => count($items),
        ];
    }

    /**
     * Recursively flatten array
     */
    private function flattenArray(array $array, int $depth, int $currentDepth = 0): array
    {
        $result = [];

        foreach ($array as $value) {
            if (is_array($value) && ($depth === -1 || $currentDepth < $depth)) {
                $result = array_merge($result, $this->flattenArray($value, $depth, $currentDepth + 1));
            } else {
                $result[] = $value;
            }
        }

        return $result;
    }
}
