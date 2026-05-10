<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AiFixSuggestion;
use App\Models\Execution;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAutofixController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List AI fix suggestions for an execution.
     *
     * GET /api/v1/workspaces/{workspace}/executions/{execution}/autofix
     */
    public function index(Workspace $workspace, Execution $execution): JsonResponse
    {
        $this->can(Permission::AiAutofix);

        $suggestions = $execution->aiFixSuggestions()->orderByDesc('created_at')->get();

        return $this->successResponse('AI fix suggestions retrieved successfully.', $suggestions);
    }

    /**
     * Generate AI fix suggestions for a failed execution.
     *
     * POST /api/v1/workspaces/{workspace}/executions/{execution}/autofix
     */
    public function generate(Request $request, Workspace $workspace, Execution $execution): JsonResponse
    {
        $this->can(Permission::AiAutofix);

        if (! $execution->status->isTerminal() || $execution->status->value !== 'failed') {
            return $this->errorResponse('Autofix is only available for failed executions.', 422);
        }

        $validated = $request->validate([
            'failed_node_key' => ['required', 'string'],
        ]);

        $errorMessage = $execution->error['message'] ?? 'Unknown error';

        $suggestion = AiFixSuggestion::create([
            'workspace_id' => $workspace->id,
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'failed_node_key' => $validated['failed_node_key'],
            'error_message' => $errorMessage,
            'diagnosis' => 'Analysis pending.',
            'suggestions' => [],
            'status' => 'pending',
            'model_used' => null,
            'tokens_used' => 0,
        ]);

        // TODO: dispatch an async job that calls the AI provider to populate
        // diagnosis and suggestions on this AiFixSuggestion record.

        return $this->successResponse('AI fix generation queued.', $suggestion, 202);
    }

    /**
     * Apply a specific suggestion by index.
     *
     * POST /api/v1/workspaces/{workspace}/executions/{execution}/autofix/{suggestion}/apply
     */
    public function apply(Request $request, Workspace $workspace, Execution $execution, AiFixSuggestion $suggestion): JsonResponse
    {
        $this->can(Permission::AiAutofix);

        $validated = $request->validate([
            'index' => ['required', 'integer', 'min:0'],
        ]);

        if (empty($suggestion->suggestions[$validated['index']])) {
            return $this->errorResponse('Suggestion index not found.', 404);
        }

        $suggestion->update(['applied_index' => $validated['index'], 'status' => 'applied']);

        return $this->successResponse('Suggestion applied.', $suggestion);
    }
}
