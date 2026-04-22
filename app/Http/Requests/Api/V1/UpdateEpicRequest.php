<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\EpicStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEpicRequest extends FormRequest
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
            'repository_url' => ['sometimes', 'nullable', 'regex:/^(https?:\/\/\S+|git@[^:]+:\S+)$/', 'max:500'],
            'status' => ['sometimes', Rule::enum(EpicStatus::class)],
        ];
    }
}
