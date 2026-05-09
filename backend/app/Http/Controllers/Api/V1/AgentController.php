<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgentResource;
use App\Models\Agent;
use App\Models\Workspace;
use App\Services\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function __construct(private readonly AgentService $agentService) {}

    /**
     * List all agents in the workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::AgentView);

        $query = $workspace->agents()
            ->with('creator')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_dir', 'desc'));

        $agents = $query->paginate((int) $request->input('per_page', 25));

        return $this->paginatedResponse('Agents retrieved successfully.', AgentResource::collection($agents));
    }

    /**
     * Create a new agent.
     */
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::AgentCreate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'instructions' => 'required|string',
            'model' => 'nullable|string|max:100',
            'provider' => 'nullable|string|max:50',
            'max_steps' => 'nullable|integer|min:1|max:50',
            'timeout_seconds' => 'nullable|integer|min:10|max:600',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
            'tools' => 'nullable|array',
            'tools.*.node_type' => 'required_with:tools|string',
            'tools.*.tool_name' => 'nullable|string',
            'tools.*.tool_description' => 'nullable|string',
            'tools.*.is_enabled' => 'nullable|boolean',
            'tools.*.sort_order' => 'nullable|integer',
        ]);

        $agent = $this->agentService->create($workspace, $request->user(), $validated);

        if (! empty($validated['tools'])) {
            $this->agentService->syncToolConfigs($agent, $validated['tools']);
        }

        $agent->load(['creator', 'toolConfigs', 'skills']);

        return $this->successResponse('Agent created successfully.', $this->formatAgent($agent), 201);
    }

    /**
     * Show an agent.
     */
    public function show(Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentView);

        $agent->load(['creator', 'toolConfigs', 'skills.references', 'skills.scripts', 'triggers']);

        return $this->successResponse('Agent retrieved successfully.', $this->formatAgent($agent, detailed: true));
    }

    /**
     * Update an agent.
     */
    public function update(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'instructions' => 'sometimes|string',
            'model' => 'nullable|string|max:100',
            'provider' => 'nullable|string|max:50',
            'max_steps' => 'nullable|integer|min:1|max:50',
            'timeout_seconds' => 'nullable|integer|min:10|max:600',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
            'tools' => 'nullable|array',
            'tools.*.node_type' => 'required_with:tools|string',
            'tools.*.tool_name' => 'nullable|string',
            'tools.*.tool_description' => 'nullable|string',
            'tools.*.is_enabled' => 'nullable|boolean',
            'tools.*.sort_order' => 'nullable|integer',
        ]);

        $agent = $this->agentService->update($agent, $validated);

        if (array_key_exists('tools', $validated)) {
            $this->agentService->syncToolConfigs($agent, $validated['tools'] ?? []);
        }

        $agent->load(['creator', 'toolConfigs', 'skills']);

        return $this->successResponse('Agent updated successfully.', $this->formatAgent($agent));
    }

    /**
     * Delete an agent.
     */
    public function destroy(Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentDelete);

        $this->agentService->delete($agent);

        return $this->successResponse('Agent deleted successfully.');
    }

    /**
     * Duplicate an agent.
     */
    public function duplicate(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentCreate);

        $copy = $this->agentService->duplicate($agent, $request->user());
        $copy->load(['creator', 'toolConfigs', 'skills']);

        return $this->successResponse('Agent duplicated successfully.', $this->formatAgent($copy), 201);
    }

    /**
     * Attach a skill to an agent.
     */
    public function attachSkill(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'skill_id' => 'required|string',
            'sort_order' => 'nullable|integer',
        ]);

        $skillId = $validated['skill_id'];
        $sortOrder = $validated['sort_order'] ?? $agent->skills()->count();

        $agent->skills()->syncWithoutDetaching([
            $skillId => ['sort_order' => $sortOrder],
        ]);

        return $this->successResponse('Skill attached to agent.');
    }

    /**
     * Detach a skill from an agent.
     */
    public function detachSkill(Workspace $workspace, Agent $agent, string $skillId): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $agent->skills()->detach($skillId);

        return $this->successResponse('Skill detached from agent.');
    }

    /**
     * Format an agent model for API response.
     *
     * @return array<string, mixed>
     */
    private function formatAgent(Agent $agent, bool $detailed = false): array
    {
        $data = [
            'id' => $agent->id,
            'name' => $agent->name,
            'slug' => $agent->slug,
            'description' => $agent->description,
            'instructions' => $agent->instructions,
            'model' => $agent->model,
            'provider' => $agent->provider,
            'max_steps' => $agent->max_steps,
            'timeout_seconds' => $agent->timeout_seconds,
            'is_active' => $agent->is_active,
            'metadata' => $agent->metadata,
            'creator' => $agent->relationLoaded('creator') ? [
                'id' => $agent->creator?->id,
                'name' => $agent->creator?->name,
            ] : null,
            'tool_configs' => $agent->relationLoaded('toolConfigs')
                ? $agent->toolConfigs->map(fn ($t) => [
                    'id' => $t->id,
                    'node_type' => $t->node_type,
                    'tool_name' => $t->tool_name,
                    'tool_description' => $t->tool_description,
                    'is_enabled' => $t->is_enabled,
                    'sort_order' => $t->sort_order,
                ])->toArray()
                : [],
            'skills_count' => $agent->relationLoaded('skills') ? $agent->skills->count() : null,
            'created_at' => $agent->created_at?->toIso8601String(),
            'updated_at' => $agent->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['skills'] = $agent->relationLoaded('skills')
                ? $agent->skills->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'slug' => $s->slug,
                    'description' => $s->description,
                    'version' => $s->version,
                    'sort_order' => $s->pivot->sort_order ?? 0,
                    'references_count' => $s->relationLoaded('references') ? $s->references->count() : null,
                    'scripts_count' => $s->relationLoaded('scripts') ? $s->scripts->count() : null,
                ])->toArray()
                : [];

            $data['triggers'] = $agent->relationLoaded('triggers')
                ? $agent->triggers->map(fn ($t) => [
                    'id' => $t->id,
                    'type' => $t->type,
                    'config' => $t->config,
                    'is_active' => $t->is_active,
                    'last_fired_at' => $t->last_fired_at?->toIso8601String(),
                ])->toArray()
                : [];
        }

        return $data;
    }
}
