<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // User must own the workflow to create a trigger
        return $this->route('workflow')->workspace_id === auth()->user()->current_workspace_id;
    }

    public function rules(): array
    {
        return [
            'trigger_type_id' => 'required|exists:trigger_types,id',
            'credential_id' => 'nullable|exists:credentials,id',
            'name' => 'nullable|string|max:255',
            'field_values' => 'array',
            'field_values.*' => 'string|nullable',
        ];
    }
}
