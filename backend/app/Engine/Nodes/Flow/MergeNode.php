<?php

namespace App\Engine\Nodes\Flow;

use App\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\NodeInput;

/**
 * Merges data from multiple upstream branches into a single output.
 */
class MergeNode implements NodeHandler
{
    public function handle(NodeInput $payload): NodeResult
    {
        $startTime = hrtime(true);

        $mode = $payload->config['mode'] ?? 'append';

        $output = match ($mode) {
            'combine' => array_merge_recursive($payload->inputData),
            default => $payload->inputData,
        };

        $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

        return NodeResult::completed($output, $durationMs);
    }
}
