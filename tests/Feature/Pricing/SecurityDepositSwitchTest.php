<?php

use App\Enums\Permission;
use App\Exceptions\Domain\QuoteException;
use App\Http\Resources\Public\QuoteResource;
use App\Models\Car;
use App\Models\DepositBand;
use App\Services\Pricing\QuoteService;
use App\Settings\PricingSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\PricingSeeder;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();
    $this->car = Car::factory()->create(['daily_rate_minor' => 1_500_000, 'car_value_minor' => 800_000_000, 'is_published' => true]);
});

function depositQuote(?string $dob)
{
    return app(QuoteService::class)->quote(
        test()->car,
        CarbonImmutable::parse('2026-12-02 10:00', 'Africa/Nairobi'),
        CarbonImmutable::parse('2026-12-04 10:00', 'Africa/Nairobi'),
        driverDob: $dob === null ? null : CarbonImmutable::parse($dob),
    );
}

function setDeposits(bool $enabled): void
{
    $pricing = app(PricingSettings::class);
    $pricing->security_deposit_enabled = $enabled;
    $pricing->save();
}

it('quotes a deposit range before the driver gives an age', function () {
    $bands = DepositBand::query()->active()->pluck('percent_bp');
    $quote = depositQuote(null);

    expect($quote->deposit)->toBeNull()
        ->and($quote->depositRange->min->minor)->toBe((int) round(800_000_000 * $bands->min() / 10_000))
        ->and($quote->depositRange->max->minor)->toBe((int) round(800_000_000 * $bands->max() / 10_000))
        ->and($quote->toArray()['deposit_range'])->not->toBeNull();
});

it('quotes the exact deposit once the age is known, without a range', function () {
    $quote = depositQuote('1991-03-15');

    expect($quote->deposit)->not->toBeNull()
        ->and($quote->depositRange)->toBeNull();
});

it('quotes no deposit and no range when deposits are switched off', function () {
    setDeposits(false);

    expect(depositQuote(null)->depositRange)->toBeNull()
        ->and(depositQuote('1991-03-15')->deposit)->toBeNull();
});

it('still refuses a driver outside every band when deposits are off', function () {
    setDeposits(false);

    depositQuote(now()->subYears(17)->toDateString());
})->throws(QuoteException::class);

it('tells renters a deposit may apply on the car page, without an amount', function () {
    $this->get(route('cars.show', $this->car))
        ->assertInertia(fn ($page) => $page
            ->where('deposit_may_apply', true)
            ->missing('deposit_range')
            ->missing('car.car_value_minor'));

    setDeposits(false);

    $this->get(route('cars.show', $this->car))
        ->assertInertia(fn ($page) => $page->where('deposit_may_apply', false));
});

it('sends the live quote a flag, not deposit amounts, before the age is known', function () {
    $json = QuoteResource::make(depositQuote(null))->resolve();

    expect($json['deposit_may_apply'])->toBeTrue()
        ->and($json['deposit'])->toBeNull()
        ->and($json)->not->toHaveKey('deposit_range');
});

it('lets staff switch deposits off from pricing settings', function () {
    $this->actingAs(staffWith([Permission::SettingsPricingUpdate]))
        ->put(route('admin.settings.update', 'pricing'), [
            'reservation_percent' => '25',
            'reservation_rounding' => 'round',
            'day_counting' => 'daytime_blocks',
            'daytime_window_start' => '08:00',
            'daytime_window_end' => '20:00',
            'rolling_grace_minutes' => 30,
            'hold_minutes' => 90,
            'draft_expiry_hours' => 12,
            'turnaround_minutes' => 60,
            'cash_hold_hours' => 24,
            'default_mileage_km' => 250,
            'unlimited_mileage_after_days' => null,
            'security_deposit_enabled' => false,
        ])
        ->assertSessionHasNoErrors();

    expect(app(PricingSettings::class)->security_deposit_enabled)->toBeFalse();
});
