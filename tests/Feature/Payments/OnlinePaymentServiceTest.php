<?php

use App\Enums\AlertKind;
use App\Enums\AvailabilityBlockType;
use App\Enums\BookingStatus;
use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Exceptions\Domain\PaymentException;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\GatewayTransaction;
use App\Models\PaymentMethod;
use App\Models\PaymentSubmission;
use App\Notifications\Booking\BookingConfirmed;
use App\Notifications\Booking\PaymentSubmitted;
use App\Services\Booking\BookingStatusService;
use App\Services\Payments\Gateways\DarajaClient;
use App\Services\Payments\Gateways\PaystackClient;
use App\Services\Payments\OnlinePaymentService;
use App\Settings\NotificationSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->travelTo(now()->setDate(2026, 10, 25)->setTime(9, 0));

    config([
        'services.daraja' => [
            'env' => 'sandbox', 'consumer_key' => 'ck', 'consumer_secret' => 'cs', 'shortcode' => '174379',
            'passkey' => 'pk', 'type' => 'paybill', 'till_number' => null, 'callback_secret' => 'cb-secret', 'allowed_ips' => [],
        ],
        'services.paystack' => ['public_key' => 'pk_test_1', 'secret_key' => 'sk_test_1'],
    ]);

    $settings = app(NotificationSettings::class);
    $settings->staff_emails = ['ops@baines.test'];
    $settings->save();

    $this->mpesa = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Mpesa, 'name' => 'M-Pesa', 'online_enabled' => true]);
    $this->card = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Card, 'name' => 'Card', 'online_enabled' => true]);
    $this->bank = PaymentMethod::factory()->create(['code' => PaymentMethodCode::Bank, 'name' => 'Bank transfer', 'online_enabled' => true]);

    $this->booking = Booking::factory()->awaitingPayment()->create(['reference' => 'BE-2026-000123']);
    BookingDriver::factory()->for($this->booking)->create(['email' => 'amina@example.test', 'phone' => '0712 345 678']);

    $this->service = app(OnlinePaymentService::class);
});

function fakeGateways(array $routes = []): void
{
    // Later keys win: the test's own answers replace the defaults.
    Http::fake([
        DarajaClient::SANDBOX_URL.'/oauth/*' => Http::response(['access_token' => 'tok', 'expires_in' => '3599']),
        DarajaClient::SANDBOX_URL.'/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => 'm-1', 'CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0', 'CustomerMessage' => 'Success',
        ]),
        DarajaClient::SANDBOX_URL.'/mpesa/stkpushquery/v1/query' => Http::response(['ResponseCode' => '0', 'ResultCode' => '0', 'ResultDesc' => 'Processed']),
        PaystackClient::URL.'/transaction/initialize' => Http::response(['status' => true, 'data' => ['access_code' => 'ac_1', 'authorization_url' => 'https://x', 'reference' => 'r']]),
        ...$routes,
    ]);
}

function paystackVerified(string $reference, array $overrides = []): array
{
    return ['status' => true, 'data' => ['id' => 99, 'status' => 'success', 'reference' => $reference, 'amount' => 1_658_800, 'currency' => 'KES', ...$overrides]];
}

it('is online only with keys, the method active and switched online, and never for bank', function () {
    expect($this->service->isOnline($this->mpesa))->toBeTrue()
        ->and($this->service->isOnline($this->card))->toBeTrue()
        ->and($this->service->isOnline($this->bank))->toBeFalse();

    $this->mpesa->update(['online_enabled' => false]);
    $this->card->update(['is_active' => false]);
    expect($this->service->isOnline($this->mpesa))->toBeFalse()
        ->and($this->service->isOnline($this->card))->toBeFalse();

    $this->mpesa->update(['online_enabled' => true]);
    config(['services.daraja.passkey' => null]);
    expect(app(OnlinePaymentService::class)->isOnline($this->mpesa))->toBeFalse();
});

it('pushes the reservation to the phone and records the attempt', function () {
    fakeGateways();

    $transaction = $this->service->startMpesa($this->booking, '0712 345 678');

    expect($transaction->gateway)->toBe(PaymentGateway::MpesaStk)
        ->and($transaction->status)->toBe(GatewayTransactionStatus::Initiated)
        ->and($transaction->external_ref)->toBe('ws_CO_1')
        ->and($transaction->amount_minor)->toBe(1_658_800)
        ->and($transaction->payer_phone)->toBe('254712345678')
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/processrequest')
        && $request['AccountReference'] === 'BE2026000123'
        && $request['Amount'] === 16588
        && $request['CallBackURL'] === url('hooks/mobile/stk/cb-secret'));
});

it('keeps the car held long enough for the renter to approve', function () {
    fakeGateways([DarajaClient::SANDBOX_URL.'/mpesa/stkpush/v1/processrequest' => Http::sequence()
        ->push(['MerchantRequestID' => 'm', 'CheckoutRequestID' => 'ws_CO_1', 'ResponseCode' => '0'])
        ->push(['MerchantRequestID' => 'm', 'CheckoutRequestID' => 'ws_CO_2', 'ResponseCode' => '0'])]);
    $this->booking->forceFill(['expires_at' => now()->addMinutes(3)])->save();
    $this->booking->availabilityBlocks()->update(['expires_at' => now()->addMinutes(3)]);

    $this->service->startMpesa($this->booking, '0712345678');

    expect($this->booking->fresh()->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue()
        ->and($this->booking->availabilityBlocks()->sole()->expires_at->equalTo(now()->addMinutes(10)))->toBeTrue();

    // A longer hold is left alone.
    $other = Booking::factory()->awaitingPayment()->create();
    BookingDriver::factory()->for($other)->create();
    $this->service->startMpesa($other, '0712345678');
    expect($other->fresh()->expires_at->equalTo(now()->addHours(2)))->toBeTrue();
});

it('refuses to push when it should not', function (Closure $setUp, string $message) {
    fakeGateways();
    $setUp->call($this);

    expect(fn () => $this->service->startMpesa($this->booking, '0712345678'))->toThrow(PaymentException::class, $message);
})->with([
    'method offline' => [fn () => $this->mpesa->update(['online_enabled' => false]), 'not available'],
    'already paid' => [fn () => $this->booking->forceFill(['status' => BookingStatus::Reserved])->save(), 'not taking a payment'],
    'manual payment pending' => [fn () => PaymentSubmission::factory()->for($this->booking)->create(), 'already waiting'],
    'just pushed' => [fn () => GatewayTransaction::factory()->for($this->booking)->create(), 'just sent'],
]);

it('allows another push once the cooldown has passed', function () {
    fakeGateways([DarajaClient::SANDBOX_URL.'/mpesa/stkpush/v1/processrequest' => Http::response(['MerchantRequestID' => 'm', 'CheckoutRequestID' => 'ws_CO_2', 'ResponseCode' => '0'])]);
    GatewayTransaction::factory()->for($this->booking)->create(['created_at' => now()->subMinutes(2)]);

    expect($this->service->startMpesa($this->booking, '0712345678')->external_ref)->toBe('ws_CO_2');
});

it('rejects a phone M-Pesa cannot prompt', function () {
    Http::fake();

    expect(fn () => $this->service->startMpesa($this->booking, '020 123 4567'))->toThrow(PaymentException::class, 'Kenyan mobile');
    Http::assertNothingSent();
});

it('tells the renter to try again when Safaricom is down, recording nothing', function () {
    fakeGateways([DarajaClient::SANDBOX_URL.'/mpesa/stkpush/v1/processrequest' => Http::response(['errorMessage' => 'System busy'], 503)]);

    expect(fn () => $this->service->startMpesa($this->booking, '0712345678'))->toThrow(PaymentException::class, 'M-Pesa could not take the payment');
    expect(GatewayTransaction::count())->toBe(0);
});

it('reserves the booking once the push is paid, as an automatically verified payment', function () {
    fakeGateways();
    $staff = staffWith([Permission::PaymentsView]);
    $transaction = $this->service->startMpesa($this->booking, '0712345678');

    $this->service->refreshStk($transaction, 'TJK1AB2CD3');

    $booking = $this->booking->fresh();
    $submission = $booking->payments()->sole();

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($transaction->fresh()->payment_submission_id)->toBe($submission->id)
        ->and($booking->status)->toBe(BookingStatus::Reserved)
        ->and($booking->reservation_paid_minor)->toBe(1_658_800)
        ->and($booking->balance_minor)->toBe(4_976_200)
        ->and($booking->availabilityBlocks()->sole()->type)->toBe(AvailabilityBlockType::Booked)
        ->and($submission->status)->toBe(PaymentStatus::Verified)
        ->and($submission->isOnline())->toBeTrue()
        ->and($submission->payment_method_code)->toBe(PaymentMethodCode::Mpesa)
        ->and($submission->reference)->toBe('TJK1AB2CD3')
        ->and($submission->verified_by)->toBeNull();

    $timeline = app(BookingStatusService::class)->timeline($booking);
    expect(array_column($timeline, 'to'))->toBe(['payment_submitted', 'reserved'])
        ->and(array_unique(array_column($timeline, 'actor')))->toBe(['system']);

    expect(Activity::query()->where('log_name', 'payments')->where('event', 'verified')->sole()->getProperty('source'))->toBe('mpesa_stk');

    // Staff hear it arrived, with nothing to check.
    expect($staff->notifications()->sole()->data['title'])->toBe('Paid online for BE-2026-000123');
});

it('tells staff the money arrived online and sends the renter the voucher', function () {
    Notification::fake();
    fakeGateways();
    $transaction = $this->service->startMpesa($this->booking, '0712345678');

    $this->service->refreshStk($transaction, 'TJK1AB2CD3');

    Notification::assertSentTo(new AnonymousNotifiable, PaymentSubmitted::class, function (PaymentSubmitted $notification, array $channels, $notifiable) {
        $mail = $notification->toMail($notifiable);
        $whatsApp = $notification->toWhatsApp($notifiable);

        return $mail->subject === 'Paid online for BE-2026-000123'
            && $mail->actionUrl === route('admin.bookings.show', 'BE-2026-000123')
            && collect($mail->introLines)->contains(fn ($line) => str_contains($line, 'TJK1AB2CD3'))
            && str_contains($whatsApp->text, 'nothing to check');
    });
    Notification::assertSentTo(new AnonymousNotifiable, BookingConfirmed::class, fn ($notification, array $channels, $notifiable) => $notifiable->routes['mail'] === 'amina@example.test');
});

it('marks a declined or unanswered push failed, and leaves one still waiting', function (array $answer, int $status, GatewayTransactionStatus $expected) {
    fakeGateways([DarajaClient::SANDBOX_URL.'/mpesa/stkpushquery/v1/query' => Http::response($answer, $status)]);
    $transaction = GatewayTransaction::factory()->for($this->booking)->create();

    $this->service->refreshStk($transaction);

    expect($transaction->fresh()->status)->toBe($expected)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
})->with([
    'cancelled' => [['ResponseCode' => '0', 'ResultCode' => '1032', 'ResultDesc' => 'Request cancelled by user'], 200, GatewayTransactionStatus::Failed],
    'processing' => [['errorCode' => '500.001.1001', 'errorMessage' => 'The transaction is being processed'], 500, GatewayTransactionStatus::Initiated],
]);

it('applies money once however many times it is settled', function () {
    fakeGateways();
    $transaction = GatewayTransaction::factory()->for($this->booking)->create();

    $this->service->refreshStk($transaction, 'R1');
    $this->service->refreshStk($transaction->fresh(), 'R1');
    $this->service->settle($transaction->fresh());

    expect(PaymentSubmission::count())->toBe(1)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
});

it('hands money to staff when it cannot be applied safely', function (Closure $setUp, string $reason) {
    $staff = staffWith([Permission::PaymentsVerify]);
    $transaction = GatewayTransaction::factory()->for($this->booking)->paid()->create();
    $setUp->call($this, $transaction);

    $this->service->settle($transaction);

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::NeedsReview)
        ->and($transaction->fresh()->failure_reason)->toContain($reason)
        ->and(PaymentSubmission::query()->whereNotNull('gateway_transaction_id')->count())->toBe(0);

    $alert = $staff->notifications()->sole();
    expect($alert->type)->toBe(AlertKind::PaymentNeedsReview->value)
        ->and($alert->data['title'])->toBe('Payment to resolve for BE-2026-000123');
})->with([
    'booking expired' => [fn () => $this->booking->forceFill(['status' => BookingStatus::Expired])->save(), 'no longer takes a payment'],
    'short payment' => [fn (GatewayTransaction $transaction) => $transaction->forceFill(['amount_minor' => 1_000_000])->save(), 'Less than the reservation'],
    'manual payment pending' => [fn () => PaymentSubmission::factory()->for($this->booking)->create(), 'waiting to be checked'],
    'car taken meanwhile' => [function () {
        $this->booking->availabilityBlocks()->delete();
        AvailabilityBlock::factory()->create([
            'car_id' => $this->booking->car_id,
            'type' => AvailabilityBlockType::Blocked,
            'starts_at' => $this->booking->pickup_at,
            'ends_at' => $this->booking->return_at,
        ]);
    }, 'car has since been taken'],
]);

it('keeps a paybill receipt that names no booking as unmatched', function () {
    $staff = staffWith([Permission::PaymentsVerify]);
    $transaction = GatewayTransaction::factory()->paid()->create(['gateway' => PaymentGateway::MpesaC2b, 'booking_id' => null]);

    $this->service->settle($transaction);

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Unmatched)
        ->and($staff->notifications()->sole()->data['title'])->toBe('M-Pesa paybill payment with no booking');
});

it('starts a card payment and reserves the booking once Paystack confirms it', function () {
    fakeGateways();

    $start = $this->service->startCard($this->booking);

    expect($start['access_code'])->toBe('ac_1')
        ->and($start['public_key'])->toBe('pk_test_1')
        ->and($start['reference'])->toStartWith('BE2026000123-');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/initialize')
        && $request['email'] === 'amina@example.test'
        && $request['amount'] === 1_658_800
        && $request['reference'] === $start['reference']);

    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(paystackVerified($start['reference']))]);

    $transaction = $this->service->confirmCard($this->booking, $start['reference']);

    expect($transaction->status)->toBe(GatewayTransactionStatus::Matched)
        ->and($transaction->receipt)->toBe('99')
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved)
        ->and($this->booking->payments()->sole()->payment_method_code)->toBe(PaymentMethodCode::Card);
});

it('sends a card charge for a different amount to staff', function () {
    staffWith([Permission::PaymentsVerify]);
    $transaction = GatewayTransaction::factory()->paystack()->for($this->booking)->create();
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(paystackVerified($transaction->external_ref, ['amount' => 100]))]);

    $this->service->confirmCard($this->booking, $transaction->external_ref);

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::NeedsReview)
        ->and($transaction->fresh()->amount_minor)->toBe(100)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

it('leaves an abandoned card payment failed and the booking open', function () {
    $transaction = GatewayTransaction::factory()->paystack()->for($this->booking)->create();
    Http::fake([PaystackClient::URL.'/transaction/verify/*' => Http::response(paystackVerified($transaction->external_ref, ['status' => 'abandoned']))]);

    $this->service->confirmCard($this->booking, $transaction->external_ref);

    expect($transaction->fresh()->status)->toBe(GatewayTransactionStatus::Failed)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::AwaitingPayment);
});

it('only confirms card payments that belong to the booking', function () {
    $other = GatewayTransaction::factory()->paystack()->create();
    Http::fake();

    expect(fn () => $this->service->confirmCard($this->booking, $other->external_ref))->toThrow(PaymentException::class, 'could not find');
    Http::assertNothingSent();
});

it('pushes to at most three phones per booking (M12.17)', function () {
    fakeGateways();
    foreach (['254711000001', '254711000002', '254711000003'] as $phone) {
        GatewayTransaction::factory()->for($this->booking)->create(['payer_phone' => $phone, 'status' => GatewayTransactionStatus::Failed, 'created_at' => now()->subMinutes(5)]);
    }

    expect(fn () => $this->service->startMpesa($this->booking, '0711 000 004'))->toThrow(PaymentException::class, 'several numbers')
        ->and($this->service->startMpesa($this->booking, '0711 000 002')->external_ref)->toBe('ws_CO_1');
});

it('reopens a recent card attempt instead of starting another (M12.17)', function () {
    fakeGateways();

    $first = $this->service->startCard($this->booking);
    $second = $this->service->startCard($this->booking);

    expect($second)->toBe($first);
    Http::assertSentCount(1);

    $this->travel(16)->minutes();

    expect($this->service->startCard($this->booking)['reference'])->not->toBe($first['reference']);
});
