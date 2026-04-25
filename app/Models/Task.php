<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Observers\TaskObserver;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[ObservedBy(TaskObserver::class)]
#[Fillable(['feature_id', 'title', 'description', 'status', 'priority', 'assigned_to', 'order_index', 'execution_order', 'tdd', 'ai_mode', 'environment'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => 'integer',
            'order_index' => 'integer',
            'execution_order' => 'integer',
            'tdd' => 'boolean',
        ];
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<TaskHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(TaskHistory::class)->latest();
    }

    /** @return HasOne<TaskHistory, $this> */
    public function latestHistory(): HasOne
    {
        return $this->hasOne(TaskHistory::class)->latestOfMany();
    }

    public function resolvedTdd(): ?bool
    {
        return $this->tdd ?? $this->feature?->tdd ?? $this->feature?->epic?->tdd;
    }

    public function resolvedAiMode(): ?string
    {
        return $this->ai_mode ?? $this->feature?->ai_mode ?? $this->feature?->epic?->ai_mode;
    }

    public function resolvedEnvironment(): ?string
    {
        return $this->environment ?? $this->feature?->environment ?? $this->feature?->epic?->environment;
    }
}
