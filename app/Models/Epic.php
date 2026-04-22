<?php

namespace App\Models;

use App\Enums\EpicStatus;
use Database\Factories\EpicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'repository_url', 'status'])]
class Epic extends Model
{
    /** @use HasFactory<EpicFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'status' => EpicStatus::class,
        ];
    }

    /** @return HasMany<Feature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }
}
