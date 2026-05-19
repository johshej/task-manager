<?php

namespace App\Models;

use App\Enums\EpicStatus;
use App\Observers\EpicObserver;
use Database\Factories\EpicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(EpicObserver::class)]
#[Fillable(['name', 'description', 'repository_url', 'status', 'tdd', 'ai_mode', 'environment'])]
class Epic extends Model
{
    /** @use HasFactory<EpicFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => EpicStatus::class,
            'tdd' => 'boolean',
        ];
    }

    /** @return HasMany<Feature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /** @return HasMany<EpicHistory, $this> */
    public function history(): HasMany
    {
        return $this->hasMany(EpicHistory::class)->orderByDesc('created_at');
    }
}
