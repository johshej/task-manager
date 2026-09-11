<?php

namespace App\Models;

use Database\Factories\FeatureTemplateTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['feature_template_id', 'title', 'description', 'priority', 'tdd', 'ai_mode', 'environment', 'order_index'])]
class FeatureTemplateTask extends Model
{
    /** @use HasFactory<FeatureTemplateTaskFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'order_index' => 'integer',
            'tdd' => 'boolean',
        ];
    }

    /** @return BelongsTo<FeatureTemplate, $this> */
    public function featureTemplate(): BelongsTo
    {
        return $this->belongsTo(FeatureTemplate::class);
    }
}
