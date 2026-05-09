<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowEnvironmentRelease;
use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowEnvironmentReleaseController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List releases for a workflow.
     */
    public function index(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::EnvironmentView);

        $perPage = min((int) $request->input('per_page', 15), self::MAX_PER_PAGE);

        $releases = $workflow->environmentReleases()
            ->with(['fromEnvironment', 'toEnvironment', 'workflowVersion', 'triggeredByUser'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->paginatedResponse('Environment releases retrieved successfully.', $releases);
    }

    /**
     * Promote or rollback a workflow version between environments.
     */
    public function store(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::EnvironmentDeploy);

        $validated = $request->validate([
            'from_environment_id' => ['required', 'integer', 'exists:workspace_environments,id'],
            'to_environment_id' => ['required', 'integer', 'exists:workspace_environments,id', 'different:from_environment_id'],
            'workflow_version_id' => ['required', 'integer', 'exists:workflow_versions,id'],
            'action' => ['required', 'string', 'in:promote,rollback,sync'],
            'commit_sha' => ['nullable', 'string', 'max:40'],
        ]);

        $fromEnv = $workspace->environments()->findOrFail($validated['from_environment_id']);
        $toEnv = $workspace->environments()->findOrFail($validated['to_environment_id']);
        $version = $workflow->versions()->findOrFail($validated['workflow_version_id']);

        $release = WorkflowEnvironmentRelease::create([
            'workspace_id' => $workspace->id,
            'workflow_id' => $workflow->id,
            'from_environment_id' => $fromEnv->id,
            'to_environment_id' => $toEnv->id,
            'workflow_version_id' => $version->id,
            'triggered_by' => $request->user()->id,
            'action' => $validated['action'],
            'commit_sha' => $validated['commit_sha'] ?? null,
            'diff_summary' => [],
        ]);

        $release->load(['fromEnvironment', 'toEnvironment', 'workflowVersion', 'triggeredByUser']);

        return $this->successResponse('Environment release created successfully.', $release, 201);
    }
}
