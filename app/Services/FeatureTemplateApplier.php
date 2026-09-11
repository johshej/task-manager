<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Feature;
use App\Models\FeatureTemplate;

class FeatureTemplateApplier
{
    public function apply(FeatureTemplate $template, Feature $feature): void
    {
        foreach ($template->tasks()->orderBy('order_index')->get() as $index => $templateTask) {
            $feature->tasks()->create([
                'title' => $templateTask->title,
                'description' => $templateTask->description,
                'status' => TaskStatus::Todo,
                'priority' => $templateTask->priority,
                'tdd' => $templateTask->tdd,
                'ai_mode' => $templateTask->ai_mode,
                'environment' => $templateTask->environment,
                'order_index' => $index,
            ]);
        }
    }
}
