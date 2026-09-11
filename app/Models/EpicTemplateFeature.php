<?php

namespace App\Models;

use Database\Factories\EpicTemplateFeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['epic_template_id', 'feature_template_id', 'order_index'])]
class EpicTemplateFeature extends Model
{
    /** @use HasFactory<EpicTemplateFeatureFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
        ];
    }

    /** @return BelongsTo<EpicTemplate, $this> */
    public function epicTemplate(): BelongsTo
    {
        return $this->belongsTo(EpicTemplate::class);
    }

    /** @return BelongsTo<FeatureTemplate, $this> */
    public function featureTemplate(): BelongsTo
    {
        return $this->belongsTo(FeatureTemplate::class);
    }
}
