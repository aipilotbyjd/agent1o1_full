# 📦 S3 Log Archiving Plan
## Automatic Cold Storage for Execution Logs Based on User Plans

---

## Executive Summary

**Problem:**  
Execution logs (from `executions`, `execution_nodes`, and `execution_logs` tables) consume significant database storage. Current plan limits define retention periods (3-365 days), but there's no mechanism to archive old data—it just stays in the database forever or gets deleted.

**Solution:**  
Implement a tiered storage architecture where:
- **Hot Storage** (PostgreSQL): Logs within the plan's retention period (3-365 days)
- **Cold Storage** (S3/S3-compatible): Logs older than the retention period, compressed and archived
- Users can retrieve archived logs on-demand when needed

**Benefits:**
- ✅ Reduce PostgreSQL storage costs by 60-80%
- ✅ Honor plan-based retention promises without data loss
- ✅ Enable long-term compliance and analytics
- ✅ Improve database query performance
- ✅ Scalable to petabytes of historical data

---

## Current State Analysis

### Tables Involved

| Table | Purpose | Avg Size/Row | Growth Rate |
|-------|---------|--------------|-------------|
| `executions` | Execution metadata | ~2 KB | 1,000-100,000/day per workspace |
| `execution_nodes` | Node-level results | ~3 KB | 5-50 per execution |
| `execution_logs` | Debug logs | ~0.5 KB | 10-500 per execution |

**Total storage growth:** 5-500 MB per day per active workspace

### Current Plan Retention Limits

From `/app/docs/planning/pricing-and-roles.md`:

| Plan | Retention Days | Hot DB Storage Cost/Month |
|------|----------------|---------------------------|
| Free | 3 | ~$0.50 |
| Starter | 7 | ~$1.20 |
| Pro | 30 | ~$5.00 |
| Teams | 90 | ~$15.00 |
| Enterprise | 365 | ~$60.00 |

*(Assuming 10GB total logs at $6/GB/month on managed PostgreSQL)*

### Current Gaps

❌ No archival mechanism exists  
❌ Old logs either stay forever (cost ↑) or get hard-deleted (data loss)  
❌ No retrieval mechanism for archived logs  
❌ No compression or optimization

---

## Architecture Design

### High-Level Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    Execution Lifecycle                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  HOT STORAGE (PostgreSQL)                                   │
│  ✓ Fast queries                                             │
│  ✓ Real-time access                                         │
│  ✓ Retention: 3-365 days (based on plan)                   │
└─────────────────────────────────────────────────────────────┘
                              │
                   (Daily cron at 02:00 UTC)
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  ARCHIVAL PROCESS                                           │
│  1. Identify old executions (created_at < retention limit)  │
│  2. Export to JSON (all related tables)                     │
│  3. Compress (gzip)                                         │
│  4. Upload to S3: s3://bucket/{workspace}/{year}/{month}/   │
│  5. Create archive_logs record                              │
│  6. Delete from PostgreSQL                                  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  COLD STORAGE (S3)                                          │
│  ✓ 95% cheaper than PostgreSQL                             │
│  ✓ Unlimited retention                                      │
│  ✓ Compressed archives                                      │
│  ✓ Lifecycle policies (move to Glacier after 1 year)       │
└─────────────────────────────────────────────────────────────┘
                              │
                   (On-demand retrieval)
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  RESTORE PROCESS                                            │
│  1. User requests archived execution                        │
│  2. Download from S3                                        │
│  3. Decompress JSON                                         │
│  4. Render in UI (read-only)                                │
│  5. Optional: temporarily restore to PostgreSQL             │
└─────────────────────────────────────────────────────────────┘
```

---

## Database Schema Changes

### New Table: `archived_execution_logs`

Track what has been archived and where it lives in S3.

```sql
CREATE TABLE archived_execution_logs (
    id UUID PRIMARY KEY,
    workspace_id UUID NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
    execution_id UUID NOT NULL, -- Original execution ID (deleted from executions table)
    workflow_id UUID NOT NULL REFERENCES workflows(id) ON DELETE SET NULL,
    
    -- Execution metadata (for quick search without S3 access)
    workflow_name VARCHAR(255),
    status VARCHAR(50),
    mode VARCHAR(50),
    started_at TIMESTAMP,
    finished_at TIMESTAMP,
    triggered_by UUID REFERENCES users(id) ON DELETE SET NULL,
    
    -- S3 storage info
    s3_bucket VARCHAR(255) NOT NULL,
    s3_key VARCHAR(500) NOT NULL, -- e.g., "workspace-abc/2025/03/execution-xyz.json.gz"
    s3_region VARCHAR(50) DEFAULT 'us-east-1',
    s3_storage_class VARCHAR(50) DEFAULT 'STANDARD', -- STANDARD, GLACIER, DEEP_ARCHIVE
    
    -- Archive metadata
    archived_at TIMESTAMP NOT NULL,
    file_size_bytes BIGINT NOT NULL,
    compressed_size_bytes BIGINT NOT NULL,
    compression_ratio DECIMAL(5,2), -- e.g., 0.15 = 85% reduction
    
    -- Restoration tracking
    is_restored BOOLEAN DEFAULT false,
    restored_at TIMESTAMP NULL,
    restore_expires_at TIMESTAMP NULL, -- If temporarily restored
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_archived_workspace (workspace_id, archived_at),
    INDEX idx_archived_execution (execution_id),
    INDEX idx_archived_workflow (workflow_id, archived_at),
    INDEX idx_archived_s3_key (s3_bucket, s3_key),
    UNIQUE(execution_id)
);
```

### Modify Existing Tables

**`workspaces` table — add S3 config to `settings` JSONB:**

```json
{
  "archive": {
    "enabled": true,
    "s3_bucket": "linkflow-archives-prod",
    "s3_region": "us-east-1",
    "s3_prefix": "workspace-{workspace_id}",
    "storage_class": "STANDARD_IA", // or GLACIER
    "compression": "gzip",
    "lifecycle": {
      "move_to_glacier_after_days": 365,
      "move_to_deep_archive_after_days": 730
    }
  }
}
```

**`plans` table — clarify retention in `limits` JSONB:**

Already exists as `execution_log_retention_days` — no changes needed.

---

## S3 Storage Strategy

### Bucket Structure

```
linkflow-execution-archives/
  ├── workspace-{uuid}/
  │   ├── 2025/
  │   │   ├── 01/
  │   │   │   ├── execution-abc123.json.gz
  │   │   │   ├── execution-def456.json.gz
  │   │   │   └── ...
  │   │   ├── 02/
  │   │   └── 03/
  │   └── 2024/
  └── workspace-{uuid}/
      └── ...
```

**Key format:**
```
{workspace_id}/{year}/{month}/execution-{execution_id}.json.gz
```

### Archive File Format (JSON)

Each archived execution is a single JSON file containing all related data:

```json
{
  "execution": {
    "id": "abc-123",
    "workflow_id": "workflow-xyz",
    "workspace_id": "workspace-abc",
    "status": "completed",
    "mode": "webhook",
    "started_at": "2025-01-15T10:00:00Z",
    "finished_at": "2025-01-15T10:05:23Z",
    "duration_ms": 323000,
    "credits_consumed": 45,
    "trigger_data": {...},
    "result_data": {...},
    "error": null,
    "triggered_by": "user-123",
    "created_at": "2025-01-15T10:00:00Z"
  },
  "nodes": [
    {
      "id": "node-1",
      "node_type": "trigger.webhook",
      "status": "completed",
      "started_at": "2025-01-15T10:00:01Z",
      "finished_at": "2025-01-15T10:00:02Z",
      "input_data": {...},
      "output_data": {...}
    }
  ],
  "logs": [
    {
      "id": "log-1",
      "execution_node_id": "node-1",
      "level": "info",
      "message": "Webhook received",
      "context": {...},
      "logged_at": "2025-01-15T10:00:01.234Z"
    }
  ],
  "metadata": {
    "archived_at": "2025-04-15T02:00:00Z",
    "archived_by": "ArchiveExecutionLogsCommand",
    "linkflow_version": "1.5.0"
  }
}
```

### S3 Configuration

**Storage Classes:**
- **First 30 days:** STANDARD (fast retrieval)
- **30-365 days:** STANDARD_IA (Infrequent Access, 50% cheaper)
- **365+ days:** GLACIER Flexible Retrieval (80% cheaper, 3-5 hour retrieval)
- **730+ days:** GLACIER Deep Archive (95% cheaper, 12-hour retrieval)

**Lifecycle Policy Example (AWS S3):**

```json
{
  "Rules": [
    {
      "Id": "TransitionToIA",
      "Status": "Enabled",
      "Transitions": [
        {
          "Days": 30,
          "StorageClass": "STANDARD_IA"
        },
        {
          "Days": 365,
          "StorageClass": "GLACIER"
        },
        {
          "Days": 730,
          "StorageClass": "DEEP_ARCHIVE"
        }
      ]
    }
  ]
}
```

**S3-Compatible Alternatives:**
- **Cloudflare R2:** No egress fees, S3-compatible API
- **Backblaze B2:** 1/4th the cost of S3
- **MinIO:** Self-hosted S3-compatible storage
- **Wasabi:** Flat pricing, no API fees

---

## Implementation Plan

### Phase 1: Infrastructure Setup (Week 1)

**Tasks:**
1. Create S3 bucket (or configure S3-compatible storage)
2. Set up IAM roles/policies for Laravel app to access S3
3. Configure Laravel Filesystem disk for archives
4. Create `archived_execution_logs` table migration
5. Add S3 credentials to `.env`

**Laravel Filesystem Config (`config/filesystems.php`):**

```php
'disks' => [
    'archive' => [
        'driver' => 's3',
        'key' => env('AWS_ARCHIVE_ACCESS_KEY_ID'),
        'secret' => env('AWS_ARCHIVE_SECRET_ACCESS_KEY'),
        'region' => env('AWS_ARCHIVE_REGION', 'us-east-1'),
        'bucket' => env('AWS_ARCHIVE_BUCKET'),
        'url' => env('AWS_ARCHIVE_URL'),
        'endpoint' => env('AWS_ARCHIVE_ENDPOINT'), // For S3-compatible services
        'use_path_style_endpoint' => env('AWS_ARCHIVE_USE_PATH_STYLE', false),
    ],
],
```

**Environment Variables (`.env`):**

```bash
AWS_ARCHIVE_BUCKET=linkflow-execution-archives
AWS_ARCHIVE_REGION=us-east-1
AWS_ARCHIVE_ACCESS_KEY_ID=your_key
AWS_ARCHIVE_SECRET_ACCESS_KEY=your_secret
AWS_ARCHIVE_ENDPOINT= # Leave empty for AWS, or set for Cloudflare R2/MinIO
AWS_ARCHIVE_USE_PATH_STYLE=false
```

---

### Phase 2: Archive Service (Week 2)

**Create Service: `App\Services\ExecutionArchiveService`**

```php
namespace App\Services;

use App\Models\Execution;
use App\Models\ArchivedExecutionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExecutionArchiveService
{
    /**
     * Archive a single execution to S3
     */
    public function archiveExecution(Execution $execution): ArchivedExecutionLog
    {
        // 1. Load all related data
        $data = $this->prepareArchiveData($execution);
        
        // 2. Convert to JSON
        $json = json_encode($data, JSON_PRETTY_PRINT);
        $originalSize = strlen($json);
        
        // 3. Compress with gzip
        $compressed = gzencode($json, 9); // Max compression
        $compressedSize = strlen($compressed);
        
        // 4. Generate S3 key
        $s3Key = $this->generateS3Key($execution);
        
        // 5. Upload to S3
        Storage::disk('archive')->put($s3Key, $compressed, [
            'StorageClass' => 'STANDARD_IA',
            'ContentType' => 'application/gzip',
            'Metadata' => [
                'execution_id' => $execution->id,
                'workspace_id' => $execution->workspace_id,
                'archived_at' => now()->toIso8601String(),
            ],
        ]);
        
        // 6. Create archive record
        $archive = ArchivedExecutionLog::create([
            'id' => Str::uuid(),
            'workspace_id' => $execution->workspace_id,
            'execution_id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'workflow_name' => $execution->workflow->name ?? 'Unknown',
            'status' => $execution->status,
            'mode' => $execution->mode,
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
            'compression_ratio' => round($compressedSize / $originalSize, 4),
        ]);
        
        // 7. Delete from PostgreSQL (cascades to execution_nodes, execution_logs)
        $execution->delete();
        
        return $archive;
    }
    
    /**
     * Prepare execution data for archival
     */
    protected function prepareArchiveData(Execution $execution): array
    {
        return [
            'execution' => $execution->toArray(),
            'nodes' => $execution->nodes()->get()->toArray(),
            'logs' => $execution->logs()->get()->toArray(),
            'metadata' => [
                'archived_at' => now()->toIso8601String(),
                'archived_by' => 'ArchiveExecutionLogsCommand',
                'linkflow_version' => config('app.version'),
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
        // 1. Download from S3
        $compressed = Storage::disk('archive')->get($archive->s3_key);
        
        // 2. Decompress
        $json = gzdecode($compressed);
        
        // 3. Parse JSON
        return json_decode($json, true);
    }
    
    /**
     * Restore execution to PostgreSQL temporarily
     */
    public function restoreToDatabase(ArchivedExecutionLog $archive, int $ttlHours = 24): Execution
    {
        $data = $this->restoreExecution($archive);
        
        DB::transaction(function () use ($data, $archive, $ttlHours) {
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
            
            return $execution;
        });
    }
}
```

---

### Phase 3: Automated Archival Command (Week 2)

**Laravel Command: `App\Console\Commands\ArchiveOldExecutionLogs`**

```php
namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\ExecutionArchiveService;
use App\Services\PlanEnforcementService;
use Illuminate\Console\Command;

class ArchiveOldExecutionLogs extends Command
{
    protected $signature = 'executions:archive
                            {--workspace= : Specific workspace ID}
                            {--dry-run : Preview without archiving}
                            {--batch-size=100 : Number of executions per batch}';
    
    protected $description = 'Archive old execution logs to S3 based on plan retention limits';
    
    public function handle(
        ExecutionArchiveService $archiveService,
        PlanEnforcementService $planService
    ): int {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        
        $workspaces = $this->option('workspace')
            ? Workspace::where('id', $this->option('workspace'))->get()
            : Workspace::where('is_active', true)->get();
        
        $totalArchived = 0;
        $totalSpaceSaved = 0;
        
        foreach ($workspaces as $workspace) {
            $this->info("Processing workspace: {$workspace->name}");
            
            // Get plan retention limit
            $retentionDays = $planService->getLogRetentionDays($workspace);
            $cutoffDate = now()->subDays($retentionDays);
            
            $this->line("  Retention: {$retentionDays} days (cutoff: {$cutoffDate->toDateString()})");
            
            // Find old executions
            $oldExecutions = $workspace->executions()
                ->where('created_at', '<', $cutoffDate)
                ->whereNotNull('finished_at') // Only archive completed executions
                ->orderBy('created_at', 'asc')
                ->limit($batchSize)
                ->get();
            
            if ($oldExecutions->isEmpty()) {
                $this->line("  No executions to archive");
                continue;
            }
            
            $this->line("  Found {$oldExecutions->count()} executions to archive");
            
            $progressBar = $this->output->createProgressBar($oldExecutions->count());
            $progressBar->start();
            
            foreach ($oldExecutions as $execution) {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would archive: {$execution->id}");
                } else {
                    try {
                        $archive = $archiveService->archiveExecution($execution);
                        $totalArchived++;
                        $totalSpaceSaved += $archive->file_size_bytes;
                    } catch (\Exception $e) {
                        $this->error("  Failed to archive {$execution->id}: {$e->getMessage()}");
                    }
                }
                
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine();
        }
        
        $this->newLine();
        $this->info("✅ Archive complete");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Executions archived', number_format($totalArchived)],
                ['Space saved', $this->formatBytes($totalSpaceSaved)],
                ['Mode', $dryRun ? 'DRY RUN' : 'LIVE'],
            ]
        );
        
        return self::SUCCESS;
    }
    
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
```

**Schedule in `app/Console/Kernel.php`:**

```php
protected function schedule(Schedule $schedule): void
{
    // Archive old logs daily at 2:00 AM UTC
    $schedule->command('executions:archive --batch-size=1000')
        ->dailyAt('02:00')
        ->withoutOverlapping()
        ->runInBackground();
}
```

---

### Phase 4: API Endpoints (Week 3)

**Controller: `App\Http\Controllers\Api\V1\ArchivedExecutionController`**

```php
namespace App\Http\Controllers\Api\V1;

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
     */
    public function index(Request $request)
    {
        $workspace = $request->attributes->get('workspace');
        
        $archives = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id)
            ->when($request->workflow_id, fn($q) => $q->where('workflow_id', $request->workflow_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('archived_at', 'desc')
            ->paginate(50);
        
        return response()->json($archives);
    }
    
    /**
     * Retrieve a single archived execution (download from S3)
     */
    public function show(Request $request, string $executionId)
    {
        $workspace = $request->attributes->get('workspace');
        
        $archive = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('execution_id', $executionId)
            ->firstOrFail();
        
        // Download and decompress from S3
        $data = $this->archiveService->restoreExecution($archive);
        
        return response()->json([
            'archived' => true,
            'archive_info' => $archive,
            'execution' => $data,
        ]);
    }
    
    /**
     * Temporarily restore an archived execution to the database
     */
    public function restore(Request $request, string $executionId)
    {
        $workspace = $request->attributes->get('workspace');
        
        $archive = ArchivedExecutionLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('execution_id', $executionId)
            ->firstOrFail();
        
        // Restore to PostgreSQL for 24 hours
        $execution = $this->archiveService->restoreToDatabase($archive, ttlHours: 24);
        
        return response()->json([
            'message' => 'Execution restored to database for 24 hours',
            'execution' => $execution,
            'expires_at' => $archive->restore_expires_at,
        ]);
    }
}
```

**Routes (`routes/api.php`):**

```php
Route::prefix('v1/workspaces/{workspace}')->group(function () {
    Route::get('executions/archived', [ArchivedExecutionController::class, 'index']);
    Route::get('executions/archived/{execution}', [ArchivedExecutionController::class, 'show']);
    Route::post('executions/archived/{execution}/restore', [ArchivedExecutionController::class, 'restore']);
});
```

---

### Phase 5: Frontend Integration (Week 4)

**UI Changes:**

1. **Execution List Page:**
   - Add "Show Archived" toggle
   - Display archived executions with a badge: `🗄️ Archived`
   - Slightly fade out archived rows

2. **Execution Detail Page:**
   - If execution is archived, show banner: *"This execution is archived. Click to load."*
   - On click, fetch from S3 and render (read-only)
   - Add "Restore to Database" button (for Teams+ plans only)

3. **Workspace Settings:**
   - Display current plan's retention limit
   - Show archive statistics:
     - Total executions archived
     - Storage saved
     - S3 storage class

**Example API Response (with archive flag):**

```json
{
  "id": "abc-123",
  "workflow_id": "xyz",
  "status": "completed",
  "archived": true,
  "archived_at": "2025-04-01T02:00:00Z",
  "s3_storage_class": "STANDARD_IA",
  "can_restore": true
}
```

---

## Cost Analysis

### Current State (No Archival)

**Assumptions:**
- 1 workspace, 10,000 executions/month
- Avg 5 nodes per execution
- Avg 50 logs per execution
- Total: 10,000 executions + 50,000 nodes + 500,000 logs = 560,000 rows/month
- PostgreSQL managed (e.g., AWS RDS): $0.10/GB/month

**Monthly Storage:**
- Executions: 10,000 × 2 KB = 20 MB
- Nodes: 50,000 × 3 KB = 150 MB
- Logs: 500,000 × 0.5 KB = 250 MB
- **Total: 420 MB/month**

**12-month storage without archival:**
- 420 MB × 12 = 5.04 GB
- Cost: 5.04 GB × $0.10 = **$0.50/month** (grows linearly)

### With S3 Archival

**Hot storage (30 days):**
- 420 MB × 1 month = 420 MB in PostgreSQL
- Cost: 0.42 GB × $0.10 = **$0.042/month**

**Cold storage (archived):**
- 420 MB × 11 months = 4.62 GB in S3 Standard-IA
- Cost: 4.62 GB × $0.0125/GB = **$0.058/month**

**Total: $0.10/month (80% savings)**

**At scale (100 workspaces, 1M executions/month):**
- Without archival: 504 GB × $0.10 = **$50.40/month** (growing)
- With archival: **$10/month** (stable)

---

## Migration Strategy

### For Existing Workspaces

**Option 1: Archive All Old Data Immediately**
```bash
php artisan executions:archive --batch-size=1000
```

**Option 2: Gradual Backfill (Recommended)**

```php
// Archive in batches over 7 days
$schedule->command('executions:archive --batch-size=500')
    ->everyFourHours()
    ->withoutOverlapping();
```

**Option 3: Manual Per-Workspace**

```bash
php artisan executions:archive --workspace=abc-123 --dry-run
php artisan executions:archive --workspace=abc-123
```

---

## Monitoring & Observability

### Metrics to Track

```php
// app/Services/ExecutionArchiveService.php

public function archiveExecution(Execution $execution): ArchivedExecutionLog
{
    $start = microtime(true);
    
    // ... archival logic ...
    
    $duration = microtime(true) - $start;
    
    // Send metrics
    Metrics::timing('executions.archive.duration', $duration);
    Metrics::increment('executions.archive.success');
    Metrics::gauge('executions.archive.size_bytes', $archive->compressed_size_bytes);
    Metrics::gauge('executions.archive.compression_ratio', $archive->compression_ratio);
    
    Log::info('Execution archived', [
        'execution_id' => $execution->id,
        'workspace_id' => $execution->workspace_id,
        's3_key' => $archive->s3_key,
        'size_bytes' => $archive->file_size_bytes,
        'compressed_size_bytes' => $archive->compressed_size_bytes,
        'duration_seconds' => $duration,
    ]);
    
    return $archive;
}
```

### Alerts to Set Up

- ❌ Archival failure rate > 1%
- ❌ S3 upload latency > 10 seconds
- ❌ Archive command hasn't run in 36 hours
- ⚠️ Compression ratio < 50% (indicates inefficient archival)
- ⚠️ S3 bucket storage > 100 GB (move to Glacier)

---

## Security & Compliance

### Encryption

**At Rest:**
- S3 Server-Side Encryption (SSE-S3 or SSE-KMS)
- Enable by default in S3 bucket policy

```php
Storage::disk('archive')->put($s3Key, $compressed, [
    'ServerSideEncryption' => 'AES256', // or 'aws:kms'
]);
```

**In Transit:**
- HTTPS only for S3 API calls (enforced by Laravel)

### Access Control

**S3 Bucket Policy:**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "AWS": "arn:aws:iam::ACCOUNT_ID:role/LinkFlowAppRole"
      },
      "Action": [
        "s3:PutObject",
        "s3:GetObject",
        "s3:DeleteObject"
      ],
      "Resource": "arn:aws:s3:::linkflow-execution-archives/*"
    }
  ]
}
```

**IAM Role (Least Privilege):**

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:PutObject",
        "s3:GetObject"
      ],
      "Resource": "arn:aws:s3:::linkflow-execution-archives/*"
    }
  ]
}
```

### GDPR & Data Retention

**Right to Deletion:**
- When a workspace is deleted, trigger:
  ```php
  Storage::disk('archive')->deleteDirectory($workspace->id);
  ```

**Data Export:**
- Users can download all archived executions via API or UI
- Provide bulk export: `GET /api/v1/workspaces/{workspace}/executions/export`

---

## Testing Strategy

### Unit Tests

```php
// tests/Unit/ExecutionArchiveServiceTest.php

public function test_archives_execution_to_s3()
{
    Storage::fake('archive');
    
    $execution = Execution::factory()->create();
    $service = new ExecutionArchiveService();
    
    $archive = $service->archiveExecution($execution);
    
    Storage::disk('archive')->assertExists($archive->s3_key);
    $this->assertDatabaseMissing('executions', ['id' => $execution->id]);
    $this->assertDatabaseHas('archived_execution_logs', ['execution_id' => $execution->id]);
}

public function test_restores_archived_execution()
{
    Storage::fake('archive');
    
    $execution = Execution::factory()->create();
    $service = new ExecutionArchiveService();
    
    $archive = $service->archiveExecution($execution);
    $restored = $service->restoreExecution($archive);
    
    $this->assertEquals($execution->id, $restored['execution']['id']);
}
```

### Integration Tests

```php
// tests/Feature/ArchiveOldExecutionLogsTest.php

public function test_archives_old_executions_based_on_plan()
{
    $workspace = Workspace::factory()->create();
    $workspace->subscription->plan->update([
        'limits' => ['execution_log_retention_days' => 7],
    ]);
    
    // Create executions older than 7 days
    $oldExecution = Execution::factory()->create([
        'workspace_id' => $workspace->id,
        'created_at' => now()->subDays(10),
    ]);
    
    // Create recent execution
    $recentExecution = Execution::factory()->create([
        'workspace_id' => $workspace->id,
        'created_at' => now()->subDays(3),
    ]);
    
    $this->artisan('executions:archive');
    
    $this->assertDatabaseMissing('executions', ['id' => $oldExecution->id]);
    $this->assertDatabaseHas('executions', ['id' => $recentExecution->id]);
    $this->assertDatabaseHas('archived_execution_logs', ['execution_id' => $oldExecution->id]);
}
```

---

## Performance Optimization

### Batch Processing

**Chunk large archival operations:**

```php
$workspace->executions()
    ->where('created_at', '<', $cutoffDate)
    ->chunkById(100, function ($executions) use ($archiveService) {
        foreach ($executions as $execution) {
            dispatch(new ArchiveExecutionJob($execution));
        }
    });
```

**Use Laravel Jobs for async archival:**

```php
namespace App\Jobs;

use App\Models\Execution;
use App\Services\ExecutionArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ArchiveExecutionJob implements ShouldQueue
{
    use Queueable;
    
    public function __construct(
        public Execution $execution
    ) {}
    
    public function handle(ExecutionArchiveService $service): void
    {
        $service->archiveExecution($this->execution);
    }
}
```

### Database Optimization

**Add index for archival queries:**

```php
Schema::table('executions', function (Blueprint $table) {
    $table->index(['workspace_id', 'created_at', 'finished_at'], 'idx_archival_candidates');
});
```

**Prevent locking during archival:**

```php
$workspace->executions()
    ->where('created_at', '<', $cutoffDate)
    ->whereNotNull('finished_at')
    ->lockForUpdate() // Prevent race conditions
    ->chunkById(100, function ($executions) {
        // Archive...
    });
```

---

## Edge Cases & Failure Handling

### What if S3 upload fails?

**Retry Logic:**

```php
// app/Jobs/ArchiveExecutionJob.php

public $tries = 3;
public $backoff = [60, 300, 900]; // 1 min, 5 min, 15 min

public function failed(\Throwable $exception): void
{
    Log::error('Failed to archive execution', [
        'execution_id' => $this->execution->id,
        'error' => $exception->getMessage(),
    ]);
    
    // Send alert to admin
    Notification::route('slack', config('services.slack.alert_webhook'))
        ->notify(new ArchivalFailedNotification($this->execution, $exception));
}
```

### What if archived execution is deleted from S3?

**Create a reconciliation command:**

```php
php artisan executions:verify-archives --fix
```

This command:
1. Checks if S3 file exists for each `archived_execution_logs` record
2. If missing, either:
   - Re-archive from source (if still in DB)
   - Mark as `s3_missing` in `archived_execution_logs`

### What if user restores and then modifies?

Restored executions should be **read-only** by default. If restoration is needed for re-execution:
- Clone the execution as a new run
- Never mutate archived data

---

## Rollout Checklist

### Pre-Launch

- [ ] Create S3 bucket with lifecycle policies
- [ ] Set up IAM roles and test access
- [ ] Run migration to create `archived_execution_logs` table
- [ ] Deploy `ExecutionArchiveService` and command
- [ ] Test archival on staging with 1,000+ executions
- [ ] Verify S3 compression ratios are > 70%
- [ ] Set up monitoring and alerts

### Launch (Week 1)

- [ ] Enable archival for 10 test workspaces
- [ ] Monitor error rates and S3 costs
- [ ] Collect user feedback on restoration UX

### Scale (Week 2-4)

- [ ] Enable archival for all Free plan workspaces
- [ ] Enable archival for Starter/Pro workspaces
- [ ] Enable archival for Teams/Enterprise (optional, configurable)

### Post-Launch

- [ ] Document archival process in user-facing help docs
- [ ] Add workspace settings UI for archive preferences
- [ ] Implement bulk export feature
- [ ] Set up S3 cost alerts

---

## Future Enhancements

### Phase 6: Advanced Features

**1. Partial Archival**
- Archive only logs, keep execution metadata in PostgreSQL
- Reduces DB size by 80% while preserving searchability

**2. Searchable Archive**
- Index archived executions in Elasticsearch
- Allow full-text search across archived logs

**3. Multi-Region Replication**
- Replicate S3 archives across regions for disaster recovery

**4. Intelligent Tiering**
- Automatically move to Glacier based on access patterns
- Keep frequently accessed archives in Standard-IA

**5. Streaming Retrieval**
- Stream large archived executions in chunks instead of full download
- Improves UX for multi-GB executions

---

## Summary

This plan provides a **complete blueprint** for implementing tiered storage for execution logs:

✅ **Hot Storage (PostgreSQL):** Logs within plan retention limits (3-365 days)  
✅ **Cold Storage (S3):** Compressed archives of older logs (unlimited retention)  
✅ **Automated Archival:** Daily cron job respects per-workspace plan limits  
✅ **On-Demand Restoration:** Download from S3 or temporarily restore to PostgreSQL  
✅ **Cost Savings:** 80% reduction in database storage costs  
✅ **Compliance:** GDPR-friendly with encryption and access controls  

**Next Steps:**
1. Approve this plan
2. Set up S3 bucket and IAM roles
3. Implement `ExecutionArchiveService` (Week 1-2)
4. Deploy archival command and schedule (Week 2)
5. Build frontend integration (Week 3-4)
6. Monitor and optimize (Ongoing)

---

**Questions or feedback?** Drop them in the implementation tickets.
