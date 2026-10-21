<?php

namespace App\Services\Pricing\Quote;

use App\Models\Car;
use App\Models\DeliveryZone;
use Carbon\CarbonImmutable;

/**
 * What the renter asked for. Amounts are KES minor units (D6).
 */
final readonly class QuoteInput
{
    /**
     * @param  list<string>  $extras  codes of the charges the renter picked
     */
    public function __construct(
        public string $carName,
        public int $dailyRateMinor,
        public ?int $carValueMinor,
        public ?int $mileageLimitKm,
        public CarbonImmutable $pickup,
        public CarbonImmutable $return,
        public array $extras = [],
        public ?DeliveryZone $zone = null,
        public ?CarbonImmutable $driverDob = null,
    ) {}

    /**
     * @param  list<string>  $extras
     */
    public static function forCar(Car $car, CarbonImmutable $pickup, CarbonImmutable $return, array $extras = [], ?DeliveryZone $zone = null, ?CarbonImmutable $driverDob = null): self
    {
        return new self($car->name, $car->daily_rate_minor, $car->car_value_minor, $car->mileage_limit_km, $pickup, $return, $extras, $zone, $driverDob);
    }
}
