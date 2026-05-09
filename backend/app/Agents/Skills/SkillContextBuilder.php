<?php

namespace App\Agents\Skills;

use App\Models\AgentSkill;

/**
 * Selects the most relevant skills for a given message.
 *
 * Uses keyword scoring to avoid injecting all skill content into every prompt.
 * This keeps token usage low and response quality high.
 *
 * When the total number of skills is ≤ MAX_INLINE_SKILLS, all skills are
 * returned as-is. For larger sets, skills are scored and the top N are picked.
 */
class SkillContextBuilder
{
    private const MAX_INLINE_SKILLS = 3;

    private const MAX_SCORED_SKILLS = 5;

    /**
     * Select the most relevant skills for the given user message.
     *
     * @param  AgentSkill[]  $skills
     * @return AgentSkill[]
     */
    public function select(array $skills, string $message): array
    {
        if (empty($skills)) {
            return [];
        }

        if (count($skills) <= self::MAX_INLINE_SKILLS) {
            return $skills;
        }

        $scored = [];

        foreach ($skills as $skill) {
            $scored[] = [
                'skill' => $skill,
                'score' => $this->score($skill, $message),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn ($item) => $item['skill'],
            array_slice($scored, 0, self::MAX_SCORED_SKILLS),
        );
    }

    /**
     * Score a skill's relevance to the message using keyword matching.
     *
     * Checks: skill name, description, and instruction keywords against
     * the normalized message. Returns a count of matching terms.
     */
    private function score(AgentSkill $skill, string $message): int
    {
        $messageLower = strtolower($message);
        $score = 0;

        $skillText = implode(' ', array_filter([
            strtolower($skill->name),
            strtolower($skill->description ?? ''),
            strtolower(substr($skill->instructions, 0, 500)),
        ]));

        $keywords = array_filter(preg_split('/\W+/', $skillText) ?: []);

        foreach ($keywords as $keyword) {
            if (strlen($keyword) > 3 && str_contains($messageLower, $keyword)) {
                $score++;
            }
        }

        return $score;
    }
}
