<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Api\V1\Workflow\UpdateWorkflowRequest;
use App\Http\Resources\Api\V1\WorkflowResource;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\WorkflowService;
use App\Traits\FiltersListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    use FiltersListQuery;

    private const SORTABLE_COLUMNS = ['name', 'created_at', 'updated_at', 'last_executed_at', 'execution_count'];

    public function __construct(private WorkflowService $workflowService) {}

    /**
     * List workflows in a workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::WorkflowView);

        $query = $workspace->workflows()->with('creator');

        $this->applySearch($query, $request, ['name', 'description']);

        if ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('name', $request->input('tag'));
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy(
            $this->sortColumn($request, self::SORTABLE_COLUMNS),
            $this->sortDirection($request),
        );

        $workflows = $query->paginate($this->perPage($request));

        return $this->paginatedResponse(
            'Workflows retrieved successfully.',
            WorkflowResource::collection($workflows),
        );
    }

    /**
     * Create a new workflow.
     */
    public function store(StoreWorkflowRequest $request, Workspace $workspace): JsonResponse
    {
        $workflow = $this->workflowService->create(
            $workspace,
            $request->user(),
            $request->validated(),
        );

        $workflow->load('creator');

        return $this->successResponse(
            'Workflow created successfully.',
            new WorkflowResource($workflow),
            201,
        );
    }

    /**
     * Show a workflow.
     */
    public function show(Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::WorkflowView);

        $workflow->load('creator');

        return $this->successResponse(
            'Workflow retrieved successfully.',
            new WorkflowResource($workflow),
        );
    }

    /**
     * Update a workflow.
     */
    public function update(UpdateWorkflowRequest $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $workflow = $this->workflowService->update($workflow, $request->validated());
        $workflow->load('creator');

        return $this->successResponse(
            'Workflow updated successfully.',
            new WorkflowResource($workflow),
        );
    }

    /**
     * Delete a workflow.
     */
    public function destroy(Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::WorkflowDelete);

        $this->workflowService->delete($workflow);

        return $this->successResponse('Workflow deleted successfully.');
    }

    /**
     * Activate a workflow.
     */
    public function activate(Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::WorkflowActivate);

        $workflow->activate();
        $workflow->load('creator');

        return $this->successResponse(
            'Workflow activated successfully.',
            new WorkflowResource($workflow),
        );
    }

    /**
     * Deactivate a workflow.
     */
    public function deactivate(Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::WorkflowActivate);

        $workflow->deactivate();
        $workflow->load('creator');

        return $this->successResponse(
            'Workflow deactivated successfully.',
            new WorkflowResource($workflow),
        );
    }

    /**
     * Duplicate a workflow.
     */
    public function duplicate(Request $request, Workspace $workspace, Workflow $workflow): JsonResponse
    {
        $this->can(Permission::WorkflowCreate);

        $newWorkflow = $this->workflowService->duplicate($workflow, $request->user());
        $newWorkflow->load('creator');

        return $this->successResponse(
            'Workflow duplicated successfully.',
            new WorkflowResource($newWorkflow),
            201,
        );
    }
}
