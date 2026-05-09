<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\Execution\NodePayload;

/**
 * Filter Node
 * 
 * Filter arrays based on conditions, remove duplicates, and more.
 */
class FilterNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'FILTER_ERROR';
    }

    protected function operations(): array
    {
        return [
            'filter_by_condition' => $this->filterByCondition(...),
            'filter_by_value' => $this->filterByValue(...),
            'remove_duplicates' => $this->removeDuplicates(...),
            'remove_empty' => $this->removeEmpty(...),
        ];
    }

    /**
     * Filter items by condition
     */
    private function filterByCondition(NodePayload $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $field = $payload->config['field'] ?? '';
        $operator = $payload->config['operator'] ?? 'equals';
        $value = $payload->config['value'] ?? null;
        $mode = $payload->config['mode'] ?? 'keep'; // keep | remove

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        if (empty($field)) {
            throw new \InvalidArgumentException('Field is required');
        }

        $filtered = array_filter($items, function ($item) use ($field, $operator, $value, $mode) {
            $itemValue = data_get($item, $field);
            $matches = $this->evaluateCondition($itemValue, $operator, $value);

            return $mode === 'keep' ? $matches : ! $matches;
        });

        return [
            'items' => array_values($filtered),
            'count' => count($filtered),
            'filtered_out' => count($items) - count($filtered),
        ];
    }

    /**
     * Filter by specific values
     */
    private function filterByValue(NodePayload $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $field = $payload->config['field'] ?? '';
        $values = $payload->config['values'] ?? [];
        $mode = $payload->config['mode'] ?? 'keep'; // keep | remove

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        if (! is_array($values)) {
            $values = [$values];
        }

        $filtered = array_filter($items, function ($item) use ($field, $values, $mode) {
            $itemValue = empty($field) ? $item : data_get($item, $field);
            $inList = in_array($itemValue, $values, true);

            return $mode === 'keep' ? $inList : ! $inList;
        });

        return [
            'items' => array_values($filtered),
            'count' => count($filtered),
            'filtered_out' => count($items) - count($filtered),
        ];
    }

    /**
     * Remove duplicate items
     */
    private function removeDuplicates(NodePayload $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $field = $payload->config['field'] ?? null; // null = compare entire object

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        $seen = [];
        $unique = [];

        foreach ($items as $item) {
            $key = $field ? data_get($item, $field) : json_encode($item);

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $item;
            }
        }

        return [
            'items' => $unique,
            'count' => count($unique),
            'duplicates_removed' => count($items) - count($unique),
        ];
    }

    /**
     * Remove empty/null values
     */
    private function removeEmpty(NodePayload $payload): array
    {
        $items = $payload->inputData['items'] ?? [];
        $field = $payload->config['field'] ?? null;
        $strict = (bool) ($payload->config['strict'] ?? false); // true = remove '', false, 0, null

        if (! is_array($items)) {
            throw new \InvalidArgumentException('Items must be an array');
        }

        $filtered = array_filter($items, function ($item) use ($field, $strict) {
            $value = $field ? data_get($item, $field) : $item;

            if ($strict) {
                return $value !== null && $value !== '';
            }

            return ! empty($value);
        });

        return [
            'items' => array_values($filtered),
            'count' => count($filtered),
            'removed' => count($items) - count($filtered),
        ];
    }

    /**
     * Evaluate condition
     */
    private function evaluateCondition($itemValue, string $operator, $compareValue): bool
    {
        return match ($operator) {
            'equals', '==', '===' => $itemValue == $compareValue,
            'not_equals', '!=', '!==' => $itemValue != $compareValue,
            'contains' => is_string($itemValue) && str_contains($itemValue, $compareValue),
            'not_contains' => is_string($itemValue) && ! str_contains($itemValue, $compareValue),
            'starts_with' => is_string($itemValue) && str_starts_with($itemValue, $compareValue),
            'ends_with' => is_string($itemValue) && str_ends_with($itemValue, $compareValue),
            'gt', '>' => $itemValue > $compareValue,
            'gte', '>=' => $itemValue >= $compareValue,
            'lt', '<' => $itemValue < $compareValue,
            'lte', '<=' => $itemValue <= $compareValue,
            'is_empty' => empty($itemValue),
            'is_not_empty' => ! empty($itemValue),
            'is_null' => $itemValue === null,
            'is_not_null' => $itemValue !== null,
            'regex' => is_string($itemValue) && preg_match($compareValue, $itemValue),
            'in' => is_array($compareValue) && in_array($itemValue, $compareValue),
            'not_in' => is_array($compareValue) && ! in_array($itemValue, $compareValue),
            default => false,
        };
    }
}
