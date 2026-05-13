<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTriggerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // User must own the trigger's workflow
        return $this->route('trigger')->workflow->workspace_id === auth()->user()->current_workspace_id;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'field_values' => 'array',
            'field_values.*' => 'string|nullable',
        ];
    }
}
