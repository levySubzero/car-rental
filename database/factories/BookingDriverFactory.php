<?php

namespace Database\Factories;

use App\Enums\IdType;
use App\Models\Booking;
use App\Models\BookingDriver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDriver>
 */
class BookingDriverFactory extends Factory
{
    /**
     * A Kenyan resident, 30 years old, with a licence held for 8 years.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'first_name' => fake()->firstName(),
            'second_name' => null,
            'surname' => fake()->lastName(),
            'dob' => now()->subYears(30)->toDateString(),
            'email' => fake()->safeEmail(),
            'phone' => '+2547'.fake()->numerify('########'),
            'nationality' => 'Kenyan',
            'is_kenyan_resident' => true,
            'id_type' => IdType::NationalId,
            'id_number' => fake()->numerify('########'),
            'kra_pin' => 'A'.fake()->numerify('#########').'Z',
            'licence_number' => fake()->bothify('DL-#######'),
            'licence_expiry' => now()->addYears(3)->toDateString(),
            'licence_years_declared' => 8,
        ];
    }

    public function passport(): static
    {
        return $this->state(fn () => [
            'nationality' => 'British',
            'is_kenyan_resident' => false,
            'id_type' => IdType::Passport,
            'id_number' => fake()->bothify('#########'),
            'kra_pin' => null,
        ]);
    }
}
