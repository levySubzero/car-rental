<?php

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\Car;
use App\Models\PaymentSubmission;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00', 'Africa/Nairobi'));

    $car = Car::factory()->published()->create(['name' => 'Prado']);
    $booking = Booking::factory()->reserved()->create(['car_id' => $car->id, 'status' => BookingStatus::Cancelled]);
    PaymentSubmission::factory()->verified(1_658_800)->create([
        'booking_id' => $booking->id,
        'payment_method_code' => 'bank',
        'reference' => 'BANK42',
        'verified_at' => CarbonImmutable::parse('2026-11-03 09:00', 'Africa/Nairobi')->utc(),
    ]);
    $booking->forceFill([
        'refund_minor' => 829_400,
        'refund_reference' => 'RF1',
        'refunded_at' => CarbonImmutable::parse('2026-11-04 10:00', 'Africa/Nairobi')->utc(),
    ])->save();
    $this->booking = $booking;
});

it('shows this month by default', function () {
    $this->actingAs(staffWith([Permission::FinanceView]))
        ->get(route('admin.finance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/finance/index')
            ->where('filters', ['from' => '2026-11-01', 'to' => '2026-11-20'])
            ->where('summary.collected_minor', 1_658_800)
            ->where('summary.refunds_minor', 829_400)
            ->where('summary.net_minor', 829_400)
            ->has('daily', 20)
            ->where('channels.0.channel', 'bank')
            ->where('cars.0.car', 'Prado')
            ->has('ledger.data', 2)
            ->where('ledger.data.0.kind', 'refund')
            ->where('ledger.meta.total', 2)
            ->where('canExport', false));
});

it('takes a date range', function () {
    $this->actingAs(staffWith([Permission::FinanceView]))
        ->get(route('admin.finance.index', ['from' => '2026-11-04', 'to' => '2026-11-04']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.collected_minor', 0)
            ->where('summary.refunds_minor', 829_400)
            ->has('daily', 1));
});

it('refuses a bad range', function (array $query, string $field) {
    $this->actingAs(staffWith([Permission::FinanceView]))
        ->get(route('admin.finance.index', $query))
        ->assertSessionHasErrors($field);
})->with([
    'to before from' => [['from' => '2026-11-10', 'to' => '2026-11-01'], 'to'],
    'longer than a year' => [['from' => '2025-01-01', 'to' => '2026-11-01'], 'to'],
    'not a date' => [['from' => 'soon', 'to' => '2026-11-01'], 'from'],
    'one end only' => [['from' => '2026-11-01'], 'to'],
]);

it('is forbidden without finance.view', function () {
    $this->actingAs(staffWith([Permission::PaymentsView]))
        ->get(route('admin.finance.index'))
        ->assertForbidden();
});

it('sends guests to log in', function () {
    $this->get(route('admin.finance.index'))->assertRedirect(route('login'));
});

it('exports the ledger as CSV, refunds negative', function () {
    $response = $this->actingAs(staffWith([Permission::FinanceView, Permission::FinanceExport]))
        ->get(route('admin.finance.export', ['from' => '2026-11-01', 'to' => '2026-11-30']));

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->assertDownload('baines-revenue-2026-11-01-2026-11-30.csv');

    $lines = array_map(str_getcsv(...), explode("\n", trim(ltrim($response->streamedContent(), "\u{FEFF}"))));

    expect($lines)->toBe([
        ['Date', 'Time', 'Type', 'Booking', 'Car', 'Channel', 'Reference', 'Amount (KES)'],
        ['2026-11-03', '09:00', 'Reservation payment', $this->booking->reference, 'Prado', 'Bank transfer', 'BANK42', '16588.00'],
        ['2026-11-04', '10:00', 'Refund', $this->booking->reference, 'Prado', '', 'RF1', '-8294.00'],
    ]);
});

it('keeps the export to finance.export', function () {
    $this->actingAs(staffWith([Permission::FinanceView]))
        ->get(route('admin.finance.export'))
        ->assertForbidden();
});

it('gives the operations role revenue but not the export', function () {
    $this->seed();

    $ops = staffWithRole('operations');

    expect($ops->can(Permission::FinanceView))->toBeTrue()
        ->and($ops->can(Permission::FinanceExport))->toBeFalse();
});
