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

    public function linkFeatureTemplate(string $featureTemplateId): ?EpicTemplateFeature
    {
        if ($this->epicTemplateFeatures()->where('feature_template_id', $featureTemplateId)->exists()) {
            return null;
        }

        return $this->epicTemplateFeatures()->create([
            'feature_template_id' => $featureTemplateId,
            'order_index' => $this->epicTemplateFeatures()->count(),
        ]);
    }

    /**
     * Copy means copy: link a duplicate of the feature template, so the
     * original can be edited or deleted without touching this epic template.
     */
    public function copyFeatureTemplate(string $featureTemplateId): EpicTemplateFeature
    {
        $copy = FeatureTemplate::findOrFail($featureTemplateId)->duplicate();

        return $this->epicTemplateFeatures()->create([
            'feature_template_id' => $copy->id,
            'order_index' => $this->epicTemplateFeatures()->count(),
        ]);
    }
}
