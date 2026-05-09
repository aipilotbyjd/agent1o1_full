<?php

namespace App\Http\Requests\Api\V1\Folder;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveWorkflowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permissions = $this->user()->workspacePermissions ?? [];

        return in_array(Permission::FolderUpdate->value, $permissions, true)
            && in_array(Permission::WorkflowUpdate->value, $permissions, true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workspaceId = $this->route('workspace')?->id;

        return [
            'workflow_ids' => ['required', 'array', 'min:1'],
            'workflow_ids.*' => [
                'uuid',
                Rule::exists('workflows', 'id')->where(fn ($q) => $q->where('workspace_id', $workspaceId)),
            ],
            'folder_id' => [
                'nullable',
                'uuid',
                Rule::exists('folders', 'id')->where(fn ($q) => $q->where('workspace_id', $workspaceId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'workflow_ids.required' => 'At least one workflow is required.',
            'folder_id.exists' => 'The target folder does not exist in this workspace.',
        ];
    }
}
