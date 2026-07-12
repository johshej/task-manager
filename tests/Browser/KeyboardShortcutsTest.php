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

test('g then e navigates to epics index from the epics index itself, not the edit route', function () {
    Epic::factory()->create(['name' => 'Selectable Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics'));

    $page->assertNoJavaScriptErrors()
        ->keys('body', 'ArrowDown')
        ->keys('body', 'g')
        ->keys('body', 'e')
        ->assertUrlIs(route('epics'));
});

test('g then e navigates to epics index from the epic board, not the edit epic shortcut', function () {
    $epic = Epic::factory()->create(['name' => 'Board Epic', 'status' => EpicStatus::Active]);

    $page = visit(route('epics.board', $epic));

    $page->assertNoJavaScriptErrors()
        ->keys('body', 'g')
        ->keys('body', 'e')
        ->assertUrlIs(route('epics'));
});
