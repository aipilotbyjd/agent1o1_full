<?php

namespace App\Services;

use App\Agents\Runner\AgentRunner;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Facades\Conversation;

class AgentConversationService
{
    public function __construct(
        private readonly AgentRunner $agentRunner,
    ) {}

    /**
     * Start a new conversation for an agent.
     *
     * @return array<string, mixed>
     */
    public function startConversation(Agent $agent, User $user, string $initialMessage): array
    {
        $conversationId = (string) Str::uuid();

        $response = $this->agentRunner->run($initialMessage, [
            'agent' => $agent,
            'conversation_id' => $conversationId,
        ]);

        $conversation = \Laravel\Ai\Models\Conversation::query()->create([
            'id' => $conversationId,
            'agent_id' => $agent->id,
            'workspace_id' => $agent->workspace_id,
            'user_id' => $user->id,
            'title' => $this->generateTitle($initialMessage),
        ]);

        return [
            'conversation_id' => $conversationId,
            'response' => $response,
            'conversation' => $conversation,
        ];
    }

    /**
     * Send a message to an existing conversation.
     *
     * @param  array<string, mixed>  $conversationData
     * @return array<string, mixed>
     */
    public function sendMessage(Agent $agent, string $conversationId, string $message, array $conversationData = []): array
    {
        $response = $this->agentRunner->run($message, [
            'agent' => $agent,
            'conversation_id' => $conversationId,
        ]);

        return [
            'conversation_id' => $conversationId,
            'message' => $message,
            'response' => $response,
        ];
    }

    /**
     * Generate a short title from the first message.
     */
    private function generateTitle(string $message): string
    {
        $title = Str::limit($message, 60, '');
        return $title ?: 'New Conversation';
    }
}
