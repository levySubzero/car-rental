<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Inertia\Testing\AssertableInertia as Assert;

// CSRF is only skipped automatically in the testing environment.
function asLocal(): void
{
    app()->detectEnvironment(fn () => 'local');
    test()->withoutMiddleware(PreventRequestForgery::class);
}

it('is off outside the local environment', function () {
    $user = User::factory()->staff()->create();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('devUsers', null));
    $this->post(route('dev.login'), ['user_id' => $user->id])->assertNotFound();

    $this->assertGuest();
});

it('is off locally when DEV_LOGIN is false', function () {
    asLocal();
    config(['baines.dev_login' => false]);
    $user = User::factory()->staff()->create();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('devUsers', null));
    $this->post(route('dev.login'), ['user_id' => $user->id])->assertNotFound();
});

it('lists active users on the login page locally', function () {
    asLocal();
    $staff = User::factory()->staff()->create(['name' => 'Ada']);
    User::factory()->staff()->inactive()->create();

    $this->get(route('login'))->assertInertia(fn (Assert $page) => $page
        ->has('devUsers', 1)
        ->where('devUsers.0.id', $staff->id)
        ->missing('devUsers.0.password'));
});

it('signs in as the chosen user locally', function () {
    asLocal();
    $user = User::factory()->staff()->create();

    $this->post(route('dev.login'), ['user_id' => $user->id])->assertRedirect('/admin');

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('refuses inactive users', function () {
    asLocal();
    $user = User::factory()->staff()->inactive()->create();

    $this->post(route('dev.login'), ['user_id' => $user->id])->assertNotFound();

    $this->assertGuest();
});
