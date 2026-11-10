<?php

use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\PaymentSubmission;
use App\Notifications\Booking\BookingConfirmed;
use App\Notifications\Booking\BookingReceived;
use App\Notifications\Booking\PaymentSubmitted;
use App\Notifications\IntegrationTestMail;
use App\Notifications\PortalCode;
use App\Services\Notifications\VoucherService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    Notification::fake();
});

function sendTestMail(array $data): TestResponse
{
    return test()->actingAs(superAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->from(route('admin.integrations.edit', 'resend'))
        ->post(route('admin.integrations.test-mail'), $data);
}

$sentTo = fn (string $email) => fn ($notification, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes === ['mail' => $email];

it('needs the permission and the password again', function () {
    $this->actingAs(staffWith([Permission::BookingsView]))
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.integrations.test-mail'), ['email' => 'me@example.test'])
        ->assertForbidden();

    $this->actingAs(superAdmin())
        ->post(route('admin.integrations.test-mail'), ['email' => 'me@example.test'])
        ->assertRedirect(route('password.confirm'));

    Notification::assertNothingSent();
});

it('sends one plain message to the typed address only', function () use ($sentTo) {
    sendTestMail(['email' => 'me@example.test'])->assertSessionHasNoErrors()->assertRedirect();

    Notification::assertSentOnDemand(IntegrationTestMail::class, $sentTo('me@example.test'));
    Notification::assertSentOnDemandTimes(IntegrationTestMail::class, 1);
});

it('refuses an unknown booking', function () {
    sendTestMail(['email' => 'me@example.test', 'reference' => 'BE-0000-000000'])->assertSessionHasErrors('reference');
});

it('sends every booking email it has data for, without touching the booking', function () use ($sentTo) {
    $booking = Booking::factory()->reserved()->create(['pickup_at' => now()->addDays(10), 'return_at' => now()->addDays(13)]);
    BookingDriver::factory()->for($booking)->create(['email' => 'renter@example.test']);
    $before = $booking->fresh()->only(['status', 'access_token_hash', 'voucher_sent_at', 'updated_at']);

    sendTestMail(['email' => 'me@example.test', 'reference' => $booking->reference])->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(BookingReceived::class, $sentTo('me@example.test'));
    Notification::assertSentOnDemand(PortalCode::class, $sentTo('me@example.test'));
    // No payment and no voucher yet: those two are skipped, not sent with gaps.
    Notification::assertNotSentTo(new AnonymousNotifiable, PaymentSubmitted::class);
    Notification::assertNotSentTo(new AnonymousNotifiable, BookingConfirmed::class);

    expect($booking->fresh()->only(array_keys($before)))->toEqual($before);

    $toast = session('inertia.flash_data')['toast'] ?? session('toast');
    expect($toast['message'])->toContain('Skipped')->toContain('Confirmation with voucher');
});

it('includes the voucher and payment emails once the booking has them', function () use ($sentTo) {
    $booking = Booking::factory()->reserved()->create(['pickup_at' => now()->addDays(10), 'return_at' => now()->addDays(13)]);
    BookingDriver::factory()->for($booking)->create();
    PaymentSubmission::factory()->verified(1_658_800)->create(['booking_id' => $booking->id]);
    app(VoucherService::class)->generate($booking);

    sendTestMail(['email' => 'me@example.test', 'reference' => $booking->reference]);

    Notification::assertSentOnDemand(BookingConfirmed::class, $sentTo('me@example.test'));
    Notification::assertSentOnDemand(PaymentSubmitted::class, $sentTo('me@example.test'));
});

it('records that a test went out without keeping the address', function () {
    sendTestMail(['email' => 'me@example.test']);

    $log = Activity::query()->where('event', 'test-mail')->sole();

    expect(json_encode($log->properties))->not->toContain('me@example.test')
        ->and($log->getProperty('sent'))->toBe(1);
});
