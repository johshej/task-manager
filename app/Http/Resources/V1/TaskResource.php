<?php

namespace App\Http\Resources\V1;

use App\Enums\ActorType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lastHistory = $this->latestHistory;

        return [
            'id' => $this->id,
            'feature_id' => $this->feature_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => $this->assigned_to,
            'order_index' => $this->order_index,
            'last_change' => $lastHistory ? [
                'actor_type' => $lastHistory->actor_type,
                'label' => $lastHistory->actor_type === ActorType::Ai ? 'AI' : 'User',
            ] : null,
            'last_change_at' => $lastHistory?->created_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
