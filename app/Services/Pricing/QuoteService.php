<?php

namespace App\Services\Pricing;

use App\Exceptions\Domain\QuoteException;
use App\Models\Car;
use App\Models\Charge;
use App\Models\DeliveryZone;
use App\Models\DepositBand;
use App\Services\Pricing\Quote\Quote;
use App\Services\Pricing\Quote\QuoteCalculator;
use App\Services\Pricing\Quote\QuoteContext;
use App\Services\Pricing\Quote\QuoteInput;
use App\Services\Pricing\Quote\QuoteSettings;
use App\Settings\PricingSettings;
use Carbon\CarbonImmutable;

/**
 * The one quote engine (M6.10, D31): the admin sandbox, the public preview and booking
 * submit (M8) all price through here. It loads the rules in force and hands them to the
 * pure QuoteCalculator.
 */
class QuoteService
{
    public function __construct(
        private readonly FxRateService $fx,
        private readonly QuoteCalculator $calculator,
    ) {}

    /**
     * @param  list<string>  $extras  codes of the charges the renter picked
     */
    public function quote(Car $car, CarbonImmutable $pickup, CarbonImmutable $return, array $extras = [], ?int $deliveryZoneId = null, ?CarbonImmutable $driverDob = null): Quote
    {
        $zone = null;

        if ($deliveryZoneId !== null) {
            $zone = DeliveryZone::query()->active()->find($deliveryZoneId) ?? throw QuoteException::unknownZone();
        }

        return $this->calculator->calculate(
            QuoteInput::forCar($car, $pickup, $return, $extras, $zone, $driverDob),
            $this->context(),
        );
    }

    /**
     * What the quote sandbox offers: every car (drafts too), the extras renters can pick
     * and the active delivery zones.
     *
     * @return array{cars: list<array{value: string, label: string}>, extras: list<array{value: string, label: string}>, zones: list<array{value: string, label: string}>}
     */
    public function sandboxOptions(): array
    {
        return [
            'cars' => array_values(Car::query()->orderBy('name')->get(['slug', 'name', 'daily_rate_minor', 'is_published'])
                ->map(fn (Car $car) => [
                    'value' => $car->slug,
                    'label' => $car->name.' — '.$car->dailyRate()->format().'/day'.($car->is_published ? '' : ' (draft)'),
                ])->all()),
            'extras' => $this->extraOptions(),
            'zones' => array_values(DeliveryZone::query()->active()->ordered()->get()
                ->map(fn (DeliveryZone $zone) => ['value' => (string) $zone->id, 'label' => $zone->name.' — '.($zone->fee()?->format() ?? 'quoted by staff')])
                ->all()),
        ];
    }

    /**
     * The extras a renter can pick, by charge code.
     *
     * @return list<array{value: string, label: string}>
     */
    public function extraOptions(): array
    {
        return array_values(Charge::query()->offeredAsExtra()->ordered()->get()
            ->map(fn (Charge $charge) => ['value' => $charge->code, 'label' => $charge->name])
            ->all());
    }

    public function context(): QuoteContext
    {
        return new QuoteContext(
            settings: QuoteSettings::fromSettings(app(PricingSettings::class)),
            fx: $this->fx->table(),
            charges: array_values(Charge::query()->active()->ordered()->get()->all()),
            bands: array_values(DepositBand::query()->active()->orderBy('min_age')->get()->all()),
        );
    }
}
