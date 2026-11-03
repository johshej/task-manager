<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestContextRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', Rule::unique('test_contexts', 'code')->where('test_matrix_id', $matrixId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'test_scenario_ids' => ['nullable', 'array'],
            'test_scenario_ids.*' => ['uuid', Rule::exists('test_scenarios', 'id')->where('test_matrix_id', $matrixId)],
        ];
    }
}
