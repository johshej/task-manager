<?php

use Illuminate\Support\Facades\File;

test('manifest.json exists with the expected app metadata', function () {
    expect(File::exists(public_path('manifest.json')))->toBeTrue();

    $manifest = json_decode(File::get(public_path('manifest.json')), true);

    expect($manifest['name'])->toBe('Task Manager')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->toHaveCount(2);
});

test('service worker script exists', function () {
    expect(File::exists(public_path('sw.js')))->toBeTrue();
});

test('pages link to the manifest and register the service worker', function () {
    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.json">', false);
});
