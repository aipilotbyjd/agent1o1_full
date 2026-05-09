<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentSkill;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Str;

class AgentService
{
    /**
     * Create a new agent in the workspace.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, User $creator, array $data): Agent
    {
        $slug = $this->generateSlug($workspace, $data['name']);

        return Agent::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $creator->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'instructions' => $data['instructions'] ?? 'You are a helpful AI assistant.',
            'model' => $data['model'] ?? 'gpt-4o',
            'provider' => $data['provider'] ?? 'openai',
            'max_steps' => $data['max_steps'] ?? 15,
            'timeout_seconds' => $data['timeout_seconds'] ?? 180,
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Update an existing agent.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Agent $agent, array $data): Agent
    {
        if (isset($data['name']) && $data['name'] !== $agent->name) {
            $data['slug'] = $this->generateSlug($agent->workspace, $data['name'], $agent->id);
        }

        $agent->update($data);

        return $agent->fresh() ?? $agent;
    }

    /**
     * Soft-delete an agent.
     */
    public function delete(Agent $agent): void
    {
        $agent->delete();
    }

    /**
     * Duplicate an agent (with its tool configs and skill attachments).
     */
    public function duplicate(Agent $agent, User $creator): Agent
    {
        $agent->loadMissing(['toolConfigs', 'skills']);

        $newName = $agent->name . ' (Copy)';
        $slug = $this->generateSlug($agent->workspace, $newName);

        $copy = Agent::query()->create([
            'workspace_id' => $agent->workspace_id,
            'created_by' => $creator->id,
            'name' => $newName,
            'slug' => $slug,
            'description' => $agent->description,
            'instructions' => $agent->instructions,
            'model' => $agent->model,
            'provider' => $agent->provider,
            'max_steps' => $agent->max_steps,
            'timeout_seconds' => $agent->timeout_seconds,
            'is_active' => false,
            'metadata' => $agent->metadata,
        ]);

        foreach ($agent->toolConfigs as $config) {
            $copy->toolConfigs()->create([
                'node_type' => $config->node_type,
                'tool_name' => $config->tool_name,
                'tool_description' => $config->tool_description,
                'is_enabled' => $config->is_enabled,
                'sort_order' => $config->sort_order,
            ]);
        }

        $skillIds = $agent->skills->mapWithKeys(
            fn ($skill, $index) => [$skill->id => ['sort_order' => $index]]
        )->toArray();

        $copy->skills()->sync($skillIds);

        return $copy;
    }

    /**
     * Sync tool configs for an agent from a request array.
     *
     * @param  array<int, array<string, mixed>>  $tools
     */
    public function syncToolConfigs(Agent $agent, array $tools): void
    {
        $agent->toolConfigs()->delete();

        foreach ($tools as $index => $tool) {
            $agent->toolConfigs()->create([
                'node_type' => $tool['node_type'],
                'tool_name' => $tool['tool_name'] ?? str_replace('.', '_', $tool['node_type']),
                'tool_description' => $tool['tool_description'] ?? '',
                'is_enabled' => $tool['is_enabled'] ?? true,
                'sort_order' => $tool['sort_order'] ?? $index,
            ]);
        }
    }

    /**
     * Generate a unique slug for the workspace.
     */
    private function generateSlug(Workspace $workspace, string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (
            Agent::query()
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
