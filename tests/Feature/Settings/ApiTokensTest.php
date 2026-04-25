<?php

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('api tokens section is visible on security settings page', function () {
    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('API tokens');
});

test('new token button is visible on security settings page', function () {
    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('New token');
});

test('new token button renders modal with name input', function () {
    Livewire::test('pages::settings.api-tokens')
        ->assertSee('New token')
        ->assertSee('create-api-token');
});

test('creating a token via the modal closes it and shows plain text', function () {
    $component = Livewire::test('pages::settings.api-tokens')
        ->set('newTokenName', 'modal token')
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSet('newTokenName', '');

    expect($component->get('createdTokenPlainText'))->not->toBeEmpty();

    $this->assertDatabaseHas('personal_access_tokens', [
        'name' => 'modal token',
        'tokenable_id' => $this->user->id,
    ]);
});

test('existing tokens are listed', function () {
    $this->user->createApiToken('my-token');

    Livewire::test('pages::settings.api-tokens')
        ->assertSee('my-token');
});

test('only own tokens are listed', function () {
    $other = User::factory()->create();
    $this->user->createApiToken('mine');
    $other->createApiToken('theirs');

    Livewire::test('pages::settings.api-tokens')
        ->assertSee('mine')
        ->assertDontSee('theirs');
});

test('can create a new api token', function () {
    Livewire::test('pages::settings.api-tokens')
        ->set('newTokenName', 'CI token')
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSet('newTokenName', '');

    $this->assertDatabaseHas('personal_access_tokens', [
        'name' => 'CI token',
        'tokenable_id' => $this->user->id,
    ]);
});

test('plain text token is shown once after creation', function () {
    $component = Livewire::test('pages::settings.api-tokens')
        ->set('newTokenName', 'my agent')
        ->call('createToken');

    expect($component->get('createdTokenPlainText'))
        ->not->toBeEmpty()
        ->toContain('|');
});

test('creating a second token replaces the plain text', function () {
    $component = Livewire::test('pages::settings.api-tokens')
        ->set('newTokenName', 'first')
        ->call('createToken');

    $firstPlainText = $component->get('createdTokenPlainText');

    $component->set('newTokenName', 'second')->call('createToken');

    expect($component->get('createdTokenPlainText'))
        ->not->toBeEmpty()
        ->not->toBe($firstPlainText);
});

test('token creation requires a name', function () {
    Livewire::test('pages::settings.api-tokens')
        ->set('newTokenName', '')
        ->call('createToken')
        ->assertHasErrors(['newTokenName' => 'required']);
});

test('can revoke own token', function () {
    $token = $this->user->createApiToken('to revoke');
    $tokenId = $token->accessToken->id;

    Livewire::test('pages::settings.api-tokens')
        ->call('revokeToken', $tokenId)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});

test('cannot revoke another users token', function () {
    $other = User::factory()->create();
    $tokenId = $other->createApiToken('theirs')->accessToken->id;

    Livewire::test('pages::settings.api-tokens')
        ->call('revokeToken', $tokenId);

    $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenId]);
})->throws(ModelNotFoundException::class);

test('revoked token no longer appears in list', function () {
    $token = $this->user->createApiToken('temp');
    $tokenId = $token->accessToken->id;

    Livewire::test('pages::settings.api-tokens')
        ->assertSee('temp')
        ->call('revokeToken', $tokenId)
        ->assertDontSee('temp');
});
