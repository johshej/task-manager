<?php

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\HistoryAction;
use App\Enums\TaskStatus;
use Database\Factories\TaskHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'changed_by_user_id', 'changed_by_token_id', 'actor_type', 'action', 'old_values', 'new_values'])]
class TaskHistory extends Model
{
    /** @use HasFactory<TaskHistoryFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'action' => HistoryAction::class,
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
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
        return match($this->action) {
            HistoryAction::Created => 'Task created',
            HistoryAction::Deleted => 'Task deleted',
            HistoryAction::StatusChanged => sprintf(
                'Status: %s → %s',
                TaskStatus::tryFrom($this->old_values['status'] ?? '')?->label() ?? '?',
                TaskStatus::tryFrom($this->new_values['status'] ?? '')?->label() ?? '?',
            ),
            HistoryAction::PriorityChanged => sprintf(
                'Priority: %d → %d',
                $this->old_values['priority'] ?? 0,
                $this->new_values['priority'] ?? 0,
            ),
            HistoryAction::Assigned => 'Assignee updated',
            HistoryAction::Updated => 'Task updated',
        };
    }
}
