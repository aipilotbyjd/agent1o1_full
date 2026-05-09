<?php

use App\Enums\ExecutionMode;
use App\Enums\ExecutionStatus;
use App\Enums\Role;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\Execution;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Workspace;
use App\Services\ExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────

function makeRetryExecution(int $attempt, int $maxAttempts, int $retryDelay = 60): Execution
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, [
        'role' => Role::Owner->value,
        'joined_at' => now(),
    ]);

    $workflow = Workflow::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'is_active' => true,
    ]);

    WorkflowVersion::factory()->published()->create([
        'workflow_id' => $workflow->id,
        'created_by' => $owner->id,
    ]);

    $workflow->refresh();

    return Execution::create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $workspace->id,
        'status' => ExecutionStatus::Failed,
        'mode' => ExecutionMode::Manual,
        'triggered_by' => $owner->id,
        'attempt' => $attempt,
        'max_attempts' => $maxAttempts,
        'retry_delay_seconds' => $retryDelay,
    ]);
}

// ── autoRetry() unit-level behaviour ─────────────────────────

it('returns null and dispatches nothing when max_attempts is 1', function () {
    Queue::fake();

    $execution = makeRetryExecution(attempt: 1, maxAttempts: 1);

    $result = app(ExecutionService::class)->autoRetry($execution);

    expect($result)->toBeNull();
    Queue::assertNothingPushed();
});

it('returns null and dispatches nothing when attempt equals max_attempts', function () {
    Queue::fake();

    $execution = makeRetryExecution(attempt: 3, maxAttempts: 3);

    $result = app(ExecutionService::class)->autoRetry($execution);

    expect($result)->toBeNull();
    Queue::assertNothingPushed();
});

it('creates a child execution with mode=retry and parent_execution_id set', function () {
    Queue::fake();

    $execution = makeRetryExecution(attempt: 1, maxAttempts: 3);

    $child = app(ExecutionService::class)->autoRetry($execution);

    expect($child)->not->toBeNull()
        ->and($child->mode)->toBe(ExecutionMode::Retry)
        ->and($child->parent_execution_id)->toBe($execution->id)
        ->and($child->attempt)->toBe(2)
        ->and($child->max_attempts)->toBe(3)
        ->and($child->retry_delay_seconds)->toBe(60)
        ->and($child->status)->toBe(ExecutionStatus::Pending)
        ->and($child->trigger_data)->toBe($execution->trigger_data);
});

it('dispatches ExecuteWorkflowJob to the workflows-low queue', function () {
    Queue::fake();

    $execution = makeRetryExecution(attempt: 1, maxAttempts: 3);

    app(ExecutionService::class)->autoRetry($execution);

    Queue::assertPushedOn('workflows-low', ExecuteWorkflowJob::class);
});

it('computes exponential delay: retry_delay_seconds * 2^(attempt-1)', function () {
    Queue::fake();

    // attempt 1 failed → delay = 60 * 2^0 = 60s
    $exec1 = makeRetryExecution(attempt: 1, maxAttempts: 5, retryDelay: 60);
    app(ExecutionService::class)->autoRetry($exec1);

    Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) {
        // Laravel stores delay as a Carbon instance or int in $job->delay
        $delay = $job->delay;
        if ($delay instanceof \DateTimeInterface) {
            $delay = (int) now()->diffInSeconds($delay);
        }
        return $delay >= 59 && $delay <= 61; // ±1s tolerance for test timing
    });
});

it('doubles the delay for each successive retry attempt', function () {
    Queue::fake();

    $retryDelay = 60;

    foreach ([1 => 60, 2 => 120, 3 => 240, 4 => 480] as $attempt => $expectedDelay) {
        Queue::clearResolvedInstances();
        Queue::fake();

        $execution = makeRetryExecution(attempt: $attempt, maxAttempts: 10, retryDelay: $retryDelay);
        app(ExecutionService::class)->autoRetry($execution);

        Queue::assertPushed(ExecuteWorkflowJob::class, function ($job) use ($expectedDelay) {
            $delay = $job->delay;
            if ($delay instanceof \DateTimeInterface) {
                $delay = (int) now()->diffInSeconds($delay);
            }
            return $delay >= ($expectedDelay - 1) && $delay <= ($expectedDelay + 1);
        });
    }
});

it('preserves trigger_data from the original execution', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, [
        'role' => Role::Owner->value,
        'joined_at' => now(),
    ]);
    $workflow = Workflow::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
    ]);
    WorkflowVersion::factory()->published()->create([
        'workflow_id' => $workflow->id,
        'created_by' => $owner->id,
    ]);
    $workflow->refresh();

    $execution = Execution::create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $workspace->id,
        'status' => ExecutionStatus::Failed,
        'mode' => ExecutionMode::Manual,
        'triggered_by' => $owner->id,
        'attempt' => 1,
        'max_attempts' => 3,
        'retry_delay_seconds' => 60,
        'trigger_data' => ['source' => 'webhook', 'payload' => ['key' => 'value']],
    ]);

    $child = app(ExecutionService::class)->autoRetry($execution);

    expect($child->trigger_data)->toBe(['source' => 'webhook', 'payload' => ['key' => 'value']]);
});

// ── trigger() snapshots retry settings ───────────────────────

it('snapshots max_attempts and retry_delay_seconds from workflow version settings on trigger', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, [
        'role' => Role::Owner->value,
        'joined_at' => now(),
    ]);
    $workflow = Workflow::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'is_active' => true,
    ]);
    WorkflowVersion::factory()->published()->create([
        'workflow_id' => $workflow->id,
        'created_by' => $owner->id,
        'settings' => ['retry' => ['max_attempts' => 4, 'retry_wait' => 30]],
    ]);
    $workflow->refresh();

    $execution = app(ExecutionService::class)->trigger(
        workflow: $workflow,
        user: $owner,
    );

    expect($execution->max_attempts)->toBe(4)
        ->and($execution->retry_delay_seconds)->toBe(30);
});

it('defaults max_attempts to 1 and retry_delay_seconds to 60 when not set in workflow settings', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner->id, [
        'role' => Role::Owner->value,
        'joined_at' => now(),
    ]);
    $workflow = Workflow::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'is_active' => true,
    ]);
    WorkflowVersion::factory()->published()->create([
        'workflow_id' => $workflow->id,
        'created_by' => $owner->id,
        'settings' => [],
    ]);
    $workflow->refresh();

    $execution = app(ExecutionService::class)->trigger(
        workflow: $workflow,
        user: $owner,
    );

    expect($execution->max_attempts)->toBe(1)
        ->and($execution->retry_delay_seconds)->toBe(60);
});
