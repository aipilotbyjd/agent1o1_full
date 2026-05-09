<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Folder\MoveWorkflowsRequest;
use App\Http\Requests\Api\V1\Folder\StoreFolderRequest;
use App\Http\Requests\Api\V1\Folder\UpdateFolderRequest;
use App\Http\Resources\Api\V1\FolderResource;
use App\Models\Folder;
use App\Models\Workspace;
use App\Services\FolderService;
use App\Traits\FiltersListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    use FiltersListQuery;

    public function __construct(private FolderService $folderService) {}

    /**
     * List folders in a workspace (optionally filter by parent_id).
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::FolderView);

        $query = $workspace->folders()->getQuery()
            ->withCount(['workflows', 'children']);

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->input('parent_id'));
        } else {
            $query->whereNull('parent_id');
        }

        $this->applySearch($query, $request, 'name');

        $query->orderBy('position')->orderBy('name');

        $folders = $query->paginate($this->perPage($request));

        return $this->paginatedResponse(
            'Folders retrieved successfully.',
            FolderResource::collection($folders),
        );
    }

    /**
     * Create a new folder.
     */
    public function store(StoreFolderRequest $request, Workspace $workspace): JsonResponse
    {
        $folder = $this->folderService->create($workspace, $request->validated());

        $folder->loadCount(['workflows', 'children']);

        return $this->successResponse(
            'Folder created successfully.',
            new FolderResource($folder),
            201,
        );
    }

    /**
     * Show a folder with its children.
     */
    public function show(Workspace $workspace, Folder $folder): JsonResponse
    {
        $this->can(Permission::FolderView);

        $folder->load('children')->loadCount(['workflows', 'children']);

        return $this->successResponse(
            'Folder retrieved successfully.',
            new FolderResource($folder),
        );
    }

    /**
     * Update a folder.
     */
    public function update(UpdateFolderRequest $request, Workspace $workspace, Folder $folder): JsonResponse
    {
        $folder = $this->folderService->update($folder, $request->validated());

        $folder->loadCount(['workflows', 'children']);

        return $this->successResponse(
            'Folder updated successfully.',
            new FolderResource($folder),
        );
    }

    /**
     * Delete a folder.
     */
    public function destroy(Workspace $workspace, Folder $folder): JsonResponse
    {
        $this->can(Permission::FolderDelete);

        $this->folderService->delete($folder);

        return $this->successResponse('Folder deleted successfully.');
    }

    /**
     * Move workflows into a folder (or to root when folder_id is null).
     */
    public function moveWorkflows(MoveWorkflowsRequest $request, Workspace $workspace): JsonResponse
    {
        $validated = $request->validated();

        $moved = $this->folderService->moveWorkflows(
            $workspace,
            $validated['folder_id'] ?? null,
            $validated['workflow_ids'],
        );

        return $this->successResponse("Moved {$moved} workflow(s) successfully.");
    }
}
