<?php

use App\Models\User;
use App\Settings\SecuritySettings;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('non-staff users are forbidden', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('2FA is optional by default', function () {
    expect(app(SecuritySettings::class)->require_two_factor)->toBeFalse();

    $this->actingAs(User::factory()->create(['is_staff' => true]))
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('when 2FA is required, staff without it are sent to set it up', function () {
    $settings = app(SecuritySettings::class);
    $settings->require_two_factor = true;
    $settings->save();

    $this->actingAs(User::factory()->create(['is_staff' => true]))
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('security.edit'));

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('unverified staff are sent to verify their email', function () {
    $user = User::factory()->staff()->unverified()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('active staff with 2FA can open the admin', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('a deactivated user is signed out on their next admin request', function () {
    $user = User::factory()->staff()->inactive()->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('deactivated users cannot sign in', function () {
    $user = User::factory()->inactive()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    $this->assertGuest();
});

test('signing in records the login time', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('the old dashboard URL redirects to the admin', function () {
    $this->get('/dashboard')->assertRedirect('/admin');
});

test('following an invite reset link verifies the email', function () {
    $user = User::factory()->unverified()->create();
    $token = Password::createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-Password-123!',
        'password_confirmation' => 'new-Password-123!',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('shared props expose permissions to staff only', function () {
    $staff = staffWith(['users.view']);

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', ['users.view'])
            ->where('auth.user.email', $staff->email)
            ->missing('auth.user.password')
            ->missing('auth.user.two_factor_secret'));

    $this->actingAs(superAdmin())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.permissions', ['*']));
});

test('account settings pages are for staff only', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertForbidden();
    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'x', 'email' => 'x@baines.test'])->assertForbidden();
    $this->actingAs($user)->put(route('user-password.update'), [])->assertForbidden();
    $this->actingAs($user)->get(route('appearance.edit'))->assertForbidden();
});

test('a deactivated user is signed out on any page, not just the admin', function () {
    $user = User::factory()->staff()->inactive()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('local storage is not served over HTTP', function () {
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.local.upload'))->toBeFalse();
});
