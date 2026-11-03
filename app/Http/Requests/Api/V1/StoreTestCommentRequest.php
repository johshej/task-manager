<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TaskStatus;
use App\Models\TestScenario;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $scenario = TestScenario::find($this->input('test_scenario_id'));

        return [
            'test_scenario_id' => ['required', 'uuid', 'exists:test_scenarios,id'],
            'test_context_ids' => ['required', 'array', 'min:1'],
            'test_context_ids.*' => ['uuid', Rule::exists('test_contexts', 'id')->where('test_matrix_id', $scenario?->test_matrix_id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
