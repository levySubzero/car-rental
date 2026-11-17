<?php

namespace App\Services\Pricing\Quote;

use App\Enums\RoundingMode;
use App\Models\DepositBand;
use App\Support\Money;

/**
 * The security deposit before the driver's age is known: the lowest and the highest band.
 */
final readonly class DepositRange
{
    public function __construct(
        public Money $min,
        public Money $max,
    ) {}

    /**
     * Null when the car's value is unknown or no active band asks for anything.
     *
     * @param  iterable<DepositBand>  $bands
     */
    public static function for(?int $carValueMinor, iterable $bands): ?self
    {
        $percents = [];

        foreach ($bands as $band) {
            if ($band->is_active) {
                $percents[] = $band->percent_bp;
            }
        }

        if ($carValueMinor === null || $percents === [] || max($percents) === 0) {
            return null;
        }

        $value = new Money($carValueMinor);

        return new self(
            $value->percentBp(min($percents), RoundingMode::Round),
            $value->percentBp(max($percents), RoundingMode::Round),
        );
    }

    /**
     * @return array{min: Money, max: Money}
     */
    public function toArray(): array
    {
        return ['min' => $this->min, 'max' => $this->max];
    }
}
