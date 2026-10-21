<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic on numeric strings (bcmath) for exchange rates. Floats never
 * touch a rate: a rate is a string such as "129.250000" from the database or the API.
 */
final class Decimal
{
    // Working precision for intermediate results; rates are stored with 6 places.
    public const SCALE = 12;

    /**
     * Normalise an API or form value to a plain decimal string. Floats are formatted with
     * 12 places first, which is exact for every rate a provider publishes (at most 6–8).
     */
    public static function of(int|float|string $value): string
    {
        $string = is_float($value) ? sprintf('%.'.self::SCALE.'F', $value) : trim((string) $value);

        if (preg_match('/^-?\d+(\.\d+)?$/', $string) !== 1) {
            throw new InvalidArgumentException("Invalid decimal [{$string}].");
        }

        return $string;
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::num($a), self::num($b), self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub(self::num($a), self::num($b), self::SCALE);
    }

    public static function mul(string $a, string $b): string
    {
        return bcmul(self::num($a), self::num($b), self::SCALE);
    }

    public static function div(string $a, string $b): string
    {
        if (self::compare($b, '0') === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv(self::num($a), self::num($b), self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp(self::num($a), self::num($b), self::SCALE);
    }

    public static function isPositive(string $value): bool
    {
        return self::compare($value, '0') > 0;
    }

    /**
     * Round half away from zero to $scale places: "126.5" → "127", "-0.125" (2) → "-0.13".
     */
    public static function round(string $value, int $scale = 0): string
    {
        $value = self::num($value);
        $half = bcdiv('5', bcpow('10', (string) ($scale + 1)), $scale + 1);

        return str_starts_with($value, '-')
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);
    }

    /**
     * Round half up to a whole integer, e.g. a converted amount in minor units.
     */
    public static function toInt(string $value): int
    {
        return (int) self::round($value);
    }

    /**
     * A percentage typed into a form ("2.5") as basis points (250).
     */
    public static function basisPoints(string $percent): int
    {
        return self::toInt(self::mul(self::of($percent), '100'));
    }

    /**
     * Absolute percentage change from $from to $to, e.g. 129 → 142 gives "10.077519...".
     */
    public static function percentChange(string $from, string $to): string
    {
        $change = self::div(self::mul(self::sub($to, $from), '100'), $from);

        return ltrim($change, '-');
    }

    /**
     * bcmath only takes plain numeric strings; anything else is a bug upstream.
     *
     * @return numeric-string
     */
    private static function num(string $value): string
    {
        if (! is_numeric($value) || preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw new InvalidArgumentException("Invalid decimal [{$value}].");
        }

        return $value;
    }
}
