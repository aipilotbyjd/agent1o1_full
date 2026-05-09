<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AgentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'model' => $this->model,
            'provider' => $this->provider,
            'max_steps' => $this->max_steps,
            'timeout_seconds' => $this->timeout_seconds,
            'is_active' => $this->is_active,
            'metadata' => $this->metadata,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'tool_configs' => $this->whenLoaded('toolConfigs', fn () => $this->toolConfigs->map(fn ($t) => [
                'id' => $t->id,
                'node_type' => $t->node_type,
                'tool_name' => $t->tool_name,
                'tool_description' => $t->tool_description,
                'is_enabled' => $t->is_enabled,
                'sort_order' => $t->sort_order,
            ])),
            'skills_count' => $this->whenLoaded('skills', fn () => $this->skills->count()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
