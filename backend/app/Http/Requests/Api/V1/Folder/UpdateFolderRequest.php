<?php

namespace App\Http\Requests\Api\V1\Folder;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permissions = $this->user()->workspacePermissions ?? [];

        return in_array(Permission::FolderUpdate->value, $permissions, true);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $workspaceId = $this->route('workspace')?->id;
        $folderId = $this->route('folder')?->id;
        $parentId = $this->has('parent_id') ? $this->input('parent_id') : $this->route('folder')?->parent_id;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('folders')->where(fn ($q) => $q
                    ->where('workspace_id', $workspaceId)
                    ->where('parent_id', $parentId))->ignore($folderId),
            ],
            'color' => ['nullable', 'string', 'max:20'],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('folders', 'id')->where(fn ($q) => $q->where('workspace_id', $workspaceId)),
                function (string $attribute, mixed $value, \Closure $fail) use ($folderId) {
                    if ($value === $folderId) {
                        $fail('A folder cannot be its own parent.');
                    }
                },
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
            'name.unique' => 'A folder with this name already exists in the same location.',
            'parent_id.exists' => 'The parent folder does not exist in this workspace.',
        ];
    }
}
