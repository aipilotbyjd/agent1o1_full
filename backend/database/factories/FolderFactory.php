<?php

namespace Database\Factories;

use App\Models\Folder;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
            'position' => 0,
        ];
    }

    /**
     * Place the folder inside a parent folder.
     */
    public function childOf(Folder $parent): static
    {
        return $this->state(fn () => [
            'workspace_id' => $parent->workspace_id,
            'parent_id' => $parent->id,
        ]);
    }
}
