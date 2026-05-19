<?php

namespace App\Http\Resources\V1;

use App\Enums\ActorType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpicHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'epic_id' => $this->epic_id,
            'actor_type' => $this->actor_type,
            'actor_label' => $this->actor_type === ActorType::Ai ? 'AI' : 'User',
            'actor_name' => $this->actor_name,
            'action' => $this->action,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
