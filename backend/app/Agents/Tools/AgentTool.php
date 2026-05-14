<?php

namespace App\Agents\Tools;

use App\Agents\AgentRunner;
use App\Agents\Skills\SkillContextBuilder;
use App\Models\Agent;
use Laravel\Ai\Contracts\Tool;

/**
 * Lets one agent call another agent as a tool (multi-agent orchestration).
 *
 * The orchestrator agent passes a message to the sub-agent and receives
 * the sub-agent's full response as the tool result.
 */
class AgentTool implements Tool
{
    public function __construct(
        private readonly Agent $subAgent,
    ) {}

    public function name(): string
    {
        return 'agent_' . str_replace([' ', '-'], '_', strtolower($this->subAgent->slug));
    }

    public function description(): string
    {
        return 'Delegate a task to the "'
            . $this->subAgent->name
            . '" agent. '
            . ($this->subAgent->description ?? 'A specialized sub-agent.');
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The message or task to send to the sub-agent.',
                ],
            ],
            'required' => ['message'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function handle(array $arguments): string
    {
        $message = $arguments['message'] ?? '';

        if (! $message) {
            return 'Error: message is required.';
        }

        try {
            $runner = new AgentRunner(new SkillContextBuilder);

            return $runner->run($message, ['agent' => $this->subAgent]);
        } catch (\Throwable $e) {
            return 'Sub-agent error: ' . $e->getMessage();
        }
    }
}
