<?php

use App\Enums\FxRateSource;
use App\Enums\Permission;
use App\Models\Currency;
use App\Models\FxRate;
use App\Services\Cms\SetupChecklistService;
use App\Services\Pricing\FxRateService;
use App\Settings\FxSettings;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PricingFixtures;

beforeEach(fn () => PricingFixtures::currencies());

function apiRate(string $code, string $rate, string $age = '1 hour'): FxRate
{
    return FxRate::factory()->create(['currency_code' => $code, 'market_rate' => $rate, 'fetched_at' => now()->sub($age)]);
}

function manualRate(string $code, string $rate, ?string $until = null): FxRate
{
    return FxRate::factory()->manual()->create(['currency_code' => $code, 'market_rate' => $rate, 'fetched_at' => now()->subMinute(), 'override_until' => $until ? now()->add($until) : null]);
}

describe('precedence (D28)', function () {
    it('uses a fresh API rate even when a manual rate is newer', function () {
        apiRate('USD', '129', '2 hours');
        manualRate('USD', '131');

        $rate = app(FxRateService::class)->table()->get('USD');

        expect($rate)->market->toBe('129.000000')->source->toBe(FxRateSource::Api)->stale->toBeFalse();
    });

    it('falls back to a live manual rate once the API rate is stale', function () {
        apiRate('USD', '129', '25 hours');
        manualRate('USD', '131', '2 days');

        $rate = app(FxRateService::class)->table()->get('USD');

        expect($rate)->market->toBe('131.000000')->source->toBe(FxRateSource::Manual)->stale->toBeFalse();
    });

    it('ignores expired manual rates and flags the old API rate as stale', function () {
        apiRate('USD', '129', '25 hours');
        manualRate('USD', '131', '-1 hour');

        $rate = app(FxRateService::class)->table()->get('USD');

        expect($rate)->market->toBe('129.000000')->stale->toBeTrue();
    });

    it('uses a manual rate when the API has never returned one', function () {
        manualRate('GBP', '163.5');

        expect(app(FxRateService::class)->table()->get('GBP')->market)->toBe('163.500000');
    });

    it('leaves out currencies without any rate and inactive ones', function () {
        apiRate('USD', '129');
        apiRate('AED', '35');
        Currency::firstWhere('code', 'AED')->update(['is_active' => false]);

        $table = app(FxRateService::class)->table();

        expect($table->has('USD'))->toBeTrue()
            ->and($table->has('KES'))->toBeTrue()
            ->and($table->has('GBP'))->toBeFalse()
            ->and($table->has('AED'))->toBeFalse();
    });

    it('applies the current margin when read, not when stored', function () {
        apiRate('USD', '129');
        $table = app(FxRateService::class)->table();
        expect($table->get('USD')->effective())->toBe('126.000000');

        $settings = app(FxSettings::class);
        $settings->margin_minor = 250;
        $settings->save();

        expect(app(FxRateService::class)->table()->get('USD')->effective())->toBe('126.500000');
    });
});

describe('conversion', function () {
    it('converts foreign charges at the market rate and display prices at market minus margin', function () {
        apiRate('USD', '129');
        $table = app(FxRateService::class)->table();

        // PRD §5: USD 50 chauffeur at 129 = KES 6,450; KES 66,350 shows as USD 526.59 at 126.
        expect($table->toBase(Money::fromMajor(50, 'USD'))->minor)->toBe(645_000)
            ->and($table->display(Money::fromMajor(66_350), 'USD')->minor)->toBe(52_659)
            ->and($table->display(Money::fromMajor(16_588), 'USD')->minor)->toBe(13_165)
            ->and($table->display(Money::fromMajor(160_000), 'USD')->minor)->toBe(126_984);
    });
});

describe('admin', function () {
    it('shows the rates in force and the history', function () {
        apiRate('USD', '129');
        manualRate('GBP', '163.5');

        $this->actingAs(staffWith([Permission::FxRatesView]))
            ->get(route('admin.fx-rates.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/fx-rates/index')
                ->where('base', 'KES')
                ->has('rates', 2)
                ->where('rates.0.code', 'USD')
                ->where('rates.0.effective', '126.000000')
                ->has('history', 2));
    });

    it('stores a manual rate with an optional end and logs it', function () {
        $staff = staffWith([Permission::FxRatesOverride]);

        $this->actingAs($staff)
            ->post(route('admin.fx-rates.store'), ['currency_code' => 'EUR', 'market_rate' => '140.255', 'override_until' => now()->addDay()->toIso8601String()])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.fx-rates.index'));

        $row = FxRate::sole();
        expect($row)->source->toBe(FxRateSource::Manual)->market_rate->toBe('140.255000')->override_by->toBe($staff->id);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'fx', 'event' => 'manual-rate']);
    });

    it('validates manual rates', function (array $input, string $field) {
        $this->actingAs(staffWith([Permission::FxRatesOverride]))
            ->post(route('admin.fx-rates.store'), [...['currency_code' => 'USD', 'market_rate' => '129'], ...$input])
            ->assertSessionHasErrors($field);
    })->with([
        'base currency' => [['currency_code' => 'KES'], 'currency_code'],
        'unknown currency' => [['currency_code' => 'XYZ'], 'currency_code'],
        'zero' => [['market_rate' => '0.000'], 'market_rate'],
        'seven places' => [['market_rate' => '129.1234567'], 'market_rate'],
        'negative' => [['market_rate' => '-1'], 'market_rate'],
        'past end' => [['override_until' => '2020-01-01'], 'override_until'],
    ]);

    it('ends a manual rate early', function () {
        apiRate('USD', '129', '30 hours');
        $manual = manualRate('USD', '131');

        $this->actingAs(staffWith([Permission::FxRatesOverride]))
            ->put(route('admin.fx-rates.expire', $manual))
            ->assertRedirect();

        expect(app(FxRateService::class)->table()->get('USD'))->market->toBe('129.000000')->stale->toBeTrue();
    });

    it('needs fx-rates.override to change rates', function () {
        $manual = manualRate('USD', '131');
        $viewer = staffWith([Permission::FxRatesView]);

        $this->actingAs($viewer)->post(route('admin.fx-rates.store'), ['currency_code' => 'USD', 'market_rate' => '1'])->assertForbidden();
        $this->actingAs($viewer)->put(route('admin.fx-rates.expire', $manual))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.fx-rates.refresh'))->assertForbidden();
    });
});

describe('dashboard', function () {
    it('lists missing and stale rates for staff who can see them', function () {
        apiRate('USD', '129', '30 hours');

        $keys = collect(app(SetupChecklistService::class)->outstanding(staffWith([Permission::FxRatesView])))->pluck('key');

        expect($keys)->toContain('fx.missing', 'fx.stale')->not->toContain('fx.key');
    });
});
