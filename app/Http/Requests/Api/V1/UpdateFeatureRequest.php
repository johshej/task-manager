<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\FeatureStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(FeatureStatus::class)],
            'order_index' => ['sometimes', 'integer', 'min:0'],
            'tdd' => ['nullable', 'boolean'],
            'ai_mode' => ['nullable', 'string'],
            'environment' => ['nullable', 'string', 'max:100'],
        ];
    }
}
