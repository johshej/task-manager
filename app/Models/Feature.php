<?php

namespace App\Models;

use App\Enums\FeatureStatus;
use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['epic_id', 'name', 'description', 'status', 'order_index', 'tdd', 'ai_mode', 'environment'])]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => FeatureStatus::class,
            'order_index' => 'integer',
            'tdd' => 'boolean',
        ];
    }

    /** @return BelongsTo<Epic, $this> */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function resolvedTdd(): ?bool
    {
        return $this->tdd ?? $this->epic?->tdd;
    }

    public function resolvedAiMode(): ?string
    {
        return $this->ai_mode ?? $this->epic?->ai_mode;
    }

    public function resolvedEnvironment(): ?string
    {
        return $this->environment ?? $this->epic?->environment;
    }
}
