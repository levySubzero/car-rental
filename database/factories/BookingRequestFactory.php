<?php

namespace Database\Factories;

use App\Enums\BookingRequestStatus;
use App\Enums\BookingRequestType;
use App\Models\Booking;
use App\Models\BookingRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingRequest>
 */
class BookingRequestFactory extends Factory
{
    /**
     * An open cancellation request on a submitted booking.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->awaitingPayment(),
            'type' => BookingRequestType::Cancel,
            'message' => fake()->sentence(),
            'status' => BookingRequestStatus::Open,
        ];
    }
}
