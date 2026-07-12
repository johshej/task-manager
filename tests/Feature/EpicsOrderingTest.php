<?php

use App\Enums\EpicStatus;
use App\Models\Epic;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('epics index lists epics ordered by order_index', function () {
    Epic::factory()->create(['name' => 'Epic A', 'status' => EpicStatus::Active, 'order_index' => 1]);
    Epic::factory()->create(['name' => 'Epic B', 'status' => EpicStatus::Active, 'order_index' => 0]);

    Livewire::test('pages::epics.index')
        ->assertSeeInOrder(['Epic B', 'Epic A']);
});

test('sortEpics reorders epics', function () {
    $epicA = Epic::factory()->create(['status' => EpicStatus::Active, 'order_index' => 0]);
    $epicB = Epic::factory()->create(['status' => EpicStatus::Active, 'order_index' => 1]);
    $epicC = Epic::factory()->create(['status' => EpicStatus::Active, 'order_index' => 2]);

    Livewire::test('pages::epics.index')
        ->call('sortEpics', $epicC->id, 0);

    expect($epicC->fresh()->order_index)->toBe(0);
    expect($epicA->fresh()->order_index)->toBe(1);
    expect($epicB->fresh()->order_index)->toBe(2);
});

test('a newly created epic is appended at the end of the manual order', function () {
    Epic::factory()->create(['status' => EpicStatus::Active, 'order_index' => 0]);
    Epic::factory()->create(['status' => EpicStatus::Active, 'order_index' => 1]);

    Livewire::test('pages::epics.index')
        ->set('name', 'Appended Epic')
        ->call('createEpic');

    $appended = Epic::where('name', 'Appended Epic')->firstOrFail();
    expect($appended->order_index)->toBe(2);
});
