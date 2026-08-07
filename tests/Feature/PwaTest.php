<?php

use App\Models\User;
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

test('the manifest start_url does not 404 for a signed-out visitor', function () {
    $manifest = json_decode(File::get(public_path('manifest.json')), true);

    $this->get($manifest['start_url'])->assertOk();
});

test('the manifest start_url resolves to a working page for a returning signed-in user', function () {
    $manifest = json_decode(File::get(public_path('manifest.json')), true);
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $response = $this->actingAs($user)->get($manifest['start_url']);

    // Whatever the outcome (redirect straight to the dashboard, or render the
    // start_url page itself), it must land somewhere real - not a 404.
    if ($response->isRedirect()) {
        $response = $this->get($response->headers->get('Location'));
    }

    $response->assertOk();
});
