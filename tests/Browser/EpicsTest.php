<?php

use App\Enums\EpicStatus;
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
    Epic::factory()->create(['name' => 'Original Name', 'status' => EpicStatus::Active]);

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
    Epic::factory()->create(['name' => 'Delete Me', 'status' => EpicStatus::Active]);

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
    Epic::factory()->create(['name' => 'Safe Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics'));

    $page->click('[icon="trash"]')
        ->assertSee('Delete epic')
        ->click('Cancel')
        ->assertSee('Safe Epic');

    expect(Epic::where('name', 'Safe Epic')->exists())->toBeTrue();
});

test('epics list only shows active epics until the filter is opened and cleared', function () {
    Epic::factory()->create(['name' => 'Active Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Paused Epic', 'status' => EpicStatus::Paused]);

    $page = visit(route('epics'));

    $page->assertSee('Active Epic')
        ->assertDontSee('Paused Epic')
        ->assertNoJavaScriptErrors()
        ->click('Filter')
        ->assertSee('Paused')
        ->click('Clear filters')
        ->assertSee('Active Epic')
        ->assertSee('Paused Epic')
        ->assertNoJavaScriptErrors();
});

test('clicking an epic card selects it for keyboard navigation', function () {
    Epic::factory()->create(['name' => 'Alpha Epic', 'status' => EpicStatus::Active]);
    Epic::factory()->create(['name' => 'Beta Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics'));

    $page->assertNoJavaScriptErrors()
        ->click('Beta Epic');

    $activeText = $page->script("document.querySelector('[data-selectable].active')?.textContent || ''");
    expect($activeText)->toContain('Beta Epic');
});

test('moving an epic with Shift+ArrowDown keeps it selected', function () {
    Epic::factory()->create(['name' => 'First Epic', 'status' => EpicStatus::Active, 'order_index' => 0]);
    Epic::factory()->create(['name' => 'Second Epic', 'status' => EpicStatus::Active, 'order_index' => 1]);

    $page = visit(route('epics'));

    $page->assertNoJavaScriptErrors()
        ->click('First Epic')
        ->keys('body', ['{Shift}', 'ArrowDown'])
        ->wait(0.5)
        ->assertSeeInOrder(['Second Epic', 'First Epic']);

    $activeText = $page->script("document.querySelector('[data-selectable].active')?.textContent || ''");
    expect($activeText)->toContain('First Epic');
});
