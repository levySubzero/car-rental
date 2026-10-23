<?php

use App\Enums\BookingRequestType;
use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\BookingRequest;
use App\Notifications\NewBookingRequest;
use App\Notifications\PortalCode;
use App\Notifications\StaffAlert;
use App\Services\Booking\BookingTokenService;
use App\Services\Notifications\VoucherService;
use App\Settings\NotificationSettings;
use App\Support\Rules\Turnstile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    config(['services.turnstile.secret_key' => Turnstile::TEST_SECRET]);
    RateLimiter::clear('portal');
    Notification::fake();

    $this->token = str_repeat('m', 64);
    $this->booking = Booking::factory()->awaitingPayment()->withToken($this->token)->create();
    BookingDriver::factory()->for($this->booking)->create(['email' => 'Amina@Example.test', 'phone' => '0712 345 678']);
});

/**
 * Ask for a code and return the one that was emailed.
 */
function portalCode(string $identifier = 'amina@example.test'): ?string
{
    test()->post(route('portal.code'), ['identifier' => $identifier])->assertRedirect(route('portal.index'));

    $code = null;
    Notification::assertSentTo(new AnonymousNotifiable, PortalCode::class, function (PortalCode $notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    return $code;
}

function signIn(string $identifier = 'amina@example.test'): void
{
    test()->post(route('portal.verify'), ['code' => portalCode($identifier)])->assertSessionHasNoErrors();
}

it('emails a code to the address on the booking, found by email in any case or by phone', function (string $identifier) {
    portalCode($identifier);

    Notification::assertSentTo(new AnonymousNotifiable, PortalCode::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'Amina@Example.test');
})->with(['AMINA@example.test', '0712345678', '+254 712 345 678']);

it('answers the same for an unknown email or phone and sends nothing', function () {
    $this->post(route('portal.code'), ['identifier' => 'nobody@example.test'])->assertRedirect(route('portal.index'));

    $this->get(route('portal.index'))->assertInertia(fn (Assert $page) => $page
        ->component('public/my-bookings/index')
        ->where('step', 'code'));
    Notification::assertNothingSent();
});

it('refuses something that is neither an email nor a phone', function () {
    $this->post(route('portal.code'), ['identifier' => 'hello'])->assertSessionHasErrors('identifier');
});

it('signs in with the right code and lists only that renter\'s submitted bookings', function () {
    Booking::factory()->create(['status' => BookingStatus::Draft])->driver()->save(BookingDriver::factory()->make(['email' => 'amina@example.test']));
    $other = Booking::factory()->awaitingPayment()->create();
    BookingDriver::factory()->for($other)->create(['email' => 'someone@example.test']);

    signIn();

    $this->get(route('portal.index'))->assertInertia(fn (Assert $page) => $page
        ->component('public/my-bookings/list')
        ->has('bookings', 1)
        ->where('bookings.0.reference', $this->booking->reference)
        ->where('bookings.0.accepts_requests', true));

    expect(Activity::query()->where('log_name', 'portal')->where('event', 'signed-in')->count())->toBe(1);

    $this->post(route('portal.open', $other->reference))->assertNotFound();
});

it('refuses a wrong code and drops the code after five wrong tries', function () {
    $code = portalCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $try) {
        $this->post(route('portal.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
    }

    $this->post(route('portal.verify'), ['code' => $code])->assertSessionHasErrors('code');
    $this->get(route('portal.index'))->assertInertia(fn (Assert $page) => $page->component('public/my-bookings/index'));
});

it('lets a code expire after ten minutes', function () {
    $code = portalCode();
    $this->travel(11)->minutes();

    $this->post(route('portal.verify'), ['code' => $code])->assertSessionHasErrors('code');
});

it('ends the portal session after an hour', function () {
    signIn();
    $this->travel(61)->minutes();

    $this->post(route('portal.open', $this->booking->reference))->assertRedirect(route('portal.index'));
});

it('opens a booking with a fresh link', function () {
    signIn();

    $response = $this->post(route('portal.open', $this->booking->reference));

    $token = basename((string) $response->headers->get('Location'));
    expect(app(BookingTokenService::class)->find($token)?->is($this->booking))->toBeTrue()
        ->and(app(BookingTokenService::class)->find($this->token))->toBeNull();
});

it('downloads the voucher and logs it', function () {
    app(VoucherService::class)->generate($this->booking);
    signIn();

    $this->get(route('portal.voucher', $this->booking->reference))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    expect(Activity::query()->where('log_name', 'media')->where('event', 'downloaded')->count())->toBe(1);
});

it('has no voucher before the booking is confirmed', function () {
    signIn();

    $this->get(route('portal.voucher', $this->booking->reference))->assertNotFound();
});

it('sends a request to staff by alert and email', function () {
    $settings = app(NotificationSettings::class);
    $settings->staff_emails = ['ops@baines.test'];
    $settings->save();
    $staff = staffWith([Permission::BookingRequestsView]);
    signIn();

    $this->post(route('portal.requests.store', $this->booking->reference), ['type' => 'cancel', 'message' => 'Plans changed, please cancel.'])
        ->assertRedirect(route('portal.index'));

    $request = BookingRequest::sole();
    expect($request->type)->toBe(BookingRequestType::Cancel)
        ->and($request->booking->is($this->booking))->toBeTrue();
    Notification::assertSentTo($staff, StaffAlert::class, fn (StaffAlert $alert) => $alert->title === "Cancellation request for {$this->booking->reference}");
    Notification::assertSentTo(new AnonymousNotifiable, NewBookingRequest::class);
});

it('refuses requests on a booking that has ended', function () {
    $this->booking->forceFill(['status' => BookingStatus::Expired])->save();
    signIn();

    $this->post(route('portal.requests.store', $this->booking->reference), ['type' => 'change', 'message' => 'New dates please.']);

    expect(session('inertia.flash_data')['toast']['type'] ?? null)->toBe('error')
        ->and(BookingRequest::count())->toBe(0);
});

it('needs Turnstile and limits code requests', function () {
    config(['services.turnstile.secret_key' => 'real-secret']);
    Http::fake(['*' => Http::response(['success' => false])]);
    $this->post(route('portal.code'), ['identifier' => 'amina@example.test'])->assertSessionHasErrors('cf-turnstile-response');

    config(['services.turnstile.secret_key' => Turnstile::TEST_SECRET]);
    foreach (range(1, 4) as $i) {
        $this->post(route('portal.code'), ['identifier' => 'amina@example.test']);
    }
    $this->post(route('portal.code'), ['identifier' => 'amina@example.test'])->assertTooManyRequests();
});

it('lets staff mark a request handled', function () {
    $request = BookingRequest::factory()->for($this->booking)->create();
    $staff = staffWith([Permission::BookingRequestsView, Permission::BookingRequestsUpdate]);

    $this->actingAs($staff)->get(route('admin.booking-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1)->where('counts.open', 1));

    $this->actingAs($staff)->post(route('admin.booking-requests.handle', $request), ['note' => 'Cancelled, refund sent.'])
        ->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->isOpen())->toBeFalse()
        ->and($request->staff_note)->toBe('Cancelled, refund sent.')
        ->and($request->handled_by)->toBe($staff->id);
});
