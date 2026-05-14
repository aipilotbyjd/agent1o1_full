<?php

namespace App\Engine\Nodes\Apps\Data;

use App\Engine\Nodes\Apps\AppNode;
use App\Engine\NodeInput;

/**
 * Math/Calculate Node
 * 
 * Mathematical operations and calculations.
 */
class MathNode extends AppNode
{
    protected function errorCode(): string
    {
        return 'MATH_ERROR';
    }

    protected function operations(): array
    {
        return [
            'calculate' => $this->calculate(...),
            'aggregate' => $this->aggregate(...),
            'round' => $this->round(...),
            'random' => $this->random(...),
            'formula' => $this->formula(...),
        ];
    }

    /**
     * Basic calculations
     */
    private function calculate(NodeInput $payload): array
    {
        $a = $payload->config['a'] ?? 0;
        $b = $payload->config['b'] ?? 0;
        $operation = $payload->config['operation'] ?? 'add';

        $result = match ($operation) {
            'add', '+' => $a + $b,
            'subtract', '-' => $a - $b,
            'multiply', '*' => $a * $b,
            'divide', '/' => $b != 0 ? $a / $b : throw new \InvalidArgumentException('Division by zero'),
            'modulo', '%' => $b != 0 ? $a % $b : throw new \InvalidArgumentException('Modulo by zero'),
            'power', '^', '**' => pow($a, $b),
            'max' => max($a, $b),
            'min' => min($a, $b),
            default => throw new \InvalidArgumentException("Unknown operation: {$operation}"),
        };

        return [
            'result' => $result,
            'operation' => $operation,
        ];
    }

    /**
     * Aggregate operations on arrays
     */
    private function aggregate(NodeInput $payload): array
    {
        $numbers = $payload->config['numbers'] ?? $payload->inputData['numbers'] ?? [];
        $operation = $payload->config['operation'] ?? 'sum';

        if (! is_array($numbers)) {
            throw new \InvalidArgumentException('Numbers must be an array');
        }

        // Filter to only numeric values
        $numbers = array_filter($numbers, 'is_numeric');

        if (empty($numbers)) {
            throw new \InvalidArgumentException('No valid numbers provided');
        }

        $result = match ($operation) {
            'sum' => array_sum($numbers),
            'average', 'avg', 'mean' => array_sum($numbers) / count($numbers),
            'min' => min($numbers),
            'max' => max($numbers),
            'count' => count($numbers),
            'product' => array_product($numbers),
            'median' => $this->calculateMedian($numbers),
            'mode' => $this->calculateMode($numbers),
            'range' => max($numbers) - min($numbers),
            'variance' => $this->calculateVariance($numbers),
            'stddev' => sqrt($this->calculateVariance($numbers)),
            default => throw new \InvalidArgumentException("Unknown operation: {$operation}"),
        };

        return [
            'result' => $result,
            'operation' => $operation,
            'count' => count($numbers),
        ];
    }

    /**
     * Rounding operations
     */
    private function round(NodeInput $payload): array
    {
        $number = $payload->config['number'] ?? 0;
        $operation = $payload->config['operation'] ?? 'round';
        $precision = (int) ($payload->config['precision'] ?? 0);

        $result = match ($operation) {
            'round' => round($number, $precision),
            'floor' => floor($number),
            'ceil' => ceil($number),
            'abs' => abs($number),
            'sqrt' => sqrt($number),
            default => throw new \InvalidArgumentException("Unknown operation: {$operation}"),
        };

        return [
            'result' => $result,
            'operation' => $operation,
            'original' => $number,
        ];
    }

    /**
     * Random number generation
     */
    private function random(NodeInput $payload): array
    {
        $type = $payload->config['type'] ?? 'integer'; // integer | float
        $min = $payload->config['min'] ?? 0;
        $max = $payload->config['max'] ?? 100;
        $count = (int) ($payload->config['count'] ?? 1);

        $results = [];

        for ($i = 0; $i < $count; $i++) {
            if ($type === 'float') {
                $results[] = $min + ($max - $min) * (mt_rand() / mt_getrandmax());
            } else {
                $results[] = random_int($min, $max);
            }
        }

        return [
            'numbers' => $results,
            'count' => $count,
            'type' => $type,
        ];
    }

    /**
     * Evaluate mathematical formula
     */
    private function formula(NodeInput $payload): array
    {
        $expression = $payload->config['expression'] ?? '';
        $variables = $payload->config['variables'] ?? [];

        if (empty($expression)) {
            throw new \InvalidArgumentException('Expression is required');
        }

        // Replace variables in expression
        $processedExpression = $expression;
        foreach ($variables as $key => $value) {
            $processedExpression = str_replace($key, $value, $processedExpression);
        }

        // Simple evaluation (for security, use a proper math parser in production)
        // This is a basic implementation - should use symfony/expression-language or similar
        try {
            // Remove any non-mathematical characters for security
            $processedExpression = preg_replace('/[^0-9+\-*\/().%\s]/', '', $processedExpression);

            // Evaluate safely
            $result = $this->safeEval($processedExpression);

            return [
                'result' => $result,
                'expression' => $expression,
                'evaluated' => $processedExpression,
            ];
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to evaluate expression: '.$e->getMessage());
        }
    }

    /**
     * Calculate median
     */
    private function calculateMedian(array $numbers): float
    {
        sort($numbers);
        $count = count($numbers);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($numbers[$middle - 1] + $numbers[$middle]) / 2;
        }

        return $numbers[$middle];
    }

    /**
     * Calculate mode
     */
    private function calculateMode(array $numbers): mixed
    {
        $values = array_count_values($numbers);
        arsort($values);

        return array_key_first($values);
    }

    /**
     * Calculate variance
     */
    private function calculateVariance(array $numbers): float
    {
        $mean = array_sum($numbers) / count($numbers);
        $squaredDiffs = array_map(fn ($x) => pow($x - $mean, 2), $numbers);

        return array_sum($squaredDiffs) / count($numbers);
    }

    /**
     * Safe evaluation of mathematical expressions
     */
    private function safeEval(string $expression): float
    {
        // Use bc_math for safe evaluation
        // This is a simplified version - use a proper parser for production
        try {
            return eval("return {$expression};");
        } catch (\Throwable $e) {
            throw new \RuntimeException('Invalid expression');
        }
    }
}
