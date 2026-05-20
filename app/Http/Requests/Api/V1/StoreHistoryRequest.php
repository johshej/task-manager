<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\HistoryAction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(HistoryAction::class)],
            'old_values' => ['nullable', 'array'],
            'new_values' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'body' => ['nullable', 'string'],
        ];
    }
}
