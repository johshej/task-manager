<?php

use App\Models\EpicTemplate;
use App\Models\EpicTemplateFeature;
use App\Models\FeatureTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('epic template page renders', function () {
    $template = EpicTemplate::factory()->create();

    $this->get(route('templates.epic', $template))->assertOk();
});

test('can update an epic template', function () {
    $template = EpicTemplate::factory()->create(['name' => 'Old Name']);

    Livewire::test('pages::templates.epic', ['epicTemplate' => $template])
        ->set('editName', 'New Name')
        ->set('editRepositoryUrl', 'https://github.com/org/repo')
        ->call('updateEpicTemplate')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('epic_templates', ['id' => $template->id, 'name' => 'New Name', 'repository_url' => 'https://github.com/org/repo']);
});

test('can add a feature template to an epic template', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create();

    Livewire::test('pages::templates.epic', ['epicTemplate' => $epicTemplate])
        ->call('addFeatureTemplate', $featureTemplate->id);

    $this->assertDatabaseHas('epic_template_features', [
        'epic_template_id' => $epicTemplate->id,
        'feature_template_id' => $featureTemplate->id,
        'order_index' => 0,
    ]);
});

test('adding the same feature template twice does not duplicate the link', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create();

    Livewire::test('pages::templates.epic', ['epicTemplate' => $epicTemplate])
        ->call('addFeatureTemplate', $featureTemplate->id)
        ->call('addFeatureTemplate', $featureTemplate->id);

    expect(EpicTemplateFeature::where('epic_template_id', $epicTemplate->id)->count())->toBe(1);
});

test('available feature templates excludes ones linked to any epic template', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $linked = FeatureTemplate::factory()->create(['name' => 'Linked']);
    $available = FeatureTemplate::factory()->create(['name' => 'Available']);
    $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $linked->id, 'order_index' => 0]);

    $linkedElsewhere = FeatureTemplate::factory()->create(['name' => 'Linked Elsewhere']);
    EpicTemplate::factory()->create()->epicTemplateFeatures()->create(['feature_template_id' => $linkedElsewhere->id, 'order_index' => 0]);

    $ids = Livewire::test('pages::templates.epic', ['epicTemplate' => $epicTemplate])
        ->instance()->availableFeatureTemplates()->pluck('id');

    expect($ids)->not->toContain($linked->id)
        ->and($ids)->not->toContain($linkedElsewhere->id)
        ->and($ids)->toContain($available->id);
});

test('can remove a feature template link from an epic template', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create();
    $link = $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $featureTemplate->id, 'order_index' => 0]);

    Livewire::test('pages::templates.epic', ['epicTemplate' => $epicTemplate])
        ->call('confirmRemoveFeatureTemplate', $link->id)
        ->call('removeFeatureTemplate');

    $this->assertDatabaseMissing('epic_template_features', ['id' => $link->id]);
    $this->assertDatabaseHas('feature_templates', ['id' => $featureTemplate->id]);
});

test('sortEpicTemplateFeatures reorders links', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $linkA = $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => FeatureTemplate::factory()->create()->id, 'order_index' => 0]);
    $linkB = $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => FeatureTemplate::factory()->create()->id, 'order_index' => 1]);

    Livewire::test('pages::templates.epic', ['epicTemplate' => $epicTemplate])
        ->call('sortEpicTemplateFeatures', $linkB->id, 0);

    expect($linkB->fresh()->order_index)->toBe(0);
    expect($linkA->fresh()->order_index)->toBe(1);
});

test('pasteFeatureTemplate in copy mode duplicates the feature template', function () {
    $sourceEpicTemplate = EpicTemplate::factory()->create();
    $destinationEpicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create(['name' => 'Copied Feature']);
    $featureTemplate->tasks()->create(['title' => 'First task', 'order_index' => 0]);
    $sourceLink = $sourceEpicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $featureTemplate->id, 'order_index' => 0]);

    Livewire::test('pages::templates.epic', ['epicTemplate' => $destinationEpicTemplate])
        ->call('pasteFeatureTemplate', $featureTemplate->id, 'copy', $sourceLink->id);

    $this->assertModelExists($sourceLink);

    $copyLink = $destinationEpicTemplate->epicTemplateFeatures()->with('featureTemplate.tasks')->first();
    expect($copyLink)->not->toBeNull();
    expect($copyLink->feature_template_id)->not->toBe($featureTemplate->id);
    expect($copyLink->featureTemplate->name)->toBe('Copied Feature');
    expect($copyLink->featureTemplate->tasks->pluck('title')->all())->toBe(['First task']);
});

test('deleting the original feature template leaves a pasted copy in the epic template', function () {
    $destinationEpicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create(['name' => 'Survivor']);

    Livewire::test('pages::templates.epic', ['epicTemplate' => $destinationEpicTemplate])
        ->call('pasteFeatureTemplate', $featureTemplate->id, 'copy', null);

    $featureTemplate->delete();

    $copyLink = $destinationEpicTemplate->epicTemplateFeatures()->with('featureTemplate')->first();
    expect($copyLink)->not->toBeNull();
    expect($copyLink->featureTemplate->name)->toBe('Survivor');
});

test('pasteFeatureTemplate in cut mode moves the link to the destination', function () {
    $sourceEpicTemplate = EpicTemplate::factory()->create();
    $destinationEpicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create();
    $sourceLink = $sourceEpicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $featureTemplate->id, 'order_index' => 0]);

    Livewire::test('pages::templates.epic', ['epicTemplate' => $destinationEpicTemplate])
        ->call('pasteFeatureTemplate', $featureTemplate->id, 'cut', $sourceLink->id);

    $this->assertModelMissing($sourceLink);
    $this->assertDatabaseHas('epic_template_features', [
        'epic_template_id' => $destinationEpicTemplate->id,
        'feature_template_id' => $featureTemplate->id,
    ]);
});

test('pasteFeatureTemplate in cut mode back onto its own source is a no-op', function () {
    $epicTemplate = EpicTemplate::factory()->create();
    $featureTemplate = FeatureTemplate::factory()->create();
    $sourceLink = $epicTemplate->epicTemplateFeatures()->create(['feature_template_id' => $featureTemplate->id, 'order_index' => 0]);

    Livewire::test('pages::templates.epic', ['epicTemplate' => $epicTemplate])
        ->call('pasteFeatureTemplate', $featureTemplate->id, 'cut', $sourceLink->id);

    $this->assertModelExists($sourceLink);
    expect($epicTemplate->epicTemplateFeatures()->count())->toBe(1);
});

test('pasteFeatureTemplate guards against a feature template that no longer exists', function () {
    $epicTemplate = EpicTemplate::factory()->create();

    Livewire::test('pages::templates.epic', ['epicTemplate' => $epicTemplate])
        ->call('pasteFeatureTemplate', (string) Str::orderedUuid(), 'copy', null);

    expect($epicTemplate->epicTemplateFeatures()->count())->toBe(0);
});
