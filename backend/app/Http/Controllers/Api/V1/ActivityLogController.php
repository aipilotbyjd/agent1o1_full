<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\Workspace;
use App\Traits\FiltersListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    use FiltersListQuery;

    /**
     * List activity logs in a workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::ActivityLogView);

        $query = $workspace->activityLogs()->with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        $this->applyDateRange($query, $request);
        $this->applySearch($query, $request, 'description');

        $query->orderBy('created_at', 'desc');

        $logs = $query->paginate($this->perPage($request));

        return $this->paginatedResponse(
            'Activity logs retrieved successfully.',
            ActivityLogResource::collection($logs),
        );
    }

    /**
     * Show an activity log entry.
     */
    public function show(Workspace $workspace, ActivityLog $activityLog): JsonResponse
    {
        $this->can(Permission::ActivityLogView);

        $activityLog->load('user');

        return $this->successResponse(
            'Activity log retrieved successfully.',
            new ActivityLogResource($activityLog),
        );
    }

    /**
     * Export activity logs as JSON.
     */
    public function export(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::AuditLogExport);

        $query = $workspace->activityLogs()->with('user');

        $this->applyDateRange($query, $request);

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        $logs = $query->orderBy('created_at', 'desc')->limit(10000)->get();

        return $this->successResponse(
            'Activity logs exported successfully.',
            [
                'exported_at' => now()->toIso8601String(),
                'total' => $logs->count(),
                'logs' => ActivityLogResource::collection($logs),
            ],
        );
    }
}
