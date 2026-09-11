<?php

namespace App\Models;

use Database\Factories\FeatureTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'tdd', 'ai_mode', 'environment'])]
class FeatureTemplate extends Model
{
    /** @use HasFactory<FeatureTemplateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'tdd' => 'boolean',
        ];
    }

    /** @return HasMany<FeatureTemplateTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(FeatureTemplateTask::class);
    }

    /** @return HasMany<EpicTemplateFeature, $this> */
    public function epicTemplateFeatures(): HasMany
    {
        return $this->hasMany(EpicTemplateFeature::class);
    }

    /** A real copy: a new template with copies of all tasks. */
    public function duplicate(): self
    {
        $copy = $this->replicate();
        $copy->save();

        $this->tasks()->orderBy('order_index')->get()->each(function ($task) use ($copy) {
            $taskCopy = $task->replicate(['feature_template_id']);
            $taskCopy->featureTemplate()->associate($copy);
            $taskCopy->save();
        });

        return $copy;
    }
}
