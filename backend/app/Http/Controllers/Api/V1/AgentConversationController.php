<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\AgentConversationResource;
use App\Models\Agent;
use App\Models\Workspace;
use App\Services\AgentConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConversationController extends Controller
{
    public function __construct(
        private readonly AgentConversationService $conversationService,
    ) {}

    /**
     * List conversations for an agent.
     */
    public function index(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentView);

        $conversations = \Laravel\Ai\Models\Conversation::query()
            ->where('agent_id', $agent->id)
            ->where('workspace_id', $workspace->id)
            ->orderBy('updated_at', 'desc')
            ->paginate((int) $request->input('per_page', 25));

        return $this->paginatedResponse('Conversations retrieved successfully.', AgentConversationResource::collection($conversations));
    }

    /**
     * Start a new conversation with the agent.
     */
    public function store(Request $request, Workspace $workspace, Agent $agent): JsonResponse
    {
        $this->can(Permission::AgentView);

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $result = $this->conversationService->startConversation(
            $agent,
            $request->user(),
            $validated['message'],
        );

        return $this->successResponse('Conversation started.', [
            'conversation_id' => $result['conversation_id'],
            'response' => $result['response'],
        ], 201);
    }

    /**
     * Show a conversation with its messages.
     */
    public function show(Workspace $workspace, Agent $agent, string $conversationId): JsonResponse
    {
        $this->can(Permission::AgentView);

        $conversation = \Laravel\Ai\Models\Conversation::query()
            ->where('id', $conversationId)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        $messages = \Laravel\Ai\Models\ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'created_at' => $msg->created_at?->toIso8601String(),
            ]);

        return $this->successResponse('Conversation retrieved successfully.', [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $messages,
            'created_at' => $conversation->created_at?->toIso8601String(),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function destroy(Workspace $workspace, Agent $agent, string $conversationId): JsonResponse
    {
        $this->can(Permission::AgentView);

        $conversation = \Laravel\Ai\Models\Conversation::query()
            ->where('id', $conversationId)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        $conversation->delete();

        return $this->successResponse('Conversation deleted successfully.');
    }

    /**
     * Send a message in an existing conversation.
     */
    public function sendMessage(Request $request, Workspace $workspace, Agent $agent, string $conversationId): JsonResponse
    {
        $this->can(Permission::AgentView);

        \Laravel\Ai\Models\Conversation::query()
            ->where('id', $conversationId)
            ->where('agent_id', $agent->id)
            ->firstOrFail();

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $result = $this->conversationService->sendMessage(
            $agent,
            $conversationId,
            $validated['message'],
        );

        return $this->successResponse('Message sent.', [
            'conversation_id' => $conversationId,
            'response' => $result['response'],
        ]);
    }
}
