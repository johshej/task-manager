<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaudeSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'epic_id',
        'feature_id',
        'task_id',
        'daemon_url',
        'project_path',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Epic, $this> */
    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    /** @return BelongsTo<Feature, $this> */
    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isAlive(): bool
    {
        return $this->last_seen_at?->gt(now()->subMinutes(15)) ?? false;
    }
}
