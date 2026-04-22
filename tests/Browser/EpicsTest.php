<?php

use App\Models\Epic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('create epic modal closes after submission', function () {
    $page = visit(route('epics'));

    $page->assertSee('Epics')
        ->assertNoJavaScriptErrors()
        ->click('New epic')
        ->assertSee('New epic')
        ->fill('name', 'Browser Epic')
        ->click('Create epic')
        ->assertSee('Browser Epic')
        ->assertSee('Epic created')
        ->assertNoJavaScriptErrors();

    expect(Epic::where('name', 'Browser Epic')->exists())->toBeTrue();
});

test('create epic modal is gone after submission', function () {
    $page = visit(route('epics'));

    $page->click('New epic')
        ->fill('name', 'Disappearing Modal Epic')
        ->click('Create epic')
        ->assertMissing('[data-flux-modal]');
});

test('can edit an epic', function () {
    Epic::factory()->create(['name' => 'Original Name']);

    $page = visit(route('epics'));

    $page->assertSee('Original Name')
        ->assertNoJavaScriptErrors()
        ->click('[icon="pencil"]')
        ->assertSee('Edit epic')
        ->fill('editName', 'Updated Name')
        ->click('Save changes')
        ->assertSee('Updated Name')
        ->assertDontSee('Original Name')
        ->assertNoJavaScriptErrors();
});

test('can delete an epic', function () {
    Epic::factory()->create(['name' => 'Delete Me']);

    $page = visit(route('epics'));

    $page->assertSee('Delete Me')
        ->assertNoJavaScriptErrors()
        ->click('[icon="trash"]')
        ->assertSee('Delete epic')
        ->click('Delete')
        ->assertDontSee('Delete Me')
        ->assertSee('Epic deleted')
        ->assertNoJavaScriptErrors();

    expect(Epic::where('name', 'Delete Me')->exists())->toBeFalse();
});

test('delete requires confirmation before removing epic', function () {
    Epic::factory()->create(['name' => 'Safe Epic']);

    $page = visit(route('epics'));

    $page->click('[icon="trash"]')
        ->assertSee('Delete epic')
        ->click('Cancel')
        ->assertSee('Safe Epic');

    expect(Epic::where('name', 'Safe Epic')->exists())->toBeTrue();
});
