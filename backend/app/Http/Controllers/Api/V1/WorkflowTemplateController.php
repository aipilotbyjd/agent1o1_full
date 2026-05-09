<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkflowResource;
use App\Http\Resources\Api\V1\WorkflowTemplateResource;
use App\Models\WorkflowTemplate;
use App\Models\Workspace;
use App\Services\WorkflowTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkflowTemplateController extends Controller
{
    private const MAX_PER_PAGE = 100;

    public function __construct(private WorkflowTemplateService $templateService) {}

    /**
     * List available workflow templates.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkflowTemplate::query()->where('is_active', true);

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\%', '\_'], $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $query->orderBy('sort_order')->orderByDesc('usage_count');

        $perPage = min((int) $request->input('per_page', 15), self::MAX_PER_PAGE);
        $templates = $query->paginate($perPage);

        return $this->paginatedResponse(
            'Workflow templates retrieved successfully.',
            WorkflowTemplateResource::collection($templates),
        );
    }

    /**
     * Show a single template.
     */
    public function show(WorkflowTemplate $workflowTemplate): JsonResponse
    {
        if (! $workflowTemplate->is_active) {
            return $this->errorResponse('Template not found.', 404);
        }

        return $this->successResponse(
            'Workflow template retrieved successfully.',
            new WorkflowTemplateResource($workflowTemplate),
        );
    }

    /**
     * Create a new template (admin operation).
     */
    public function store(Request $request): JsonResponse
    {
        $this->can(Permission::TemplateCreate);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'trigger_type' => ['required', 'string'],
            'trigger_config' => ['nullable', 'array'],
            'nodes' => ['required', 'array'],
            'edges' => ['required', 'array'],
            'viewport' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'instructions' => ['nullable', 'string'],
            'required_credentials' => ['nullable', 'array'],
            'is_featured' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $validated['slug'] = Str::slug($validated['name'] . '-' . Str::random(6));
        $validated['is_active'] = true;

        $template = WorkflowTemplate::create($validated);

        return $this->successResponse(
            'Workflow template created successfully.',
            new WorkflowTemplateResource($template),
            201,
        );
    }

    /**
     * Update an existing template (admin operation).
     */
    public function update(Request $request, WorkflowTemplate $workflowTemplate): JsonResponse
    {
        $this->can(Permission::TemplateUpdate);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'trigger_type' => ['sometimes', 'string'],
            'trigger_config' => ['nullable', 'array'],
            'nodes' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
            'viewport' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
            'instructions' => ['nullable', 'string'],
            'required_credentials' => ['nullable', 'array'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $workflowTemplate->update($validated);

        return $this->successResponse(
            'Workflow template updated successfully.',
            new WorkflowTemplateResource($workflowTemplate),
        );
    }

    /**
     * Delete a template (admin operation).
     */
    public function destroy(WorkflowTemplate $workflowTemplate): JsonResponse
    {
        $this->can(Permission::TemplateDelete);

        $workflowTemplate->delete();

        return $this->successResponse('Workflow template deleted successfully.');
    }

    /**
     * Create a workflow from a template within a workspace.
     */
    public function use(Request $request, Workspace $workspace, WorkflowTemplate $workflowTemplate): JsonResponse
    {
        $this->can(Permission::WorkflowCreate);

        $workflow = $this->templateService->useTemplate(
            $workflowTemplate,
            $workspace,
            $request->user(),
        );

        return $this->successResponse(
            'Workflow created from template successfully.',
            new WorkflowResource($workflow),
            201,
        );
    }
}
