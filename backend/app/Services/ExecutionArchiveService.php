<?php

namespace App\Services;

use App\Models\ArchivedExecutionLog;
use App\Models\Execution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExecutionArchiveService
{
    /**
     * Archive a single execution to S3
     */
    public function archiveExecution(Execution $execution): ArchivedExecutionLog
    {
        $startTime = microtime(true);

        DB::beginTransaction();

        try {
            // 1. Load all related data
            $data = $this->prepareArchiveData($execution);

            // 2. Convert to JSON
            $json = json_encode($data, JSON_PRETTY_PRINT);
            $originalSize = strlen($json);

            // 3. Compress with gzip
            $compressed = gzencode($json, 9); // Max compression level
            $compressedSize = strlen($compressed);

            // 4. Generate S3 key
            $s3Key = $this->generateS3Key($execution);

            // 5. Upload to S3
            Storage::disk('archive')->put($s3Key, $compressed, [
                'StorageClass' => 'STANDARD_IA',
                'ContentType' => 'application/gzip',
                'Metadata' => [
                    'execution_id' => (string) $execution->id,
                    'workspace_id' => (string) $execution->workspace_id,
                    'archived_at' => now()->toIso8601String(),
                ],
            ]);

            // 6. Create archive record
            $archive = ArchivedExecutionLog::create([
                'id' => Str::uuid(),
                'workspace_id' => $execution->workspace_id,
                'execution_id' => $execution->id,
                'workflow_id' => $execution->workflow_id,
                'workflow_name' => $execution->workflow?->name ?? 'Unknown',
                'status' => $execution->status->value,
                'mode' => $execution->mode->value,
                'started_at' => $execution->started_at,
                'finished_at' => $execution->finished_at,
                'triggered_by' => $execution->triggered_by,
                's3_bucket' => config('filesystems.disks.archive.bucket'),
                's3_key' => $s3Key,
                's3_region' => config('filesystems.disks.archive.region'),
                's3_storage_class' => 'STANDARD_IA',
                'archived_at' => now(),
                'file_size_bytes' => $originalSize,
                'compressed_size_bytes' => $compressedSize,
                'compression_ratio' => $originalSize > 0 ? round($compressedSize / $originalSize, 4) : 0,
            ]);

            // 7. Delete from PostgreSQL (cascades to execution_nodes, execution_logs)
            $execution->delete();

            DB::commit();

            // Log success metrics
            $duration = microtime(true) - $startTime;
            Log::info('Execution archived successfully', [
                'execution_id' => $archive->execution_id,
                'workspace_id' => $archive->workspace_id,
                's3_key' => $archive->s3_key,
                'file_size_bytes' => $archive->file_size_bytes,
                'compressed_size_bytes' => $archive->compressed_size_bytes,
                'compression_ratio' => $archive->compression_ratio,
                'duration_seconds' => round($duration, 3),
            ]);

            return $archive;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to archive execution', [
                'execution_id' => $execution->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Prepare execution data for archival
     */
    protected function prepareArchiveData(Execution $execution): array
    {
        // Load relationships
        $execution->load(['nodes', 'logs', 'workflow']);

        return [
            'execution' => $execution->toArray(),
            'nodes' => $execution->nodes->map(function ($node) {
                return [
                    'id' => $node->id,
                    'node_id' => $node->node_id,
                    'node_run_key' => $node->node_run_key,
                    'node_type' => $node->node_type,
                    'node_name' => $node->node_name,
                    'status' => $node->status->value,
                    'started_at' => $node->started_at?->toIso8601String(),
                    'finished_at' => $node->finished_at?->toIso8601String(),
                    'duration_ms' => $node->duration_ms,
                    'input_data' => $node->input_data,
                    'output_data' => $node->output_data,
                    'error' => $node->error,
                    'sequence' => $node->sequence,
                    'loop_index' => $node->loop_index,
                    'parent_frame' => $node->parent_frame,
                ];
            })->toArray(),
            'logs' => $execution->logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'execution_node_id' => $log->execution_node_id,
                    'level' => $log->level->value,
                    'message' => $log->message,
                    'context' => $log->context,
                    'logged_at' => $log->logged_at?->toIso8601String(),
                ];
            })->toArray(),
            'metadata' => [
                'archived_at' => now()->toIso8601String(),
                'archived_by' => 'ExecutionArchiveService',
                'linkflow_version' => config('app.version', '1.0.0'),
            ],
        ];
    }

    /**
     * Generate S3 key path
     */
    protected function generateS3Key(Execution $execution): string
    {
        $date = $execution->created_at;

        return sprintf(
            '%s/%s/%s/execution-%s.json.gz',
            $execution->workspace_id,
            $date->format('Y'),
            $date->format('m'),
            $execution->id
        );
    }

    /**
     * Restore an archived execution (download and parse)
     */
    public function restoreExecution(ArchivedExecutionLog $archive): array
    {
        try {
            // 1. Download from S3
            $compressed = Storage::disk('archive')->get($archive->s3_key);

            if ($compressed === false || $compressed === null) {
                throw new \RuntimeException("Failed to download archive from S3: {$archive->s3_key}");
            }

            // 2. Decompress
            $json = gzdecode($compressed);

            if ($json === false) {
                throw new \RuntimeException("Failed to decompress archive: {$archive->s3_key}");
            }

            // 3. Parse JSON
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Failed to parse JSON: '.json_last_error_msg());
            }

            Log::info('Execution restored from archive', [
                'execution_id' => $archive->execution_id,
                's3_key' => $archive->s3_key,
            ]);

            return $data;
        } catch (\Exception $e) {
            Log::error('Failed to restore execution from archive', [
                'execution_id' => $archive->execution_id,
                's3_key' => $archive->s3_key,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Restore execution to PostgreSQL temporarily
     */
    public function restoreToDatabase(ArchivedExecutionLog $archive, int $ttlHours = 24): Execution
    {
        $data = $this->restoreExecution($archive);

        return DB::transaction(function () use ($data, $archive, $ttlHours) {
            // Recreate execution
            $execution = Execution::create($data['execution']);

            // Recreate nodes
            foreach ($data['nodes'] as $nodeData) {
                $execution->nodes()->create($nodeData);
            }

            // Recreate logs
            foreach ($data['logs'] as $logData) {
                $execution->logs()->create($logData);
            }

            // Mark as temporarily restored
            $archive->update([
                'is_restored' => true,
                'restored_at' => now(),
                'restore_expires_at' => now()->addHours($ttlHours),
            ]);

            Log::info('Execution restored to database', [
                'execution_id' => $archive->execution_id,
                'expires_at' => $archive->restore_expires_at,
            ]);

            return $execution;
        });
    }

    /**
     * Check if an archived execution can be restored
     */
    public function canRestore(ArchivedExecutionLog $archive): bool
    {
        // Check if S3 file exists
        return Storage::disk('archive')->exists($archive->s3_key);
    }

    /**
     * Get archive statistics for a workspace
     */
    public function getWorkspaceStats(string $workspaceId): array
    {
        $archives = ArchivedExecutionLog::where('workspace_id', $workspaceId);

        return [
            'total_archived' => $archives->count(),
            'total_size_bytes' => $archives->sum('file_size_bytes'),
            'total_compressed_bytes' => $archives->sum('compressed_size_bytes'),
            'oldest_archive' => $archives->min('archived_at'),
            'newest_archive' => $archives->max('archived_at'),
            'avg_compression_ratio' => $archives->avg('compression_ratio'),
        ];
    }
}
