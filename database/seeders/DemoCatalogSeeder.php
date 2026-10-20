<?php

namespace Database\Seeders;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use Illuminate\Database\Seeder;

/**
 * Sample fleet for local and staging (M4.11). Cars have no photos, so they stay drafts:
 * add photos in the admin, then publish. The real catalogue comes from the seed pack (M5.9).
 * Safe to run again: rows are matched by name.
 */
class DemoCatalogSeeder extends Seeder
{
    private const BRANDS = ['Toyota', 'Land Rover', 'Mercedes-Benz', 'BMW', 'Lexus', 'Nissan', 'Mazda', 'Volkswagen'];

    private const TYPES = [
        'SUV' => 'Comfortable on city roads and rough tracks.',
        '4x4 Safari' => 'Built for game drives and long-distance travel.',
        'Luxury Sedan' => 'Executive comfort for business and events.',
        'Executive' => 'Chauffeur-ready cars for VIP transfers.',
        'Economy' => 'Efficient cars for getting around town.',
        'Van' => 'Room for groups and luggage.',
    ];

    /**
     * name, brand, type, fuel, transmission, seats, daily rate (KES), extra info
     *
     * @var list<array{0: string, 1: string, 2: string, 3: FuelType, 4: Transmission, 5: int, 6: int, 7: array<string, string>}>
     */
    private const CARS = [
        ['Toyota Land Cruiser V8', 'Toyota', '4x4 Safari', FuelType::Diesel, Transmission::Automatic, 7, 25_000, ['drive' => '4WD', 'luggage_bags' => '5']],
        ['Toyota Prado TX', 'Toyota', 'SUV', FuelType::Diesel, Transmission::Automatic, 7, 15_000, ['drive' => '4WD', 'luggage_bags' => '4']],
        ['Range Rover Sport', 'Land Rover', 'SUV', FuelType::Petrol, Transmission::Automatic, 5, 35_000, ['zero_to_hundred_s' => '5.9', 'drive' => 'AWD']],
        ['Range Rover Vogue', 'Land Rover', 'Executive', FuelType::Diesel, Transmission::Automatic, 5, 45_000, ['features' => 'Massage seats, Meridian audio']],
        ['Mercedes-Benz S-Class', 'Mercedes-Benz', 'Luxury Sedan', FuelType::Petrol, Transmission::Automatic, 5, 40_000, ['zero_to_hundred_s' => '4.9']],
        ['Mercedes-Benz V-Class', 'Mercedes-Benz', 'Van', FuelType::Diesel, Transmission::Automatic, 7, 30_000, ['luggage_bags' => '6']],
        ['BMW X5', 'BMW', 'SUV', FuelType::Hybrid, Transmission::Automatic, 5, 28_000, ['zero_to_hundred_s' => '5.6', 'drive' => 'AWD']],
        ['BMW 7 Series', 'BMW', 'Executive', FuelType::Petrol, Transmission::Automatic, 5, 42_000, []],
        ['Lexus LX 600', 'Lexus', '4x4 Safari', FuelType::Petrol, Transmission::Automatic, 7, 38_000, ['drive' => '4WD']],
        ['Nissan X-Trail', 'Nissan', 'SUV', FuelType::Petrol, Transmission::Automatic, 5, 9_000, []],
        ['Mazda Demio', 'Mazda', 'Economy', FuelType::Petrol, Transmission::Automatic, 5, 4_500, []],
        ['Volkswagen Polo', 'Volkswagen', 'Economy', FuelType::Petrol, Transmission::Manual, 5, 5_000, []],
    ];

    public function run(): void
    {
        $brands = collect(self::BRANDS)->mapWithKeys(
            fn (string $name) => [$name => Brand::query()->firstOrCreate(['name' => $name])],
        );

        $types = collect(self::TYPES)->mapWithKeys(
            fn (string $description, string $name) => [$name => CarType::query()->firstOrCreate(['name' => $name], ['description' => $description])],
        );

        foreach (self::CARS as [$name, $brand, $type, $fuel, $transmission, $seats, $rate, $extra]) {
            Car::query()->firstOrCreate(['name' => $name], [
                'brand_id' => $brands[$brand]->id,
                'car_type_id' => $types[$type]->id,
                'fuel_type' => $fuel,
                'transmission' => $transmission,
                'doors' => $seats > 5 ? 5 : 4,
                'seats' => $seats,
                'daily_rate_minor' => $rate * 100,
                'metadata' => $extra ?: null,
            ]);
        }
    }
}
