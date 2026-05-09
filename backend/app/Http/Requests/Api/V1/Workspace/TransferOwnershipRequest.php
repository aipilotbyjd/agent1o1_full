<?php

namespace App\Http\Requests\Api\V1\Workspace;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');
        $user = $this->user();

        if ($workspace->owner_id !== $user->id) {
            throw new AuthorizationException('Only the current workspace owner can transfer ownership.');
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'A target user must be specified.',
            'user_id.exists' => 'The specified user does not exist.',
        ];
    }
}
