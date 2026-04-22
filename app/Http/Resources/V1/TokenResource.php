<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    protected ?string $plainTextToken = null;

    public function withPlainToken(string $token): static
    {
        $this->plainTextToken = $token;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'is_ai' => $this->is_ai,
            'version' => $this->version,
            'abilities' => $this->abilities,
            'created_by_user_id' => $this->created_by_user_id,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
        ];

        if ($this->plainTextToken !== null) {
            $data['plain_text_token'] = $this->plainTextToken;
        }

        return $data;
    }
}
