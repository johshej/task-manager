<?php

namespace App\Models;

use Database\Factories\EpicTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'repository_url', 'tdd', 'ai_mode', 'environment'])]
class EpicTemplate extends Model
{
    /** @use HasFactory<EpicTemplateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'tdd' => 'boolean',
        ];
    }

    /** @return HasMany<EpicTemplateFeature, $this> */
    public function epicTemplateFeatures(): HasMany
    {
        return $this->hasMany(EpicTemplateFeature::class)->orderBy('order_index');
    }
}
