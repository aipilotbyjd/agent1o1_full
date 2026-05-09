<?php

namespace App\Jobs;

use App\Agents\Runner\AgentRunner;
use App\Agents\Skills\SkillContextBuilder;
use App\Models\Agent;
use App\Models\AgentTrigger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(
        public readonly string $agentId,
        public readonly string $triggerId,
        public readonly string $message,
        public readonly array $context = [],
    ) {
        $this->onQueue('workflows-default');
    }

    public function handle(): void
    {
        $agent = Agent::query()
            ->with(['toolConfigs', 'skills.references', 'skills.scripts'])
            ->find($this->agentId);

        if (! $agent instanceof Agent) {
            Log::warning("RunAgentJob: Agent [{$this->agentId}] not found, skipping.");
            return;
        }

        $trigger = AgentTrigger::query()->find($this->triggerId);

        try {
            $runner = new AgentRunner(new SkillContextBuilder);

            $response = $runner->run($this->message, array_merge(
                $this->context,
                ['agent' => $agent],
            ));

            Log::info("RunAgentJob: Agent [{$agent->name}] completed.", [
                'agent_id' => $this->agentId,
                'trigger_id' => $this->triggerId,
                'response_length' => strlen($response),
            ]);

            if ($trigger instanceof AgentTrigger) {
                $trigger->update(['last_fired_at' => now()]);
            }

        } catch (\Throwable $e) {
            Log::error("RunAgentJob: Agent [{$agent->name}] failed.", [
                'agent_id' => $this->agentId,
                'trigger_id' => $this->triggerId,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }
}
