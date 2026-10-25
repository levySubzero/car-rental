<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Car;
use App\Models\GatewayTransaction;
use App\Models\PaymentSubmission;
use App\Services\Finance\FinancePeriod;
use App\Services\Finance\FinanceService;
use Carbon\CarbonImmutable;

// A hand-built November in Nairobi (UTC+3). Amounts are KES minor units. Times are stored in
// UTC, as the app stores them.
function nairobiTime(string $at): CarbonImmutable
{
    return CarbonImmutable::parse($at, 'Africa/Nairobi')->utc();
}

function ledgerPayment(Booking $booking, int $amount, string $at, PaymentMethodCode $method = PaymentMethodCode::Bank, ?GatewayTransaction $gateway = null): PaymentSubmission
{
    return PaymentSubmission::factory()->verified($amount)->create([
        'booking_id' => $booking->id,
        'payment_method_code' => $method,
        'gateway_transaction_id' => $gateway?->id,
        'verified_at' => nairobiTime($at),
    ]);
}

function ledgerHandover(Booking $booking, string $type, string $at, array $amounts): void
{
    $booking->handoverRecords()->create([
        'type' => $type,
        'odometer_km' => 1000,
        'fuel_level' => 'full',
        'recorded_at' => nairobiTime($at),
        ...$amounts,
    ]);
}

beforeEach(function () {
    $this->travelTo(nairobiTime('2026-11-20 12:00'));

    $this->prado = Car::factory()->published()->create(['name' => 'Prado']);
    $this->vitz = Car::factory()->published()->create(['name' => 'Vitz']);
    $this->completed = Booking::factory()->reserved()->create(['car_id' => $this->prado->id, 'status' => BookingStatus::Completed]);
    $this->cancelled = Booking::factory()->reserved()->create(['car_id' => $this->vitz->id, 'status' => BookingStatus::Cancelled]);

    // Either side of midnight on 1 November, Nairobi time.
    ledgerPayment($this->completed, 1_000_000, '2026-10-31 23:30');
    ledgerPayment($this->completed, 2_000_000, '2026-11-01 00:30');

    $stk = GatewayTransaction::factory()->paid()->create(['booking_id' => $this->completed->id]);
    ledgerPayment($this->completed, 300_000, '2026-11-02 10:00', PaymentMethodCode::Mpesa, $stk);
    $c2b = GatewayTransaction::factory()->unmatched()->create(['booking_id' => $this->cancelled->id, 'status' => 'matched']);
    ledgerPayment($this->cancelled, 400_000, '2026-11-02 11:00', PaymentMethodCode::Mpesa, $c2b);
    $card = GatewayTransaction::factory()->paystack()->paid()->create(['booking_id' => $this->cancelled->id]);
    ledgerPayment($this->cancelled, 500_000, '2026-11-03 09:00', PaymentMethodCode::Card, $card);
    ledgerPayment($this->cancelled, 600_000, '2026-11-03 09:30', PaymentMethodCode::Mpesa);

    // Never counted: still pending, and rejected.
    PaymentSubmission::factory()->create(['booking_id' => $this->completed->id, 'amount_minor' => 9_999_999]);
    PaymentSubmission::factory()->rejected()->create(['booking_id' => $this->completed->id, 'amount_minor' => 9_999_999, 'verified_at' => nairobiTime('2026-11-05 10:00')]);

    // The Prado went out with its balance and a deposit, and came back with extra charges.
    ledgerHandover($this->completed, 'handover', '2026-11-05 10:00', ['balance_received_minor' => 4_000_000, 'deposit_received_minor' => 16_000_000]);
    ledgerHandover($this->completed, 'return', '2026-11-08 18:00', ['deposit_refunded_minor' => 15_000_000, 'extra_charges_minor' => 250_000]);

    // The Vitz booking was cancelled and part refunded.
    $this->cancelled->forceFill(['refund_minor' => 700_000, 'refund_reference' => 'RF1', 'refunded_at' => nairobiTime('2026-11-10 15:00')])->save();

    // On hire now with the deposit still held; and a firm booking whose balance is still due.
    $onHire = Booking::factory()->active()->create(['car_id' => $this->vitz->id]);
    ledgerHandover($onHire, 'handover', '2026-10-25 10:00', ['balance_received_minor' => 4_976_200, 'deposit_received_minor' => 8_000_000]);
    Booking::factory()->reserved()->create(['car_id' => $this->vitz->id, 'balance_minor' => 3_000_000]);
});

it('adds up collected, refunds and net for the period, by Nairobi day', function () {
    $summary = app(FinanceService::class)->summary(FinancePeriod::fromDates('2026-11-01', '2026-11-30'));

    // 2,000,000 + 300,000 + 400,000 + 500,000 + 600,000 payments; 4,000,000 balance; 250,000 extras.
    expect($summary['collected_minor'])->toBe(8_050_000)
        ->and($summary['refunds_minor'])->toBe(700_000)
        ->and($summary['net_minor'])->toBe(7_350_000);
});

it('counts the payment before midnight in October', function () {
    $october = app(FinanceService::class)->summary(FinancePeriod::fromDates('2026-10-01', '2026-10-31'));

    // The 23:30 payment and the on-hire booking's balance at its handover.
    expect($october['collected_minor'])->toBe(1_000_000 + 4_976_200);
});

it('holds only deposits whose car has not come back, and lists balances still due', function () {
    $summary = app(FinanceService::class)->summary(FinancePeriod::fromDates('2026-11-01', '2026-11-30'));

    expect($summary['deposits_held_minor'])->toBe(8_000_000)
        ->and($summary['outstanding_count'])->toBe(1)
        ->and($summary['outstanding_minor'])->toBe(3_000_000);
});

it('splits collected money by channel and by car', function () {
    $report = app(FinanceService::class)->report(FinancePeriod::fromDates('2026-11-01', '2026-11-30'));

    expect(collect($report['channels'])->pluck('amount_minor', 'channel')->all())->toBe([
        'handover' => 4_250_000,
        'bank' => 2_000_000,
        'mpesa_manual' => 600_000,
        'paystack' => 500_000,
        'mpesa_paybill' => 400_000,
        'mpesa_stk' => 300_000,
    ])->and($report['cars'])->toBe([
        ['car_id' => $this->prado->id, 'car' => 'Prado', 'collected_minor' => 6_550_000, 'refunds_minor' => 0, 'net_minor' => 6_550_000],
        ['car_id' => $this->vitz->id, 'car' => 'Vitz', 'collected_minor' => 1_500_000, 'refunds_minor' => 700_000, 'net_minor' => 800_000],
    ]);
});

it('gives every day of the period, empty days included', function () {
    $daily = app(FinanceService::class)->daily(FinancePeriod::fromDates('2026-11-01', '2026-11-10'));

    expect($daily)->toHaveCount(10)
        ->and($daily[0])->toBe(['date' => '2026-11-01', 'collected_minor' => 2_000_000, 'refunds_minor' => 0])
        ->and($daily[3])->toBe(['date' => '2026-11-04', 'collected_minor' => 0, 'refunds_minor' => 0])
        ->and($daily[9])->toBe(['date' => '2026-11-10', 'collected_minor' => 0, 'refunds_minor' => 700_000]);
});

it('lists the ledger newest first with refunds negative', function () {
    $ledger = app(FinanceService::class)->paginateLedger(FinancePeriod::fromDates('2026-11-01', '2026-11-30'), perPage: 3);

    expect($ledger->total())->toBe(8)
        ->and($ledger->items()[0])->toMatchArray(['kind' => 'refund', 'amount_minor' => -700_000, 'reference' => 'RF1', 'car' => 'Vitz'])
        ->and($ledger->items()[1])->toMatchArray(['kind' => 'return', 'amount_minor' => 250_000, 'channel' => 'handover'])
        ->and($ledger->items()[2])->toMatchArray(['kind' => 'handover', 'amount_minor' => 4_000_000, 'date' => '2026-11-05']);
});

it('defaults to this month so far', function () {
    $period = FinancePeriod::fromDates(null, null);

    expect($period->from->toDateString())->toBe('2026-11-01')
        ->and($period->to->toDateString())->toBe('2026-11-20')
        ->and($period->start->toIso8601String())->toBe('2026-10-31T21:00:00+00:00')
        ->and($period->days())->toBe(20);
});

it('ignores pending and rejected payments', function () {
    expect(PaymentSubmission::query()->where('status', '!=', PaymentStatus::Verified)->count())->toBe(2)
        ->and(app(FinanceService::class)->entries(FinancePeriod::fromDates('2026-11-01', '2026-11-30'))
            ->where('amountMinor', 9_999_999))->toBeEmpty();
});
