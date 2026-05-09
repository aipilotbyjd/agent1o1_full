# S3 Log Archiving System - Implementation Complete

## Overview

The S3 log archiving system automatically moves old execution logs from PostgreSQL to S3-compatible storage based on workspace plan retention limits, reducing database costs by 60-80%.

## Components Implemented

### 1. Database
- ✅ Migration: `archived_execution_logs` table
- ✅ Model: `ArchivedExecutionLog`
- ✅ Relationship added to `Workspace` model

### 2. Core Service
- ✅ `ExecutionArchiveService` - Handles archival, restoration, and stats
- ✅ Compression with gzip (level 9)
- ✅ S3 upload with metadata
- ✅ Database cleanup after archival

### 3. Automation
- ✅ Command: `php artisan executions:archive`
- ✅ Scheduled daily at 2:30 AM
- ✅ Dry-run mode for testing
- ✅ Batch processing support

### 4. API Endpoints
- ✅ `GET /api/v1/workspaces/{workspace}/executions/archived` - List archived executions
- ✅ `GET /api/v1/workspaces/{workspace}/executions/archived/stats` - Archive statistics
- ✅ `GET /api/v1/workspaces/{workspace}/executions/archived/{execution}` - Get archived execution
- ✅ `GET /api/v1/workspaces/{workspace}/executions/archived/{execution}/download` - Download as JSON
- ✅ `POST /api/v1/workspaces/{workspace}/executions/archived/{execution}/restore` - Restore to database

### 5. Configuration
- ✅ Filesystem disk: `archive` (S3-compatible)
- ✅ Environment variables for S3/MinIO
- ✅ Docker Compose with MinIO service

### 6. Testing
- ✅ Feature tests for service
- ✅ Feature tests for command
- ✅ Test data seeder

## Quick Start

### 1. Start MinIO (Local Development)

```bash
docker-compose up -d minio
```

MinIO UI: http://localhost:9001
- Username: minioadmin
- Password: minioadmin

### 2. Create S3 Bucket

Via MinIO UI or CLI:
```bash
# Using mc (MinIO Client)
mc alias set local http://localhost:9000 minioadmin minioadmin
mc mb local/linkflow-execution-archives
```

### 3. Run Migration

```bash
php artisan migrate
```

### 4. Seed Test Data (Optional)

```bash
php artisan db:seed --class=ArchiveTestExecutionsSeeder
```

This creates:
- 10 executions 5 days old
- 15 executions 10 days old
- 20 executions 35 days old
- 15 executions 100 days old

### 5. Test Archival

```bash
# Dry run (preview without archiving)
php artisan executions:archive --dry-run

# Archive for specific workspace
php artisan executions:archive --workspace={workspace-id}

# Archive all workspaces
php artisan executions:archive

# Limit batch size
php artisan executions:archive --batch-size=100
```

## API Usage

### List Archived Executions

```bash
curl -X GET "http://localhost/api/v1/workspaces/{workspace}/executions/archived" \
  -H "Authorization: Bearer {token}"
```

**Filters:**
- `workflow_id` - Filter by workflow
- `status` - Filter by status (completed, failed, cancelled)
- `mode` - Filter by mode (webhook, manual, schedule)
- `search` - Search workflow names
- `archived_after` - Filter by date
- `archived_before` - Filter by date

### Get Archive Statistics

```bash
curl -X GET "http://localhost/api/v1/workspaces/{workspace}/executions/archived/stats" \
  -H "Authorization: Bearer {token}"
```

**Response:**
```json
{
  "total_archived": 150,
  "total_size_bytes": 52428800,
  "total_compressed_bytes": 7864320,
  "space_saved_bytes": 44564480,
  "space_saved_percentage": 85.0,
  "oldest_archive": "2025-01-15T00:00:00Z",
  "newest_archive": "2025-04-05T02:30:00Z",
  "avg_compression_ratio": 0.15
}
```

### Retrieve Archived Execution

```bash
# Metadata only
curl -X GET "http://localhost/api/v1/workspaces/{workspace}/executions/archived/{execution}" \
  -H "Authorization: Bearer {token}"

# Include full data (downloads from S3)
curl -X GET "http://localhost/api/v1/workspaces/{workspace}/executions/archived/{execution}?include_data=true" \
  -H "Authorization: Bearer {token}"
```

### Download Archived Execution

```bash
curl -X GET "http://localhost/api/v1/workspaces/{workspace}/executions/archived/{execution}/download" \
  -H "Authorization: Bearer {token}" \
  -o execution.json
```

### Restore to Database (Temporary)

```bash
curl -X POST "http://localhost/api/v1/workspaces/{workspace}/executions/archived/{execution}/restore" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"ttl_hours": 24}'
```

## Plan-Based Retention

Retention is automatically determined by workspace plan:

| Plan | Retention Days |
|------|----------------|
| Free | 3 days |
| Starter | 7 days |
| Pro | 30 days |
| Teams | 90 days |
| Enterprise | 365 days |

The archival command respects these limits automatically via `PlanEnforcementService::getLogRetentionDays()`.

## Production Setup

### AWS S3

Update `.env`:
```bash
AWS_ARCHIVE_BUCKET=your-production-bucket
AWS_ARCHIVE_REGION=us-east-1
AWS_ARCHIVE_ACCESS_KEY_ID=your-access-key
AWS_ARCHIVE_SECRET_ACCESS_KEY=your-secret-key
AWS_ARCHIVE_ENDPOINT=
AWS_ARCHIVE_USE_PATH_STYLE=false
```

### Cloudflare R2

```bash
AWS_ARCHIVE_BUCKET=your-r2-bucket
AWS_ARCHIVE_REGION=auto
AWS_ARCHIVE_ACCESS_KEY_ID=your-r2-access-key
AWS_ARCHIVE_SECRET_ACCESS_KEY=your-r2-secret-key
AWS_ARCHIVE_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
AWS_ARCHIVE_USE_PATH_STYLE=false
```

### Backblaze B2

```bash
AWS_ARCHIVE_BUCKET=your-b2-bucket
AWS_ARCHIVE_REGION=us-west-001
AWS_ARCHIVE_ACCESS_KEY_ID=your-b2-key-id
AWS_ARCHIVE_SECRET_ACCESS_KEY=your-b2-application-key
AWS_ARCHIVE_ENDPOINT=https://s3.us-west-001.backblazeb2.com
AWS_ARCHIVE_USE_PATH_STYLE=false
```

## Monitoring

Key metrics to track:

1. **Archival Success Rate**
   - Check logs for failed archival attempts
   - Set up alerts for repeated failures

2. **S3 Storage Growth**
   - Monitor total bucket size
   - Set up billing alerts

3. **Compression Ratio**
   - Average should be ~0.15 (85% reduction)
   - Lower ratios may indicate issues

4. **Database Size Reduction**
   - Monitor PostgreSQL storage before/after archival
   - Should see 60-80% reduction over time

## Troubleshooting

### S3 Connection Failed

```bash
# Test S3 connection
php artisan tinker
>>> Storage::disk('archive')->exists('test.txt')
```

### Archival Command Not Running

```bash
# Check scheduler
php artisan schedule:list

# Run manually
php artisan executions:archive --dry-run
```

### Archive Not Found

```bash
# Verify archive record exists
php artisan tinker
>>> \App\Models\ArchivedExecutionLog::where('execution_id', 'xxx')->first()

# Check if S3 file exists
>>> $archive = \App\Models\ArchivedExecutionLog::first()
>>> Storage::disk('archive')->exists($archive->s3_key)
```

## Running Tests

```bash
# All archive tests
php artisan test --filter=Archive

# Service tests only
php artisan test tests/Feature/ExecutionArchiveServiceTest.php

# Command tests only
php artisan test tests/Feature/ArchiveOldExecutionLogsCommandTest.php
```

## Cost Savings Example

**Before Archival:**
- 1 workspace, 100,000 executions/year
- Avg 500 KB per execution (with nodes + logs)
- Total: 50 GB in PostgreSQL
- Cost: 50 GB × $0.10/GB = **$5/month** (and growing)

**After Archival (30-day retention):**
- Hot storage: ~8,200 executions × 500 KB = 4.1 GB in PostgreSQL
- Cold storage: ~91,800 executions × 75 KB (compressed) = 6.9 GB in S3
- PostgreSQL cost: 4.1 GB × $0.10 = $0.41/month
- S3 cost: 6.9 GB × $0.0125 = $0.09/month
- **Total: $0.50/month (90% savings)**

## File Structure

```
/app
├── app/
│   ├── Console/Commands/
│   │   └── ArchiveOldExecutionLogs.php
│   ├── Http/Controllers/Api/V1/
│   │   └── ArchivedExecutionController.php
│   ├── Models/
│   │   └── ArchivedExecutionLog.php
│   └── Services/
│       └── ExecutionArchiveService.php
├── config/
│   └── filesystems.php (archive disk)
├── database/
│   ├── migrations/
│   │   └── 2026_04_05_100000_create_archived_execution_logs_table.php
│   └── seeders/
│       └── ArchiveTestExecutionsSeeder.php
├── routes/
│   ├── api.php (archived execution routes)
│   └── console.php (scheduled command)
├── tests/Feature/
│   ├── ArchiveOldExecutionLogsCommandTest.php
│   └── ExecutionArchiveServiceTest.php
└── docker-compose.yml (MinIO service)
```

## Next Steps

1. **Production Deployment:**
   - Set up AWS S3 bucket with lifecycle policies
   - Configure IAM roles for access
   - Update `.env` with production credentials

2. **Monitoring:**
   - Set up CloudWatch/Datadog metrics
   - Configure alerts for failed archival
   - Track storage costs

3. **Frontend Integration:**
   - Add "Archived" tab in execution list
   - Show archive status badge
   - Add restore button for archived executions

4. **Advanced Features:**
   - Implement partial archival (logs only)
   - Add Elasticsearch for searchable archives
   - Multi-region replication
   - Intelligent tiering based on access patterns

## Support

For issues or questions:
1. Check logs: `tail -f storage/logs/laravel.log`
2. Run diagnostic: `php artisan executions:archive --dry-run --workspace={id}`
3. Review S3 bucket permissions
4. Verify environment variables are set correctly

---

**Status:** ✅ Implementation Complete
**Last Updated:** April 2026
