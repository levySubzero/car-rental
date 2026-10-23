<?php

namespace Database\Factories;

use App\Enums\PaymentMethodCode;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\PaymentSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentSubmission>
 */
class PaymentSubmissionFactory extends Factory
{
    /**
     * A pending M-Pesa submission.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->awaitingPayment(),
            'payment_method_code' => PaymentMethodCode::Mpesa,
            'transaction_message' => 'TJK'.strtoupper(fake()->bothify('?#?#?#?')).' Confirmed. Ksh16,588.00 sent to BAINES EXECUTIVES.',
            'status' => PaymentStatus::Pending,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn () => ['payment_method_code' => PaymentMethodCode::Cash, 'transaction_message' => null]);
    }

    public function verified(int $amountMinor = 1_658_800): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Verified,
            'reference' => strtoupper(fake()->bothify('???#####')),
            'amount_minor' => $amountMinor,
            'verified_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'No matching payment found.'): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
        ]);
    }
}
