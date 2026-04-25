<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpicResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'repository_url' => $this->repository_url,
            'status' => $this->status,
            'tdd' => $this->tdd,
            'ai_mode' => $this->ai_mode,
            'environment' => $this->environment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
