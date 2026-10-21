<?php

namespace Database\Factories;

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\ChargePayableAt;
use App\Models\Charge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Needs the currency to exist (PricingFixtures::currencies()).
 *
 * @extends Factory<Charge>
 */
class ChargeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('charge_????'),
            'name' => fake()->words(2, true),
            'basis' => ChargeBasis::PerBooking,
            'amount_minor' => 100_000,
            'currency_code' => 'KES',
            'tax_inclusive' => true,
            'payable_at' => ChargePayableAt::Reservation,
            'applies' => ChargeApplies::Extra,
            'is_active' => true,
        ];
    }

    /**
     * The PRD chauffeur: USD 50 per day, in the total.
     */
    public function chauffeur(): self
    {
        return $this->state([
            'code' => 'chauffeur',
            'name' => 'Chauffeur',
            'basis' => ChargeBasis::PerDay,
            'amount_minor' => 5_000,
            'currency_code' => 'USD',
        ]);
    }
}
