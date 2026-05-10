<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Services\NodeSandboxService;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NodeSandboxController extends Controller
{
    public function __construct(private NodeSandboxService $sandboxService) {}

    /**
     * Execute a node's code in an isolated sandbox.
     * Used by the workflow editor to test individual nodes without triggering a full execution.
     *
     * POST /api/v1/workspaces/{workspace}/nodes/sandbox
     */
    public function execute(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::WorkflowExecute);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50000'],
            'input_data' => ['nullable', 'array'],
        ]);

        if (! $this->sandboxService->canExecute()) {
            return $this->errorResponse('Sandbox execution is not available in this environment.', 503);
        }

        try {
            $result = $this->sandboxService->executeCode(
                $validated['code'],
                $validated['input_data'] ?? [],
            );

            return $this->successResponse('Node executed successfully.', $result);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
