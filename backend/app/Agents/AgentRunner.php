<?php

namespace App\Agents;

use App\Agents\Internal\WorkflowAgent;
use App\Agents\Skills\SkillContextBuilder;
use App\Agents\Tools\SkillScriptTool;
use App\Agents\Tools\UpdateSkillTool;
use App\Agents\Tools\WorkflowNodeTool;
use App\Agents\Tools\WorkflowTool;
use App\Contracts\AgentRunnable;
use App\Models\Agent;
use App\Models\AgentToolConfig;
use App\Models\Node;
use Laravel\Ai\Enums\Lab;

class AgentRunner implements AgentRunnable
{
    public function __construct(
        private readonly SkillContextBuilder $skillContextBuilder,
    ) {}

    /**
     * Build the agent from a model record and run it with the given message.
     *
     * Steps:
     *  1. Load tool configs + skills with references and scripts
     *  2. Select relevant skills for this specific message (context pruning)
     *  3. Compose system prompt: base instructions + skill instructions + references
     *  4. Build tool list: node tools + skill script tools + UpdateSkillTool
     *  5. Prompt the WorkflowAgent and return the response string
     *
     * @param  array<string, mixed>  $context
     */
    public function run(string $message, array $context = []): string
    {
        $agent = $context['agent'] ?? null;

        if (! $agent instanceof Agent) {
            throw new \InvalidArgumentException('AgentRunner requires an Agent model in $context[\'agent\'].');
        }

        $agent->loadMissing(['toolConfigs', 'skills.references', 'skills.scripts']);

        $selectedSkills = $this->skillContextBuilder->select($agent->skills->all(), $message);

        $systemPrompt = $this->buildSystemPrompt($agent, $selectedSkills);

        $tools = $this->buildTools($agent, $selectedSkills, $context);

        $workflowAgent = new WorkflowAgent($systemPrompt, $tools);

        $provider = $this->resolveProvider($agent->provider);
        $model = $agent->model;

        $response = $workflowAgent->prompt(
            $message,
            provider: $provider,
            model: $model,
        );

        return (string) $response;
    }

    /**
     * Compose the full system prompt from agent instructions + selected skills.
     *
     * @param  \App\Models\AgentSkill[]  $skills
     */
    private function buildSystemPrompt(Agent $agent, array $skills): string
    {
        $parts = [$agent->instructions];

        foreach ($skills as $skill) {
            $parts[] = "\n\n---\n## Skill: {$skill->name}";

            if ($skill->description) {
                $parts[] = $skill->description;
            }

            $parts[] = $skill->instructions;

            foreach ($skill->references as $reference) {
                $parts[] = "\n### {$reference->title}\n{$reference->content}";
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Build the full tool list for the agent.
     *
     * Includes:
     *  - WorkflowNodeTool instances from AgentToolConfig records
     *  - SkillScriptTool instances from enabled skill scripts
     *  - UpdateSkillTool for self-improvement (if skills are attached)
     *  - WorkflowTool for triggering saved workflows
     *
     * @param  \App\Models\AgentSkill[]  $skills
     * @param  array<string, mixed>  $context
     * @return list<\Laravel\Ai\Contracts\Tool>
     */
    private function buildTools(Agent $agent, array $skills, array $context): array
    {
        $tools = [];

        foreach ($agent->toolConfigs->where('is_enabled', true) as $config) {
            $nodeDefinition = Node::query()
                ->select(['id', 'name', 'description', 'input_schema', 'config_schema'])
                ->where('type', $config->node_type)
                ->first();

            $tools[] = new WorkflowNodeTool(
                nodeType: $config->node_type,
                toolName: $config->tool_name,
                toolDescription: $config->tool_description,
                inputSchema: $nodeDefinition?->input_schema ?? $nodeDefinition?->config_schema ?? [],
                credentials: $context['credentials'][$config->node_type] ?? [],
            );
        }

        foreach ($skills as $skill) {
            foreach ($skill->scripts->where('is_enabled', true) as $script) {
                $tools[] = new SkillScriptTool($script);
            }
        }

        if ($agent->skills->isNotEmpty()) {
            $tools[] = new UpdateSkillTool($agent);
        }

        if ($agent->default_workflow_id) {
            $tools[] = new WorkflowTool($agent->default_workflow_id);
        }

        return $tools;
    }

    private function resolveProvider(?string $provider): ?Lab
    {
        if ($provider === null) {
            return null;
        }

        return Lab::tryFrom($provider);
    }
}
