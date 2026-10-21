<?php

namespace Database\Seeders;

use App\Enums\ChargeApplies;
use App\Enums\ChargeBasis;
use App\Enums\ChargePayableAt;
use App\Models\Charge;
use App\Models\Currency;
use App\Models\DeliveryZone;
use App\Models\DepositBand;
use App\Support\Decimal;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Pricing rules from the seed pack's pricing.json (M6): currencies, charges, delivery zones
 * and deposit bands. Runs in every environment after CmsSeeder and is insert-only: rows are
 * matched by code or name, so admin edits are never overwritten. No exchange rates are
 * seeded (D26): they come from the provider or are entered by staff. The pricing settings'
 * migration defaults already match the pack (25%, ceil to the shilling, calendar days).
 */
class PricingSeeder extends Seeder
{
    public function run(): void
    {
        $pack = json_decode((string) file_get_contents(database_path('seeders/data/pricing.json')), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($pack) {
            $this->currencies($pack['currencies']);
            $this->charges($pack['pricing']);
            $this->zones($pack['pricing']['delivery_fee_kes']);
            $this->bands($pack['pricing']['security_deposit_bands']);
        });
    }

    /**
     * @param  list<array{code: string, name: string, symbol: string, decimals: int, is_active: bool, sort_order: int}>  $rows
     */
    private function currencies(array $rows): void
    {
        $existing = Currency::query()->pluck('code')->all();
        $created = 0;

        foreach ($rows as $row) {
            if (! in_array($row['code'], $existing, true)) {
                $currency = new Currency([
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'symbol' => $row['symbol'],
                    'decimals' => $row['decimals'],
                    'is_active' => $row['is_active'],
                ]);
                $currency->sort_order = $row['sort_order'];
                $currency->save();
                $created++;
            }
        }

        $this->report("Currencies: {$created} created");
    }

    /**
     * @param  array<string, mixed>  $pricing
     */
    private function charges(array $pricing): void
    {
        $usd = fn (int|float $amount) => Money::fromMajor($amount, 'USD')->minor;

        $rows = [
            ['chauffeur', 'Chauffeur', 'A professional driver for the whole rental.', ChargeBasis::PerDay, $usd($pricing['chauffeur']['amount']), 'USD', ChargePayableAt::Reservation, ChargeApplies::Extra],
            ['additional_driver', 'Additional driver', 'Paid at the counter when the car is collected.', ChargeBasis::PerDay, $usd($pricing['additional_driver']['amount']), 'USD', ChargePayableAt::Counter, ChargeApplies::Extra],
            ['fuel_service', 'Fuel service charge', 'Non-refundable.', ChargeBasis::PerBooking, $usd($pricing['fuel_service_charge']['amount']), 'USD', ChargePayableAt::Counter, ChargeApplies::Extra],
            ['one_way', 'One-way rental', 'Quoted on request.', ChargeBasis::InfoOnly, null, 'KES', ChargePayableAt::Counter, ChargeApplies::Extra],
            ['valeting', 'Valeting', 'Charged if the car is returned needing more than a standard clean.', ChargeBasis::InfoOnly, null, 'KES', ChargePayableAt::Counter, ChargeApplies::Listed],
            ['admin', 'Admin fee', 'For fines, tolls and claims handled on the renter\'s behalf.', ChargeBasis::InfoOnly, null, 'KES', ChargePayableAt::Counter, ChargeApplies::Listed],
            ['full_protection', 'Full protection', 'Reduces the damage excess. Quoted on request.', ChargeBasis::InfoOnly, null, 'KES', ChargePayableAt::Counter, ChargeApplies::Extra],
            ['excess_km', 'Excess kilometres', 'Per kilometre above the mileage allowance, settled at return.', ChargeBasis::PerKm, $pricing['mileage']['excess_rate_kes_per_km'] === null ? null : Money::fromMajor($pricing['mileage']['excess_rate_kes_per_km'])->minor, 'KES', ChargePayableAt::Counter, ChargeApplies::Listed],
        ];

        $existing = Charge::query()->pluck('code')->all();
        $created = 0;

        foreach ($rows as $sort => [$code, $name, $description, $basis, $amount, $currency, $payableAt, $applies]) {
            if (! in_array($code, $existing, true)) {
                $charge = new Charge([
                    'code' => $code,
                    'name' => $name,
                    'description' => $description,
                    'basis' => $basis,
                    'amount_minor' => $amount,
                    'currency_code' => $currency,
                    'tax_inclusive' => true,
                    'payable_at' => $payableAt,
                    'applies' => $applies,
                    'is_active' => true,
                ]);
                $charge->sort_order = $sort + 1;
                $charge->save();
                $created++;
            }
        }

        $this->report("Charges: {$created} created");
    }

    private function zones(int $nairobiFee): void
    {
        $created = 0;

        foreach ([['Nairobi', Money::fromMajor($nairobiFee)->minor, true], ['Outside Nairobi', null, false]] as $sort => [$name, $fee, $default]) {
            if (! DeliveryZone::query()->where('name', $name)->exists()) {
                $zone = new DeliveryZone(['name' => $name, 'fee_minor' => $fee, 'is_default' => $default, 'is_active' => true]);
                $zone->sort_order = $sort + 1;
                $zone->save();
                $created++;
            }
        }

        $this->report("Delivery zones: {$created} created");
    }

    /**
     * Bands are only seeded into an empty table: they must not overlap, and once staff have
     * set their own the pack's would clash.
     *
     * @param  list<array{min_age: int, max_age: int, percent_of_car_value: float}>  $rows
     */
    private function bands(array $rows): void
    {
        if (DepositBand::query()->exists()) {
            $this->report('Deposit bands: kept as they are');

            return;
        }

        foreach ($rows as $row) {
            DepositBand::create([
                'min_age' => $row['min_age'],
                'max_age' => $row['max_age'],
                'percent_bp' => Decimal::basisPoints(Decimal::mul(Decimal::of($row['percent_of_car_value']), '100')),
                'is_active' => true,
            ]);
        }

        $this->report('Deposit bands: '.count($rows).' created');
    }

    private function report(string $message): void
    {
        $this->command->info("  Pricing seed pack: {$message}");
    }
}
