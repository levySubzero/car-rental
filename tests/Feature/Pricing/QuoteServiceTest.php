<?php

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Exceptions\Domain\QuoteException;
use App\Models\Car;
use App\Models\Charge;
use App\Models\DeliveryZone;
use App\Services\Pricing\Quote\Quote;
use App\Services\Pricing\QuoteService;
use App\Settings\FxSettings;
use App\Settings\PricingSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\PricingSeeder;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();
    $this->car = Car::factory()->create(['name' => 'Toyota Prado TX', 'daily_rate_minor' => 1_500_000, 'car_value_minor' => 800_000_000]);
});

function prdQuote(): Quote
{
    return app(QuoteService::class)->quote(
        test()->car,
        CarbonImmutable::parse('2026-11-02 10:00', 'Africa/Nairobi'),
        CarbonImmutable::parse('2026-11-04 10:00', 'Africa/Nairobi'),
        ['chauffeur'],
        DeliveryZone::firstWhere('name', 'Nairobi')->id,
        CarbonImmutable::parse('1991-03-15'),
    );
}

it('prices the PRD worked example from the seeded rules', function () {
    $quote = prdQuote();

    expect($quote->days)->toBe(3)
        ->and($quote->total->toMajorString())->toBe('66350.00')
        ->and($quote->reservation->toMajorString())->toBe('16588.00')
        ->and($quote->balance->toMajorString())->toBe('49762.00')
        ->and($quote->deposit->toMajorString())->toBe('160000.00')
        ->and($quote->display['USD']['total']->toMajorString())->toBe('526.59')
        ->and(array_keys($quote->display))->toBe(['USD', 'GBP', 'EUR', 'AED']);
});

it('produces a snapshot that later setting changes cannot alter', function () {
    $snapshot = json_encode(prdQuote()->toArray());

    $pricing = app(PricingSettings::class);
    $pricing->reservation_percent_bp = 5000;
    $pricing->save();
    $fx = app(FxSettings::class);
    $fx->margin_minor = 500;
    $fx->save();

    $decoded = json_decode((string) $snapshot, true);

    expect($decoded['reservation']['major'])->toBe('16588.00')
        ->and($decoded['fx']['rates']['USD']['effective'])->toBe('126.000000')
        ->and($decoded['settings']['reservation_percent_bp'])->toBe(2500)
        ->and($decoded['settings_hash'])->toHaveLength(64)
        ->and(prdQuote()->reservation->toMajorString())->toBe('33175.00')
        ->and(prdQuote()->toArray()['settings_hash'])->not->toBe($decoded['settings_hash']);
});

it('refuses inactive or unknown delivery zones', function () {
    $zone = DeliveryZone::factory()->create(['is_active' => false]);

    app(QuoteService::class)->quote($this->car, now(), now()->addDay(), [], $zone->id);
})->throws(QuoteException::class);

it('quotes an extra staff created, without any code change', function () {
    Charge::factory()->create(['code' => 'child_seat', 'name' => 'Child seat', 'basis' => ChargeBasis::PerDay, 'amount_minor' => 50_000, 'applies' => ChargeApplies::Extra]);

    $quote = app(QuoteService::class)->quote($this->car, CarbonImmutable::parse('2026-11-02 10:00', 'Africa/Nairobi'), CarbonImmutable::parse('2026-11-03 10:00', 'Africa/Nairobi'), ['child_seat']);

    expect(collect($quote->lines)->firstWhere('code', 'child_seat'))->counted->toBeTrue()
        ->and($quote->total->toMajorString())->toBe('31000.00')
        ->and(app(QuoteService::class)->extraOptions())->toContain(['value' => 'child_seat', 'label' => 'Child seat']);
});

it('adds always-on charges unasked and never adds listed ones', function () {
    Charge::factory()->create(['code' => 'admin_fee', 'amount_minor' => 50_000, 'applies' => ChargeApplies::Always]);

    $quote = app(QuoteService::class)->quote($this->car, CarbonImmutable::parse('2026-11-02 10:00', 'Africa/Nairobi'), CarbonImmutable::parse('2026-11-02 18:00', 'Africa/Nairobi'), ['valeting']);
    $codes = collect($quote->lines)->pluck('code')->all();

    expect($codes)->toContain('admin_fee')->not->toContain('valeting')
        ->and($quote->total->toMajorString())->toBe('15500.00');
});
