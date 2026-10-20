<?php

namespace Database\Factories;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Brand;
use App\Models\Car;
use App\Models\CarType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft car without photos. Tests that need a gallery upload through MediaService.
 *
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'car_type_id' => CarType::factory(),
            'name' => fake()->unique()->bothify('Model ?? ###'),
            'model_year' => fake()->numberBetween(2018, 2026),
            'engine_type' => fake()->randomElement(['2.0L Turbo', '3.0L V6', '2.8L Turbo Diesel', '4.4L V8']),
            'engine_capacity_litres' => fake()->randomElement(['2.0', '3.0', '2.8', '4.4']),
            'fuel_type' => fake()->randomElement(FuelType::cases()),
            'transmission' => Transmission::Automatic,
            'colour' => fake()->safeColorName(),
            'doors' => 4,
            'seats' => 5,
            'daily_rate_minor' => fake()->numberBetween(80, 600) * 100_00,
            'car_value_minor' => null,
            'mileage_limit_km' => null,
            'metadata' => null,
        ];
    }

    /**
     * Listed on the site. A real published car also has a cover photo.
     */
    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true, 'published_at' => now()]);
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['is_featured' => true]);
    }

    public function withValue(int $minor = 4_500_000_00): static
    {
        return $this->state(fn () => ['car_value_minor' => $minor]);
    }
}
