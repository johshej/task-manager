<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\FeatureStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'epic_id' => ['required', 'uuid', 'exists:epics,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(FeatureStatus::class)],
            'order_index' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
