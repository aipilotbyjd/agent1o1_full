<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Models\AgentSkill;
use Laravel\Ai\Contracts\Tool;

/**
 * Lets an agent rewrite an attached skill's instructions for self-improvement.
 *
 * When the user corrects the agent or the agent detects a gap in its knowledge,
 * it can call this tool to update the skill's instructions in the database.
 * The updated instructions will be used in all subsequent conversations.
 */
class UpdateSkillTool implements Tool
{
    public function __construct(
        private readonly Agent $agent,
    ) {}

    public function name(): string
    {
        return 'update_skill';
    }

    public function description(): string
    {
        return 'Update the instructions of one of your attached skills to improve future responses. '
            . 'Use this when you learn something new or when the user corrects your behavior. '
            . 'List available skills with list_skills_tool if you are unsure of the skill slug.';
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'skill_slug' => [
                    'type' => 'string',
                    'description' => 'The slug of the skill to update.',
                ],
                'new_instructions' => [
                    'type' => 'string',
                    'description' => 'The full updated instructions for the skill.',
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Brief explanation of why you are updating the skill.',
                ],
            ],
            'required' => ['skill_slug', 'new_instructions'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): string
    {
        $skillSlug = $arguments['skill_slug'] ?? '';
        $newInstructions = $arguments['new_instructions'] ?? '';

        if (! $skillSlug || ! $newInstructions) {
            return 'Error: skill_slug and new_instructions are required.';
        }

        $skill = $this->agent->skills()
            ->where('slug', $skillSlug)
            ->first();

        if (! $skill instanceof AgentSkill) {
            return "Error: Skill [{$skillSlug}] is not attached to this agent.";
        }

        $skill->update([
            'instructions' => $newInstructions,
            'version' => $skill->version + 1,
        ]);

        return json_encode([
            'updated' => true,
            'skill_slug' => $skill->slug,
            'new_version' => $skill->version,
        ]);
    }
}
