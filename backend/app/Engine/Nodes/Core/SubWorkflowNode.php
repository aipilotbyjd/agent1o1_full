<?php

namespace App\Engine\Nodes\Core;

use App\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\NodeInput;
use App\Enums\ExecutionMode;
use App\Models\Workflow;
use App\Services\ExecutionService;

/**
 * Triggers another workflow as a sub-workflow execution.
 */
class SubWorkflowNode implements NodeHandler
{
    public function __construct(private ExecutionService $executionService) {}

    public function handle(NodeInput $payload): NodeResult
    {
        $startTime = hrtime(true);

        try {
            $workflowId = $payload->config['workflow_id'] ?? null;

            if (! $workflowId) {
                return NodeResult::failed('No workflow_id configured.', 'SUB_WORKFLOW_MISSING_ID');
            }

            // Guard against infinite sub-workflow chains (A → B → A → …)
            $currentDepth = (int) ($payload->executionMeta['trigger_data']['__sub_depth'] ?? 0);
            if ($currentDepth >= 5) {
                return NodeResult::failed(
                    'Sub-workflow depth limit reached (max 5 levels).',
                    'SUB_WORKFLOW_DEPTH_LIMIT'
                );
            }

            $workflow = Workflow::query()->find($workflowId);

            if (! $workflow) {
                return NodeResult::failed("Workflow [{$workflowId}] not found.", 'SUB_WORKFLOW_NOT_FOUND');
            }

            $triggeredBy = \App\Models\User::query()->find($payload->executionMeta['triggered_by'] ?? 0);

            if (! $triggeredBy) {
                return NodeResult::failed('Cannot resolve triggering user.', 'SUB_WORKFLOW_NO_USER');
            }

            $execution = $this->executionService->trigger(
                $workflow,
                $triggeredBy,
                array_merge($payload->inputData, ['__sub_depth' => $currentDepth + 1]),
                ExecutionMode::SubWorkflow,
            );

            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::completed([
                'sub_execution_id' => $execution->id,
                'sub_workflow_id' => $workflow->id,
                'status' => $execution->status->value,
            ], $durationMs);
        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed($e->getMessage(), 'SUB_WORKFLOW_ERROR', $durationMs);
        }
    }
}
