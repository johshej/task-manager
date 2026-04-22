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
#[Fillable(['feature_id', 'title', 'description', 'status', 'priority', 'assigned_to', 'order_index'])]
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
}
