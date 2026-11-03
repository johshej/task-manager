<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestScenarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $matrixId = $this->input('test_matrix_id');

        return [
            'test_matrix_id' => ['required', 'uuid', 'exists:test_matrices,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'test_context_ids' => ['nullable', 'array'],
            'test_context_ids.*' => ['uuid', Rule::exists('test_contexts', 'id')->where('test_matrix_id', $matrixId)],
        ];
    }
}
