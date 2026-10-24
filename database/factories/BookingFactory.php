<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Enums\BookingStep;
use App\Models\AvailabilityBlock;
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

    /**
     * Submitted with the PRD worked example's amounts (66,350 / 16,588 / 49,762) and the car
     * held for two hours, as BookingService::submit() leaves it.
     */
    public function awaitingPayment(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::AwaitingPayment,
            'reference' => 'BE-'.now()->year.'-'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'current_step' => BookingStep::Review,
            'total_minor' => 6_635_000,
            'reservation_minor' => 1_658_800,
            'balance_minor' => 4_976_200,
            'submitted_at' => now(),
            'expires_at' => now()->addHours(2),
        ])->afterCreating(function (Booking $booking) {
            AvailabilityBlock::factory()->hold($booking)->create([
                'car_id' => $booking->car_id,
                'starts_at' => $booking->pickup_at,
                'ends_at' => $booking->return_at,
                'expires_at' => $booking->expires_at,
            ]);
        });
    }

    /**
     * Paid in full and firm-booked, as PaymentVerificationService::verify() leaves it, with a
     * three-day quote snapshot (300 km a day) and a KES 160,000 security deposit.
     */
    public function reserved(): static
    {
        return $this->awaitingPayment()->state(fn () => [
            'status' => BookingStatus::Reserved,
            'reservation_paid_minor' => 1_658_800,
            'security_deposit_minor' => 16_000_000,
            'paid_at' => now(),
            'expires_at' => null,
            'quote' => [
                'days' => 3,
                'mileage_km_per_day' => 300,
                'total' => ['minor' => 6_635_000, 'currency' => 'KES'],
                'lines' => [],
                'requires_staff_quote' => false,
            ],
        ])->afterCreating(function (Booking $booking) {
            $booking->availabilityBlocks()->update(['type' => 'booked', 'expires_at' => null]);
        });
    }

    /**
     * On hire: reserved, then handed over.
     */
    public function active(): static
    {
        return $this->reserved()->state(fn () => ['status' => BookingStatus::Active]);
    }
}
