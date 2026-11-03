<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\TestResultStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTestResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(TestResultStatus::class)],
            'relevant' => ['sometimes', 'required', 'boolean'],
            'expectation' => ['nullable', 'string', 'max:255'],
        ];
    }
}
