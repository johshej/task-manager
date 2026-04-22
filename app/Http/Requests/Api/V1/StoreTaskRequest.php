<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'feature_id' => ['required', 'uuid', 'exists:features,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', 'integer', 'min:0'],
            'assigned_to' => ['nullable', 'uuid', 'exists:users,id'],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
