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

#[Fillable(['epic_id', 'name', 'description', 'status', 'order_index'])]
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => FeatureStatus::class,
            'order_index' => 'integer',
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
}
