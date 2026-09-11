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
