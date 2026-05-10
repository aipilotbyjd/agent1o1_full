<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceEnvironment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceEnvironmentController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List environments for a workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::EnvironmentView);

        $perPage = min((int) $request->input('per_page', 15), self::MAX_PER_PAGE);

        $environments = $workspace->environments()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate($perPage);

        return $this->paginatedResponse('Environments retrieved successfully.', $environments);
    }

    /**
     * Create a new environment.
     */
    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::EnvironmentCreate);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'base_branch' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            $workspace->environments()->update(['is_default' => false]);
        }

        $environment = $workspace->environments()->create($validated);

        return $this->successResponse('Environment created successfully.', $environment, 201);
    }

    /**
     * Show a single environment.
     */
    public function show(Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        $this->can(Permission::EnvironmentView);

        return $this->successResponse('Environment retrieved successfully.', $environment);
    }

    /**
     * Update an environment.
     */
    public function update(Request $request, Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        $this->can(Permission::EnvironmentUpdate);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'git_branch' => ['nullable', 'string', 'max:255'],
            'base_branch' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            $workspace->environments()->where('id', '!=', $environment->id)->update(['is_default' => false]);
        }

        $environment->update($validated);

        return $this->successResponse('Environment updated successfully.', $environment);
    }

    /**
     * Delete an environment.
     */
    public function destroy(Workspace $workspace, WorkspaceEnvironment $environment): JsonResponse
    {
        $this->can(Permission::EnvironmentDelete);

        if ($environment->is_default) {
            return $this->errorResponse('Cannot delete the default environment.', 422);
        }

        $environment->delete();

        return $this->successResponse('Environment deleted successfully.');
    }
}
