<?php

use App\Models\Execution;
use App\Models\ExecutionNode;
use App\Models\User;
use App\Models\Variable;
use App\Models\Workflow;
use App\Models\Workspace;

test('secret variable values are masked in execution node output when displayed to users', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => 'owner']);

    // Create a secret variable
    $secret = Variable::factory()->create([
        'workspace_id' => $workspace->id,
        'key' => 'API_KEY',
        'value' => 'sk_live_super_secret_key_12345',
        'is_secret' => true,
    ]);

    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $execution = Execution::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $workspace->id,
    ]);

    // Create an execution node with output containing the secret
    $node = ExecutionNode::factory()->create([
        'execution_id' => $execution->id,
        'output_data' => [
            'response' => [
                'authorization' => 'Bearer sk_live_super_secret_key_12345',
                'status' => 'success',
            ],
        ],
    ]);

    // Fetch via API
    $response = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/executions/{$execution->id}/nodes");

    $response->assertSuccessful();

    // The secret should be masked in the response
    $nodeData = collect($response->json('data'))->firstWhere('id', $node->id);
    $output = $nodeData['output_data'];
    expect($output['response']['authorization'])->toContain('***REDACTED***')
        ->and($output['response']['authorization'])->not->toContain('sk_live_super_secret_key_12345');
});

test('secret variable values are stored unmasked in database for admin debugging', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

    $secret = Variable::factory()->create([
        'workspace_id' => $workspace->id,
        'key' => 'API_KEY',
        'value' => 'sk_live_super_secret_key_12345',
        'is_secret' => true,
    ]);

    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $execution = Execution::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $workspace->id,
    ]);

    $node = ExecutionNode::factory()->create([
        'execution_id' => $execution->id,
        'output_data' => [
            'api_key' => 'sk_live_super_secret_key_12345',
        ],
    ]);

    // Direct database access should show the real value
    $nodeFromDb = ExecutionNode::find($node->id);
    expect($nodeFromDb->output_data['api_key'])->toBe('sk_live_super_secret_key_12345');
});

test('non-secret values are not masked', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => 'owner']);

    $workflow = Workflow::factory()->create(['workspace_id' => $workspace->id]);
    $execution = Execution::factory()->create([
        'workflow_id' => $workflow->id,
        'workspace_id' => $workspace->id,
    ]);

    $node = ExecutionNode::factory()->create([
        'execution_id' => $execution->id,
        'output_data' => [
            'public_data' => 'this is public information',
            'user_id' => 12345,
        ],
    ]);

    $response = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/executions/{$execution->id}/nodes");

    $response->assertSuccessful();

    $nodeData = collect($response->json('data'))->firstWhere('id', $node->id);
    $output = $nodeData['output_data'];
    expect($output['public_data'])->toBe('this is public information')
        ->and($output['user_id'])->toBe(12345);
});

test('masking cache is cleared when secret variable is updated', function () {
    $workspace = Workspace::factory()->create();
    $secret = Variable::factory()->create([
        'workspace_id' => $workspace->id,
        'key' => 'API_KEY',
        'value' => 'old_secret',
        'is_secret' => true,
    ]);

    // Cache should be populated
    $service = app(\App\Services\ExecutionLogMaskingService::class);
    $masked = $service->maskData('old_secret', $workspace->id);
    expect($masked)->toBe('***REDACTED***');

    // Update the secret
    $secret->update(['value' => 'new_secret']);

    // New secret should be masked (cache was cleared)
    $masked = $service->maskData('new_secret', $workspace->id);
    expect($masked)->toBe('***REDACTED***');
});
