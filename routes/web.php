<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::view('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('epics', 'pages::epics.index')->name('epics');
    Route::livewire('epics/{epic}', 'pages::epics.show')->name('epics.board');
    Route::livewire('epics/{epic}/kanban', 'pages::epics.show')->name('epics.board.kanban');
    Route::livewire('epics/{epic}/queue', 'pages::epics.show')->name('epics.board.queue');
    Route::livewire('epics/{epic}/edit', 'pages::epics.show')->name('epics.board.edit');
    Route::livewire('epics/{epic}/features/{feature}', 'pages::epics.show')->name('epics.board.feature');
    Route::livewire('epics/{epic}/tasks/{task}', 'pages::epics.show')->name('epics.board.task');
});

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__.'/settings.php';
