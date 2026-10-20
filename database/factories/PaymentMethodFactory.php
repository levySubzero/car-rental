<?php

namespace Database\Factories;

use App\Enums\PaymentMethodCode;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Codes are unique and fixed (D25): pass one, e.g. PaymentMethod::factory()->create(['code' => 'bank']).
 *
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => PaymentMethodCode::Mpesa,
            'name' => 'M-Pesa',
            'instructions' => 'Pay to Paybill 123456 and paste the confirmation message.',
            'requires_transaction_message' => true,
            'requires_proof' => false,
            'is_active' => true,
            'metadata' => null,
        ];
    }
}
