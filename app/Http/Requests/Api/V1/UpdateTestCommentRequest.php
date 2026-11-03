<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $comment = $this->route('testComment');

        return [
            'test_context_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'test_context_ids.*' => ['uuid', Rule::exists('test_contexts', 'id')->where('test_matrix_id', $comment->test_matrix_id)],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', 'required', 'integer', 'min:0'],
        ];
    }
}
