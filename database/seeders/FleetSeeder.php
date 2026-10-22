<?php

namespace Database\Seeders;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use Database\Seeders\Concerns\AttachesCarPhotos;
use Illuminate\Database\Seeder;

/**
 * The client's real cars, with the photos they sent (M7). Runs in every environment after
 * CmsSeeder and is insert-only: a car is matched by name, and one that exists is not touched.
 * Plates are blurred in the bundled photos. Car value, year and engine size are still to come
 * from the client's fleet list, so they stay empty.
 */
class FleetSeeder extends Seeder
{
    use AttachesCarPhotos;

    /**
     * @var list<array{name: string, brand: string, type: string, fuel: FuelType, transmission: Transmission, colour: string, doors: int, seats: int, daily_rate_kes: int, featured: bool, photos: list<string>}>
     */
    private const CARS = [
        [
            'name' => 'Mazda Demio',
            'brand' => 'Mazda',
            'type' => 'Economy',
            'fuel' => FuelType::Petrol,
            'transmission' => Transmission::Automatic,
            'colour' => 'White',
            'doors' => 5,
            'seats' => 5,
            'daily_rate_kes' => 4_500,
            'featured' => true,
            'photos' => [
                'mazda-demio/1-front-three-quarter.jpg',
                'mazda-demio/2-front.jpg',
                'mazda-demio/3-rear-three-quarter.jpg',
                'mazda-demio/4-boot.jpg',
                'mazda-demio/5-dashboard.jpg',
                'mazda-demio/6-front-seats.jpg',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::CARS as $row) {
            if (Car::withTrashed()->where('name', $row['name'])->exists()) {
                continue;
            }

            $car = Car::create([
                'brand_id' => Brand::query()->firstOrCreate(['name' => $row['brand']])->id,
                'car_type_id' => CarType::query()->firstOrCreate(['name' => $row['type']])->id,
                'name' => $row['name'],
                'fuel_type' => $row['fuel'],
                'transmission' => $row['transmission'],
                'colour' => $row['colour'],
                'doors' => $row['doors'],
                'seats' => $row['seats'],
                'daily_rate_minor' => $row['daily_rate_kes'] * 100,
            ]);
            $car->forceFill(['is_featured' => $row['featured']])->save();

            $this->attachPhotos($car, $row['photos']);
        }
    }
}
