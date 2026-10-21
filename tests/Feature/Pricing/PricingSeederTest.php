<?php

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\ChargePayableAt;
use App\Models\Charge;
use App\Models\Currency;
use App\Models\DeliveryZone;
use App\Models\DepositBand;
use App\Models\FxRate;
use Database\Seeders\PricingSeeder;

it('loads the pricing pack once', function () {
    $this->seed(PricingSeeder::class);
    $this->seed(PricingSeeder::class);

    expect(Currency::count())->toBe(6)
        ->and(Currency::query()->active()->count())->toBe(5)
        ->and(Charge::count())->toBe(8)
        ->and(DeliveryZone::count())->toBe(2)
        ->and(DepositBand::count())->toBe(2)
        ->and(FxRate::count())->toBe(0);
});

it('seeds the PRD amounts', function () {
    $this->seed(PricingSeeder::class);

    expect(Charge::firstWhere('code', 'chauffeur'))
        ->basis->toBe(ChargeBasis::PerDay)
        ->amount_minor->toBe(5_000)
        ->currency_code->toBe('USD')
        ->payable_at->toBe(ChargePayableAt::Reservation)
        ->and(Charge::firstWhere('code', 'additional_driver')->payable_at)->toBe(ChargePayableAt::Counter)
        ->and(Charge::firstWhere('code', 'excess_km')->amount_minor)->toBeNull()
        ->and(Charge::query()->where('applies', ChargeApplies::Listed)->orderBy('code')->pluck('code')->all())->toBe(['admin', 'excess_km', 'valeting'])
        ->and(DeliveryZone::firstWhere('name', 'Nairobi'))->fee_minor->toBe(200_000)->is_default->toBeTrue()
        ->and(DeliveryZone::firstWhere('name', 'Outside Nairobi')->fee_minor)->toBeNull()
        ->and(DepositBand::query()->orderBy('min_age')->pluck('percent_bp', 'min_age')->all())->toBe([23 => 300, 24 => 200])
        ->and(Currency::firstWhere('code', 'KES')->decimals)->toBe(0);
});

it('leaves rows staff changed alone', function () {
    $this->seed(PricingSeeder::class);
    Charge::firstWhere('code', 'chauffeur')->update(['amount_minor' => 6_000]);
    DepositBand::query()->delete();
    DepositBand::factory()->create(['min_age' => 21, 'max_age' => 75]);

    $this->seed(PricingSeeder::class);

    expect(Charge::firstWhere('code', 'chauffeur')->amount_minor)->toBe(6_000)
        ->and(DepositBand::count())->toBe(1);
});
