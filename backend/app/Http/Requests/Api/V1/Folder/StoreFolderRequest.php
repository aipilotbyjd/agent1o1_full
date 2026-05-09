<?php

namespace App\Http\Requests\Api\V1\Folder;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permissions = $this->user()->workspacePermissions ?? [];

        return in_array(Permission::FolderCreate->value, $permissions, true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workspaceId = $this->route('workspace')?->id;
        $parentId = $this->input('parent_id');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('folders')->where(fn ($q) => $q
                    ->where('workspace_id', $workspaceId)
                    ->where('parent_id', $parentId)),
            ],
            'color' => ['nullable', 'string', 'max:20'],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('folders', 'id')->where(fn ($q) => $q->where('workspace_id', $workspaceId)),
            ],
            'position' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A folder name is required.',
            'name.unique' => 'A folder with this name already exists in the same location.',
            'parent_id.exists' => 'The parent folder does not exist in this workspace.',
        ];
    }
}
