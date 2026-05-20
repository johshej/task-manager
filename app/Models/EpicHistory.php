<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\EpicStatus;
use App\Enums\HistoryAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['epic_id', 'changed_by_user_id', 'changed_by_token_id', 'actor_type', 'actor_name', 'action', 'old_values', 'new_values', 'metadata', 'body'])]
class EpicHistory extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'action' => HistoryAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Epic, $this> */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    /** @return BelongsTo<User, $this> */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /** @return BelongsTo<ApiToken, $this> */
    public function changedByToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'changed_by_token_id');
    }

    public function summary(): string
    {
        return match ($this->action) {
            HistoryAction::Created => 'Epic created',
            HistoryAction::Deleted => 'Epic deleted',
            HistoryAction::StatusChanged => sprintf(
                'Status: %s → %s',
                EpicStatus::tryFrom($this->old_values['status'] ?? '')?->label() ?? '?',
                EpicStatus::tryFrom($this->new_values['status'] ?? '')?->label() ?? '?',
            ),
            HistoryAction::Updated => 'Epic updated',
            HistoryAction::Note => $this->body ?? $this->metadata['message'] ?? 'Note',
            default => $this->action->label(),
        };
    }
}
