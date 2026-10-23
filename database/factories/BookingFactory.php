<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\BookingStep;
use App\Models\Booking;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * A draft collected at the office. Use withToken() when a test needs the plain token.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pickup = now()->addDays(fake()->numberBetween(5, 40))->setTime(10, 0);

        return [
            'access_token_hash' => hash('sha256', Str::random(64)),
            'car_id' => Car::factory()->published(),
            'status' => BookingStatus::Draft,
            'pickup_at' => $pickup,
            'return_at' => $pickup->addDays(3),
            'pickup_mode' => 'office',
            'return_mode' => 'office',
            'extras' => [],
            'current_step' => BookingStep::Trip,
        ];
    }

    public function withToken(string $plain): static
    {
        return $this->state(fn () => ['access_token_hash' => hash('sha256', $plain)]);
    }

    public function status(BookingStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function awaitingPayment(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::AwaitingPayment,
            'reference' => 'BE-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'current_step' => BookingStep::Review,
            'submitted_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);
    }
}
