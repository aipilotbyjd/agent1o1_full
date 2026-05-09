<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgentSkillResource;
use App\Models\AgentSkill;
use App\Models\AgentSkillReference;
use App\Models\AgentSkillScript;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentSkillController extends Controller
{
    /**
     * List all skills in the workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::AgentView);

        $query = $workspace->agentSkills()
            ->with('creator')
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_shared'), function ($q) use ($request) {
                $q->where('is_shared', filter_var($request->input('is_shared'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_dir', 'desc'));

        $skills = $query->paginate((int) $request->input('per_page', 25));

        return $this->paginatedResponse('Agent skills retrieved successfully.', AgentSkillResource::collection($skills));
    }

    /**
     * Create a new skill.
     */
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::AgentCreate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'instructions' => 'required|string',
            'is_shared' => 'nullable|boolean',
        ]);

        $slug = $this->generateSkillSlug($workspace, $validated['name']);

        $skill = AgentSkill::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'],
            'is_shared' => $validated['is_shared'] ?? false,
            'version' => 1,
        ]);

        $skill->load('creator');

        return $this->successResponse('Agent skill created successfully.', $this->formatSkill($skill, detailed: true), 201);
    }

    /**
     * Show a skill with all details.
     */
    public function show(Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        $this->can(Permission::AgentView);

        $agentSkill->load(['creator', 'references', 'scripts']);

        return $this->successResponse('Agent skill retrieved successfully.', $this->formatSkill($agentSkill, detailed: true));
    }

    /**
     * Update a skill.
     */
    public function update(Request $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'instructions' => 'sometimes|string',
            'is_shared' => 'nullable|boolean',
        ]);

        if (isset($validated['name']) && $validated['name'] !== $agentSkill->name) {
            $validated['slug'] = $this->generateSkillSlug($workspace, $validated['name'], $agentSkill->id);
        }

        if (isset($validated['instructions']) && $validated['instructions'] !== $agentSkill->instructions) {
            $validated['version'] = $agentSkill->version + 1;
        }

        $agentSkill->update($validated);

        $agentSkill->load(['creator', 'references', 'scripts']);

        return $this->successResponse('Agent skill updated successfully.', $this->formatSkill($agentSkill, detailed: true));
    }

    /**
     * Delete a skill.
     */
    public function destroy(Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        $this->can(Permission::AgentDelete);

        $agentSkill->delete();

        return $this->successResponse('Agent skill deleted successfully.');
    }

    /**
     * Add a reference document to a skill.
     */
    public function addReference(Request $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'sort_order' => 'nullable|integer',
        ]);

        $reference = $agentSkill->references()->create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'sort_order' => $validated['sort_order'] ?? $agentSkill->references()->count(),
        ]);

        return $this->successResponse('Reference added successfully.', [
            'id' => $reference->id,
            'title' => $reference->title,
            'content' => $reference->content,
            'sort_order' => $reference->sort_order,
        ], 201);
    }

    /**
     * Update a reference.
     */
    public function updateReference(Request $request, Workspace $workspace, AgentSkill $agentSkill, AgentSkillReference $reference): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'sort_order' => 'nullable|integer',
        ]);

        $reference->update($validated);

        return $this->successResponse('Reference updated successfully.', [
            'id' => $reference->id,
            'title' => $reference->title,
            'content' => $reference->content,
            'sort_order' => $reference->sort_order,
        ]);
    }

    /**
     * Remove a reference from a skill.
     */
    public function removeReference(Workspace $workspace, AgentSkill $agentSkill, AgentSkillReference $reference): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $reference->delete();

        return $this->successResponse('Reference removed successfully.');
    }

    /**
     * Add a script to a skill.
     */
    public function addScript(Request $request, Workspace $workspace, AgentSkill $agentSkill): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'language' => 'nullable|in:php,javascript',
            'code' => 'required|string',
            'is_enabled' => 'nullable|boolean',
        ]);

        $script = $agentSkill->scripts()->create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'language' => $validated['language'] ?? 'php',
            'code' => $validated['code'],
            'is_enabled' => $validated['is_enabled'] ?? true,
        ]);

        return $this->successResponse('Script added successfully.', $this->formatScript($script), 201);
    }

    /**
     * Update a script.
     */
    public function updateScript(Request $request, Workspace $workspace, AgentSkill $agentSkill, AgentSkillScript $script): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'language' => 'nullable|in:php,javascript',
            'code' => 'sometimes|string',
            'is_enabled' => 'nullable|boolean',
        ]);

        $script->update($validated);

        return $this->successResponse('Script updated successfully.', $this->formatScript($script));
    }

    /**
     * Remove a script from a skill.
     */
    public function removeScript(Workspace $workspace, AgentSkill $agentSkill, AgentSkillScript $script): JsonResponse
    {
        $this->can(Permission::AgentUpdate);

        $script->delete();

        return $this->successResponse('Script removed successfully.');
    }

    /**
     * Format a skill for API response.
     *
     * @return array<string, mixed>
     */
    private function formatSkill(AgentSkill $skill, bool $detailed = false): array
    {
        $data = [
            'id' => $skill->id,
            'name' => $skill->name,
            'slug' => $skill->slug,
            'description' => $skill->description,
            'instructions' => $skill->instructions,
            'is_shared' => $skill->is_shared,
            'version' => $skill->version,
            'creator' => $skill->relationLoaded('creator') ? [
                'id' => $skill->creator?->id,
                'name' => $skill->creator?->name,
            ] : null,
            'created_at' => $skill->created_at?->toIso8601String(),
            'updated_at' => $skill->updated_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['references'] = $skill->relationLoaded('references')
                ? $skill->references->map(fn ($r) => [
                    'id' => $r->id,
                    'title' => $r->title,
                    'content' => $r->content,
                    'sort_order' => $r->sort_order,
                ])->toArray()
                : [];

            $data['scripts'] = $skill->relationLoaded('scripts')
                ? $skill->scripts->map(fn ($s) => $this->formatScript($s))->toArray()
                : [];
        }

        return $data;
    }

    /**
     * Format a script for API response.
     *
     * @return array<string, mixed>
     */
    private function formatScript(AgentSkillScript $script): array
    {
        return [
            'id' => $script->id,
            'name' => $script->name,
            'description' => $script->description,
            'language' => $script->language,
            'code' => $script->code,
            'is_enabled' => $script->is_enabled,
            'created_at' => $script->created_at?->toIso8601String(),
            'updated_at' => $script->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Generate a unique skill slug.
     */
    private function generateSkillSlug(Workspace $workspace, string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            AgentSkill::query()
                ->where('workspace_id', $workspace->id)
                ->where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
