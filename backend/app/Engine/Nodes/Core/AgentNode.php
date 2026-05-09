<?php

namespace App\Engine\Nodes\Core;

use App\Agents\Runner\AgentRunner;
use App\Agents\Skills\SkillContextBuilder;
use App\Engine\Contracts\NodeHandler;
use App\Engine\NodeResult;
use App\Engine\Execution\NodePayload;
use App\Models\Agent;

/**
 * Runs a saved Agent from the Agent builder inside a workflow.
 *
 * Config:
 *   agent_id  — the UUID of the Agent model to load
 *   message   — the prompt to send (supports {{ expression }} placeholders,
 *               already resolved by the engine before this node runs)
 */
class AgentNode implements NodeHandler
{
    public function handle(NodePayload $payload): NodeResult
    {
        $startTime = hrtime(true);

        try {
            $agentId = $payload->config['agent_id'] ?? null;

            if (! $agentId) {
                return NodeResult::failed('No agent_id configured.', 'AGENT_NODE_MISSING_ID');
            }

            $agent = Agent::query()
                ->with(['toolConfigs', 'skills.references', 'skills.scripts'])
                ->find($agentId);

            if (! $agent instanceof Agent) {
                return NodeResult::failed("Agent [{$agentId}] not found.", 'AGENT_NODE_NOT_FOUND');
            }

            if (! $agent->is_active) {
                return NodeResult::failed("Agent [{$agent->name}] is not active.", 'AGENT_NODE_INACTIVE');
            }

            $message = $payload->config['message']
                ?? $payload->inputData['message']
                ?? $payload->inputData['prompt']
                ?? '';

            if (! $message) {
                return NodeResult::failed('No message provided to the agent.', 'AGENT_NODE_NO_MESSAGE');
            }

            $runner = new AgentRunner(new SkillContextBuilder);

            $response = $runner->run($message, [
                'agent' => $agent,
                'credentials' => $payload->credentials,
            ]);

            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::completed([
                'response' => $response,
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'model' => $agent->model,
                'provider' => $agent->provider,
            ], $durationMs);

        } catch (\Throwable $e) {
            $durationMs = (int) ((hrtime(true) - $startTime) / 1_000_000);

            return NodeResult::failed($e->getMessage(), 'AGENT_NODE_ERROR', $durationMs);
        }
    }
}
