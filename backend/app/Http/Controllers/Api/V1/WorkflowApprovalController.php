<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\WorkflowApproval;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowApprovalController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List approvals in a workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::ApprovalView);

        $query = $workspace->approvals()->with(['workflow', 'execution', 'approver']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('workflow_id')) {
            $query->where('workflow_id', $request->integer('workflow_id'));
        }

        $query->orderByDesc('created_at');

        $perPage = min((int) $request->input('per_page', 15), self::MAX_PER_PAGE);
        $approvals = $query->paginate($perPage);

        return $this->paginatedResponse('Approvals retrieved successfully.', $approvals);
    }

    /**
     * Show a single approval.
     */
    public function show(Workspace $workspace, WorkflowApproval $approval): JsonResponse
    {
        $this->can(Permission::ApprovalView);

        $approval->load(['workflow', 'execution', 'approver']);

        return $this->successResponse('Approval retrieved successfully.', $approval);
    }

    /**
     * Approve a pending approval.
     */
    public function approve(Request $request, Workspace $workspace, WorkflowApproval $approval): JsonResponse
    {
        $this->can(Permission::ApprovalApprove);

        if ($approval->status !== 'pending') {
            return $this->errorResponse('Only pending approvals can be approved.', 422);
        }

        $validated = $request->validate([
            'decision_payload' => ['nullable', 'array'],
        ]);

        $approval->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'decision_payload' => $validated['decision_payload'] ?? null,
        ]);

        return $this->successResponse('Approval approved successfully.', $approval->fresh(['approver']));
    }

    /**
     * Reject a pending approval.
     */
    public function reject(Request $request, Workspace $workspace, WorkflowApproval $approval): JsonResponse
    {
        $this->can(Permission::ApprovalReject);

        if ($approval->status !== 'pending') {
            return $this->errorResponse('Only pending approvals can be rejected.', 422);
        }

        $validated = $request->validate([
            'decision_payload' => ['nullable', 'array'],
        ]);

        $approval->update([
            'status' => 'rejected',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'decision_payload' => $validated['decision_payload'] ?? null,
        ]);

        return $this->successResponse('Approval rejected successfully.', $approval->fresh(['approver']));
    }
}
