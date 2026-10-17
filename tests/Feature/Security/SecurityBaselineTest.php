<?php

use App\Http\Middleware\AdminIdleTimeout;
use App\Models\User;
use App\Support\Rules\Turnstile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

describe('security headers', function () {
    it('are sent on every web response', function () {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        expect($response->headers->get('Content-Security-Policy'))
            ->toContain("frame-ancestors 'self'")
            ->toContain("object-src 'none'");
    });

    it('add HSTS in production only', function () {
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');

        app()->detectEnvironment(fn () => 'production');

        $this->get(route('login'))->assertHeader('Strict-Transport-Security');
    });
});

describe('rate limiters', function () {
    it('are registered for the public endpoints', function (string $name) {
        expect(RateLimiter::limiter($name))->not->toBeNull();
    })->with(['booking-start', 'booking-step', 'media-upload', 'contact']);

    it('throttle with 429', function () {
        Route::post('/_test/contact', fn () => 'ok')->middleware(['web', 'throttle:contact']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/_test/contact')->assertOk();
        }

        $this->post('/_test/contact')->assertTooManyRequests();
    });
});

describe('turnstile rule', function () {
    function turnstile(mixed $token): bool
    {
        return Validator::make(['token' => $token], ['token' => [new Turnstile('127.0.0.1')]])->passes();
    }

    beforeEach(fn () => config(['services.turnstile.secret_key' => 'real-secret']));

    it('passes when Cloudflare confirms the token', function () {
        Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => true])]);

        expect(turnstile('token'))->toBeTrue();

        Http::assertSent(fn ($request) => $request['secret'] === 'real-secret' && $request['response'] === 'token');
    });

    it('fails when Cloudflare rejects the token or is unreachable', function () {
        Http::fake([Turnstile::VERIFY_URL => Http::response(['success' => false])]);
        expect(turnstile('token'))->toBeFalse();

        Http::fake([Turnstile::VERIFY_URL => fn () => throw new RuntimeException('down')]);
        expect(turnstile('token'))->toBeFalse();
    });

    it('fails without a token', function () {
        Http::fake();

        expect(turnstile(''))->toBeFalse();
        Http::assertNothingSent();
    });

    it('always passes with the Cloudflare test secret outside production', function () {
        config(['services.turnstile.secret_key' => Turnstile::TEST_SECRET]);
        Http::fake();

        expect(turnstile('anything'))->toBeTrue();
        Http::assertNothingSent();
    });
});

describe('admin idle timeout', function () {
    it('signs staff out after the idle limit', function () {
        config(['baines.admin_idle_minutes' => 30]);
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

        $this->travel(31)->minutes();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('keeps active sessions alive', function () {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->withSession([AdminIdleTimeout::SESSION_KEY => now()->subMinutes(5)->getTimestamp()])
            ->get(route('admin.dashboard'))
            ->assertOk();
    });
});
