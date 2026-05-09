<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ArchivedExecutionLog;
use App\Services\ExecutionArchiveService;
use Illuminate\Http\Request;

class ArchivedExecutionController extends Controller
{
    public function __construct(
        protected ExecutionArchiveService $archiveService
    ) {}

    /**
     * List archived executions for a workspace
     *
     * GET /api/v1/workspaces/{workspace}/executions/archived
     */
    public function index(Request $request)
    {
        $workspace = $request->attributes->get('workspace');

        $query = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id);

        // Filter by workflow
        if ($request->has('workflow_id')) {
            $query->where('workflow_id', $request->workflow_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by mode
        if ($request->has('mode')) {
            $query->where('mode', $request->mode);
        }

        // Filter by date range
        if ($request->has('archived_after')) {
            $query->where('archived_at', '>=', $request->archived_after);
        }

        if ($request->has('archived_before')) {
            $query->where('archived_at', '<=', $request->archived_before);
        }

        // Search by workflow name
        if ($request->has('search')) {
            $query->where('workflow_name', 'ILIKE', "%{$request->search}%");
        }

        // Sort
        $sortBy = $request->input('sort_by', 'archived_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate
        $perPage = min($request->input('per_page', 50), 100);
        $archives = $query->paginate($perPage);

        return response()->json([
            'data' => $archives->items(),
            'meta' => [
                'current_page' => $archives->currentPage(),
                'per_page' => $archives->perPage(),
                'total' => $archives->total(),
                'last_page' => $archives->lastPage(),
            ],
        ]);
    }

    /**
     * Get statistics about archived executions
     *
     * GET /api/v1/workspaces/{workspace}/executions/archived/stats
     */
    public function stats(Request $request)
    {
        $workspace = $request->attributes->get('workspace');

        $stats = $this->archiveService->getWorkspaceStats($workspace->id);

        return response()->json([
            'total_archived' => $stats['total_archived'],
            'total_size_bytes' => $stats['total_size_bytes'],
            'total_compressed_bytes' => $stats['total_compressed_bytes'],
            'space_saved_bytes' => $stats['total_size_bytes'] - $stats['total_compressed_bytes'],
            'space_saved_percentage' => $stats['total_size_bytes'] > 0
                ? round((1 - ($stats['total_compressed_bytes'] / $stats['total_size_bytes'])) * 100, 2)
                : 0,
            'oldest_archive' => $stats['oldest_archive'],
            'newest_archive' => $stats['newest_archive'],
            'avg_compression_ratio' => round($stats['avg_compression_ratio'] ?? 0, 4),
        ]);
    }

    /**
     * Retrieve a single archived execution (download from S3)
     *
     * GET /api/v1/workspaces/{workspace}/executions/archived/{execution}
     */
    public function show(Request $request, string $executionId)
    {
        $workspace = $request->attributes->get('workspace');

        $archive = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('execution_id', $executionId)
            ->firstOrFail();

        // Check if we should download the full data
        if ($request->boolean('include_data', false)) {
            try {
                $data = $this->archiveService->restoreExecution($archive);

                return response()->json([
                    'archived' => true,
                    'archive_info' => $archive,
                    'execution' => $data['execution'],
                    'nodes' => $data['nodes'],
                    'logs' => $data['logs'],
                    'metadata' => $data['metadata'],
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to retrieve archived data',
                    'message' => $e->getMessage(),
                ], 500);
            }
        }

        // Return just metadata
        return response()->json([
            'archived' => true,
            'archive_info' => $archive,
        ]);
    }

    /**
     * Temporarily restore an archived execution to the database
     *
     * POST /api/v1/workspaces/{workspace}/executions/archived/{execution}/restore
     */
    public function restore(Request $request, string $executionId)
    {
        $workspace = $request->attributes->get('workspace');

        $archive = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('execution_id', $executionId)
            ->firstOrFail();

        // Check if already restored and not expired
        if ($archive->is_restored && $archive->restore_expires_at && $archive->restore_expires_at->isFuture()) {
            return response()->json([
                'message' => 'Execution is already restored',
                'expires_at' => $archive->restore_expires_at,
            ]);
        }

        try {
            $ttlHours = $request->input('ttl_hours', 24);

            // Restore to PostgreSQL for specified hours
            $execution = $this->archiveService->restoreToDatabase($archive, $ttlHours);

            return response()->json([
                'message' => 'Execution restored to database successfully',
                'execution' => $execution,
                'expires_at' => $archive->fresh()->restore_expires_at,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to restore execution',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download archived execution as JSON file
     *
     * GET /api/v1/workspaces/{workspace}/executions/archived/{execution}/download
     */
    public function download(Request $request, string $executionId)
    {
        $workspace = $request->attributes->get('workspace');

        $archive = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('execution_id', $executionId)
            ->firstOrFail();

        try {
            $data = $this->archiveService->restoreExecution($archive);

            $filename = "execution-{$executionId}.json";

            return response()->json($data)
                ->header('Content-Disposition', "attachment; filename=\"{$filename}\"")
                ->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to download archived execution',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
