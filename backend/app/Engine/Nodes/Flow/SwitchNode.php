<?php

namespace App\Engine\Nodes\Flow;

use App\Engine\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\Execution\NodePayload;
use App\Enums\ExecutionNodeStatus;

/**
 * Switch/Router Node
 * 
 * Multi-way branching based on value matching.
 * Routes execution to different paths based on case values.
 */
class SwitchNode implements NodeHandler
{
    public function handle(NodePayload $payload): NodeResult
    {
        $startTime = hrtime(true);

        // Configuration
        $value = $payload->config['value'] ?? $payload->inputData['value'] ?? null;
        $cases = $payload->config['cases'] ?? []; // ['case1' => 'route1', 'case2' => 'route2']
        $defaultRoute = $payload->config['default_route'] ?? 'default';
        $mode = $payload->config['mode'] ?? 'exact'; // exact | regex | range | type

        $matchedRoute = null;
        $matchedCase = null;

        // Match against cases
        foreach ($cases as $caseValue => $route) {
            if ($this->matchCase($value, $caseValue, $mode)) {
                $matchedRoute = $route;
                $matchedCase = $caseValue;
                break;
            }
        }

        // Use default if no match
        if ($matchedRoute === null) {
            $matchedRoute = $defaultRoute;
            $matchedCase = 'default';
        }

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return new NodeResult(
            status: ExecutionNodeStatus::Completed,
            output: [
                'matched_route' => $matchedRoute,
                'matched_case' => $matchedCase,
                'input_value' => $value,
                'total_cases' => count($cases),
            ],
            durationMs: $durationMs,
        );
    }

    /**
     * Match value against case
     */
    private function matchCase($value, $caseValue, string $mode): bool
    {
        return match ($mode) {
            'exact' => $value === $caseValue,
            'loose' => $value == $caseValue,
            'regex' => is_string($value) && preg_match($caseValue, $value),
            'range' => $this->matchRange($value, $caseValue),
            'type' => gettype($value) === $caseValue,
            'contains' => is_string($value) && is_string($caseValue) && str_contains($value, $caseValue),
            default => $value === $caseValue,
        };
    }

    /**
     * Match value against range
     */
    private function matchRange($value, $range): bool
    {
        if (! is_numeric($value)) {
            return false;
        }

        // Range format: "min-max" or ">value" or "<value"
        if (is_string($range)) {
            if (str_contains($range, '-')) {
                [$min, $max] = explode('-', $range, 2);

                return $value >= (float) $min && $value <= (float) $max;
            }

            if (str_starts_with($range, '>')) {
                return $value > (float) ltrim($range, '>');
            }

            if (str_starts_with($range, '<')) {
                return $value < (float) ltrim($range, '<');
            }
        }

        return false;
    }
}
