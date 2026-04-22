<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTokenRequest extends FormRequest
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
            'is_ai' => ['boolean'],
            'version' => ['nullable', 'string', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => ['string', 'max:255'],
        ];
    }
}
