<?php

namespace App\Services;

use App\Models\Folder;
use App\Models\Workspace;

class FolderService
{
    /**
     * Create a new folder in the workspace.
     *
     * @param  array{name: string, color?: string, parent_id?: string|null, position?: int}  $data
     */
    public function create(Workspace $workspace, array $data): Folder
    {
        return $workspace->folders()->create($data);
    }

    /**
     * Update an existing folder.
     *
     * @param  array{name?: string, color?: string, parent_id?: string|null, position?: int}  $data
     */
    public function update(Folder $folder, array $data): Folder
    {
        $folder->update($data);

        return $folder;
    }

    /**
     * Delete a folder. Workflows inside are unlinked (folder_id set to null via DB constraint).
     */
    public function delete(Folder $folder): void
    {
        $folder->delete();
    }

    /**
     * Move workflows into a folder (or to root if folder is null).
     *
     * @param  array<string>  $workflowIds
     */
    public function moveWorkflows(Workspace $workspace, ?string $folderId, array $workflowIds): int
    {
        return $workspace->workflows()
            ->whereIn('id', $workflowIds)
            ->update(['folder_id' => $folderId]);
    }
}
