<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestScenarioRelevanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $scenario = $this->route('testScenario');

        return [
            'test_context_ids' => ['present', 'array'],
            'test_context_ids.*' => ['uuid', Rule::exists('test_contexts', 'id')->where('test_matrix_id', $scenario->test_matrix_id)],
        ];
    }
}
