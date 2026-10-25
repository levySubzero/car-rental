<?php

namespace Database\Factories;

use App\Enums\GatewayTransactionStatus;
use App\Enums\PaymentGateway;
use App\Models\Booking;
use App\Models\GatewayTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GatewayTransaction>
 */
class GatewayTransactionFactory extends Factory
{
    /**
     * An STK push waiting for the renter to approve it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gateway' => PaymentGateway::MpesaStk,
            'external_ref' => 'ws_CO_'.fake()->unique()->numerify('##############'),
            'booking_id' => Booking::factory()->awaitingPayment(),
            'amount_minor' => 1_658_800,
            'payer_phone' => '254712345678',
            'status' => GatewayTransactionStatus::Initiated,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => GatewayTransactionStatus::Paid,
            'receipt' => strtoupper(fake()->bothify('??#?#?#?#?')),
        ]);
    }

    public function paystack(): static
    {
        return $this->state(fn () => [
            'gateway' => PaymentGateway::Paystack,
            'external_ref' => 'BE-'.fake()->unique()->bothify('????????'),
            'payer_phone' => null,
        ]);
    }

    /**
     * A paybill receipt whose account reference named no booking.
     */
    public function unmatched(string $billRef = 'WRONGREF'): static
    {
        return $this->state(fn () => [
            'gateway' => PaymentGateway::MpesaC2b,
            'external_ref' => strtoupper(fake()->unique()->bothify('??#?#?#?#?')),
            'booking_id' => null,
            'status' => GatewayTransactionStatus::Unmatched,
            'payer_name' => 'JOHN',
            'payload' => ['bill_ref' => $billRef],
        ]);
    }
}
