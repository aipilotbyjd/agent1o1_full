<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Jobs\RunAgentJob;
use App\Models\Agent;
use App\Models\AgentTrigger;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentTriggerController extends Controller
{
    /**
     * List triggers for an agent.
     */
    public function index(Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentView);

        $triggers = $agent->triggers()->orderBy('created_at', 'desc')->get();

        return $this->successResponse('Triggers retrieved successfully.', $triggers->map(
            fn ($t) => $this->formatTrigger($t),
        )->toArray());
    }

    /**
     * Create a new trigger.
     */
    public function store(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentCreate);

        $validated = $request->validate([
            'type' => 'required|in:schedule,webhook,event',
            'config' => 'nullable|array',
            'initial_message' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
        ]);

        $trigger = AgentTrigger::query()->create([
            'id' => (string) Str::uuid(),
            'agent_id' => $agent->id,
            'workspace_id' => $workspace->id,
            'type' => $validated['type'],
            'config' => $validated['config'] ?? [],
            'initial_message' => $validated['initial_message'] ?? '',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->successResponse('Trigger created successfully.', $this->formatTrigger($trigger), 201);
    }

    /**
     * Update a trigger.
     */
    public function update(Request $request, Workspace $workspace, Agent $agent, AgentTrigger $trigger): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'type' => 'sometimes|in:schedule,webhook,event',
            'config' => 'nullable|array',
            'initial_message' => 'nullable|string|max:5000',
            'is_active' => 'nullable|boolean',
        ]);

        $trigger->update($validated);

        return $this->successResponse('Trigger updated successfully.', $this->formatTrigger($trigger));
    }

    /**
     * Delete a trigger.
     */
    public function destroy(Workspace $workspace, Agent $agent, AgentTrigger $trigger): JsonResponse
    {
        $this->can(Permission::AgentDelete);

        $trigger->delete();

        return $this->successResponse('Trigger deleted successfully.');
    }

    /**
     * Manually fire a trigger (for testing).
     */
    public function fire(Workspace $workspace, Agent $agent, AgentTrigger $trigger): JsonResponse
    {
        $this->can(Permission::AgentCreate);

        $message = $trigger->initial_message ?? 'Trigger fired manually.';

        RunAgentJob::dispatch($agent->id, $trigger->id, $message);

        return $this->successResponse('Trigger fired. Agent will run in the background.');
    }

    /**
     * Format a trigger for API response.
     *
     * @return array<string, mixed>
     */
    private function formatTrigger(AgentTrigger $trigger): array
    {
        return [
            'id' => $trigger->id,
            'type' => $trigger->type,
            'config' => $trigger->config,
            'initial_message' => $trigger->initial_message,
            'is_active' => $trigger->is_active,
            'last_fired_at' => $trigger->last_fired_at?->toIso8601String(),
            'created_at' => $trigger->created_at?->toIso8601String(),
            'updated_at' => $trigger->updated_at?->toIso8601String(),
        ];
    }
}
