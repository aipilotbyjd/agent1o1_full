<?php

namespace App\Agents\Tools;

use App\Enums\ExecutionMode;
use App\Models\User;
use App\Models\Workflow;
use App\Services\ExecutionService;
use Laravel\Ai\Contracts\Tool;

/**
 * Lets an agent trigger a saved workflow by ID.
 *
 * The agent can pass an arbitrary JSON payload which becomes
 * the workflow's trigger input data.
 */
class WorkflowTool implements Tool
{
    public function __construct(
        private readonly string $defaultWorkflowId,
    ) {}

    public function name(): string
    {
        return 'trigger_workflow';
    }

    public function description(): string
    {
        return 'Trigger a saved workflow by its ID and pass input data to it. Returns the execution ID.';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'workflow_id' => [
                    'type' => 'string',
                    'description' => 'The ID of the workflow to trigger. Leave empty to use the default workflow.',
                ],
                'input_data' => [
                    'type' => 'object',
                    'description' => 'Key-value pairs to pass as input to the workflow trigger node.',
                    'additionalProperties' => true,
                ],
            ],
            'required' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): string
    {
        $workflowId = $arguments['workflow_id'] ?? $this->defaultWorkflowId;
        $inputData = $arguments['input_data'] ?? [];

        $workflow = Workflow::query()->find($workflowId);

        if (! $workflow) {
            return "Error: Workflow [{$workflowId}] not found.";
        }

        $user = User::query()->first();

        if (! $user) {
            return 'Error: Could not resolve a user to trigger the workflow.';
        }

        try {
            /** @var ExecutionService $executionService */
            $executionService = app(ExecutionService::class);

            $execution = $executionService->trigger(
                $workflow,
                $user,
                $inputData,
                ExecutionMode::Manual,
            );

            return json_encode([
                'execution_id' => $execution->id,
                'workflow_id' => $workflow->id,
                'status' => $execution->status->value,
            ]);
        } catch (\Throwable $e) {
            return 'Error triggering workflow: ' . $e->getMessage();
        }
    }
}
