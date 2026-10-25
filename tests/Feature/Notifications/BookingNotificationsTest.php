<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethodCode;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\CancellationTier;
use App\Models\Media;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Notifications\Booking\BookingConfirmed;
use App\Notifications\Booking\BookingReceived;
use App\Notifications\Booking\PaymentRejected;
use App\Notifications\Booking\PaymentSubmitted;
use App\Notifications\Booking\StaffNewBooking;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\Booking\BookingTokenService;
use App\Services\Notifications\BookingNotifier;
use App\Services\Notifications\VoucherService;
use App\Services\Notifications\WhatsApp\Drivers\ClickToChatDriver;
use App\Services\Notifications\WhatsApp\WhatsAppDriver;
use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use App\Services\Payments\PaymentSubmissionService;
use App\Services\Payments\PaymentVerificationService;
use App\Settings\NotificationSettings;
use App\Support\Money;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));

    $settings = app(NotificationSettings::class);
    $settings->staff_emails = ['ops@baines.test'];
    $settings->staff_whatsapp_number = '+254 700 111 222';
    $settings->save();

    $this->token = str_repeat('n', 64);
    $this->booking = Booking::factory()->awaitingPayment()->withToken($this->token)->create([
        'status' => BookingStatus::PaymentSubmitted,
        'expires_at' => null,
        'pickup_at' => '2026-11-02 07:00:00',
        'return_at' => '2026-11-05 07:00:00',
        'security_deposit_minor' => 16_000_000,
        'quote' => ['days' => 3, 'requires_staff_quote' => false],
        'cancellation_tiers' => [['hours_before_pickup' => 72, 'refund_percent_bp' => 5000, 'label' => null]],
    ]);
    $this->driver = BookingDriver::factory()->for($this->booking)->create([
        'first_name' => 'Amina', 'surname' => 'Otieno', 'email' => 'amina@example.test', 'phone' => '0712 345 678',
    ]);
    $this->submission = PaymentSubmission::factory()->for($this->booking)->create();
    $this->staff = staffWith([Permission::PaymentsView, Permission::PaymentsVerify, Permission::PaymentsReject]);
});

function setChannel(string $event, string $channel, bool $on): void
{
    $settings = app(NotificationSettings::class);
    $channels = $settings->channels;
    $channels[$event][$channel] = $on;
    $settings->channels = $channels;
    $settings->save();
}

it('acknowledges a submitted booking to the renter with their own link, and alerts staff', function () {
    Notification::fake();

    app(BookingNotifier::class)->submitted($this->booking, $this->token);

    Notification::assertSentTo(new AnonymousNotifiable, BookingReceived::class, function (BookingReceived $notification, array $channels, AnonymousNotifiable $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notifiable->routes['mail'] === 'amina@example.test'
            && $channels === ['mail']
            && $mail->actionUrl === route('bookings.show', $this->token);
    });
    Notification::assertSentTo(new AnonymousNotifiable, StaffNewBooking::class, fn ($notification, array $channels, $notifiable) => $notifiable->routes['mail'] === ['ops@baines.test']
        && $channels === ['mail', WhatsAppChannel::class]);

    // The renter's link still works: submit never rotates it.
    expect(app(BookingTokenService::class)->find($this->token)?->is($this->booking))->toBeTrue();
});

it('alerts staff when a payment comes in, by message or cash', function () {
    Notification::fake();
    $booking = Booking::factory()->awaitingPayment()->create();
    BookingDriver::factory()->for($booking)->create();
    $cash = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Cash, 'name' => 'Cash', 'requires_transaction_message' => false]);

    app(PaymentSubmissionService::class)->submit($booking, $cash, null);

    Notification::assertSentTo(new AnonymousNotifiable, PaymentSubmitted::class, function (PaymentSubmitted $notification, array $channels, $notifiable) use ($booking) {
        return $notification->booking->is($booking)
            && $notification->payment->isCash()
            && str_contains($notification->toMail($notifiable)->subject, 'Cash payment coming');
    });
});

it('sends the voucher on verify with a fresh link, and stores the PDF', function () {
    Notification::fake();

    app(PaymentVerificationService::class)->verify($this->submission, 'REF1', Money::fromMajor('16588'), $this->staff);

    $voucher = app(VoucherService::class)->current($this->booking);
    expect($voucher)->not->toBeNull()
        ->and($voucher->collection)->toBe('booking.voucher')
        ->and($voucher->mime_type)->toBe('application/pdf')
        ->and(Storage::disk($voucher->disk)->get($voucher->path))->toStartWith('%PDF');

    $newToken = null;
    Notification::assertSentTo(new AnonymousNotifiable, BookingConfirmed::class, function (BookingConfirmed $notification, array $channels, $notifiable) use ($voucher, &$newToken) {
        $newToken = $notification->token;
        $mail = $notification->toMail($notifiable);

        return $channels === ['mail', WhatsAppChannel::class]
            && $notification->voucher?->is($voucher)
            && count($mail->rawAttachments) + count($mail->attachments) === 1
            && $mail->subject === "Your Baines Executives booking {$this->booking->reference} is confirmed";
    });

    // The old link stops working; the mailed one opens the booking.
    $tokens = app(BookingTokenService::class);
    expect($tokens->find($this->token))->toBeNull()
        ->and($tokens->find($newToken)?->is($this->booking))->toBeTrue();
});

it('tells the renter about a rejection with the reason and a fresh link', function () {
    Notification::fake();

    app(PaymentVerificationService::class)->reject($this->submission, 'No payment with this code.', $this->staff);

    Notification::assertSentTo(new AnonymousNotifiable, PaymentRejected::class, function (PaymentRejected $notification, array $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);

        return $notification->reason === 'No payment with this code.'
            && $channels === ['mail']
            && $mail->actionUrl === route('bookings.show', $notification->token)
            && $notification->token !== $this->token;
    });
});

it('respects the channel toggles and keeps the link when no mail goes out', function () {
    Notification::fake();
    setChannel('payment_rejected', 'mail', false);
    setChannel('staff_new_booking', 'whatsapp', false);

    app(PaymentVerificationService::class)->reject($this->submission, 'No payment with this code.', $this->staff);
    app(BookingNotifier::class)->submitted($this->booking, $this->token);

    Notification::assertNotSentTo(new AnonymousNotifiable, PaymentRejected::class, fn ($notification, array $channels) => $channels !== []);
    Notification::assertSentTo(new AnonymousNotifiable, StaffNewBooking::class, fn ($notification, array $channels) => $channels === ['mail']);
    expect(app(BookingTokenService::class)->find($this->token)?->is($this->booking))->toBeTrue();
});

it('sends no staff alert when no staff recipients are set', function () {
    Notification::fake();
    $settings = app(NotificationSettings::class);
    $settings->staff_emails = [];
    $settings->staff_whatsapp_number = null;
    $settings->save();

    app(BookingNotifier::class)->submitted($this->booking, $this->token);

    Notification::assertNotSentTo(new AnonymousNotifiable, StaffNewBooking::class);
    Notification::assertSentTo(new AnonymousNotifiable, BookingReceived::class);
});

it('builds the voucher from the snapshot only', function () {
    $html = app(VoucherService::class)->html($this->booking);

    expect($html)->toContain($this->booking->reference)
        ->toContain('Amina Otieno')
        ->toContain($this->booking->car->name)
        ->toContain('KES 66,350')
        ->toContain('KES 49,762')
        ->toContain('KES 160,000')
        ->toContain('Monday 2 November 2026')
        ->toContain('10:00')
        ->toContain('72 hours or more before pick-up')
        ->toContain('50%');

    // Later tier and price changes never reach an existing booking's voucher.
    CancellationTier::factory()->create(['hours_before_pickup' => 24, 'refund_percent_bp' => 1000, 'label' => 'Late']);
    $this->booking->car->forceFill(['daily_rate_minor' => 9_900_000])->save();

    expect(app(VoucherService::class)->html($this->booking->fresh()))->toBe($html);
});

it('keeps one voucher when it is generated again', function () {
    $vouchers = app(VoucherService::class);
    $first = $vouchers->generate($this->booking);
    $second = $vouchers->generate($this->booking);

    expect($this->booking->mediaIn('booking.voucher')->pluck('uuid')->all())->toBe([$second->uuid])
        ->and(Media::withTrashed()->find($first->id)?->trashed())->toBeTrue();
});

it('logs each booking notification that goes out', function () {
    app(PaymentVerificationService::class)->verify($this->submission, 'REF1', Money::fromMajor('16588'), $this->staff);

    $sent = Activity::query()->with('subject')->where('log_name', 'notifications')->get();

    expect($sent->map(fn (Activity $activity) => $activity->getProperty('notification').':'.$activity->getProperty('channel'))->all())
        ->toEqualCanonicalizing(['booking_confirmed:mail', 'booking_confirmed:whatsapp'])
        ->and($sent->first()->subject->is($this->booking))->toBeTrue();

    $link = Activity::query()->where('log_name', 'whatsapp')->sole();
    // The link holds the renter's phone and the message, so only the fact is logged (M12.4).
    expect($link->event)->toBe('link-built')
        ->and($link->getProperty('notification'))->toBe('booking_confirmed')
        ->and($link->getProperty('link'))->toBeNull();
});

it('builds click-to-chat links and refuses the unbuilt Cloud API driver', function () {
    $message = new WhatsAppMessage('+254 700 111 222', 'Hello', 'staff_new_booking', $this->booking);

    expect(app(ClickToChatDriver::class)->send($message))->toBe('https://wa.me/254700111222?text=Hello');

    config(['services.whatsapp.driver' => 'cloud_api']);
    expect(fn () => app(WhatsAppDriver::class)->send($message))->toThrow(LogicException::class, 'P2.3');
});

it('offers the WhatsApp confirmation in the verify toast', function () {
    Notification::fake();

    $this->actingAs($this->staff)
        ->post(route('admin.payments.verify', $this->submission), ['reference' => 'REF1', 'amount' => '16588'])
        ->assertSessionHasNoErrors();

    $toast = session('inertia.flash_data')['toast'] ?? session('toast');

    expect($toast['action']['label'])->toBe('Send on WhatsApp')
        ->and($toast['action']['url'])->toStartWith('https://wa.me/254712345678?text=Hello%20from%20Baines%20Executives.');
});
