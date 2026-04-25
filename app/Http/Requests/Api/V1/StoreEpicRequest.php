<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\EpicStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEpicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'repository_url' => ['nullable', 'regex:/^(https?:\/\/\S+|git@[^:]+:\S+)$/', 'max:500'],
            'status' => ['nullable', Rule::enum(EpicStatus::class)],
            'tdd' => ['nullable', 'boolean'],
            'ai_mode' => ['nullable', 'string'],
            'environment' => ['nullable', 'string', 'max:100'],
        ];
    }
}
