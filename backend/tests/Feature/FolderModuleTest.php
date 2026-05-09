<?php

use App\Enums\Role;
use App\Models\Folder;
use App\Models\User;
use App\Models\Workflow;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ──────────────────────────────────────────────────

function setupFolderWorkspace(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => Role::Owner->value,
        'joined_at' => now(),
    ]);

    return [$owner, $workspace];
}

function createFolder(Workspace $workspace, array $overrides = []): Folder
{
    return Folder::factory()->create([
        'workspace_id' => $workspace->id,
        ...$overrides,
    ]);
}

// ── CRUD ─────────────────────────────────────────────────────

test('owner can create a folder', function () {
    [$owner, $workspace] = setupFolderWorkspace();

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
            'name' => 'Production',
            'color' => '#ff5733',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Production')
        ->assertJsonPath('data.color', '#ff5733');

    $this->assertDatabaseHas('folders', [
        'workspace_id' => $workspace->id,
        'name' => 'Production',
    ]);
});

test('owner can create a nested folder', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $parent = createFolder($workspace, ['name' => 'Parent']);

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);
});

test('owner can list root folders', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    createFolder($workspace, ['name' => 'Alpha']);
    createFolder($workspace, ['name' => 'Beta']);
    $parent = createFolder($workspace, ['name' => 'Parent']);
    createFolder($workspace, ['name' => 'Child', 'parent_id' => $parent->id]);

    $response = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders");

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('can list child folders by parent_id', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $parent = createFolder($workspace, ['name' => 'Parent']);
    createFolder($workspace, ['name' => 'Child A', 'parent_id' => $parent->id]);
    createFolder($workspace, ['name' => 'Child B', 'parent_id' => $parent->id]);

    $response = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders?parent_id={$parent->id}");

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('folders index includes workflows count', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $folder = createFolder($workspace);
    Workflow::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'folder_id' => $folder->id,
    ]);

    $response = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders");

    $response->assertSuccessful()
        ->assertJsonPath('data.0.workflows_count', 1);
});

test('can search folders by name', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    createFolder($workspace, ['name' => 'Production']);
    createFolder($workspace, ['name' => 'Staging']);

    $response = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders?search=Prod");

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Production');
});

test('owner can view a folder with children', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $parent = createFolder($workspace, ['name' => 'Parent']);
    createFolder($workspace, ['name' => 'Child', 'parent_id' => $parent->id]);

    $response = $this->actingAs($owner, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders/{$parent->id}");

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Parent')
        ->assertJsonCount(1, 'data.children');
});

test('owner can update a folder', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $folder = createFolder($workspace);

    $response = $this->actingAs($owner, 'api')
        ->putJson("/api/v1/workspaces/{$workspace->id}/folders/{$folder->id}", [
            'name' => 'Renamed',
            'color' => '#00ff00',
        ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Renamed')
        ->assertJsonPath('data.color', '#00ff00');
});

test('owner can delete a folder', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $folder = createFolder($workspace);

    $response = $this->actingAs($owner, 'api')
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/folders/{$folder->id}");

    $response->assertSuccessful();
    $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
});

test('deleting a folder unlinks its workflows', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $folder = createFolder($workspace);
    $workflow = Workflow::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'folder_id' => $folder->id,
    ]);

    $this->actingAs($owner, 'api')
        ->deleteJson("/api/v1/workspaces/{$workspace->id}/folders/{$folder->id}");

    $this->assertDatabaseHas('workflows', ['id' => $workflow->id, 'folder_id' => null]);
});

// ── Move Workflows ──────────────────────────────────────────

test('owner can move workflows into a folder', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $folder = createFolder($workspace);
    $w1 = Workflow::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);
    $w2 = Workflow::factory()->create(['workspace_id' => $workspace->id, 'created_by' => $owner->id]);

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders/move-workflows", [
            'folder_id' => $folder->id,
            'workflow_ids' => [$w1->id, $w2->id],
        ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('workflows', ['id' => $w1->id, 'folder_id' => $folder->id]);
    $this->assertDatabaseHas('workflows', ['id' => $w2->id, 'folder_id' => $folder->id]);
});

test('owner can move workflows to root (null folder_id)', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $folder = createFolder($workspace);
    $workflow = Workflow::factory()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $owner->id,
        'folder_id' => $folder->id,
    ]);

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders/move-workflows", [
            'folder_id' => null,
            'workflow_ids' => [$workflow->id],
        ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('workflows', ['id' => $workflow->id, 'folder_id' => null]);
});

// ── Validation ──────────────────────────────────────────────

test('duplicate name in same parent fails validation', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    createFolder($workspace, ['name' => 'Duplicate']);

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
            'name' => 'Duplicate',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('same name allowed in different parents', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $parent = createFolder($workspace, ['name' => 'Parent']);
    createFolder($workspace, ['name' => 'Shared Name']);

    $response = $this->actingAs($owner, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
            'name' => 'Shared Name',
            'parent_id' => $parent->id,
        ]);

    $response->assertCreated();
});

test('folder cannot be its own parent', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $folder = createFolder($workspace);

    $response = $this->actingAs($owner, 'api')
        ->putJson("/api/v1/workspaces/{$workspace->id}/folders/{$folder->id}", [
            'parent_id' => $folder->id,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

// ── Authorization ───────────────────────────────────────────

test('viewer cannot create a folder', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $viewer = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id, 'role' => Role::Viewer->value, 'joined_at' => now()]);

    $response = $this->actingAs($viewer, 'api')
        ->postJson("/api/v1/workspaces/{$workspace->id}/folders", [
            'name' => 'Not Allowed',
        ]);

    $response->assertForbidden();
});

test('viewer can view folders', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $viewer = User::factory()->create();
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id, 'role' => Role::Viewer->value, 'joined_at' => now()]);
    createFolder($workspace);

    $response = $this->actingAs($viewer, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders");

    $response->assertSuccessful();
});

test('non-member cannot access folders', function () {
    [$owner, $workspace] = setupFolderWorkspace();
    $stranger = User::factory()->create();

    $response = $this->actingAs($stranger, 'api')
        ->getJson("/api/v1/workspaces/{$workspace->id}/folders");

    $response->assertForbidden();
});
