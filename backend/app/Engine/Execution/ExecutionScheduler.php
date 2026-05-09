<?php

namespace App\Engine\Execution;

use App\Engine\Enums\NodeType;
use App\Engine\Graph\WorkflowGraph;
use App\Engine\NodeRegistry;

/**
 * Classifies ready nodes into sync, async, and blocking categories.
 *
 * The WorkflowEngine calls this each loop iteration to decide how to
 * execute each group of ready nodes:
 *  - Sync nodes  → execute inline, no I/O
 *  - Async nodes → execute concurrently via Laravel Concurrency
 *  - Blocking nodes → checkpoint state and suspend execution
 */
class ExecutionScheduler
{
    /**
     * Partition a list of ready node IDs into three groups.
     *
     * @param  list<string>  $nodeIds
     * @return array{list<string>, list<string>, list<string>}  [sync, async, blocking]
     */
    public function partition(array $nodeIds, WorkflowGraph $graph): array
    {
        $sync = [];
        $async = [];
        $blocking = [];

        foreach ($nodeIds as $nodeId) {
            $node = $graph->getNode($nodeId);
            $type = $node['type'] ?? '';
            $nodeType = NodeType::tryFrom($type);

            if ($nodeType === null) {
                // App nodes (google_sheets.*, slack.*, etc.) always do I/O
                if (NodeRegistry::isAppNode($type)) {
                    $async[] = $nodeId;
                } else {
                    $sync[] = $nodeId;
                }

                continue;
            }

            if ($nodeType->isSuspendable()) {
                $blocking[] = $nodeId;
            } elseif ($nodeType->isSync()) {
                $sync[] = $nodeId;
            } else {
                $async[] = $nodeId;
            }
        }

        return [$sync, $async, $blocking];
    }
}
