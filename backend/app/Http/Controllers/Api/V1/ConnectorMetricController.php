<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectorMetricController extends Controller
{
    private const MAX_PER_PAGE = 100;

    /**
     * List daily connector metrics for the workspace.
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::ConnectorViewMetrics);

        $query = $workspace->connectorMetrics();

        if ($request->filled('connector_key')) {
            $query->where('connector_key', $request->input('connector_key'));
        }

        if ($request->filled('from')) {
            $query->where('day', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('day', '<=', $request->input('to'));
        }

        $query->orderByDesc('day');

        $perPage = min((int) $request->input('per_page', 30), self::MAX_PER_PAGE);
        $metrics = $query->paginate($perPage);

        return $this->paginatedResponse('Connector metrics retrieved successfully.', $metrics);
    }

    /**
     * Get aggregated metrics summary per connector key.
     */
    public function summary(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::ConnectorViewMetrics);

        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to = $request->input('to', now()->toDateString());

        $summary = $workspace->connectorMetrics()
            ->whereBetween('day', [$from, $to])
            ->selectRaw('connector_key, connector_operation, SUM(total_calls) as total_calls, SUM(success_calls) as success_calls, SUM(failure_calls) as failure_calls, SUM(retry_calls) as retry_calls, AVG(p50_latency_ms) as avg_p50_ms, AVG(p95_latency_ms) as avg_p95_ms')
            ->groupBy('connector_key', 'connector_operation')
            ->orderByDesc('total_calls')
            ->get();

        return $this->successResponse('Connector metrics summary retrieved successfully.', $summary);
    }

    /**
     * List connector call attempts for the workspace.
     */
    public function calls(Request $request, Workspace $workspace): JsonResponse
    {
        $this->can(Permission::ConnectorViewMetrics);

        $query = $workspace->connectorCallAttempts()->with('execution');

        if ($request->filled('connector_key')) {
            $query->where('connector_key', $request->input('connector_key'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('execution_id')) {
            $query->where('execution_id', $request->input('execution_id'));
        }

        $query->orderByDesc('happened_at');

        $perPage = min((int) $request->input('per_page', 30), self::MAX_PER_PAGE);
        $calls = $query->paginate($perPage);

        return $this->paginatedResponse('Connector call attempts retrieved successfully.', $calls);
    }
}
