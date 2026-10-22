<?php

namespace Database\Seeders;

use App\Enums\FuelType;
use App\Enums\FxRateSource;
use App\Enums\Transmission;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use App\Models\Currency;
use App\Models\FxRate;
use Database\Seeders\Concerns\AttachesCarPhotos;
use Illuminate\Database\Seeder;

/**
 * Sample fleet for local and staging (M4.11). Each car gets the "photo coming soon"
 * placeholder and is published, so the public site has cards to show (M7). Also sets manual
 * exchange rates, so prices convert before an FX API key exists (M6). The real cars come from
 * FleetSeeder. Safe to run again: rows are matched by name, and a car that already has photos
 * keeps them. Brand and type names match the seed pack, so after CmsSeeder they reuse its rows.
 */
class DemoCatalogSeeder extends Seeder
{
    use AttachesCarPhotos;

    private const BRANDS = ['Toyota', 'Land Rover', 'Mercedes-Benz', 'BMW', 'Lexus', 'Nissan', 'Volkswagen', 'Porsche'];

    // The client's car types (information sheet); descriptions are only used when CmsSeeder has not run.
    private const TYPES = [
        'Executive Sedans' => 'Chauffeur-ready cars for VIP transfers.',
        'Executive SUVs' => 'Executive comfort for business and events.',
        'SUVs' => 'Comfortable on city roads and rough tracks.',
        'Medium SUVs' => 'Compact SUVs for town and weekend trips.',
        'Safari Landcruiser' => 'Built for game drives and long-distance travel.',
        'Sports Cars' => 'Performance for special occasions.',
        'Economy' => 'Efficient cars for getting around town.',
    ];

    /**
     * name, brand, type, fuel, transmission, doors, seats, daily rate (KES), extra info
     *
     * @var list<array{0: string, 1: string, 2: string, 3: FuelType, 4: Transmission, 5: int, 6: int, 7: int, 8: array<string, string>}>
     */
    private const CARS = [
        ['Toyota Land Cruiser V8', 'Toyota', 'Safari Landcruiser', FuelType::Diesel, Transmission::Automatic, 5, 7, 25_000, ['drive' => '4WD', 'luggage_bags' => '5']],
        ['Toyota Prado TX', 'Toyota', 'SUVs', FuelType::Diesel, Transmission::Automatic, 5, 7, 15_000, ['drive' => '4WD', 'luggage_bags' => '4']],
        ['Toyota Harrier', 'Toyota', 'Medium SUVs', FuelType::Hybrid, Transmission::Automatic, 5, 5, 8_000, []],
        ['Range Rover Sport', 'Land Rover', 'Executive SUVs', FuelType::Petrol, Transmission::Automatic, 5, 5, 35_000, ['zero_to_hundred_s' => '5.9', 'drive' => 'AWD']],
        ['Range Rover Vogue', 'Land Rover', 'Executive SUVs', FuelType::Diesel, Transmission::Automatic, 5, 5, 45_000, ['features' => 'Massage seats, Meridian audio']],
        ['Mercedes-Benz S-Class', 'Mercedes-Benz', 'Executive Sedans', FuelType::Petrol, Transmission::Automatic, 4, 5, 40_000, ['zero_to_hundred_s' => '4.9']],
        ['BMW X5', 'BMW', 'Executive SUVs', FuelType::Hybrid, Transmission::Automatic, 5, 5, 28_000, ['zero_to_hundred_s' => '5.6', 'drive' => 'AWD']],
        ['BMW 7 Series', 'BMW', 'Executive Sedans', FuelType::Petrol, Transmission::Automatic, 4, 5, 42_000, []],
        ['Lexus LX 600', 'Lexus', 'Safari Landcruiser', FuelType::Petrol, Transmission::Automatic, 5, 7, 38_000, ['drive' => '4WD']],
        ['Nissan X-Trail', 'Nissan', 'Medium SUVs', FuelType::Petrol, Transmission::Automatic, 5, 5, 9_000, []],
        ['Porsche 911 Carrera', 'Porsche', 'Sports Cars', FuelType::Petrol, Transmission::Automatic, 2, 4, 60_000, ['zero_to_hundred_s' => '4.2']],
        ['Volkswagen Polo', 'Volkswagen', 'Economy', FuelType::Petrol, Transmission::Manual, 5, 5, 5_000, []],
    ];

    // KES per unit; a fresh API rate replaces these as soon as one is fetched (D28).
    private const DEMO_RATES = ['USD' => '129', 'GBP' => '163.5', 'EUR' => '140.25', 'AED' => '35.12'];

    public function run(): void
    {
        $this->rates();

        $brands = collect(self::BRANDS)->mapWithKeys(
            fn (string $name) => [$name => Brand::query()->firstOrCreate(['name' => $name])],
        );

        $types = collect(self::TYPES)->mapWithKeys(
            fn (string $description, string $name) => [$name => CarType::query()->firstOrCreate(['name' => $name], ['description' => $description])],
        );

        foreach (self::CARS as [$name, $brand, $type, $fuel, $transmission, $doors, $seats, $rate, $extra]) {
            $car = Car::query()->firstOrCreate(['name' => $name], [
                'brand_id' => $brands[$brand]->id,
                'car_type_id' => $types[$type]->id,
                'fuel_type' => $fuel,
                'transmission' => $transmission,
                'doors' => $doors,
                'seats' => $seats,
                'daily_rate_minor' => $rate * 100,
                'car_value_minor' => $this->value($name, $rate) * 100,
                'metadata' => $extra ?: null,
            ]);

            $this->attachPhotos($car, ['placeholder-car.jpg']);
        }
    }

    /**
     * A rough car value in KES: 500 days' rent, except the PRD worked example's Prado (8M).
     */
    private function value(string $name, int $dailyRate): int
    {
        return $name === 'Toyota Prado TX' ? 8_000_000 : $dailyRate * 500;
    }

    private function rates(): void
    {
        foreach (self::DEMO_RATES as $code => $rate) {
            if (Currency::query()->where('code', $code)->exists() && ! FxRate::query()->where('currency_code', $code)->exists()) {
                FxRate::create(['currency_code' => $code, 'market_rate' => $rate, 'source' => FxRateSource::Manual, 'fetched_at' => now()]);
            }
        }
    }
}
