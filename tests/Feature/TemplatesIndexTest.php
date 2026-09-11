<?php

use App\Models\EpicTemplate;
use App\Models\FeatureTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests are redirected from templates index', function () {
    auth()->logout();

    $this->get(route('templates'))->assertRedirect(route('login'));
});

test('templates index page renders', function () {
    $this->get(route('templates'))->assertOk();
});

test('templates index lists feature templates and epic templates', function () {
    FeatureTemplate::factory()->create(['name' => 'Alpha Feature Template']);
    EpicTemplate::factory()->create(['name' => 'Alpha Epic Template']);

    Livewire::test('pages::templates.index')
        ->assertSee('Alpha Feature Template')
        ->assertSee('Alpha Epic Template');
});

test('can create a feature template', function () {
    Livewire::test('pages::templates.index')
        ->set('newFeatureTemplateName', 'My Feature Template')
        ->call('createFeatureTemplate')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('feature_templates', ['name' => 'My Feature Template']);
});

test('feature template name is required', function () {
    Livewire::test('pages::templates.index')
        ->set('newFeatureTemplateName', '')
        ->call('createFeatureTemplate')
        ->assertHasErrors(['newFeatureTemplateName' => 'required']);
});

test('can create an epic template', function () {
    Livewire::test('pages::templates.index')
        ->set('newEpicTemplateName', 'My Epic Template')
        ->call('createEpicTemplate')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epic_templates', ['name' => 'My Epic Template']);
});

test('can delete a feature template', function () {
    $template = FeatureTemplate::factory()->create();

    Livewire::test('pages::templates.index')
        ->call('confirmDeleteFeatureTemplate', $template->id)
        ->call('deleteFeatureTemplate');

    $this->assertDatabaseMissing('feature_templates', ['id' => $template->id]);
});

test('can delete an epic template', function () {
    $template = EpicTemplate::factory()->create();

    Livewire::test('pages::templates.index')
        ->call('confirmDeleteEpicTemplate', $template->id)
        ->call('deleteEpicTemplate');

    $this->assertDatabaseMissing('epic_templates', ['id' => $template->id]);
});

test('linkFeatureTemplateToEpicTemplate links a feature template to an epic template', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create();

    Livewire::test('pages::templates.index')
        ->call('linkFeatureTemplateToEpicTemplate', $epicTemplate->id, $featureTemplate->id);

    $this->assertDatabaseHas('epic_template_features', [
        'epic_template_id' => $epicTemplate->id,
        'feature_template_id' => $featureTemplate->id,
    ]);
});

test('linkFeatureTemplateToEpicTemplate does not duplicate an existing link', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create();
    $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $featureTemplate->id, 'order_index' => 0]);

    Livewire::test('pages::templates.index')
        ->call('linkFeatureTemplateToEpicTemplate', $epicTemplate->id, $featureTemplate->id);

    expect($epicTemplate->epicTemplateFeatures()->count())->toBe(1);
});

test('a feature template dropped on an epic template moves out of the unconnected list', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create(['name' => 'Moved Away']);

    $component = Livewire::test('pages::templates.index')
        ->call('linkFeatureTemplateToEpicTemplate', $epicTemplate->id, $featureTemplate->id);

    $ids = $component->instance()->featureTemplates->pluck('id')->all();
    expect($ids)->not->toContain($featureTemplate->id);
});

test('the unconnected list shows only feature templates not linked to any epic template', function () {
    $unconnected = FeatureTemplate::factory()->create(['name' => 'Free']);
    $linked = FeatureTemplate::factory()->create(['name' => 'Taken']);
    EpicTemplate::factory()->create()->epicTemplateFeatures()->create(['feature_template_id' => $linked->id, 'order_index' => 0]);

    $ids = Livewire::test('pages::templates.index')->instance()->featureTemplates->pluck('id')->all();

    expect($ids)->toContain($unconnected->id);
    expect($ids)->not->toContain($linked->id);
});

test('pasting a copied feature template into the unlinked area creates an unconnected duplicate', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create(['name' => 'Copied Back']);
    $featureTemplate->tasks()->create(['title' => 'A task', 'order_index' => 0]);
    $sourceLink = $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $featureTemplate->id, 'order_index' => 0]);

    $component = Livewire::test('pages::templates.index')
        ->call('pasteFeatureTemplate', $featureTemplate->id, 'copy', $sourceLink->id);

    $this->assertModelExists($sourceLink);

    $unconnected = $component->instance()->featureTemplates;
    expect($unconnected->pluck('name')->all())->toContain('Copied Back');
    $copy = $unconnected->firstWhere('name', 'Copied Back');
    expect($copy->id)->not->toBe($featureTemplate->id);
    expect($copy->tasks()->pluck('title')->all())->toBe(['A task']);
});

test('pasting a cut feature template into the unlinked area moves it out of its epic template', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create(['name' => 'Moved Back']);
    $sourceLink = $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $featureTemplate->id, 'order_index' => 0]);

    $component = Livewire::test('pages::templates.index')
        ->call('pasteFeatureTemplate', $featureTemplate->id, 'cut', $sourceLink->id);

    $this->assertModelMissing($sourceLink);
    expect($component->instance()->featureTemplates->pluck('id')->all())->toContain($featureTemplate->id);
    expect(FeatureTemplate::count())->toBe(1);
});

test('pasting a feature template that no longer exists into the unlinked area is guarded', function () {
    Livewire::test('pages::templates.index')
        ->call('pasteFeatureTemplate', Str::uuid7()->toString(), 'copy', null)
        ->assertOk();
});
