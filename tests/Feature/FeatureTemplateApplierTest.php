<?php

use App\Models\Epic;
use App\Models\Feature;
use App\Models\FeatureTemplate;
use App\Services\FeatureTemplateApplier;

test('apply creates tasks from the template tasks in order, defaulting status to todo', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $template = FeatureTemplate::factory()->create();
    $template->tasks()->create(['title' => 'Second', 'priority' => 3, 'order_index' => 1]);
    $template->tasks()->create(['title' => 'First', 'priority' => 7, 'order_index' => 0]);

    (new FeatureTemplateApplier)->apply($template, $feature);

    expect($feature->tasks()->count())->toBe(2);

    $tasks = $feature->tasks()->orderBy('order_index')->get();
    expect($tasks[0]->title)->toBe('First')
        ->and($tasks[0]->priority)->toBe(7)
        ->and($tasks[0]->status->value)->toBe('todo')
        ->and($tasks[1]->title)->toBe('Second');
});

test('apply carries over tdd, ai_mode, and environment from the template task', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $template = FeatureTemplate::factory()->create();
    $template->tasks()->create([
        'title' => 'Configured task',
        'tdd' => true,
        'ai_mode' => 'Be careful',
        'environment' => 'Staging',
        'order_index' => 0,
    ]);

    (new FeatureTemplateApplier)->apply($template, $feature);

    $this->assertDatabaseHas('tasks', [
        'feature_id' => $feature->id,
        'title' => 'Configured task',
        'tdd' => true,
        'ai_mode' => 'Be careful',
        'environment' => 'Staging',
    ]);
});

test('apply does nothing when the template has no tasks', function () {
    $epic = Epic::factory()->create();
    $feature = Feature::factory()->for($epic)->create();
    $template = FeatureTemplate::factory()->create();

    (new FeatureTemplateApplier)->apply($template, $feature);

    expect($feature->tasks()->count())->toBe(0);
});
