<?php

use App\Models\ArchivedExecutionLog;
use App\Models\Execution;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Services\ExecutionArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('archive');

    $this->workspace = Workspace::factory()->create();
    $this->user = User::factory()->create();
    $this->workflow = Workflow::factory()->create(['workspace_id' => $this->workspace->id]);
});

test('can archive an execution to S3', function () {
    $execution = Execution::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'status' => 'completed',
        'finished_at' => now()->subDays(5),
    ]);

    $service = app(ExecutionArchiveService::class);
    $archive = $service->archiveExecution($execution);

    // Assert archive record was created
    expect($archive)->toBeInstanceOf(ArchivedExecutionLog::class);
    expect($archive->execution_id)->toBe($execution->id);
    expect($archive->workspace_id)->toBe($this->workspace->id);

    // Assert S3 file was created
    Storage::disk('archive')->assertExists($archive->s3_key);

    // Assert original execution was deleted
    expect(Execution::find($execution->id))->toBeNull();
});

test('archived data can be restored', function () {
    $execution = Execution::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'status' => 'completed',
        'finished_at' => now()->subDays(5),
    ]);

    $originalId = $execution->id;

    $service = app(ExecutionArchiveService::class);
    $archive = $service->archiveExecution($execution);

    // Restore from S3
    $data = $service->restoreExecution($archive);

    expect($data)->toHaveKeys(['execution', 'nodes', 'logs', 'metadata']);
    expect($data['execution']['id'])->toBe($originalId);
    expect($data['metadata']['archived_by'])->toBe('ExecutionArchiveService');
});

test('archived execution can be restored to database', function () {
    $execution = Execution::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'status' => 'completed',
        'finished_at' => now()->subDays(5),
    ]);

    $originalId = $execution->id;

    $service = app(ExecutionArchiveService::class);
    $archive = $service->archiveExecution($execution);

    // Restore to database
    $restoredExecution = $service->restoreToDatabase($archive, 24);

    expect($restoredExecution->id)->toBe($originalId);
    expect($archive->fresh()->is_restored)->toBeTrue();
    expect($archive->fresh()->restore_expires_at)->not->toBeNull();
});

test('archive includes compression metadata', function () {
    $execution = Execution::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'status' => 'completed',
        'finished_at' => now()->subDays(5),
    ]);

    $service = app(ExecutionArchiveService::class);
    $archive = $service->archiveExecution($execution);

    expect($archive->file_size_bytes)->toBeGreaterThan(0);
    expect($archive->compressed_size_bytes)->toBeGreaterThan(0);
    expect($archive->compressed_size_bytes)->toBeLessThan($archive->file_size_bytes);
    expect($archive->compression_ratio)->toBeGreaterThan(0);
    expect($archive->compression_ratio)->toBeLessThan(1);
});

test('archive service generates correct s3 key path', function () {
    $execution = Execution::factory()->create([
        'workspace_id' => $this->workspace->id,
        'workflow_id' => $this->workflow->id,
        'created_at' => now()->setDate(2025, 3, 15),
        'finished_at' => now(),
    ]);

    $service = app(ExecutionArchiveService::class);
    $archive = $service->archiveExecution($execution);

    $expectedKey = "{$this->workspace->id}/2025/03/execution-{$execution->id}.json.gz";
    expect($archive->s3_key)->toBe($expectedKey);
});

test('can get workspace archive stats', function () {
    // Create and archive multiple executions
    for ($i = 0; $i < 5; $i++) {
        $execution = Execution::factory()->create([
            'workspace_id' => $this->workspace->id,
            'workflow_id' => $this->workflow->id,
            'finished_at' => now()->subDays($i + 5),
        ]);

        app(ExecutionArchiveService::class)->archiveExecution($execution);
    }

    $service = app(ExecutionArchiveService::class);
    $stats = $service->getWorkspaceStats($this->workspace->id);

    expect($stats['total_archived'])->toBe(5);
    expect($stats['total_size_bytes'])->toBeGreaterThan(0);
    expect($stats['total_compressed_bytes'])->toBeGreaterThan(0);
    expect($stats['oldest_archive'])->not->toBeNull();
    expect($stats['newest_archive'])->not->toBeNull();
});
