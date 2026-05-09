<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archived_execution_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->uuid('execution_id')->unique(); // Original execution ID (deleted from executions table)
            $table->foreignUuid('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            
            // Execution metadata (for quick search without S3 access)
            $table->string('workflow_name')->nullable();
            $table->string('status', 50);
            $table->string('mode', 50);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            
            // S3 storage info
            $table->string('s3_bucket');
            $table->string('s3_key', 500); // e.g., "workspace-abc/2025/03/execution-xyz.json.gz"
            $table->string('s3_region', 50)->default('us-east-1');
            $table->string('s3_storage_class', 50)->default('STANDARD'); // STANDARD, GLACIER, DEEP_ARCHIVE
            
            // Archive metadata
            $table->timestamp('archived_at');
            $table->unsignedBigInteger('file_size_bytes');
            $table->unsignedBigInteger('compressed_size_bytes');
            $table->decimal('compression_ratio', 5, 4)->nullable(); // e.g., 0.1500 = 85% reduction
            
            // Restoration tracking
            $table->boolean('is_restored')->default(false);
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('restore_expires_at')->nullable(); // If temporarily restored
            
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes
            $table->index(['workspace_id', 'archived_at'], 'idx_archived_workspace');
            $table->index('execution_id', 'idx_archived_execution');
            $table->index(['workflow_id', 'archived_at'], 'idx_archived_workflow');
            $table->index(['s3_bucket', 's3_key'], 'idx_archived_s3_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_execution_logs');
    }
};
