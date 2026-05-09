<?php

use App\Models\Execution;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('archive');
});

test('archive command archives old executions based on plan retention', function () {
    // Create workspace with Free plan (3 days retention)
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $plan = Plan::factory()->create([
        'slug' => 'free',
        'limits' => [
            'execution_log_retention_days' => 3,
        ],
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create old execution (5 days old - should be archived)
    $oldExecution = Execution::factory()->create([
        'workspace_id' => $workspace->id,
        'workflow_id' => $workflow->id,
        'created_at' => now()->subDays(5),
        'finished_at' => now()->subDays(5),
        'status' => 'completed',
    ]);

    // Create recent execution (1 day old - should NOT be archived)
    $recentExecution = Execution::factory()->create([
        'workspace_id' => $workspace->id,
        'workflow_id' => $workflow->id,
        'created_at' => now()->subDays(1),
        'finished_at' => now()->subDays(1),
        'status' => 'completed',
    ]);

    // Run archive command
    Artisan::call('executions:archive', ['--workspace' => $workspace->id]);

    // Assert old execution was archived
    expect(Execution::find($oldExecution->id))->toBeNull();
    expect(\App\Models\ArchivedExecutionLog::where('execution_id', $oldExecution->id)->exists())->toBeTrue();

    // Assert recent execution was NOT archived
    expect(Execution::find($recentExecution->id))->not->toBeNull();
});

test('archive command in dry-run mode does not archive', function () {
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $plan = Plan::factory()->create([
        'limits' => ['execution_log_retention_days' => 3],
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $execution = Execution::factory()->create([
        'workspace_id' => $workspace->id,
        'workflow_id' => $workflow->id,
        'created_at' => now()->subDays(5),
        'finished_at' => now()->subDays(5),
        'status' => 'completed',
    ]);

    // Run in dry-run mode
    Artisan::call('executions:archive', [
        '--workspace' => $workspace->id,
        '--dry-run' => true,
    ]);

    // Assert execution still exists
    expect(Execution::find($execution->id))->not->toBeNull();
    expect(\App\Models\ArchivedExecutionLog::count())->toBe(0);
});

test('archive command respects batch size', function () {
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $plan = Plan::factory()->create([
        'limits' => ['execution_log_retention_days' => 3],
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create 10 old executions
    for ($i = 0; $i < 10; $i++) {
        Execution::factory()->create([
            'workspace_id' => $workspace->id,
            'workflow_id' => $workflow->id,
            'created_at' => now()->subDays(5),
            'finished_at' => now()->subDays(5),
            'status' => 'completed',
        ]);
    }

    // Archive with batch size of 5
    Artisan::call('executions:archive', [
        '--workspace' => $workspace->id,
        '--batch-size' => 5,
    ]);

    // Should only archive 5
    expect(Execution::count())->toBe(5);
    expect(\App\Models\ArchivedExecutionLog::count())->toBe(5);
});

test('archive command only archives finished executions', function () {
    $workspace = Workspace::factory()->create();
    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);

    $plan = Plan::factory()->create([
        'limits' => ['execution_log_retention_days' => 3],
    ]);

    Subscription::factory()->create([
        'workspace_id' => $workspace->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    // Create old running execution (should NOT be archived)
    $runningExecution = Execution::factory()->create([
        'workspace_id' => $workspace->id,
        'workflow_id' => $workflow->id,
        'created_at' => now()->subDays(5),
        'finished_at' => null,
        'status' => 'running',
    ]);

    // Create old completed execution (should be archived)
    $completedExecution = Execution::factory()->create([
        'workspace_id' => $workspace->id,
        'workflow_id' => $workflow->id,
        'created_at' => now()->subDays(5),
        'finished_at' => now()->subDays(5),
        'status' => 'completed',
    ]);

    Artisan::call('executions:archive', ['--workspace' => $workspace->id]);

    // Running execution should still exist
    expect(Execution::find($runningExecution->id))->not->toBeNull();

    // Completed execution should be archived
    expect(Execution::find($completedExecution->id))->toBeNull();
});
