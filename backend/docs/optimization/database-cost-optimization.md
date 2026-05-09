# 💾 Database Storage Cost Optimization Guide

**Advanced strategies to reduce database costs by 70-95%**

---

## 📊 Cost Breakdown

### Current Database Costs

**PostgreSQL Storage Pricing:**
| Provider | Storage | Backup | IOPS | Total (100GB) |
|----------|---------|--------|------|---------------|
| **Self-hosted** | $0.10/GB | Free | Free | $10/month |
| **AWS RDS** | $0.23/GB | $0.20/GB | $0.20/IOPS | $43/month |
| **Supabase** | $0.125/GB | Included | Included | $12.50/month |
| **Digital Ocean** | $0.10/GB | Included | Included | $10/month |

### Your Data Growth

**Typical LinkFlow database growth:**

```
Executions table:
- Average execution: 50 KB (input + output + logs)
- 1,000 executions/day = 50 MB/day = 1.5 GB/month
- 100,000 executions/day = 5 GB/day = 150 GB/month

Execution nodes table:
- Average node result: 10 KB
- 5 nodes per execution average
- 1,000 executions/day = 50 MB/day
- 100,000 executions/day = 5 GB/day

Vector embeddings (if using RAG):
- 1 embedding = 1536 floats × 4 bytes = 6 KB
- 1M documents = 6 GB just for vectors
```

### Cost Scenarios

**Scenario 1: Small Business (1K executions/day)**
- Storage: 50 GB/month
- Cost: $5-11.50/month
- Annual: $60-138

**Scenario 2: Growing Startup (10K executions/day)**
- Storage: 200 GB/month
- Cost: $20-46/month
- Annual: $240-552

**Scenario 3: High-Volume SaaS (100K executions/day)**
- Storage: 1.5 TB/month
- Cost: $150-645/month
- Annual: $1,800-7,740 💸

**Scenario 4: Enterprise (1M executions/day)**
- Storage: 15 TB/month
- Cost: $1,500-6,450/month
- Annual: $18,000-77,400 💸💸

---

## 🎯 Optimization Strategies

### Strategy 1: Aggressive Retention Policies (60-90% savings)

**Problem:** Storing all execution history forever.

**Solution:** Tiered retention based on plan + auto-cleanup.

#### Retention Policy Configuration

```php
// config/retention.php
return [
    'plans' => [
        'free' => [
            'executions' => 3,          // days
            'execution_nodes' => 3,
            'logs' => 1,
            'failed_executions' => 7,   // Keep failures longer
        ],
        'starter' => [
            'executions' => 7,
            'execution_nodes' => 7,
            'logs' => 3,
            'failed_executions' => 14,
        ],
        'pro' => [
            'executions' => 30,
            'execution_nodes' => 30,
            'logs' => 14,
            'failed_executions' => 60,
        ],
        'teams' => [
            'executions' => 90,
            'execution_nodes' => 90,
            'logs' => 30,
            'failed_executions' => 180,
        ],
        'enterprise' => [
            'executions' => 365,
            'execution_nodes' => 365,
            'logs' => 90,
            'failed_executions' => 730,
        ],
    ],
    
    // Archive to S3 instead of delete
    'archive_enabled' => true,
    'archive_after_days' => 30,
];
```

#### Automated Cleanup Job

```php
// app/Console/Commands/CleanupOldData.php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupOldData extends Command
{
    protected $signature = 'cleanup:old-data {--dry-run}';
    
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $totalDeleted = 0;
        $totalArchived = 0;
        $totalFreed = 0; // MB
        
        // Process each workspace
        Workspace::chunk(100, function($workspaces) use ($dryRun, &$totalDeleted, &$totalArchived, &$totalFreed) {
            foreach ($workspaces as $workspace) {
                $plan = $workspace->subscription->plan ?? 'free';
                $retention = config("retention.plans.{$plan}");
                
                $this->info("Processing workspace: {$workspace->name} (Plan: {$plan})");
                
                // 1. Archive old executions to S3
                if (config('retention.archive_enabled')) {
                    $toArchive = Execution::where('workspace_id', $workspace->id)
                        ->where('status', 'success')
                        ->where('created_at', '<', now()->subDays($retention['executions']))
                        ->where('created_at', '>', now()->subDays(365)) // Don't archive > 1 year
                        ->whereNull('archived_at')
                        ->get();
                    
                    foreach ($toArchive as $execution) {
                        if (!$dryRun) {
                            $this->archiveExecution($execution);
                            $totalArchived++;
                        }
                    }
                }
                
                // 2. Delete very old executions (archived or older than retention + 365 days)
                $deleted = Execution::where('workspace_id', $workspace->id)
                    ->where(function($q) use ($retention) {
                        // Archived and older than retention
                        $q->whereNotNull('archived_at')
                          ->where('created_at', '<', now()->subDays($retention['executions']));
                    })
                    ->orWhere(function($q) {
                        // Very old (> 1 year)
                        $q->where('created_at', '<', now()->subYear());
                    });
                
                $count = $deleted->count();
                $sizeMB = $this->estimateSize($deleted->first(), $count);
                
                if (!$dryRun && $count > 0) {
                    $deleted->delete();
                    $totalDeleted += $count;
                    $totalFreed += $sizeMB;
                    $this->line("  Deleted: {$count} executions (~{$sizeMB} MB)");
                }
                
                // 3. Delete old execution nodes
                $nodeCount = DB::table('execution_nodes')
                    ->whereIn('execution_id', function($q) use ($workspace, $retention) {
                        $q->select('id')
                          ->from('executions')
                          ->where('workspace_id', $workspace->id)
                          ->where('created_at', '<', now()->subDays($retention['execution_nodes']));
                    })
                    ->count();
                
                if (!$dryRun && $nodeCount > 0) {
                    DB::table('execution_nodes')
                        ->whereIn('execution_id', function($q) use ($workspace, $retention) {
                            $q->select('id')
                              ->from('executions')
                              ->where('workspace_id', $workspace->id)
                              ->where('created_at', '<', now()->subDays($retention['execution_nodes']));
                        })
                        ->delete();
                    
                    $this->line("  Deleted: {$nodeCount} execution nodes");
                }
            }
        });
        
        $this->info("\n=== Summary ===");
        $this->info("Archived: {$totalArchived} executions");
        $this->info("Deleted: {$totalDeleted} executions");
        $this->info("Space freed: " . round($totalFreed / 1024, 2) . " GB");
        
        // Update metrics
        Metrics::gauge('database.cleanup.deleted', $totalDeleted);
        Metrics::gauge('database.cleanup.freed_mb', $totalFreed);
    }
    
    private function archiveExecution(Execution $execution): void
    {
        // Serialize execution data
        $data = [
            'id' => $execution->id,
            'workflow_id' => $execution->workflow_id,
            'workspace_id' => $execution->workspace_id,
            'status' => $execution->status,
            'input_data' => $execution->input_data,
            'output_data' => $execution->output_data,
            'logs' => $execution->logs,
            'started_at' => $execution->started_at,
            'finished_at' => $execution->finished_at,
            'duration_seconds' => $execution->duration_seconds,
        ];
        
        // Compress and upload to S3
        $compressed = gzcompress(json_encode($data), 9);
        $path = "archives/{$execution->workspace_id}/{$execution->created_at->format('Y/m')}/{$execution->id}.json.gz";
        
        Storage::disk('s3')->put($path, $compressed);
        
        // Mark as archived
        $execution->update([
            'archived_at' => now(),
            'archive_path' => $path,
        ]);
        
        // Clear large fields from database
        $execution->update([
            'input_data' => null,
            'output_data' => null,
            'logs' => null,
        ]);
    }
    
    private function estimateSize($execution, int $count): float
    {
        if (!$execution) return 0;
        
        // Estimate average size per execution (KB)
        $avgSize = 50; // 50 KB average
        
        return ($avgSize * $count) / 1024; // Convert to MB
    }
}
```

#### Schedule Cleanup

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Run daily at 2 AM
    $schedule->command('cleanup:old-data')->dailyAt('02:00');
    
    // Send weekly report
    $schedule->call(function() {
        $report = DB::table('database_size_tracking')
            ->whereBetween('date', [now()->subWeek(), now()])
            ->get();
        
        Mail::to(config('app.admin_email'))->send(new WeeklyStorageReport($report));
    })->weekly();
}
```

**Savings:**
- Free tier: 90% reduction (3 days vs 30 days)
- Paid tiers: 60-80% reduction with archival
- **Example:** 150 GB → 15-45 GB = $120/month savings

---

### Strategy 2: JSONB Field Compression (50-80% savings)

**Problem:** Large JSONB fields (input_data, output_data, logs) consume lots of space.

**Solution:** Compress large fields before storing.

#### Automatic Compression

```php
// app/Models/Execution.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Execution extends Model
{
    protected $casts = [
        'input_data' => 'array',
        'output_data' => 'array',
        'logs' => 'array',
    ];
    
    private const COMPRESSION_THRESHOLD = 10240; // 10 KB
    
    /**
     * Compress large data before saving
     */
    public function setOutputDataAttribute($value)
    {
        if (is_array($value)) {
            $json = json_encode($value);
            
            // Only compress if larger than threshold
            if (strlen($json) > self::COMPRESSION_THRESHOLD) {
                $compressed = gzcompress($json, 9); // Max compression
                
                // Only use compressed if it's actually smaller
                if (strlen($compressed) < strlen($json)) {
                    $this->attributes['output_data'] = base64_encode($compressed);
                    $this->attributes['output_data_compressed'] = true;
                    
                    $savings = strlen($json) - strlen($compressed);
                    $savingsPercent = ($savings / strlen($json)) * 100;
                    
                    Log::debug("Compressed output_data", [
                        'original_size' => strlen($json),
                        'compressed_size' => strlen($compressed),
                        'savings_percent' => round($savingsPercent, 2),
                    ]);
                    
                    return;
                }
            }
            
            // Store uncompressed
            $this->attributes['output_data'] = $json;
            $this->attributes['output_data_compressed'] = false;
        }
    }
    
    /**
     * Decompress when reading
     */
    public function getOutputDataAttribute($value)
    {
        if (!$value) return null;
        
        if ($this->attributes['output_data_compressed'] ?? false) {
            // Decompress
            $compressed = base64_decode($value);
            $json = gzuncompress($compressed);
            return json_decode($json, true);
        }
        
        // Not compressed
        return json_decode($value, true);
    }
    
    // Same for input_data and logs
    public function setInputDataAttribute($value)
    {
        // Same logic as output_data
    }
    
    public function setLogsAttribute($value)
    {
        // Same logic as output_data
    }
}
```

#### Migration to Add Compression Flags

```php
// database/migrations/xxxx_add_compression_flags.php
Schema::table('executions', function (Blueprint $table) {
    $table->boolean('input_data_compressed')->default(false);
    $table->boolean('output_data_compressed')->default(false);
    $table->boolean('logs_compressed')->default(false);
});
```

#### Bulk Compression for Existing Data

```php
// app/Console/Commands/CompressExistingData.php
class CompressExistingData extends Command
{
    protected $signature = 'db:compress-existing {--limit=1000}';
    
    public function handle()
    {
        $limit = $this->option('limit');
        $compressed = 0;
        $savedMB = 0;
        
        // Find uncompressed executions with large data
        Execution::whereRaw("LENGTH(output_data::text) > 10240")
            ->where('output_data_compressed', false)
            ->orWhereNull('output_data_compressed')
            ->chunk(100, function($executions) use (&$compressed, &$savedMB) {
                foreach ($executions as $execution) {
                    $before = strlen($execution->getRawOriginal('output_data'));
                    
                    // Trigger compression by re-saving
                    $execution->output_data = $execution->output_data;
                    $execution->save();
                    
                    $after = strlen($execution->getRawOriginal('output_data'));
                    $saved = ($before - $after) / 1024 / 1024; // MB
                    
                    $compressed++;
                    $savedMB += $saved;
                    
                    if ($compressed >= $this->option('limit')) {
                        return false; // Stop chunking
                    }
                }
            });
        
        $this->info("Compressed {$compressed} executions");
        $this->info("Saved " . round($savedMB, 2) . " MB");
    }
}
```

**Compression Results:**

```
Typical compression ratios for different data types:

JSON with repetitive data (logs): 70-85% compression
JSON with varied data (API responses): 50-70% compression
Base64 encoded images: 5-15% compression
Already compressed data: No benefit

Average overall: 60% size reduction
```

**Savings Example:**

```
Before: 100 GB of execution data
After: 40 GB (60% compression)
Savings: 60 GB × $0.23 = $13.80/month
Annual: $165.60
```

---

### Strategy 3: Offload to Cheap Storage (80-90% savings)

**Problem:** PostgreSQL storage is expensive for infrequently accessed data.

**Solution:** Move old data to S3 (10x cheaper) or Glacier (57x cheaper).

#### S3 Storage Layer

```php
// app/Services/Storage/HybridStorage.php
class HybridStorage
{
    /**
     * Store execution data in appropriate tier
     */
    public function store(Execution $execution): void
    {
        $age = $execution->created_at->diffInDays(now());
        
        if ($age > 90) {
            // Very old - move to S3 Glacier Deep Archive
            $this->moveToGlacier($execution);
        } elseif ($age > 30) {
            // Old - move to S3 Standard
            $this->moveToS3($execution);
        } else {
            // Recent - keep in PostgreSQL
            // (already there)
        }
    }
    
    private function moveToS3(Execution $execution): void
    {
        // Serialize execution
        $data = $this->serializeExecution($execution);
        
        // Compress
        $compressed = gzcompress(json_encode($data), 9);
        
        // Upload to S3
        $path = $this->getS3Path($execution);
        Storage::disk('s3')->put($path, $compressed);
        
        // Update database record (remove large fields)
        $execution->update([
            'storage_tier' => 's3',
            'storage_path' => $path,
            'input_data' => null,
            'output_data' => null,
            'logs' => null,
        ]);
        
        Log::info("Moved execution to S3", [
            'execution_id' => $execution->id,
            'path' => $path,
            'size_kb' => strlen($compressed) / 1024,
        ]);
    }
    
    private function moveToGlacier(Execution $execution): void
    {
        // Similar to S3 but with Glacier storage class
        $data = $this->serializeExecution($execution);
        $compressed = gzcompress(json_encode($data), 9);
        $path = $this->getS3Path($execution);
        
        // Upload to S3 with Glacier Deep Archive storage class
        Storage::disk('s3')->put($path, $compressed, [
            'StorageClass' => 'DEEP_ARCHIVE',
        ]);
        
        $execution->update([
            'storage_tier' => 'glacier',
            'storage_path' => $path,
            'input_data' => null,
            'output_data' => null,
            'logs' => null,
        ]);
    }
    
    /**
     * Retrieve execution from storage tier
     */
    public function retrieve(Execution $execution): array
    {
        if ($execution->storage_tier === 'postgresql') {
            // Data is in database
            return [
                'input_data' => $execution->input_data,
                'output_data' => $execution->output_data,
                'logs' => $execution->logs,
            ];
        }
        
        if ($execution->storage_tier === 's3') {
            // Fetch from S3 (instant)
            return $this->retrieveFromS3($execution);
        }
        
        if ($execution->storage_tier === 'glacier') {
            // Initiate Glacier retrieval (4-12 hours)
            return $this->initiateGlacierRetrieval($execution);
        }
    }
    
    private function retrieveFromS3(Execution $execution): array
    {
        $compressed = Storage::disk('s3')->get($execution->storage_path);
        $json = gzuncompress($compressed);
        return json_decode($json, true);
    }
}
```

#### Automated Migration Job

```php
// app/Console/Commands/MigrateToS3.php
class MigrateToS3 extends Command
{
    protected $signature = 'storage:migrate-to-s3 {--dry-run}';
    
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $storage = new HybridStorage();
        
        // Migrate executions older than 30 days
        $executions = Execution::where('created_at', '<', now()->subDays(30))
            ->where('storage_tier', 'postgresql')
            ->orWhereNull('storage_tier')
            ->get();
        
        $this->info("Found {$executions->count()} executions to migrate");
        
        $progress = $this->output->createProgressBar($executions->count());
        $totalSizeMB = 0;
        
        foreach ($executions as $execution) {
            if (!$dryRun) {
                $sizeBefore = $this->estimateSize($execution);
                $storage->store($execution);
                $totalSizeMB += $sizeBefore;
            }
            
            $progress->advance();
        }
        
        $progress->finish();
        
        $this->info("\n\nMigrated {$executions->count()} executions");
        $this->info("Freed " . round($totalSizeMB / 1024, 2) . " GB from PostgreSQL");
        
        // Calculate savings
        $pgCost = ($totalSizeMB / 1024) * 0.23; // AWS RDS pricing
        $s3Cost = ($totalSizeMB / 1024) * 0.023; // S3 pricing
        $savings = $pgCost - $s3Cost;
        
        $this->info("Monthly savings: $" . round($savings, 2));
    }
}
```

**Cost Comparison:**

| Storage Type | Cost per GB/month | Retrieval | Best For |
|--------------|-------------------|-----------|----------|
| **PostgreSQL (RDS)** | $0.23 | Instant | Recent data (< 30 days) |
| **S3 Standard** | $0.023 | Instant | Old data (30-90 days) |
| **S3 Glacier** | $0.004 | 1-5 hours | Archive (> 90 days) |
| **S3 Glacier Deep** | $0.00099 | 12 hours | Long-term archive |

**Savings:**

```
Scenario: 1 TB total data
- Recent (30 days): 100 GB in PostgreSQL = $23
- Old (60 days): 400 GB in S3 = $9.20
- Archive (365+ days): 500 GB in Glacier = $2

Total: $34.20/month

vs. All in PostgreSQL: $230/month

Savings: $195.80/month (85%)
```

---

### Strategy 4: Optimize Vector Embeddings (67% savings)

**Problem:** RAG features use 1536-dimension vectors (6 KB each).

**Solution:** Use smaller embeddings or quantization.

#### Switch to Smaller Embeddings

```php
// config/embeddings.php
return [
    // Old: text-embedding-3-large (1536 dimensions, 6 KB per embedding)
    // New: text-embedding-3-small (512 dimensions, 2 KB per embedding)
    
    'default_model' => 'text-embedding-3-small',
    
    'models' => [
        'text-embedding-3-small' => [
            'dimensions' => 512,
            'cost_per_1m_tokens' => 0.02,
            'size_per_embedding' => 2048, // bytes
        ],
        'text-embedding-3-large' => [
            'dimensions' => 1536,
            'cost_per_1m_tokens' => 0.13,
            'size_per_embedding' => 6144, // bytes
        ],
    ],
];
```

#### Vector Quantization

```php
// app/Services/VectorQuantization.php
class VectorQuantization
{
    /**
     * Quantize float32 vectors to int8 (75% size reduction)
     */
    public function quantize(array $vector): array
    {
        // Find min and max
        $min = min($vector);
        $max = max($vector);
        
        // Scale to 0-255 range
        $quantized = [];
        foreach ($vector as $value) {
            $scaled = (($value - $min) / ($max - $min)) * 255;
            $quantized[] = (int) round($scaled);
        }
        
        return [
            'quantized' => $quantized,
            'min' => $min,
            'max' => $max,
        ];
    }
    
    /**
     * Dequantize back to float32
     */
    public function dequantize(array $quantized, float $min, float $max): array
    {
        $vector = [];
        foreach ($quantized as $value) {
            $scaled = ($value / 255) * ($max - $min) + $min;
            $vector[] = $scaled;
        }
        
        return $vector;
    }
}
```

**Savings:**

```
1M documents with embeddings:

Large model (1536 dims, float32):
- Size: 1M × 6 KB = 6 GB
- Cost: 6 GB × $0.23 = $1.38/month

Small model (512 dims, float32):
- Size: 1M × 2 KB = 2 GB
- Cost: 2 GB × $0.23 = $0.46/month
- Savings: 67%

Small model + quantization (512 dims, int8):
- Size: 1M × 0.5 KB = 0.5 GB
- Cost: 0.5 GB × $0.23 = $0.115/month
- Savings: 92%
```

---

### Strategy 5: Query Optimization (30-50% faster, less I/O cost)

**Problem:** Slow queries scan entire tables, increasing I/O costs.

**Solution:** Optimize indexes and query patterns.

#### Index Optimization

```sql
-- Analyze current indexes
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
WHERE idx_scan = 0 AND schemaname = 'public'
ORDER BY pg_relation_size(indexrelid) DESC;

-- Drop unused indexes (save space and write performance)
DROP INDEX IF EXISTS unused_index_name;

-- Add missing indexes based on query patterns
CREATE INDEX CONCURRENTLY idx_executions_workspace_created 
ON executions(workspace_id, created_at DESC) 
WHERE deleted_at IS NULL;

-- Partial indexes for common filters
CREATE INDEX CONCURRENTLY idx_executions_failed 
ON executions(workspace_id, created_at DESC) 
WHERE status = 'failed';

-- Include columns to avoid table lookup
CREATE INDEX CONCURRENTLY idx_executions_workspace_status_include 
ON executions(workspace_id, status) 
INCLUDE (duration_seconds, credits_used);
```

#### Query Pattern Optimization

```php
// app/Repositories/ExecutionRepository.php
class ExecutionRepository
{
    /**
     * Optimized query with selective fields
     */
    public function getRecentExecutions(string $workspaceId, int $limit = 20): Collection
    {
        // Bad: SELECT * loads all fields (including large JSONB)
        // return Execution::where('workspace_id', $workspaceId)->limit($limit)->get();
        
        // Good: Select only needed fields
        return Execution::where('workspace_id', $workspaceId)
            ->select([
                'id',
                'workflow_id',
                'status',
                'started_at',
                'duration_seconds',
                'credits_used',
                // Omit: input_data, output_data, logs (large fields)
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Use covering index
     */
    public function getFailedCount(string $workspaceId): int
    {
        // Uses partial index idx_executions_failed
        return Execution::where('workspace_id', $workspaceId)
            ->where('status', 'failed')
            ->count();
    }
    
    /**
     * Avoid N+1 queries
     */
    public function getWorkflowsWithStats(string $workspaceId): Collection
    {
        return Workflow::where('workspace_id', $workspaceId)
            ->withCount([
                'executions',
                'executions as success_count' => fn($q) => $q->where('status', 'success'),
            ])
            ->withAvg('executions', 'duration_seconds')
            ->get();
    }
}
```

#### Connection Pooling

```ini
# /etc/pgbouncer/pgbouncer.ini
[databases]
linkflow = host=localhost port=5432 dbname=linkflow pool_size=25 max_db_connections=100

[pgbouncer]
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 25
```

**Benefits:**
- 30-50% faster queries
- Reduced I/O operations (cheaper on cloud)
- Better resource utilization

---

## 📊 Comprehensive Savings

### Before Optimization

```
Scenario: 100K executions/day, 100 days retention

Database size:
- Executions: 100K × 50 KB × 100 days = 500 GB
- Execution nodes: 100K × 5 × 10 KB × 100 days = 500 GB
- Total: 1 TB

Cost (AWS RDS):
- Storage: 1000 GB × $0.23 = $230/month
- Backups: 1000 GB × $0.20 = $200/month
- Total: $430/month
- Annual: $5,160
```

### After Full Optimization

```
Same workload with all strategies:

1. Retention Policy (Pro plan: 30 days):
   - 1 TB → 300 GB (70% reduction)

2. JSONB Compression (60%):
   - 300 GB → 120 GB

3. Offload to S3 (data > 30 days):
   - Recent (10 days): 40 GB in PostgreSQL = $9.20
   - Old (20 days): 80 GB in S3 = $1.84
   
4. Smaller embeddings (if using RAG):
   - Vector data: 50 GB → 17 GB (67% reduction)

Total storage:
- PostgreSQL: 40 GB = $9.20
- S3: 80 GB = $1.84
- Total: $11.04/month
- Annual: $132.48

SAVINGS: $430 - $11.04 = $418.96/month (97.4%)
Annual savings: $5,027.52
```

---

## 🎯 Implementation Roadmap

### Week 1: Quick Wins
1. ✅ Implement retention policies
2. ✅ Set up cleanup job
3. ✅ Drop unused indexes

**Expected savings:** 60-70%

### Week 2: Compression
1. ✅ Add compression to Execution model
2. ✅ Migrate existing data
3. ✅ Monitor compression ratios

**Expected savings:** Additional 15-20%

### Week 3: S3 Migration
1. ✅ Set up S3 bucket
2. ✅ Implement hybrid storage
3. ✅ Migrate old data

**Expected savings:** Additional 10-15%

**Total savings after 3 weeks:** 85-95%

---

## 📈 Monitoring

### Storage Dashboard

```sql
-- Database size by table
SELECT 
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size,
    pg_total_relation_size(schemaname||'.'||tablename) AS size_bytes
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC
LIMIT 10;

-- Growth rate
SELECT 
    DATE(created_at) as date,
    COUNT(*) as executions,
    pg_size_pretty(SUM(LENGTH(output_data::text))) as data_size
FROM executions
WHERE created_at > NOW() - INTERVAL '30 days'
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Compression effectiveness
SELECT 
    COUNT(*) FILTER (WHERE output_data_compressed = true) as compressed,
    COUNT(*) FILTER (WHERE output_data_compressed = false) as uncompressed,
    ROUND(COUNT(*) FILTER (WHERE output_data_compressed = true) * 100.0 / COUNT(*), 2) as compression_rate
FROM executions;
```

---

**With these optimizations, you can reduce database costs by 85-97%!** 🎉

*Last Updated: December 2024*
